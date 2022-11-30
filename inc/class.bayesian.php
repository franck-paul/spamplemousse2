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

/**
@ingroup SPAMPLE2
@brief Spamplemousse2 main class

This class implements all the bayesian filtering logic.
 */
class bayesian
{
    public const SPAM_TOKEN_TABLE_NAME = 'spam_token';

    private $con;
    private $table;
    private $val_hapax;
    private $sct_spam;
    private $sct_ham;
    private $bias;
    private $retrain_limit;
    private $training_mode;
    private $tum_maturity;

    public function __construct()
    {
        $this->con   = dcCore::app()->con;
        $this->table = dcCore::app()->prefix . self::SPAM_TOKEN_TABLE_NAME;

        # all parameters
        $this->val_hapax     = 0.45; # hapaxial value
        $this->sct_spam      = 0.9999; # single corpus token (spam) probability
        $this->sct_ham       = 0.0001; # single corpus token (ham) probability
        $this->bias          = 1; # bias used in the computing of the word probability
        $this->retrain_limit = 5; # number of retries when retraining a message
        $this->tum_maturity  = 20; # number of hits for a token to be considered as mature
        $this->training_mode = 'TUM';
        /* valid values for training_mode are  :
            'TEFT' : train everything
                + works well if the amount of spam is not greater than 80% of the amount of ham
                + can cope with blogs having constantly changing comments
                - can cause errors if the amount of spam >> amount of ham
                - resource hungry, not for large volume of comments
                - creates about 70% of uninteresting data in the dataset
            'TOE' : train on error
                + can deal with large volume of spams
                + disk space use much lower than TEFT
                + works well if the spam ratio is greater than 90%
                - false positives, very poor accuracy for blogs with constantly changing comments
                - slow at learning new types of spam
            'TUM' : train until mature
                + middle ground between TEFT and TOE
                + like TEFT, learns new data but stops when it has matured (data mature only updated when an error is done)
                + quick retrain
                + best for medium volume of comments
        */
    }

    /**
    Handles new messages (filtering and training of the filter)

    @param		author	<b>string</b>		Comment author
    @param		email	<b>string</b>		Comment author email
    @param		site	<b>string</b>		Comment author website
    @param		ip		<b>string</b>		Comment author IP address
    @param		content	<b>string</b>		Comment content
    @return				<b>mixed</b>		true if spam, false if non-spam or null if undefined
     */
    public function handle_new_message(
        $author,
        $email,
        $site,
        $ip,
        $content
    ) {
        $spam = 0;
        $tok  = $this->tokenize(
            $author,
            $email,
            $site,
            $ip,
            $content
        );
        $proba = $this->get_probabilities($tok);
        $p     = $this->combine($proba);
        if ($p > 0.5) {
            $spam = 1;
        }
        if ($this->training_mode != 'TOE') {
            $this->basic_train($tok, $spam);
            dcCore::app()->spamplemousse2_learned = 1;
        }

        $result = null;
        if ($p < 0.1) {
            $result = false;
        } elseif ($p > 0.5) {
            $result = true;
        }

        return $result;
    }

    /**
    Initial training of the filter for a given message

    @param		author	<b>string</b>		Comment author
    @param		email	<b>string</b>		Comment author email
    @param		site	<b>string</b>		Comment author website
    @param		ip		<b>string</b>		Comment author IP address
    @param		content	<b>string</b>		Comment content
    @param		spam	<b>integer</b>		1 if spam
     */
    public function train($author, $email, $site, $ip, $content, $spam)
    {
        $tok = $this->tokenize($author, $email, $site, $ip, $content);
        if ($this->training_mode != 'TOE') {
            $this->basic_train($tok, $spam);
        }
    }

    /**
    Retraining of the filter for a given message

    @param		author	<b>string</b>		Comment author
    @param		email	<b>string</b>		Comment author email
    @param		site	<b>string</b>		Comment author website
    @param		ip		<b>string</b>		Comment author IP address
    @param		content	<b>string</b>		Comment content
    @param		spam	<b>integer</b>		1 if spam
     */
    public function retrain($author, $email, $site, $ip, $content, $spam)
    {
        $tok = $this->tokenize($author, $email, $site, $ip, $content);

        # we retrain the dataset with this message until the
        #	probability of this message to be a spam changes
        $init_spam = $current_spam = 0;
        $proba     = $this->get_probabilities($tok);
        $p         = $this->combine($proba);
        if ($p > 0.5) {
            $init_spam = $current_spam = 1;
        }
        $count = 0;
        # the neutralization of the dataset is done by the first pass in this loop
        do {
            $proba = $this->get_probabilities($tok);
            $p     = $this->combine($proba);
            if ($p > 0.5) {
                $current_spam = 1;
            } else {
                $current_spam = 0;
            }
            $count++;
            $this->basic_train($tok, $spam, true);
        } while (($init_spam == $current_spam) && ($count < $this->retrain_limit));
    }

