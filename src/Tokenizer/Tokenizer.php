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
 * This class is the parent of all tokenizers.
 */
abstract class Tokenizer
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
     * @return     array<int, string>|int|string     Array of strings, containing : (left string, match1, match2, ..., right string)
     */
    abstract protected function match(string $str);

    /**
     * Creates an element of the token array.
     *
     * @param      string  $elem    The string containing tokens
     * @param      string  $prefix  The prefix associated to the $elem string
     * @param      bool    $final   The final state of the token
     *
     * @return     array<string, mixed>|null   The element of the token array
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
     * @param      array<int, array<string, mixed>>  $t      Array of tokens
     *
     * @return     array<int, array<string, mixed>>  Array of tokens
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

                        if (is_array($matches) && $matches !== []) {
                            # trim and insert the first match
                            $n          = count($matches) - 1;
                            $matches[0] = trim($matches[0]);
                            # part of the string left to the found tokens
                            if ($matches[0] !== '' && !is_null($new_token = $this->create_token($matches[0], $pre, false))) {
                                $cur[] = $new_token;
                            }

                            # matched tokens handling
                            $i = 1;
                            while ($i != $n) {
                                # we compute here the new prefix
                                $p = !empty($pre) && $this->prefix !== '' ? $pre . '*' . $this->prefix : $pre . $this->prefix;
                                if (!is_null($new_token = $this->create_token($matches[$i], $p, true))) {
                                    $cur[] = $new_token;
                                }

                                ++$i;
                            }

                            # we trim the part of the string right to the found tokens
                            # and we insert it in $remain
                            $remain = trim($matches[$n]);
                        } else {
                            # part of the string right to the found tokens
                            $remain = trim((string) $remain);
                            if ($remain !== '') {
                                if (!is_null($new_token = $this->create_token($remain, $pre, false))) {
                                    $cur[] = $new_token;
                                }

                                $remain = '';
                            }
                        }
                    }
                } while ($remain != '');

                $tab = [...$tab, ...$cur];
            } else {
                $tab[] = $e;
            }
        }

        return $tab;
    }

    /**
     * Default tokenization of a string, based on a fixed list of delimiters
     *
     * @param      array<int, array<string, mixed>>     $t        Array of tokens
     * @param      string                               $prefix   The prefix to add to the new tokens
     * @param      string                               $delim    List of delimiters to use for the tokenization
     *
     * @return     array<array<string, mixed>>  Array of tokens
     */
    public function default_tokenize_token(array $t, string $prefix = '', string $delim = ''): array
    {
        return array_filter($this->default_tokenize($t, $prefix, 'token', $delim), static fn (array|string $value): bool => is_array($value));
    }

    /**
     * Default tokenization of a string, based on a fixed list of delimiters
     *
     * @param      array<int, array<string, mixed>>     $t        Array of tokens
     * @param      string                               $prefix   The prefix to add to the new tokens
     * @param      string                               $delim    List of delimiters to use for the tokenization
     *
     * @return     array<string>  Array of strings
     */
    public function default_tokenize_string(array $t, string $prefix = '', string $delim = ''): array
    {
        return array_filter($this->default_tokenize($t, $prefix, 'string', $delim), static fn (array|string $value): bool => is_string($value));
    }

    /**
     * Default tokenization of a string, based on a fixed list of delimiters
     *
     * @param      array<int, array<string, mixed>>     $t        Array of tokens
     * @param      string                               $prefix   The prefix to add to the new tokens
     * @param      string                               $type     The result type : 'token' or 'string', returns an array of tokens
     *                                                            or an array of string (like match_url)
     * @param      string                               $delim    List of delimiters to use for the tokenization
     *
     * @return     array<array<string, mixed>>|array<string>  Array of tokens or array of strings
     */
    public function default_tokenize(array $t, string $prefix = '', string $type = 'token', string $delim = ''): array
    {
        if ($delim === '') {
            $delim = '.,;:"?[]{}()+-*/=<>|&~`@_
';
        }

        $tab = [];

        foreach ($t as $e) {
            if (!$e['final']) {
                if (!empty($e['elem'])) {
                    $i   = 0; # start of mb_substring
                    $j   = 0; # end of mb_substring
                    $s   = $e['elem'];
                    $n   = mb_strlen((string) $s);
                    $pre = $e['prefix'];
                    while ($j !== $n) {
                        if ((mb_strpos($delim, mb_substr((string) $s, $j, 1)) !== false) || (mb_substr((string) $s, $j, 1) === ' ')) {
                            $sub = mb_substr((string) $s, $i, $j - $i);
                            if ($sub !== '') {
                                if ($type === 'token') {
                                    # new prefix
                                    $p     = !empty($pre) && $prefix !== '' ? $pre . '*' . $prefix : $pre . $prefix;
                                    $tab[] = $this->create_token($sub, $p, true);
                                } else {
                                    $tab[] = $sub;
                                }
                            }

                            $i = $j + 1;
                        }

                        ++$j;
                    }

                    --$j;
                    # handling of the last word
                    if (!((mb_strpos($delim, mb_substr((string) $s, $j, 1)) !== false) && (mb_substr((string) $s, $j, 1) === ' '))) {
                        $sub = mb_substr((string) $s, $i, $j - $i + 1);
                        if ($sub !== '') {
                            if ($type === 'token') {
                                # new prefix
                                $p = !empty($pre) && $prefix !== '' ? $pre . '*' . $prefix : $pre . $prefix;
                                if (!is_null($new_token = $this->create_token($sub, $p, true))) {
                                    $tab[] = $new_token;
                                }
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
