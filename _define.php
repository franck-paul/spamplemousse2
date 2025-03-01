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
$this->registerModule(
    'Spamplemousse2',
    'A bayesian spam filter for dotclear',
    'Alain Vagner and contributors',
    '8.1',
    [
        'date'        => '2025-02-19T22:00:44+0100',
        'requires'    => [['core', '2.33']],
        'type'        => 'plugin',
        'priority'    => 100,
        'permissions' => 'My',

        'details'    => 'https://open-time.net/?q=spamplemousse2',
        'support'    => 'https://github.com/franck-paul/spamplemousse2',
        'repository' => 'https://raw.githubusercontent.com/franck-paul/spamplemousse2/main/dcstore.xml',
    ]
);