    /**
    Get the probability of a message to be a spam

    @param		author	<b>string</b>		Comment author
    @param		email	<b>string</b>		Comment author email
    @param		site	<b>string</b>		Comment author website
    @param		ip		<b>string</b>		Comment author IP address
    @param		content	<b>string</b>		Comment content
    @return				<b>float</b>		Spam probability
     */
    public function getMsgProba(
        $author,
        $email,
        $site,
        $ip,
        $content
    ) {
        $tok = $this->tokenize(
            $author,
            $email,
            $site,
            $ip,
            $content
        );
        $proba = $this->get_probabilities($tok);

        return $this->combine($proba);
    }

    /**
    decodes the input string,
        for the moment, it deletes the html tags and comments

    @param		s	<b>string</b>		The string to decode
    @return			<b>string</b>		The decoded string
     */
    private function decode($s)
    {
        $s = preg_replace('/&lt;/uism', '<', $s);
        $s = preg_replace('/&gt;/uism', '>', $s);
        $s = preg_replace('/&quot;/uism', '"', $s);

        $s = preg_replace('/<a href="([^"\'>]*)">([^<]+)<\/a>/ism', ' $2 $1 ', $s);
        $s = preg_replace('/<!-- .* -->/Uuism', ' ', $s);
        $s = strip_tags($s);
        $s = trim($s);

        return $s;
    }

    /**
    tokenization of a comment

    @param		m_author	<b>string</b>		Comment author
    @param		m_email		<b>string</b>		Comment author email
    @param		m_site		<b>string</b>		Comment author website
    @param		m_ip		<b>string</b>		Comment author IP address
    @param		m_content	<b>string</b>		Comment content
    @return					<b>array</b>		Array of tokens
     */
    private function tokenize(
        $m_author,
        $m_email,
        $m_site,
        $m_ip,
        $m_content
    ) {
        $url_t   = new url_tokenizer();
        $email_t = new email_tokenizer();
        $ip_t    = new ip_tokenizer();
        $red_t   = new redundancies_tokenizer();
        $rea_t   = new reassembly_tokenizer();

        # headers handling
        $nom = $mail = $site = $ip = $contenu = [];

        # name
        $elem = $url_t->create_token($this->decode($m_author), 'Hname');
        $nom  = [$elem];
        $nom  = $url_t->tokenize($nom);
        $nom  = $email_t->tokenize($nom);
        $nom  = $ip_t->tokenize($nom);
        $nom  = $red_t->tokenize($nom);
        $nom  = $rea_t->tokenize($nom);
        $nom  = $rea_t->default_tokenize($nom);

        # mail
        $elem = $url_t->create_token($this->decode($m_email), 'Hmail');
        $mail = [$elem];
        $mail = $email_t->tokenize($mail);
        $mail = $email_t->default_tokenize($mail);

        # website
        $elem = $url_t->create_token($this->decode($m_site), 'Hsite');
        $site = [$elem];
        $site = $url_t->tokenize($site);
        $site = $url_t->default_tokenize($site);

        # ip
        $elem = $url_t->create_token($this->decode($m_ip), 'Hip');
        $ip   = [$elem];
        $ip   = $ip_t->tokenize($ip);
        $ip   = $ip_t->default_tokenize($ip);

        # content handling
        $elem    = $url_t->create_token($this->decode($m_content), '');
        $contenu = [$elem];
        $contenu = $url_t->tokenize($contenu);
        $contenu = $email_t->tokenize($contenu);
        $contenu = $ip_t->tokenize($contenu);
        $contenu = $red_t->tokenize($contenu);
        $contenu = $rea_t->tokenize($contenu);
        $contenu = $rea_t->default_tokenize($contenu);

        # result
        $tok = array_merge($nom, $mail, $site, $ip, $contenu);
        $tok = $this->clean_tokenized_string($tok);

        return $tok;
    }

