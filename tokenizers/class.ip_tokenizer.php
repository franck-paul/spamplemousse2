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
@brief ip tokenizer

this class has to tokenize ip addresses
 */
class ip_tokenizer extends tokenizer
{
    public function __construct()
    {
        $this->prefix = 'ip';
    }

    /**
    Matches ip addresses in a string

    @param	str		<b>string</b>		the string to analyze
    @return 		<b>array</b>		array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match($str)
    {
        $result  = '';
        $matches = '';
        $num     = '(25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
        $regexp  = $num . '\.' . $num . '\.' . $num . '\.' . $num;
        $res     = preg_match('/' . $regexp . '(.*)/uism', $str, $matches);
        if ($res != 0) {
            $result   = [];
            $ip       = $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '.' . $matches[4];
            $pos      = mb_strpos($str, $ip . $matches[5]);
            $result[] = mb_substr($str, 0, $pos);
            $result[] = $ip;
            $result[] = $matches[5];
        } else {
            $result = 0;
        }

        return $result;
    }
}
