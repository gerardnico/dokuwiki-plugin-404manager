<?php
/**
 * DokuWiki function tests for the 404manager plugin
 *
 * @group plugin_404manager
 * @group plugins
 */
class constant_parameters  {

    const MANAGER404_NAMESPACE = 'test';
    const PATH_SEPARATOR = ':';


    const PAGE_EXIST_ID = constant_parameters::MANAGER404_NAMESPACE . constant_parameters::PATH_SEPARATOR. 'page_exist';
    const PAGE_DOES_NOT_EXIST_ID = constant_parameters::MANAGER404_NAMESPACE . constant_parameters::PATH_SEPARATOR. 'page_does_not_exist';

    const PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID = constant_parameters::MANAGER404_NAMESPACE . constant_parameters::PATH_SEPARATOR. 'page_does_not_exist_no_redirection';

    const DIR_RESOURCES = __DIR__.'/../_testResources';

    static $INFO_PLUGIN ;
    static $PLUGIN_BASE ;

    static function init()
    {
        $info = __DIR__.'/../plugin.info.txt';
        if(file_exists($info)) {
            self::$INFO_PLUGIN = confToHash($info);
            self::$PLUGIN_BASE = self::$INFO_PLUGIN['base'];
        }
    }
}
constant_parameters::init();
