<?php

/**
 * DokuWiki function tests for the 404manager plugin
 *
 * @group plugin_404manager
 * @group plugins
 */
class constant_parameters
{

    static $MANAGER404_NAMESPACE;
    const PATH_SEPARATOR = ':';


    static $PAGE_EXIST_ID;
    static $PAGE_DOES_NOT_EXIST_ID;

    static $PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID;

    static $DIR_RESOURCES;

    static $INFO_PLUGIN;
    static $PLUGIN_BASE;
    static $PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE;

    static $EXPLICIT_REDIRECT_PAGE_SOURCE;
    static $EXPLICIT_REDIRECT_PAGE_TARGET;

    static function init()
    {
        $pluginInfoFile = __DIR__ . '/../plugin.info.txt';
        self::$DIR_RESOURCES = __DIR__ . '/../_testResources';

        self::$INFO_PLUGIN = confToHash($pluginInfoFile);
        self::$PLUGIN_BASE = self::$INFO_PLUGIN['base'];

        self::$MANAGER404_NAMESPACE = self::$INFO_PLUGIN['base'];

        self::$PAGE_EXIST_ID = self::$MANAGER404_NAMESPACE . self::PATH_SEPARATOR . 'page_exist';
        self::$PAGE_DOES_NOT_EXIST_ID = self::$MANAGER404_NAMESPACE . self::PATH_SEPARATOR . 'page_does_not_exist';
        self::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID = self::$MANAGER404_NAMESPACE . self::PATH_SEPARATOR . 'page_does_not_exist_no_redirection';

        self::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE = self::$MANAGER404_NAMESPACE . self::PATH_SEPARATOR . 'redirect_to_external_website';

        self::$EXPLICIT_REDIRECT_PAGE_SOURCE = self::$MANAGER404_NAMESPACE . self::PATH_SEPARATOR . 'explicit_redirect_to_internal_page_source';
        self::$EXPLICIT_REDIRECT_PAGE_TARGET = self::$MANAGER404_NAMESPACE . self::PATH_SEPARATOR . 'explicit_redirect_to_internal_page_target';


    }
}

constant_parameters::init();
