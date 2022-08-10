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
@brief email tokenizer

this class has to tokenize email addresses
 */
class email_tokenizer extends tokenizer
{
    /**
    Constructor
     */
    public function __construct()
    {
        $this->prefix = 'email';
    }

    /**
    Matches mail addresses in a string

    @param	str		<b>string</b>		the string to analyze
    @return 		<b>array</b>		array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match($str)
    {
        $result  = '';
        $matches = '';

        $debut_mail  = '[\d\w\/+!=#|$?%{^&}*`\'~-]';
        $elem_mail   = '[.\d\w\/+!=#|$?%{^&}*`\'~-]';
        $extrem_host = '[A-Z0-9]';
        $elem_host   = '[A-Z0-9.-]{1,61}';
        $tld         = '[A-Z]{2,6}';

        $regexp = '(' . $debut_mail . $elem_mail . '*)@(' . $extrem_host . $elem_host . $extrem_host . '\.' . $tld . ')';
        $res    = preg_match('/' . $regexp . '(.*)/uism', $str, $matches);
        if ($res != 0) {
            $result = [];

            $mail     = $matches[1] . '@' . $matches[2];
            $pos      = mb_strpos($str, $mail);
            $result[] = mb_substr($str, 0, $pos);
            $result[] = $matches[1];
            $result   = array_merge($result, explode('.', $matches[2]));
            $result[] = $matches[3];
        } else {
            $result = 0;
        }

        return $result;
    }
}
