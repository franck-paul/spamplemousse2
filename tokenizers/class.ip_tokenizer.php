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
 * this class has to tokenize ip addresses
 */
class ip_tokenizer extends tokenizer
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
     * @return     array|int|string  Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match(string $str)
    {
        $result  = '';
        $matches = '';
        $num     = '(25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
        $regexp  = $num . '\.' . $num . '\.' . $num . '\.' . $num;
        if (preg_match('/' . $regexp . '(.*)/uism', $str, $matches)) {
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
