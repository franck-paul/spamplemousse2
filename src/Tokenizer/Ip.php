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
 * this class has to tokenize ip addresses
 */
class Ip extends Tokenizer
{
    public function __construct()
    {
        $this->prefix = 'ip';
    }

    /**
     * Matches IPv4 addresses in a string
     *
     * @param      string            $str    The string to analyze
     *
     * @return     array<string>|int  Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match(string $str): array|int
    {
        $result  = [];
        $matches = '';
        $num     = '(25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
        $regexp  = $num . '\.' . $num . '\.' . $num . '\.' . $num;
        if (preg_match('/' . $regexp . '(.*)/uism', $str, $matches)) {
            $result   = [];
            $ip       = $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '.' . $matches[4];
            $pos      = mb_strpos($str, $ip . $matches[5]);
            $result[] = mb_substr($str, 0, (int) $pos);
            $result[] = $ip;
            $result[] = $matches[5];
        } else {
            $result = 0;
        }

        return $result;
    }
}
