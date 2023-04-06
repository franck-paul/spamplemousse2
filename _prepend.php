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

use Dotclear\Helper\Clearbricks;

Clearbricks::lib()->autoload([
    'bayesian'               => __DIR__ . '/inc/class.bayesian.php',
    'tokenizer'              => __DIR__ . '/tokenizers/class.tokenizer.php',
    'url_tokenizer'          => __DIR__ . '/tokenizers/class.url_tokenizer.php',
    'email_tokenizer'        => __DIR__ . '/tokenizers/class.email_tokenizer.php',
    'ip_tokenizer'           => __DIR__ . '/tokenizers/class.ip_tokenizer.php',
    'redundancies_tokenizer' => __DIR__ . '/tokenizers/class.redundancies_tokenizer.php',
    'reassembly_tokenizer'   => __DIR__ . '/tokenizers/class.reassembly_tokenizer.php',
    'dcFilterSpample2'       => __DIR__ . '/inc/class.dc.filter.spample2.php',
    'progress'               => __DIR__ . '/inc/class.progress.php',
]);

dcCore::app()->spamfilters[] = 'dcFilterSpample2';
