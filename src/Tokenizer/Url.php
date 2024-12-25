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

namespace Dotclear\Plugin\spamplemousse2\Tokenizer;

/**
this class tokenizes urls
 */
class Url extends Tokenizer
{
    public function __construct()
    {
        $this->prefix = 'url';
    }

    /**
     * Matches urls in a string
     *
     * @param      string            $str    The string
     *
     * @return     array<string>|int     Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match(string $str)
    {
        $result  = [];
        $matches = '';

        $scheme      = '[http|https]:\/\/';
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
            $url        = 'http://' . ($matches[1] ?? '') . ($matches[8] ?? '');    // @phpstan-ignore-line
            $pos        = mb_strpos($str, $url);
            $result[]   = mb_substr($str, 0, (int) $pos);
            $matched_ip = $matches[3] ?? '';    // @phpstan-ignore-line
            if ($matched_ip !== '' && $matched_ip !== '0') {
                $result[] = $matched_ip;
            } else { // we matched a domain name
                $tok = $this->create_token($matches[2] ?? '', '');  // @phpstan-ignore-line
                if (!is_null($tok)) {
                    $default = $this->default_tokenize_string([$tok], '', '.');
                    $result  = [...$result, ...$default];
                }
            }

            $tok = $this->create_token($matches[8] ?? '', '');  // @phpstan-ignore-line
            if (!is_null($tok)) {
                $default  = $this->default_tokenize_string([$tok], '', '/?=.:&');
                $result   = array_merge($result, $default);
                $result[] = $matches[9] ?? '';  // @phpstan-ignore-line
            }
        } else {
            $result = 0;
        }

        return $result;
    }
}
