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
 * this class detects and reassembles tokens like v.i.a.g.r.a
 */
class Reassembly extends Tokenizer
{
    /**
     * Matches tokens of length equal to 1 separated only by 1 delimiter
     *
     * @param      string            $str    The string to analyze
     *
     * @return     array<string>|int  Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    protected function match(string $str): array|int
    {
        $result  = [];
        $matches = '';

        $regexp = '([$.:*|`@_]?([\w][$.:*|`@_])+[\w]?)';
        # FIXME this regexp does not detect "v i a g r a"
        # FIXME it does not seem to work with UTF8 words
        if (preg_match('/' . $regexp . '(.*)/uism', $str, $matches)) {
            $result   = [];
            $word_tmp = $matches[1];
            $word     = '';
            $i        = 0;
            $n        = mb_strlen($word_tmp);
            if ($n >= 4) {
                if (!preg_match('/[\w]/uis', $word_tmp[0])) {
                    $i = 1;
                }

                for (;$i < $n; $i += 2) {
                    $word .= $word_tmp[$i];
                }

                $pos      = mb_strpos($str, $word_tmp);
                $result[] = mb_substr($str, 0, (int) $pos);
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
