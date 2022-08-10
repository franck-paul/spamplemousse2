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
@brief reassembly tokenizer

this class detects and reassembles tokens like v.i.a.g.r.a
 */
class reassembly_tokenizer extends tokenizer
{
    /**
    Matches tokens of length equal to 1 separated only by 1 delimiter

    @param	str		<b>string</b>		the string to analyze
    @return 		<b>array</b>		array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match($str)
    {
        $result  = '';
        $matches = '';

        $regexp = '([$.:*|`@_]?([\w][$.:*|`@_])+[\w]?)';
        # FIXME this regexp does not detect "v i a g r a"
        # FIXME it does not seem to work with UTF8 words
        $res = preg_match('/' . $regexp . '(.*)/uism', $str, $matches);
        if ($res != 0) {
            $result   = [];
            $word_tmp = $matches[1];
            $word     = '';
            $i        = 0;
            $n        = mb_strlen($word_tmp);
            if ($n >= 4) {
                if (!preg_match('/[\w]/uis', $word_tmp[0])) {
                    $i = 1;
                }
                for (;$i < $n; $i = $i + 2) {
                    $word .= $word_tmp[$i];
                }
                $pos      = mb_strpos($str, $word_tmp);
                $result[] = mb_substr($str, 0, $pos);
                $result[] = $word;
                $result[] = $matches[3];
            } else {
                $result = 0;
            }
        } else {
            $result = 0;
        }

        return $result;
    }
}
