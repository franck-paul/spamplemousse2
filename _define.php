<?php
/**
 * @brief Spamplemousse2, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Alain Vagner
 * @author Franck Paul
 *
 * @copyright Olivier Meunier
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('DC_RC_PATH')) {
    return;
}

$this->registerModule(
    'Spamplemousse2',                       // Name
    'A bayesian spam filter for dotclear',  // Description
    'Alain Vagner and contributors',        // Author
    '2.1',
    [
        'requires'    => [['core', '2.23']],    // Dependencies
        'type'        => 'plugin',              // Type
        'priority'    => 100,                   // Priority
        'permissions' => 'usage,contentadmin',  // Permissions

        'details'    => 'https://open-time.net/?q=spamplemousse2',       // Details URL
        'support'    => 'https://github.com/franck-paul/spamplemousse2', // Support URL
        'repository' => 'https://raw.githubusercontent.com/franck-paul/spamplemousse2/main/dcstore.xml',
    ]
);
