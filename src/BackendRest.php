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

namespace Dotclear\Plugin\spamplemousse2;

use dcCore;
use Dotclear\Helper\Html\XmlTag;

/**
 * This class calls the toXml method of the progress class with the GET parameters
 */
class BackendRest
{
    /**
     * Posts a progress.
     *
     * @param      dcCore                   $core   The core
     * @param      array<string, string>    $get    The get parameters
     * @param      array<string, string>    $post   The post parameters
     *
     * @return     XmlTag  The xml message.
     */
    public static function postProgress(dcCore $core, array $get, array $post): XmlTag
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

        $progress = new Progress($title, $urlprefix, $urlreturn, $func, $start, (int) $stop, (int) $baseInc);

        return $progress->toXml();
    }
}
