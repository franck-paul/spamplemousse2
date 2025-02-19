<?php

/**
 * @brief spamplemousse2, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\spamplemousse2;

use Dotclear\App;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Li;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Ul;
use Dotclear\Plugin\antispam\SpamFilter;

/**
 * This class implements all the methods needed for this plugin to run as a spam filter.
 */
class AntispamFilterSpamplemousse2 extends SpamFilter
{
    /** @var string Filter name */
    public string $name = 'Spamplemousse2';

    /** @var bool Filter has settings GUI? */
    public bool $has_gui = true;

    /**
     * Sets the filter description.
     */
    protected function setInfo(): void
    {
        $this->description = __('A bayesian filter');
    }

    /**
     * This method returns filter status message. You can overload this method to
     * return a custom message. Message is shown in comment details and in
     * comments list.
     *
     * @param      string  $status      The status
     * @param      int     $comment_id  The comment identifier
     *
     * @return     string  The status message.
     */
    public function getStatusMessage(string $status, ?int $comment_id): string
    {
        $p          = 0;
        $spamFilter = new Bayesian();

        $rs = (new SelectStatement())
            ->columns(['comment_author', 'comment_email', 'comment_site', 'comment_ip', 'comment_content'])
            ->from(App::con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
            ->where('comment_id = ' . $comment_id)
            ->select()
        ;

        if ($rs) {
            $rs->fetch();
            $p = $spamFilter->getMsgProba($rs->comment_author, $rs->comment_email, $rs->comment_site, $rs->comment_ip, $rs->comment_content);
        }

        $p = round($p * 100);

        return sprintf(__('Filtered by %s, actual spamminess: %s %%'), $this->guiLink(), $p);
    }

    /**
     * This method should return if a comment is a spam or not. If it returns true
     * or false, execution of next filters will be stoped. If should return nothing
     * to let next filters apply.
     *
     * Your filter should also fill $status variable with its own information if
     * comment is a spam.
     *
     * @param      string  $type     The comment type (comment / trackback)
     * @param      string  $author   The comment author
     * @param      string  $email    The comment author email
     * @param      string  $site     The comment author site
     * @param      string  $ip       The comment author IP
     * @param      string  $content  The comment content
     * @param      int     $post_id  The comment post_id
     * @param      string  $status   The comment status
     */
    public function isSpam(string $type, ?string $author, ?string $email, ?string $site, ?string $ip, ?string $content, ?int $post_id, string &$status)
    {
        $spamFilter = new Bayesian();
        $spam       = $spamFilter->handle_new_message((string) $author, (string) $email, (string) $site, (string) $ip, (string) $content);
        if ($spam) {
            $status = '';
        }

        return $spam;
    }

    /**
     * Train the antispam filter
     *
     * @param      string        $status   The comment status
     * @param      string        $filter   The filter
     * @param      string        $type     The comment type
     * @param      string        $author   The comment author
     * @param      string        $email    The comment author email
     * @param      string        $site     The comment author site
     * @param      string        $ip       The comment author IP
     * @param      string        $content  The comment content
     * @param      MetaRecord    $rs       The comment record
     */
    public function trainFilter(string $status, string $filter, string $type, ?string $author, ?string $email, ?string $site, ?string $ip, ?string $content, MetaRecord $rs): void
    {
        $spamFilter = new Bayesian();

        $rsBayes = (new SelectStatement())
            ->fields(['comment_bayes', 'comment_bayes_err'])
            ->from(App::con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
            ->where('comment_id = ' . $rs->comment_id)
            ->select();

        if ($rsBayes) {
            $rsBayes->fetch();

            $spam = false;
            if ($status === 'spam') { # the current action marks the comment as spam
                $spam = true;
            }

            if ($rsBayes->comment_bayes == 0) {
                $spamFilter->train((string) $author, (string) $email, (string) $site, (string) $ip, (string) $content, $spam);
                (new UpdateStatement())
                    ->ref(App::con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
                    ->set('comment_bayes = 1')
                    ->where('comment_id = ' . $rs->comment_id)
                    ->update()
                ;
            } else {
                $spamFilter->retrain((string) $author, (string) $email, (string) $site, (string) $ip, (string) $content, $spam);
                $err = $rsBayes->comment_bayes_err ? 0 : 1;
                (new UpdateStatement())
                    ->ref(App::con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
                    ->set('comment_bayes_err = ' . $err)
                    ->where('comment_id = ' . $rs->comment_id)
                    ->update()
                ;
            }
        }
    }

    /**
     * This method handles the main gui used to configure this plugin.
     *
     * @param      string           $url    The url of the plugin
     *
     * @return     string  HTML content
     */
    public function gui(string $url): string
    {
        $items      = [];
        $spamFilter = new Bayesian();
        $action     = empty($_POST['action']) ? null : $_POST['action'];

        # count number of comments
        $nb_comm = 0;
        $sql     = new SelectStatement();
        $sql
            ->column($sql->count('comment_id'))
            ->from(App::con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
        ;
        $rs = $sql->select();
        if ($rs && $rs->fetch()) {
            $nb_comm = $rs->f(0);
        }

        $learned = 0;

        # request handling
        if ($action == 'cleanup') {
            $spamFilter->cleanup();
            $items[] = (new Note())
                ->class('message')
                ->text(__('Cleanup successful.'));
        } elseif ($action == 'oldmsg') {
            $formparams = (new Hidden('action', 'oldmsg'))->render();
            $start      = 0;
            $pos        = $spamFilter->getNumLearnedComments();
            $stop       = $nb_comm;
            $inc        = 10;
            $title      = __('Learning in progress...');
            $class      = Bayesian::class;
            $progress   = new Progress($title, $url, $url, [$class, 'feedCorpus'], $start, (int) $stop, $inc, $pos, $formparams);

            return $progress->gui('');
        } elseif ($action == 'reset') {
            $spamFilter->resetFilter();
            $items[] = (new Note())
                ->class('message')
                ->text(__('Reset successful.'));
        }

        $errors  = $spamFilter->getNumErrorComments();
        $learned = $spamFilter->getNumLearnedComments();

        $accuracy = [];
        if ($learned > 0) {
            $percent    = ($learned - $errors) / $learned * 100;
            $accuracy[] = (new Li())
                ->items([
                    (new Text('strong', __('Accuracy:') . ' ' . sprintf('%.02f %%', $percent))),
                ]);
        }

        $items[] = (new Text(
            null,
            Page::jsJson('spamplemousse2', [
                'msg_reset' => __('Are you sure?'),
            ]) .
            My::jsLoad('gui.js')
        ));

        return (new Set())
            ->items([
                ... $items,
                (new Text('h4', __('Statistics'))),
                (new Ul())
                    ->items([
                        (new Li())
                            ->text(__('Learned comments:') . ' ' . $learned),
                        (new Li())
                            ->text(__('Total comments:') . ' ' . $nb_comm),
                        (new Li())
                            ->text(__('Learned tokens:') . ' ' . $spamFilter->getNumLearnedTokens()),
                        ... $accuracy,
                    ]),
                (new Text('h4', __('Actions'))),
                (new Text('h5', __('Initialization'))),
                (new Form('spamplemousse2-init-form'))
                    ->action($url)
                    ->method('post')
                    ->fields([
                        (new Para())->items([
                            (new Submit(['s2_init'], __('Learn from old messages')))
                                ->disabled($learned == $nb_comm),
                            (new Hidden(['action'], 'oldmsg')),
                            App::nonce()->formNonce(),
                        ]),
                    ]),
                (new Text('h5', __('Maintenance'))),
                (new Form('spamplemousse2-maintenance-form'))
                    ->action($url)
                    ->method('post')
                    ->fields([
                        (new Para())->items([
                            (new Submit(['s2_maintenance'], __('Cleanup'))),
                            (new Hidden(['action'], 'cleanup')),
                            App::nonce()->formNonce(),
                        ]),
                    ]),
                (new Text('h5', __('Reset filter'))),
                (new Form('spamplemousse2-reset-form'))
                    ->action($url)
                    ->method('post')
                    ->fields([
                        (new Para())->items([
                            (new Submit(['s2_reset'], __('Delete all learned data'))),
                            (new Hidden(['action'], 'reset')),
                            App::nonce()->formNonce(),
                        ]),
                    ]),
            ])
        ->render();
    }

    /**
     * This method is a hack to toggle the "learned" flag on a given comment.
     *
     * When a comment passes through the isSpam method of this filter for the first
     * time, it is possible that the filter learns from this message, but in isSpam
     * we are too early in the filtering process to be able to toggle the flag. So
     * we set the global App::backend()/frontend()->spamplemousse2_learned to 1,
     * and when the process comes to its end, this method is triggered (by the events
     * publicAfterCommentCreate and publicAfterTrackbackCreate), and we update the
     * flag in the database.
     *
     * @param      Cursor  $cur    The cursor on the comment
     * @param      int     $id     The identifier of the comment
     */
    public static function toggleLearnedFlag(Cursor $cur, int $id): void
    {
        if (App::task()->checkContext('FRONTEND')) {
            $learned = App::frontend()->spamplemousse2_learned;
        } else {
            $learned = App::backend()->spamplemousse2_learned;
        }

        if ($learned === 1) {
            (new UpdateStatement())
                ->ref(App::con()->prefix() . App::blog()::COMMENT_TABLE_NAME)
                ->set('comment_bayes = 1')
                ->where('comment_id = ' . $id)
                ->update()
            ;
        }
    }
}
