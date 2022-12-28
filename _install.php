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
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

if (!dcCore::app()->newVersion(basename(__DIR__), dcCore::app()->plugins->moduleInfo(basename(__DIR__), 'version'))) {
    return;
}

try {
    $s = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);

    // spam_token table creation
    $s->spam_token
        ->token_id('varchar', 255, false, 0)
        ->token_nham('integer', 0, false, 0)
        ->token_nspam('integer', 0, false, 0)
        ->token_mdate('timestamp', 0, false, 'now()')
        ->token_p('float', 0, false, 0)
        ->token_mature('smallint', 0, false, 0)
        ->primary('pk_spam_token', 'token_id')
    ;

    // we add two columns on the comment table
    $s->comment
        ->comment_bayes('smallint', 0, false, 0)
        ->comment_bayes_err('smallint', 0, false, 0)
    ;

    // schema sync
    $si = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);
    $si->synchronize($s);

    return true;
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

return false;
