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
dcCore::app()->addBehaviors([
    'publicAfterCommentCreate'   => [dcFilterSpample2::class,'toggleLearnedFlag'],
    'publicAfterTrackbackCreate' => [dcFilterSpample2::class,'toggleLearnedFlag'],
]);
