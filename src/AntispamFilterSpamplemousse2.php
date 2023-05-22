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

use dcBlog;
use dcCore;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Plugin\antispam\SpamFilter;

/**
 * This class implements all the methods needed for this plugin to run as a spam filter.
 */
class AntispamFilterSpamplemousse2 extends SpamFilter
{
    /** @var string Filter name */
    public $name = 'Spamplemousse2';

    /** @var bool Filter has settings GUI? */
    public $has_gui = true;

    /**
     * Sets the filter description.
     */
    protected function setInfo()
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
    public function getStatusMessage(string $status, ?int $comment_id)
    {
        $p          = 0;
        $spamFilter = new Bayesian();

        $rs = (new SelectStatement())
            ->columns(['comment_author', 'comment_email', 'comment_site', 'comment_ip', 'comment_content'])
            ->from(dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME)
            ->where('comment_id = ' . $comment_id)
            ->select()
        ;
        $rs->fetch();

        $p = $spamFilter->getMsgProba($rs->comment_author, $rs->comment_email, $rs->comment_site, $rs->comment_ip, $rs->comment_content);
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
        $spam       = $spamFilter->handle_new_message($author, $email, $site, $ip, $content);
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
    public function trainFilter(string $status, string $filter, string $type, ?string $author, ?string $email, ?string $site, ?string $ip, ?string $content, MetaRecord $rs)
    {
        $spamFilter = new Bayesian();

        $rsBayes = (new SelectStatement())
            ->fields(['comment_bayes', 'comment_bayes_err'])
            ->from(dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME)
            ->where('comment_id = ' . $rs->comment_id)
            ->select();

        $rsBayes->fetch();

        $spam = 0;
        if ($status == 'spam') { # the current action marks the comment as spam
            $spam = 1;
        }

        if ($rsBayes->comment_bayes == 0) {
            $spamFilter->train($author, $email, $site, $ip, $content, $spam);
            (new UpdateStatement())
                ->ref(dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME)
                ->set('comment_bayes = 1')
                ->where('comment_id = ' . $rs->comment_id)
                ->update()
            ;
        } else {
            $spamFilter->retrain($author, $email, $site, $ip, $content, $spam);
            $err = $rsBayes->comment_bayes_err ? 0 : 1;
            (new UpdateStatement())
                ->ref(dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME)
                ->set('comment_bayes_err = ' . $err)
                ->where('comment_id = ' . $rs->comment_id)
                ->update()
            ;
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
        $content    = '';
        $spamFilter = new Bayesian();

        $action = !empty($_POST['action']) ? $_POST['action'] : null;

        # count nr of comments
        $nb_comm = 0;
        $sql     = new SelectStatement();
        $sql
            ->column($sql->count('comment_id'))
            ->from(dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME)
        ;
        $rs = $sql->select();
        if ($rs->fetch()) {
            $nb_comm = $rs->f(0);
        }
        $learned = 0;

        # request handling
        if ($action == 'cleanup') {
            $spamFilter->cleanup();
            $content .= '<p class="message">' . __('Cleanup successful.') . '</p>';
        } elseif ($action == 'oldmsg') {
            $formparams = '<input type="hidden" name="action" value="oldmsg" />';
            $func       = ['bayesian', 'feedCorpus'];
            $start      = 0;
            $pos        = $spamFilter->getNumLearnedComments();
            $stop       = $nb_comm;
            $inc        = 10;
            $title      = __('Learning in progress...');
            $progress   = new Progress($title, $url, $url, $func, $start, $stop, $inc, dcCore::app()->getNonce(), $pos, $formparams);
            $content    = $progress->gui($content);

            return $content;
        } elseif ($action == 'reset') {
            $spamFilter->resetFilter();
            $content .= '<p class="message">' . __('Reset successful.') . '</p>';
        }

        $errors  = $spamFilter->getNumErrorComments();
        $learned = $spamFilter->getNumLearnedComments();

        $content .= '<h4>' . __('Statistics') . '</h4>';
        $content .= '<ul>';
        $content .= '<li>' . __('Learned comments:') . ' ' . $learned . '</li>';
        $content .= '<li>' . __('Total comments:') . ' ' . $nb_comm . '</li>';
        $content .= '<li>' . __('Learned tokens:') . ' ' . $spamFilter->getNumLearnedTokens() . '</li>';
        if ($learned != 0) {
            $percent = ($learned - $errors) / $learned * 100;
            $content .= '<li><strong>' . __('Accuracy:') . ' ' . sprintf('%.02f %%', $percent) . '</strong></li>';
        }

        $content .= '</ul>';
        $content .= '<h4>' . __('Actions') . '</h4>';

        $content .= '<h5>' . __('Initialization') . '</h5>';
        $content .= (new Form('spamplemousse2-init-form'))
            ->action($url)
            ->method('post')
            ->fields([
                (new Para())->items([
                    (new Submit(['s2_init'], __('Learn from old messages')))
                        ->disabled($learned == $nb_comm),
                    (new Hidden(['action'], 'oldmsg')),
                    dcCore::app()->formNonce(false),
                ]),
            ])
            ->render();

        $content .= '<h5>' . __('Maintenance') . '</h5>';
        $content .= (new Form('spamplemousse2-maintenance-form'))
            ->action($url)
            ->method('post')
            ->fields([
                (new Para())->items([
                    (new Submit(['s2_maintenance'], __('Cleanup'))),
                    (new Hidden(['action'], 'cleanup')),
                    dcCore::app()->formNonce(false),
                ]),
            ])
            ->render();

        $content .= '<h5>' . __('Reset filter') . '</h5>';
        $content .= (new Form('spamplemousse2-reset-form'))
            ->action($url)
            ->method('post')
            ->fields([
                (new Para())->items([
                    (new Submit(['s2_reset'], __('Delete all learned data')))
                        ->extra('onclick="return(confirm(\'' . __('Are you sure?') . '\'));"'),
                    (new Hidden(['action'], 'reset')),
                    dcCore::app()->formNonce(false),
                ]),
            ])
            ->render();

        return $content;
    }

    /**
     * This method is a hack to toggle the "learned" flag on a given comment.
     *
     * When a comment passes through the isSpam method of this filter for the first
     * time, it is possible that the filter learns from this message, but in isSpam
     * we are too early in the filtering process to be able to toggle the flag. So
     * we set the global dcCore::app()->spamplemousse2_learned to 1, and when the process comes to
     * its end, this method is triggered (by the events publicAfterCommentCreate and
     * publicAfterTrackbackCreate), and we update the flag in the database.
     *
     * @param      Cursor  $cur    The cursor on the comment
     * @param      int     $id     The identifier of the comment
     */
    public static function toggleLearnedFlag(Cursor $cur, int $id)
    {
        if (isset(dcCore::app()->spamplemousse2_learned) && dcCore::app()->spamplemousse2_learned == 1) {   // @phpstan-ignore-line
            (new UpdateStatement())
                ->ref(dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME)
                ->set('comment_bayes = 1')
                ->where('comment_id = ' . $id)
                ->update()
            ;
        }
    }
}
