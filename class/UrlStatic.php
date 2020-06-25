<?php

// Surprisingly there is no constant for the info level
if (!defined('MANAGER404_MSG_ERROR')) define('MANAGER404_MSG_ERROR', -1);
if (!defined('MANAGER404_MSG_INFO')) define('MANAGER404_MSG_INFO', 0);
if (!defined('MANAGER404_MSG_SUCCESS')) define('MANAGER404_MSG_SUCCESS', 1);
if (!defined('MANAGER404_MSG_NOTIFY')) define('MANAGER404_MSG_NOTIFY', 2);


/**
 * Class url static
 * List of static utilities
 */
class UrlStatic
{
    /**
     * @var helper_plugin_sqlite $sqlite
     */
    static $sqlite;

    /**
     * Init via the {@link init}
     * @var
     */
    static $PLUGIN_BASE_NAME;

    /**
     * @var array
     */
    static $INFO_PLUGIN;

    static $lang;

    /**
     * Validate URL
     * Allows for port, path and query string validations
     * @param string $url string containing url user input
     * @return   boolean     Returns TRUE/FALSE
     */
    static function isValidURL($url)
    {
        // of preg_match('/^https?:\/\//',$url) ? from redirect plugin
        return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
    }

    /**
     * Init the data store
     */
    static function getSqlite()
    {

        if (self::$sqlite == null) {

            global $lang;

            self::$sqlite = plugin_load('helper', 'sqlite');
            if (self::$sqlite == null) {
                msg($lang[self::$PLUGIN_BASE_NAME]['SqliteMandatory'], MANAGER404_MSG_INFO, $allow = MSG_MANAGERS_ONLY);
            }

            $init = self::$sqlite->init(self::$PLUGIN_BASE_NAME, DOKU_PLUGIN . self::$PLUGIN_BASE_NAME . '/db/');
            if (!$init) {
                msg($lang[self::$PLUGIN_BASE_NAME]['SqliteUnableToInitialize'], MSG_MANAGERS_ONLY);
            }
        }

        return self::$sqlite;

    }

    /**
     * Dokuwiki will show a pink message when throwing an exception
     * and it's difficult to see from where it comes
     *
     * This utility function will add the plugin name to it
     *
     * @param $message
     * @throws RuntimeException
     */
    static function throwRuntimeException($message): void
    {
        throw new RuntimeException(self::$PLUGIN_BASE_NAME . ' - ' . $message);
    }

    /**
     * Initiate the static variable
     * See the call after this class
     */
    static function init()
    {
        $pluginInfoFile = __DIR__ . '/../plugin.info.txt';

        self::$INFO_PLUGIN = confToHash($pluginInfoFile);
        self::$PLUGIN_BASE_NAME = self::$INFO_PLUGIN['base'];
        global $lang;
        self::$lang = $lang[self::$PLUGIN_BASE_NAME];

    }



}

UrlStatic::init();