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
     * @var string
     */
    static $DIR_RESOURCES;

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
     * Sqlite cannot be static because
     * between two test classes
     * the data dir where the database is saved is deleted.
     *
     * You need to store the variable in your plugin
     *
     * @return helper_plugin_sqlite $sqlite
     */
    static function getSqlite()
    {

        $sqlite = plugin_load('helper', 'sqlite');
        if ($sqlite == null) {
            # TODO: Man we cannot get the message anymore ['SqliteMandatory'];
            $sqliteMandatoryMessage = "The Sqlite Plugin is mandatory";
            msg($sqliteMandatoryMessage, MANAGER404_MSG_INFO, $allow = MSG_MANAGERS_ONLY);
            self::throwRuntimeException($sqliteMandatoryMessage);
        }

        $init = $sqlite->init(self::$PLUGIN_BASE_NAME, DOKU_PLUGIN . self::$PLUGIN_BASE_NAME . '/db/');
        if (!$init) {
            # TODO: Message 'SqliteUnableToInitialize'
            $message = "Unable to initialize Sqlite";
            msg($message, MSG_MANAGERS_ONLY);
            self::throwRuntimeException($message);
        }
        return $sqlite;

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
        if (defined('DOKU_UNITTEST')) {
            throw new RuntimeException(self::$PLUGIN_BASE_NAME . ' - ' . $message);
        }
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
        self::$DIR_RESOURCES = __DIR__ . '/../_testResources';

    }


}

UrlStatic::init();
