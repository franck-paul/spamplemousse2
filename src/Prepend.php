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

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Process;

class Prepend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::PREPEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehaviors([
            'AntispamInitFilters' => static function (ArrayObject $spamfilters) : void {
                $spamfilters->append(AntispamFilterSpamplemousse2::class);
            },
        ]);

        // Add cleaners to Uninstaller
        App::behavior()->addBehavior('UninstallerCleanersConstruct', static function (\Dotclear\Plugin\Uninstaller\CleanersStack $cleaners) : void {
            $cleaners
                ->set(new Cleaner\Fields())
            ;
        });

        return true;
    }
}
