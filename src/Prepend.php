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
use dcNsProcess;

class Prepend extends dcNsProcess
{
    protected static $init = false; /** @deprecated since 2.27 */
    public static function init(): bool
    {
        static::$init = My::checkContext(My::PREPEND);

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->spamfilters[] = AntispamFilterSpamplemousse2::class;

        if (dcCore::app()->plugins->moduleExists('Uninstaller')) {
            // Add cleaners to Uninstaller
            dcCore::app()->addBehavior('UninstallerCleanersConstruct', function (\Dotclear\Plugin\Uninstaller\CleanersStack $cleaners): void {
                $cleaners
                    ->set(new Cleaner\Fields())
                ;
            });
        }

        return true;
    }
}
