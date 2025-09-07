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

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

class Install
{
    use TraitProcess;

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
            $s = App::db()->structure();

            // spam_token table creation
            $s->spam_token
                ->field('token_id', 'varchar', 255, false, 0)
                ->field('token_nham', 'integer', 0, false, 0)
                ->field('token_nspam', 'integer', 0, false, 0)
                ->field('token_mdate', 'timestamp', 0, false, 'now()')
                ->field('token_p', 'float', 0, false, 0)
                ->field('token_mature', 'smallint', 0, false, 0)
                ->primary('pk_spam_token', 'token_id')
            ;

            // we add two columns on the comment table
            $s->comment
                ->field('comment_bayes', 'smallint', 0, false, 0)
                ->field('comment_bayes_err', 'smallint', 0, false, 0)
            ;

            // schema sync
            $si = App::db()->structure();
            $si->synchronize($s);
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }
}
