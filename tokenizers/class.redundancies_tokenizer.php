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
 * This class detects and normalizes redundancies in a string
 */
class redundancies_tokenizer extends tokenizer
{
    /**
     * Matches redundancies in a string (example: viagra!!!!!!! becomes viagra!)
     *
     * @param      string            $str    The string to analyze
     *
     * @return     array|int|string  Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match(string $str)
    {
        $result  = '';
        $matches = '';

        $regexp = '([\w.-]+[!?]{1})([!?]+)';
        if (preg_match('/' . $regexp . '(.*)/uism', $str, $matches)) {
            $result   = [];
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
