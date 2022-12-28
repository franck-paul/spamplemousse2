<?php
/**
 * @brief Spamplemousse2, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Alain Vagner and contributors
 *
 * @copyright Alain Vagner
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * This class implements all the methods needed for this plugin to run as a spam filter.
 */
class dcFilterSpample2 extends dcSpamFilter
{
    public $name    = 'Spamplemousse2';
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
        $con        = dcCore::app()->con;
        $spamFilter = new bayesian();
        $rs         = new dcRecord($con->select('SELECT comment_author, comment_email, comment_site, comment_ip, comment_content FROM ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' WHERE comment_id = ' . $comment_id));
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
        $spamFilter = new bayesian();
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
     * @param      dcRecord      $rs       The comment record
     */
    public function trainFilter(string $status, string $filter, string $type, ?string $author, ?string $email, ?string $site, ?string $ip, ?string $content, dcRecord $rs)
    {
        $spamFilter = new bayesian();

        $rs2 = new dcRecord(dcCore::app()->con->select('SELECT comment_bayes, comment_bayes_err FROM ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' WHERE comment_id = ' . $rs->comment_id));
        $rs2->fetch();

        $spam = 0;
        if ($status == 'spam') { # the current action marks the comment as spam
            $spam = 1;
        }

        if ($rs2->comment_bayes == 0) {
            $spamFilter->train($author, $email, $site, $ip, $content, $spam);
            $req = 'UPDATE ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' SET comment_bayes = 1 WHERE comment_id = ' . $rs->comment_id;
            dcCore::app()->con->execute($req);
        } else {
            $spamFilter->retrain($author, $email, $site, $ip, $content, $spam);
            $err = $rs2->comment_bayes_err ? 0 : 1;
            $req = 'UPDATE ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' SET comment_bayes_err = ' . $err . ' WHERE comment_id = ' . $rs->comment_id;
            dcCore::app()->con->execute($req);
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
        $spamFilter = new bayesian();

        $action = !empty($_POST['action']) ? $_POST['action'] : null;

        # count nr of comments
        $nb_comm = 0;
        $req     = 'SELECT COUNT(comment_id) FROM ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME;
        $rs      = new dcRecord(dcCore::app()->con->select($req));
        if ($rs->fetch()) {
            $nb_comm = $rs->f(0);
        }
        $learned = 0;

        # request handling
        if ($action == 'cleanup') {
            $spamFilter->cleanup();
            $content .= '<p class="message">' . __('Cleanup successful.') . '</p>';
        } elseif ($action == 'oldmsg') {
            $urlprefix  = 'plugin.php?p=antispam&amp;f=dcFilterSpample2';
            $urlreturn  = $urlprefix;
            $formparams = '<input type="hidden" name="action" value="oldmsg" />';
            $func       = ['bayesian', 'feedCorpus'];
            $start      = 0;
            $pos        = $spamFilter->getNumLearnedComments();
            $stop       = $nb_comm;
            $inc        = 10;
            $title      = __('Learning in progress...');
            $progress   = new progress($title, $urlprefix, $urlreturn, $func, $start, $stop, $inc, dcCore::app()->getNonce(), $pos, $formparams);
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

        $content .= '<h5>' . __('Initialization') . '</h5>' .
                    '<form action="plugin.php?p=antispam&amp;f=dcFilterSpample2" method="post">' .
                    '<p><input type="submit" value="' . __('Learn from old messages') . '" ' . (($learned == $nb_comm) ? 'disabled="true"' : '') . '/> ' .
                    form::hidden(['action'], 'oldmsg') .
                    dcCore::app()->formNonce() .
                    '</p>' .
                    '</form>';

        $content .= '<h5>' . __('Maintenance') . '</h5>' .
                    '<form action="plugin.php?p=antispam&amp;f=dcFilterSpample2" method="post">' .
                    '<p><input type="submit" value="' . __('Cleanup') . '" /> ' .
                    form::hidden(['action'], 'cleanup') .
                    dcCore::app()->formNonce() .
                    '</p>' .
                    '</form>';

        $content .= '<h5>' . __('Reset filter') . '</h5>' .
                    '<form action="plugin.php?p=antispam&amp;f=dcFilterSpample2" method="post">' .
                    '<p><input type="submit" onclick="return(confirm(\'' . __('Are you sure?') . '\'));" value="' . __('Delete all learned data') . '" /> ' .
                    form::hidden(['action'], 'reset') .
                    dcCore::app()->formNonce() .
                    '</p>' .
                    '</form>';

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
     * @param      cursor  $cur    The cursor on the comment
     * @param      int     $id     The identifier of the comment
     */
    public static function toggleLearnedFlag(cursor $cur, int $id)
    {
        if (isset(dcCore::app()->spamplemousse2_learned) && dcCore::app()->spamplemousse2_learned == 1) {   // @phpstan-ignore-line
            $req = 'UPDATE ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' SET comment_bayes = 1 WHERE comment_id = ' . $id;
            dcCore::app()->con->execute($req);
        }
    }
}