    /**
    gives a simple array of strings from an array of tokens

    @param		tok			<b>array</b>		Array of tokens
    @return					<b>array</b>		Array of strings
     */
    private function clean_tokenized_string($tok)
    {
        $token = [];

        foreach ($tok as $i) {
            $token[] = $i['elem'];
        }

        return $token;
    }

    /**
    Gives probabilities for each token

    @param		tok			<b>array</b>		Array of tokens
    @return					<b>array</b>		Array of probabilities
     */
    private function get_probabilities($tok)
    {
        $proba = [];

        foreach ($tok as $i) {
            $p      = $this->val_hapax;
            $strReq = 'SELECT token_nham, token_nspam, token_p FROM ' . $this->table . ' WHERE token_id = \'' . $this->con->escape($i) . '\'';
            $rs     = new dcRecord($this->con->select($strReq));
            if (!$rs->isEmpty()) {
                $p = $rs->token_p;
            }
            $proba[] = $p;
        }

        return $proba;
    }

    /**
    Basic training for one token

    @param		t			<b>string</b>		the token
    @param		spam		<b>integer</b>		1 if spam
    @param		retrain		<b>boolean</b>		true if the message was already trained
     */
    private function basic_train_unit($t, $spam, $retrain = false)
    {
        $strReq    = 'SELECT COUNT(token_nham) FROM ' . $this->table;
        $rs        = new dcRecord($this->con->select($strReq));
        $total_ham = $rs->f(0);

        $strReq     = 'SELECT COUNT(token_nspam) FROM ' . $this->table;
        $rs         = new dcRecord($this->con->select($strReq));
        $total_spam = $rs->f(0);

        $token       = null;
        $known_token = false;

        # we determine if the token is already in the dataset
        $strReq = 'SELECT token_nham, token_nspam, token_p, token_mature FROM ' . $this->table . ' WHERE token_id = \'' . $this->con->escape($t) . '\'';
        $rs     = new dcRecord($this->con->select($strReq));

        if (!$rs->isEmpty()) {
            $known_token = true;
            if ($retrain) {
                # we test if it is possible to move the state of the token
                # if it is present in 0 ham and we try to pass it in spam, we have a problem
                if (!(($spam && (!$rs->token_nham)) || ((!$spam) && (!$rs->token_nspam)))) {
                    $known_token = true;
                } else {
                    return;
                }
            } else {
                $known_token = true;
            }
        } else {
            $known_token = false;
        }

        if ($known_token) {
            $token = ['token_id' => $t, 'token_nham' => $rs->token_nham, 'token_nspam' => $rs->token_nspam, 'token_p' => $rs->token_p, 'token_mature' => $rs->token_mature];
        }

        # we compute the new values for total_spam and total_ham
        if ($spam) {
            $total_spam += $known_token ? 1 : 0;
            $total_spam += $known_token ? 0 : 1;
            if ($retrain) {
                $total_ham -= $known_token ? 1 : 0;
            }
        } else {
            $total_ham += $known_token ? 1 : 0;
            $total_ham += $known_token ? 0 : 1;
            if ($retrain) {
                $total_spam -= $known_token ? 1 : 0;
            }
        }

        if ($known_token) {
            if (($this->training_mode != 'TUM') || ($token['token_mature'] != 1) || $retrain) {
                # update
                # nr of occurences in each corpuses
                $nspam = 0;
                $nham  = 0;
                $nr    = 0;
                if ($spam) {
                    $nspam = $token['token_nspam'] + 1;
                    if ($retrain) {
                        $nham = $token['token_nham'] - 1;
                    } else {
                        $nham = $token['token_nham'];
                    }
                } else {
                    if ($retrain) {
                        $nspam = $token['token_nspam'] - 1;
                    } else {
                        $nspam = $token['token_nspam'];
                    }
                    $nham = $token['token_nham'] + 1;
                }
                $nr = $nspam * 2 + $nham; # number of occurences in the two corpuses

                # hapaxes handling
                if ($nr < 5) {
                    $p = $this->val_hapax;
                } elseif ($nham == 0) { # single corpus token handling
                    $p = $this->sct_ham;
                } elseif ($nspam == 0) {
                    $p = $this->sct_spam;
                } else {
                    $p = $this->compute_proba($nham, $nspam, $total_ham, $total_spam);
                    if ($p >= 1) {
                        $p = $this->sct_spam;
                    }
                    if ($p <= 0) {
                        $p = $this->sct_ham;
                    }
                }
                if ($this->training_mode == 'TUM') {
                    # evaluate token maturity
                    $maturity = ($nr >= $this->tum_maturity) ? 1 : 0;
                    $strReq   = 'UPDATE ' . $this->table . ' SET token_nham=' . $nham . ', token_nspam=' .
                            $nspam . ', token_mdate=\'' . date('Y-m-d H:i:s') . '\', token_p=\'' .
                            $p . '\', token_mature=\'' . $maturity . '\' WHERE token_id=\'' . $this->con->escape($token['token_id']) . '\'';
                    $this->con->execute($strReq);
                } else {
                    $strReq = 'UPDATE ' . $this->table . ' SET token_nham=' . $nham . ', token_nspam=' .
                            $nspam . ', token_mdate=\'' . date('Y-m-d H:i:s') . '\', token_p=\'' .
                            $p . '\' WHERE token_id=\'' . $this->con->escape($token['token_id']) . '\'';
                    $this->con->execute($strReq);
                }
            }
        }

        if (!$known_token) { # unknown token
            #insert an hapax
            $nspam = 0;
            $nham  = 0;
            if ($spam) {
                $nspam = 1;
            } else {
                $nham = 1;
            }
            $p      = $this->val_hapax;
            $strReq = 'INSERT INTO ' . $this->table . ' (token_id, token_nham, token_nspam, token_mdate, token_p) VALUES (\'' . $this->con->escape($t) . '\',' . $nham . ',' . $nspam . ',\'' . date('Y-m-d H:i:s') . '\' ,\'' . $p . '\')';
            $this->con->execute($strReq);
        }
    }

