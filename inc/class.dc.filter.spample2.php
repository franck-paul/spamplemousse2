<?php

# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of Spamplemousse2, a plugin for Dotclear 2.
#
# Copyright (c) 2003-2008 Olivier Meunier and contributors
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

/// @defgroup SPAMPLE2 Spamplemousse2, a bayesian spam filter

/**
@ingroup SPAMPLE2
@brief Spamplemousse2 filter adapter class

This class implements all the methods needed for this plugin
to run as a spam filter.
 */
class dcFilterSpample2 extends dcSpamFilter
{
    public $name    = 'Spamplemousse2';
    public $has_gui = true;

    /**
    Set here the localized description of the filter.

    @return			<b>string</b>
     */
    protected function setInfo()
    {
        $this->description = __('A bayesian filter');
    }

    /**
    Returns a status message for a given comment which relates to the filtering process.

    @param	status			<b>integer</b>		Status of the comment
    @param	comment_id		<b>integer</b>		Id of the comment
    @return					<b>string</b>
     */
    public function getStatusMessage($status, $comment_id)
    {
        $p          = 0;
        $con        = $this->core->con;
        $spamFilter = new bayesian($this->core);
        $rs         = $con->select('SELECT comment_author, comment_email, comment_site, comment_ip, comment_content FROM ' . $this->core->blog->prefix . 'comment WHERE comment_id = ' . $comment_id);
        $rs->fetch();
        $p = $spamFilter->getMsgProba($rs->comment_author, $rs->comment_email, $rs->comment_site, $rs->comment_ip, $rs->comment_content);
        $p = round($p * 100);

        return sprintf(__('Filtered by %s, actual spamminess: %s %%'), $this->guiLink(), $p);
    }

    /**
    This method should return if a comment is a spam or not.

    Your filter should also fill $status variable with its own information if
    comment is a spam.

    @param		type	<b>string</b>		Comment type (comment or trackback)
    @param		author	<b>string</b>		Comment author
    @param		email	<b>string</b>		Comment author email
    @param		site	<b>string</b>		Comment author website
    @param		ip		<b>string</b>		Comment author IP address
    @param		content	<b>string</b>		Comment content
    @param		post_id	<b>integer</b>		Comment post_id
    @param[out]	status	<b>integer</b>		Comment status
    @return				<b>boolean</b>
     */
    public function isSpam(
        $type,
        $author,
        $email,
        $site,
        $ip,
        $content,
        $post_id,
        &$status
    ) {
        $spamFilter = new bayesian($this->core);

        $spam = $spamFilter->handle_new_message($author, $email, $site, $ip, $content);

        if ($spam == true) {
            $status = '';
        }

        return $spam;
    }

    /**
    This method is called when a non-spam (ham) comment becomes spam or when a
    spam becomes a ham. It trains the filter with this new user decision.

    @param[out]	status	<b>integer</b>		Comment status
    @param	filter		<b>string</b>		Filter name
    @param	type		<b>string</b>		Comment type (comment or trackback)
    @param	author		<b>string</b>		Comment author
    @param	email		<b>string</b>		Comment author email
    @param	site		<b>string</b>		Comment author website
    @param	ip			<b>string</b>		Comment author IP address
    @param	content		<b>string</b>		Comment content
    @param	rs			<b>record</b>		Comment record
     */
    public function trainFilter(
        $status,
        $filter,
        $type,
        $author,
        $email,
        $site,
        $ip,
        $content,
        $rs
    ) {
        $spamFilter = new bayesian($this->core);

        $rs2 = $this->core->con->select('SELECT comment_bayes, comment_bayes_err FROM ' . $this->core->blog->prefix . 'comment WHERE comment_id = ' . $rs->comment_id);
        $rs2->fetch();

        $spam = 0;
        if ($status == 'spam') { # the current action marks the comment as spam
            $spam = 1;
        }

        if ($rs2->comment_bayes == 0) {
            $spamFilter->train($author, $email, $site, $ip, $content, $spam);
            $req = 'UPDATE ' . $this->core->blog->prefix . 'comment SET comment_bayes = 1 WHERE comment_id = ' . $rs->comment_id;
            $this->core->con->execute($req);
        } else {
            $spamFilter->retrain($author, $email, $site, $ip, $content, $spam);
            $err = $rs2->comment_bayes_err ? 0 : 1;
            $req = 'UPDATE ' . $this->core->blog->prefix . 'comment SET comment_bayes_err = ' . $err . ' WHERE comment_id = ' . $rs->comment_id;
            $this->core->con->execute($req);
        }
    }

    /**
    This method handles the main gui used to configure this plugin.

    @param	url			<b>string</b>		url of the plugin
    @return				<b>string</b>		html content
     */
    public function gui($url)
    {
        $content    = '';
        $spamFilter = new bayesian($this->core);

        $action = !empty($_POST['action']) ? $_POST['action'] : null;

        # count nr of comments
        $nb_comm = 0;
        $req     = 'SELECT COUNT(comment_id) FROM ' . $this->core->blog->prefix . 'comment';
        $rs      = dcCore::app()->con->select($req);
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
            $params     = [$this->core, $spamFilter];
            $title      = __('Learning in progress...');
            $progress   = new progress($title, $urlprefix, $urlreturn, $func, $start, $stop, $inc, $this->core->getNonce(), $pos, $formparams);
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
                    $this->core->formNonce() .
                    '</p>' .
                    '</form>';

        $content .= '<h5>' . __('Maintenance') . '</h5>' .
                    '<form action="plugin.php?p=antispam&amp;f=dcFilterSpample2" method="post">' .
                    '<p><input type="submit" value="' . __('Cleanup') . '" /> ' .
                    form::hidden(['action'], 'cleanup') .
                    $this->core->formNonce() .
                    '</p>' .
                    '</form>';

        $content .= '<h5>' . __('Reset filter') . '</h5>' .
                    '<form action="plugin.php?p=antispam&amp;f=dcFilterSpample2" method="post">' .
                    '<p><input type="submit" onclick="return(confirm(\'' . __('Are you sure?') . '\'));" value="' . __('Delete all learned data') . '" /> ' .
                    form::hidden(['action'], 'reset') .
                    $this->core->formNonce() .
                    '</p>' .
                    '</form>';

        return $content;
    }

    /**
    This method is a hack to toggle the "learned" flag on a given comment.
    When a comment passes through the isSpam method of this filter for the first
    time, it is possible that the filter learns from this message, but in isSpam
    we are too early in the filtering process to be able to toggle the flag. So
    we set the global $GLOBALS['sp2_learned'] to 1, and when the process comes to
    its end, this method is triggered (by the events publicAfterCommentCreate and
    publicAfterTrackbackCreate), and we update the flag in the database.

    @param	cur			<b>cursor</b>		cursor on the comment
    @param	id			<b>integer</b>		id of the comment
     */
    public static function toggleLearnedFlag($cur, $id)
    {
        if (isset($GLOBALS['sp2_learned']) && $GLOBALS['sp2_learned'] == 1) {
            $req = 'UPDATE ' . dcCore::app()->blog->prefix . 'comment SET comment_bayes = 1 WHERE comment_id = ' . $id;
            dcCore::app()->con->execute($req);
        }
    }
}
