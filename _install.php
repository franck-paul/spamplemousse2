<?php

# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of Spamplemousse2, a plugin for Dotclear 2.
#
# Copyright (c) 2003-2008 Olivier Meunier and contributors
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

#plugin label
$label = 'spamplemousse2';

# We read the plugin version
$m_version = dcCore::app()->plugins->moduleInfo($label, 'version');

# We read the plugin version in the version table
$i_version = dcCore::app()->getVersion($label);

# If the version in the version table is greater than
# the one of this module, -> do nothing
if (version_compare((string) $i_version, $m_version, '>=')) {
    return;
}

$s = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);

# spam_token table creation
$s->spam_token
    ->token_id('varchar', 255, false, 0)
    ->token_nham('integer', 0, false, 0)
    ->token_nspam('integer', 0, false, 0)
    ->token_mdate('timestamp', 0, false, 'now()')
    ->token_p('float', 0, false, 0)
    ->token_mature('smallint', 0, false, 0)
    ->primary('pk_spam_token', 'token_id')
;

# we add two columns on the comment table
$s->comment
    ->comment_bayes('smallint', 0, false, 0)
    ->comment_bayes_err('smallint', 0, false, 0)
;

# schema sync
$si = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);
$si->synchronize($s);

dcCore::app()->setVersion($label, $m_version);

return true;
