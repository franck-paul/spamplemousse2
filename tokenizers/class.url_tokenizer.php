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
@brief url tokenizer

this class tokenizes urls
 */
class url_tokenizer extends tokenizer
{
    public function __construct()
    {
        $this->prefix = 'url';
    }

    /**
    Matches urls in a string

    @param	str		<b>string</b>		the string to analyze
    @return 		<b>array</b>		array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match($str)
    {
        $result  = '';
        $matches = '';

        $scheme      = 'http:\/\/';
        $extrem_host = '[A-Z0-9]';
        $elem_host   = '[A-Z0-9.-]{1,61}';
        $tld         = '[A-Z]{2,6}';
        $num         = '(25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
        $ip          = $num . '\.' . $num . '\.' . $num . '\.' . $num;
        $path        = '[^\'">\s\r\n]*';
        $delim       = '[\'">\s\r\n]?';

        $regexp = $scheme . '((' . $extrem_host . $elem_host . $extrem_host . '\.' . $tld . ')|(' . $ip . '))(' . $path . ')' . $delim;
        $res    = preg_match('/' . $regexp . '(.*)/uism', $str, $matches);
        if ($res != 0) {
            $result     = [];
            $url        = 'http://' . $matches[1] . $matches[8];
            $pos        = mb_strpos($str, $url);
            $result[]   = mb_substr($str, 0, $pos);
            $matched_ip = $matches[3];
            if ($matched_ip) {
                $result[] = $matched_ip;
            } else { // we matched a domain name
                $tok    = $this->create_token($matches[2], '');
                $result = array_merge($result, $this->default_tokenize([$tok], '', 'string', '.'));
            }
            $tok      = $this->create_token($matches[8], '');
            $result   = array_merge($result, $this->default_tokenize([$tok], '', 'string', '/?=.:&'));
            $result[] = $matches[9];
        } else {
            $result = 0;
        }

        return $result;
    }
}
