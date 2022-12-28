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
 * This class calls the toXml method of the progress class with the GET parameters
 */
class progressRest
{
    /**
     * Posts a progress.
     *
     * @param      dcCore  $core   The core
     * @param      array   $get    The get parameters
     * @param      array   $post   The post parameters
     *
     * @return     xmlTag  The xml message.
     */
    public static function postProgress(dcCore $core, array $get, array $post): xmlTag
    {
        $title      = '';
        $urlprefix  = '';
        $urlreturn  = '1';
        $funcClass  = !empty($post['funcClass']) ? $post['funcClass'] : null;
        $funcMethod = !empty($post['funcMethod']) ? $post['funcMethod'] : null;
        $func       = [$funcClass, $funcMethod];
        $start      = !empty($post['start']) ? $post['start'] : 0;
        $stop       = !empty($post['stop']) ? $post['stop'] : 0;
        $baseInc    = !empty($post['baseInc']) ? $post['baseInc'] : 0;

        $progress = new progress($title, $urlprefix, $urlreturn, $func, $start, $stop, $baseInc, dcCore::app()->getNonce());

        return $progress->toXml();
    }
}

dcCore::app()->rest->addFunction('postProgress', [progressRest::class, 'postProgress']);
