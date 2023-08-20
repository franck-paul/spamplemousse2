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
use Dotclear\Core\Process;
use Dotclear\Database\Structure;
use Exception;

class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Init
            $s = new Structure(dcCore::app()->con, dcCore::app()->prefix);

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
            $si = new Structure(dcCore::app()->con, dcCore::app()->prefix);
            $si->synchronize($s);
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }
}
