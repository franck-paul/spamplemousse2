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

dcCore::app()->rest->addFunction('postProgress', ['progressRest','postProgress']);

/**
@ingroup PROGRESS
@brief Progress rest interface

This class calls the toXml method of the progress class with the GET parameters
 */
class progressRest
{
    /**
    Call the toXml method

    @param	core		<b>DcCore</b>		Dotclear core object
    @param	get			<b>array</b>		Get	parameters
    @param	post		<b>array</b>		Post parameters
    @return				<b>XmlTag</b>		XML message
     */
    public static function postProgress($core, $get, $post)
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
        $content  = $progress->toXml();

        return $content;
    }
}