    /**
    Basic training for a message

    @param		tok			<b>array</b>		an array of tokens
    @param		spam		<b>integer</b>		1 if spam
    @param		retrain		<b>boolean</b>		true if the message was already trained
     */
    private function basic_train($tok, $spam, $retrain = false)
    {
        foreach ($tok as $t) {
            $this->basic_train_unit($t, $spam, $retrain);
        }
    }

    /**
    Computes the probability of a token according to its parameters

    @param		nham			<b>integer</b>		number of occurences in the ham corpus
    @param		nspam			<b>integer</b>		number of occurences in the spam corpus
    @param		total_ham		<b>integer</b>		total occurences in the ham corpus
    @param		total_spam		<b>integer</b>		total occurences in the spam corpus
    @return						<b>float</b>		probability
     */
    private function compute_proba($nham, $nspam, $total_ham, $total_spam)
    {
        if ($total_spam == 0) {
            $total_spam++;
        }

        if ($total_ham == 0) {
            $total_ham++;
        }

        $a = ($nspam / $total_spam);
        $b = ($nham / $total_ham);
        if ($this->bias) {
            $b = 2 * $b;
        }

        return $a / ($a + $b);
    }

    /**
    Computes Fisher-Robinson's inverse Chi-Square function
            adapted from a C version ("Ending Spam", Jonathan Zdziarski, p. 79)

    @param		x	<b>float</b>	parameter x :)
    @param		v	<b>integer</b>	parameter v
    @return			<b>float</b>
     */
    private function inverse_chi_square($x, $v)
    {
        $i = 0;

        $m = $x / 2;
        $s = exp(0 - $m);
        $t = $s;

        for ($i = 1; $i < ($v / 2); $i++) {
            $t *= $m / $i;
            $s += $t;
        }

        return ($s < 1) ? $s : 1;
    }

    /**
    Computes the final probability of a message using Fisher-Robinson's inverse Chi-Square

    @param		proba	<b>array</b>	array of probabilities
    @return				<b>float</b>	the resulting probability
     */
    private function combine($proba)
    {
        # filter useful data (probability in [0;0.1] or [0.9;1])
        foreach ($proba as $key => $p) {
            if (($p > 0.1) && ($p < 0.9)) {
                unset($proba[$key]);
            }
        }

        $n = count($proba);
        $i = 0.5;
        if ($n != 0) {
            $prod1 = 1;
            $prod2 = 1;
            foreach ($proba as $p) {
                $prod1 *= $p;
                $prod2 *= (1 - $p);
            }

            $h = $this->inverse_chi_square(-2 * log($prod1), 2 * $n);
            $s = $this->inverse_chi_square(-2 * log($prod2), 2 * $n);
            $i = (1 + $h - $s) / 2;
        }

        return $i;
    }

