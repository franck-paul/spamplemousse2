<?php

/**
 * @brief spamplemousse2, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul contact@open-time.net
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\spamplemousse2\Cleaner;

use Dotclear\App;
use Dotclear\Plugin\Uninstaller\{
    ActionDescriptor,
    CleanerDescriptor,
    CleanerParent,
};

/**
 * Cleaner for Adaptive images cache directory.
 *
 * @todo switch to SqlStatement
 */
class Fields extends CleanerParent
{
    public function __construct()
    {
        parent::__construct(new CleanerDescriptor(
            id:   'spamplemousse2_fields',
            name: __('Comments fields'),
            desc: __('Spamplemousse2 fields in comments table'),
            actions: [
                // delete a $ns table:field.
                new ActionDescriptor(
                    id:      'delete_bayes',
                    select:  __('delete selected field'),
                    query:   __('delete "%s" field'),
                    success: __('"%s" field has been succesfully been deleted'),
                    error:   __('Failed to delete "%s" field'),
                    default: false
                ),
                // delete a $ns table:field.
                new ActionDescriptor(
                    id:      'delete_bayes_err',
                    select:  __('delete selected field'),
                    query:   __('delete "%s" field'),
                    success: __('"%s" field has been succesfully been deleted'),
                    error:   __('Failed to delete "%s" field'),
                    default: false
                ),
            ]
        ));
    }

    /**
     * @return array{}
     */
    public function distributed(): array
    {
        return [
        ];
    }

    /**
     * @return array{}
     */
    public function values(): array
    {
        return [];
    }

    public function execute(string $action, string $ns): bool
    {
        if (in_array($action, ['delete_bayes', 'delete_bayes_err'], true)) {
            [$table, $field] = explode(PATH_SEPARATOR, $ns);

            if ($table && $field) {
                $sql = 'ALTER TABLE ' . App::db()->con()->prefix() . $table . ' DROP COLUMN ' . $field;
                App::db()->con()->execute($sql);
            }

            return true;
        }

        return false;
    }
}
