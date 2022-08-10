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
@brief redundancies tokenizer

this class detects and normalizes redundancies in a string
 */
class redundancies_tokenizer extends tokenizer
{
    /**
    Matches redundancies in a string (example: viagra!!!!!!! becomes viagra!)

    @param	str		<b>string</b>		the string to analyze
    @return 		<b>array</b>		array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match($str)
    {
        $result  = '';
        $matches = '';

        $regexp = '([\w.-]+[!?]{1})([!?]+)';
        $res    = preg_match('/' . $regexp . '(.*)/uism', $str, $matches);
        if ($res != 0) {
            $result = [];

            $word     = $matches[1];
            $pos      = mb_strpos($str, $word);
            $result[] = mb_substr($str, 0, $pos);
            $result[] = $word;
            $result[] = $matches[3];
        } else {
            $result = 0;
        }

        return $result;
    }
}