    /**
    Trains the filter on old messages

    @param		limit	<b>integer</b>	number of comments to process
    @param		offset	<b>integer</b>	number of comments to skip before processing
     */
    public static function feedCorpus($limit, $offset)
    {
        if (!isset(dcCore::app()->spamplemousse2_bayes)) {
            dcCore::app()->spamplemousse2_bayes = new bayesian(dcCore::app());
        }

        $rs = new dcRecord(dcCore::app()->con->select('SELECT comment_id, comment_author, comment_email, comment_site, comment_ip, comment_content, comment_status, comment_bayes FROM ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' ORDER BY comment_id LIMIT ' . $limit . ' OFFSET ' . $offset));

        while ($rs->fetch()) {
            if ($rs->comment_bayes == 0) {
                $spam = 0;
                if ($rs->comment_status == dcBlog::COMMENT_JUNK) {
                    $spam = 1;
                }
                dcCore::app()->spamplemousse2_bayes->train($rs->comment_author, $rs->comment_email, $rs->comment_site, $rs->comment_ip, $rs->comment_content, $spam);
                $req = 'UPDATE ' . dcCore::app()->blog->prefix . dcBlog::COMMENT_TABLE_NAME . ' SET comment_bayes = 1 WHERE comment_id = ' . $rs->comment_id;
                dcCore::app()->con->execute($req);
            }
        }
    }

    /**
    Cleans the dataset from non-pertinent tokens
     */
    public function cleanup()
    {
        $delim = '';
        if (DC_DBDRIVER == 'pgsql') {
            $delim = '\'';
        }
        # delete data stale for 6 months
        $req = 'DELETE FROM ' . $this->table . ' WHERE (NOW() - INTERVAL ' . $delim . '6 MONTH' . $delim . ') > token_mdate';
        dcCore::app()->con->execute($req);

        # delete all hapaxes stale for 15 days
        $req = 'DELETE FROM ' . $this->table . ' WHERE  (token_nham +2 * token_nspam ) < 5 AND (NOW() - INTERVAL ' . $delim . '15 DAY' . $delim . ') > token_mdate';
        dcCore::app()->con->execute($req);

        # if in TUM mode, delete all matured data between 0.3 and 0.7
        if ($this->training_mode == 'TUM') {
            $req = 'DELETE FROM ' . $this->table . ' WHERE token_p > 0.3 AND token_p < 0.7 AND token_mature = 1';
            dcCore::app()->con->execute($req);
        }
    }

    /**
    Reset filter : deletes all learned data
     */
    public function resetFilter()
    {
        $req = 'UPDATE ' . dcCore::app()->blog->prefix . dcblog::COMMENT_TABLE_NAME . ' SET comment_bayes = 0, comment_bayes_err = 0';
        dcCore::app()->con->execute($req);
        $req = 'DELETE FROM ' . $this->table;
        dcCore::app()->con->execute($req);
    }

    /**
    Returns the number of learned comments

    @return		<b>integer</b>	number of learned comments
     */
    public function getNumLearnedComments()
    {
        $result = 0;
        $req    = 'SELECT COUNT(comment_id) FROM ' . dcCore::app()->blog->prefix . dcblog::COMMENT_TABLE_NAME . ' WHERE comment_bayes = 1';
        $rs     = new dcRecord($this->con->select($req));
        if ($rs->fetch()) {
            $result = $rs->f(0);
        }

        return $result;
    }

    /**
    Returns the number of wrongly classified comments

    @return		<b>integer</b>	number of wrongly classified comments
     */
    public function getNumErrorComments()
    {
        $result = 0;
        $req    = 'SELECT COUNT(comment_id) FROM ' . dcCore::app()->blog->prefix . dcblog::COMMENT_TABLE_NAME . ' WHERE comment_bayes_err = 1';
        $rs     = new dcRecord($this->con->select($req));
        if ($rs->fetch()) {
            $result = $rs->f(0);
        }

        return $result;
    }

    /**
    Returns the number of learned tokens

    @return		<b>integer</b>	number of learned tokens
     */
    public function getNumLearnedTokens()
    {
        $result = 0;
        $req    = 'SELECT COUNT(token_id) FROM ' . $this->table;
        $rs     = new dcRecord($this->con->select($req));
        if ($rs->fetch()) {
            $result = $rs->f(0);
        }

        return $result;
    }
}
