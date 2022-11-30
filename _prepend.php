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
