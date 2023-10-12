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
 * This class has to tokenize email addresses
 */
class Email extends Tokenizer
{
    public function __construct()
    {
        $this->prefix = 'email';
    }

    /**
     * Matches email addresses in a string
     *
     * @param      string            $str    The string to analyze
     *
     * @return     array<string>|int|string  Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match(string $str)
    {
        $result  = [];
        $matches = '';

        $debut_mail  = '[\d\w\/+!=#|$?%{^&}*`\'~-]';
        $elem_mail   = '[.\d\w\/+!=#|$?%{^&}*`\'~-]';
        $extrem_host = '[A-Z0-9]';
        $elem_host   = '[A-Z0-9.-]{1,61}';
        $tld         = '[A-Z]{2,6}';

        $regexp = '(' . $debut_mail . $elem_mail . '*)@(' . $extrem_host . $elem_host . $extrem_host . '\.' . $tld . ')';
        if (preg_match('/' . $regexp . '(.*)/uism', $str, $matches)) {
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
