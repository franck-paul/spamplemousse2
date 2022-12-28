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
 * This class is the parent of all tokenizers.
 */
abstract class tokenizer
{
    /**
     * The prefix associated to each generated elements
     */
    protected string $prefix = '';

    /**
     * Matches something in a string
     *
     * @param      string            $str    The string
     *
     * @return     array|int|string     Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    abstract protected function match(string $str);

    /**
     * Creates an element of the token array.
     *
     * @param      string  $elem    The string containing tokens
     * @param      string  $prefix  The prefix associated to the $elem string
     * @param      bool    $final   The final state of the token
     *
     * @return     array|null   The element of the token array
     */
    public function create_token(string $elem, string $prefix, bool $final = false)
    {
        $token = null;

        if ($elem !== '') {
            if ($final && ($prefix !== '')) {
                $elem = $prefix . '*' . $elem;
            }

            $token = [
                'elem'   => $elem,
                'prefix' => $prefix,
                'final'  => $final,
            ];
        }

        return $token;
    }

    /**
     * Tokenizes strings not finalized in an array of token, based on a specified matching method
     *
     * @param      array  $t      Array of tokens
     *
     * @return     array  Array of tokens
     */
    public function tokenize(array $t): array
    {
        $tab = [];
        foreach ($t as $e) {
            # we are working on non-finalized strings
            if (!$e['final']) {
                $s      = $e['elem'];
                $pre    = $e['prefix'];
                $cur    = [];
                $remain = $s;
                do {
                    if ($remain != '') {
                        # call the matching method
                        $matches = $this->match($remain);

                        if (is_array($matches) && count($matches) > 0) {
                            # trim and insert the first match
                            $n          = count($matches) - 1;
                            $matches[0] = trim((string) $matches[0]);
                            # part of the string left to the found tokens
                            if ($matches[0] != '') {
                                $cur[] = $this->create_token($matches[0], $pre, false);
                            }

                            # matched tokens handling
                            $i = 1;
                            while ($i != $n) {
                                # we compute here the new prefix
                                $p = '';
                                if (!empty($pre) && !empty($this->prefix)) {
                                    $p = $pre . '*' . $this->prefix;
                                } else {
                                    $p = $pre . $this->prefix;
                                }
                                $cur[] = $this->create_token($matches[$i], $p, true);
                                $i++;
                            }

                            # we trim the part of the string right to the found tokens
                            # and we insert it in $remain
                            $remain = trim((string) $matches[$n]);
                        } else {
                            # part of the string right to the found tokens
                            $remain = trim((string) $remain);
                            if ($remain != '') {
                                $cur[]  = $this->create_token($remain, $pre, false);
                                $remain = '';
                            }
                        }
                    }
                } while ($remain != '');
                $tab = array_merge($tab, $cur);
            } else {
                $tab[] = $e;
            }
        }

        return($tab);
    }

    /**
     * Default tokenization of a string, based on a fixed list of delimiters
     *
     * @param      array  $t        Array of tokens
     * @param      string $prefix   The prefix to add to the new tokens
     * @param      string $type     The result type : 'token' or 'string', returns an array of tokens or an array of string (like match_url)
     * @param      string $delim    List of delimiters to use for the tokenization
     *
     * @return     array  Array of tokens or array of strings
     */
    public function default_tokenize(array $t, string $prefix = '', string $type = 'token', string $delim = ''): array
    {
        if ($delim == '') {
            $delim = '.,;:"?[]{}()+-*/=<>|&~`@_' . "\r\n";
        }

        $tab = [];
        if (!is_array($t)) {
            return $tab;
        }

        foreach ($t as $e) {
            if (!$e['final']) {
                if (!empty($e['elem'])) {
                    $i   = 0; # start of mb_substring
                    $j   = 0; # end of mb_substring
                    $s   = $e['elem'];
                    $n   = mb_strlen($s);
                    $pre = $e['prefix'];
                    while ($j != $n) {
                        if ((mb_strpos($delim, mb_substr($s, $j, 1)) !== false) || (mb_substr($s, $j, 1) == ' ')) {
                            $sub = mb_substr($s, $i, $j - $i);
                            if ($sub != '') {
                                if ($type == 'token') {
                                    $p = ''; # new prefix
                                    if (!empty($pre) && !empty($prefix)) {
                                        $p = $pre . '*' . $prefix;
                                    } else {
                                        $p = $pre . $prefix;
                                    }
                                    $tab[] = $this->create_token($sub, $p, true);
                                } else {
                                    $tab[] = $sub;
                                }
                            }
                            $i = $j + 1;
                        }
                        $j++;
                    }
                    $j--;
                    # handling of the last word
                    if (!((mb_strpos($delim, mb_substr($s, $j, 1)) !== false) && (mb_substr($s, $j, 1) == ' '))) {
                        $sub = mb_substr($s, $i, $j - $i + 1);
                        if ($sub != '') {
                            if ($type == 'token') {
                                $p = ''; # new prefix
                                if (!empty($pre) && !empty($prefix)) {
                                    $p = $pre . '*' . $prefix;
                                } else {
                                    $p = $pre . $prefix;
                                }
                                $tab[] = $this->create_token($sub, $p, true);
                            } else {
                                $tab[] = $sub;
                            }
                        }
                    }
                }
            } else {
                $tab[] = $type === 'token' ? $e : $e['elem'];
            }
        }

        return $tab;
    }
}
