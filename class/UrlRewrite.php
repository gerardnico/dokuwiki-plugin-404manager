<?php


/**
 * The manager that handles the redirection metadata
 * @deprecated
 */
require_once(__DIR__ . '/UrlStatic.php');

class UrlRewrite
{

    // A static function to hold the 404 manager
    private static $urlRedirection = null;

    // Data Store Type
    // The Data Store Type variable
    private $dataStoreType;

    // The Data Store Type possible value
    const DATA_STORE_TYPE_CONF_FILE = 'confFile';
    const DATA_STORE_TYPE_SQLITE = 'sqlite';


    // Variable var and not public/private because php4 can't handle this kind of variable

    // ###################################
    // Data Stored in a conf file
    // Deprecated
    // ###################################
    // The file path of the direct redirection (from an Page to a Page or URL)
    // No more used, replaced by a sqlite database
    const DATA_STORE_CONF_FILE_PATH = __DIR__ . "/404managerRedirect.conf";
    // The content of the conf file in memory
    var $pageRedirections = array();


    // Use to pass parameter between the handle and the html function to keep the form data
    var $redirectionSource = '';
    var $redirectionTarget = '';
    var $currentDate = '';
    // Deprecated
    private $redirectionType;
    // Deprecated
    var $isValidate = '';
    // Deprecated
    var $targetResourceType = 'Default';


    /** @var helper_plugin_sqlite $sqlite */
    private $sqlite;



    /**
     * @return UrlRewrite
     */
    public static function get()
    {
        if (self::$urlRedirection == null) {
            self::$urlRedirection = new UrlRewrite();
        }
        return self::$urlRedirection;
    }


    /**
     * Delete Redirection
     * @param string $sourcePageId
     */
    function deleteRedirection($sourcePageId)
    {

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {
            unset($this->pageRedirections[strtolower($sourcePageId)]);
            $this->savePageRedirections();
        } else {

            $res = $this->sqlite->query('delete from redirections where source = ?', $sourcePageId);
            if (!$res) {
                $this->throwRuntimeException("Something went wrong when deleting the redirections");
            }

        }

    }


    /**
     * Is Redirection of a page Id Present
     * @param string $sourcePageId
     * @return boolean
     */
    function isRedirectionPresent($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            if (isset($this->pageRedirections[$sourcePageId])) {
                return true;
            } else {
                return false;
            }

        } else {

            $res = $this->sqlite->query("SELECT * FROM redirections where SOURCE = ?", $sourcePageId);
            if ($this->sqlite->res2count($res) == 1){
                return true;
            } else {
                return false;
            }

        }

    }


    /**
     * @param $sourcePageId
     * @param $targetPageId
     */
    function addRedirection($sourcePageId, $targetPageId)
    {
        $this->addRedirectionWithDate($sourcePageId, $targetPageId, $this->currentDate);
    }

    /**
     * Add Redirection
     * This function was needed to migrate the date of the file conf store
     * You would use normally the function addRedirection
     * @param string $sourcePageId
     * @param string $targetPageId
     * @param $creationDate
     */
    function addRedirectionWithDate($sourcePageId, $targetPageId, $creationDate)
    {

        // Lower page name is the dokuwiki Id
        $sourcePageId = strtolower($sourcePageId);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            if (isset($this->pageRedirections[$sourcePageId])) {
                $this->throwRuntimeException('Redirection for page (' . $sourcePageId . 'already exist');
            }

            $this->pageRedirections[$sourcePageId]['TargetPage'] = $targetPageId;
            $this->pageRedirections[$sourcePageId]['CreationDate'] = $creationDate;
            // If the call come from the admin page and not from the process function
            if (substr_count($_SERVER['HTTP_REFERER'], 'admin.php')) {

                $this->pageRedirections[$sourcePageId]['IsValidate'] = 'Y';
                $this->pageRedirections[$sourcePageId]['CountOfRedirection'] = 0;
                $this->pageRedirections[$sourcePageId]['LastRedirectionDate'] = $this->lang['Never'];
                $this->pageRedirections[$sourcePageId]['LastReferrer'] = 'Never';

            } else {

                $this->pageRedirections[$sourcePageId]['IsValidate'] = 'N';
                $this->pageRedirections[$sourcePageId]['CountOfRedirection'] = 1;
                $this->pageRedirections[$sourcePageId]['LastRedirectionDate'] = $creationDate;
                if ($_SERVER['HTTP_REFERER'] <> '') {
                    $this->pageRedirections[$sourcePageId]['LastReferrer'] = $_SERVER['HTTP_REFERER'];
                } else {
                    $this->pageRedirections[$sourcePageId]['LastReferrer'] = $this->lang['Direct Access'];
                }

            }

            if (!UrlStatic::isValidURL($targetPageId)) {
                $this->pageRedirections[$sourcePageId]['TargetPageType'] = 'Internal Page';
            } else {
                $this->pageRedirections[$sourcePageId]['TargetPageType'] = 'Url';
            }

            $this->savePageRedirections();

        } else {

            // Note the order is important
            // because it's used in the bin of the update statement
            $entry = array(
                'target' => $targetPageId,
                'creation_timestamp' => $creationDate,
                'source' => $sourcePageId
            );

            $statement = 'select * from redirections where source = ?';
            $res = $this->sqlite->query($statement, $sourcePageId);
            $count = $this->sqlite->res2count($res);
            if ($count <> 1) {
                $res = $this->sqlite->storeEntry('redirections', $entry);
                if (!$res) {
                    UrlStatic::throwRuntimeException("There was a problem during insertion");
                }
            } else {
                // Primary key constraint, the storeEntry function does not use an UPSERT
                $statement = 'update redirections set target = ?, creation_timestamp = ? where source = ?';
                $res = $this->sqlite->query($statement, $entry);
                if (!$res) {
                    UrlStatic::throwRuntimeException("There was a problem during the update");
                }
            }

        }
    }

    /**
     * Validate a Redirection
     * @param string $sourcePageId
     */
    function validateRedirection($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            $this->pageRedirections[$sourcePageId]['IsValidate'] = 'Y';
            $this->savePageRedirections();
        } else {

            UrlStatic::throwRuntimeException('Not implemented for a SQLite data store');

        }
    }

    /**
     * Get IsValidate Redirection
     * @param string $sourcePageId
     * @return string
     */
    function getIsValidate($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            if ($this->pageRedirections[$sourcePageId]['IsValidate'] == null) {
                return 'N';
            } else {
                return $this->pageRedirections[$sourcePageId]['IsValidate'];
            }
        }

        UrlStatic::throwRuntimeException("Not Yet implemented");

    }

    /**
     * Get TargetPageType
     * @param string $sourcePageId
     * @return
     * @throws Exception
     */
    function getTargetPageType($sourcePageId)
    {
        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            $sourcePageId = strtolower($sourcePageId);
            return $this->pageRedirections[$sourcePageId]['TargetPageType'];

        } else {

            throw new Exception('Not Yet implemented');

        }

    }


    /**
     * Get TargetResource (It can be an external URL as an intern page id
     * @param string $sourcePageId
     * @return string|boolean - can be false if no data
     * @throws Exception
     */
    function getRedirectionTarget($sourcePageId)
    {

        $sourcePageId = strtolower($sourcePageId);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            return $this->pageRedirections[strtolower($sourcePageId)]['TargetPage'];

        } else {

            $res = $this->sqlite->query("select target from redirections where source = ?", $sourcePageId);
            if (!$res) {
                throw new RuntimeException("An exception has occurred with the query");
            }
            return $this->sqlite->res2single($res);

        }
    }



    /**
     * Serialize and save the redirection data file
     *
     * ie Flush
     *
     */
    function savePageRedirections()
    {

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            io_saveFile(self::DATA_STORE_CONF_FILE_PATH, serialize($this->pageRedirections));

        } else {

            $this->throwRuntimeException('SavePageRedirections must no be called for a SQLite data store');

        }
    }


    /**
     * @param $inputExpression
     * @return false|int 1|0
     * returns:
     *    - 1 if the input expression is a pattern,
     *    - 0 if not,
     *    - FALSE if an error occurred.
     */
    static function isRegularExpression($inputExpression)
    {

        $regularExpressionPattern = "/(\\/.*\\/[gmixXsuUAJ]?)/";
        return preg_match($regularExpressionPattern, $inputExpression);

    }

    /**
     *
     * Set the data store type. The value must be one of the constants
     *   * DATA_STORE_TYPE_CONF_FILE
     *   * DATA_STORE_TYPE_SQLITE
     *
     * @param $dataStoreType
     * @return $this
     *
     */
    public function setDataStoreType($dataStoreType)
    {
        $this->dataStoreType = $dataStoreType;
        $this->initDataStore();
        return $this;
    }

    /**
     * Init the data store
     */
    private function initDataStore()
    {

        if ($this->dataStoreType == null) {
            $this->sqlite = plugin_load('helper', 'sqlite');
            if (!$this->sqlite) {
                $this->dataStoreType = self::DATA_STORE_TYPE_CONF_FILE;
            } else {
                $this->dataStoreType = self::DATA_STORE_TYPE_SQLITE;
            }
        }

        if ($this->getDataStoreType() == self::DATA_STORE_TYPE_CONF_FILE) {

            msg(UrlStatic::$lang['SqliteMandatory'], MANAGER404_MSG_INFO, $allow = MSG_MANAGERS_ONLY);

            //Set the redirection data
            if (@file_exists(self::DATA_STORE_CONF_FILE_PATH)) {
                $this->pageRedirections = unserialize(io_readFile(self::DATA_STORE_CONF_FILE_PATH, false));
            }

        } else {


            $this->sqlite = UrlStatic::getSqlite();


            // Migration of the old store
            if (@file_exists(self::DATA_STORE_CONF_FILE_PATH)) {
                $this->dataStoreMigration();
            }


        }

    }

    /**
     * Delete all redirections
     * Use with caution
     */
    function deleteAllRedirections()
    {
        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {

            $res = $this->sqlite->query("delete from redirections");
            if (!$res) {
                $this->throwRuntimeException('Errors during delete of all redirections');
            }

        } else {

            if (file_exists(self::DATA_STORE_CONF_FILE_PATH)) {
                $res = unlink(self::DATA_STORE_CONF_FILE_PATH);
                if (!$res) {
                    $this->throwRuntimeException('Unable to delete the file ' . self::DATA_STORE_TYPE_CONF_FILE);
                }
            }
            $this->pageRedirections = array();

        }
    }

    /**
     * Return the number of redirections
     * @return integer
     */
    function countRedirections()
    {
        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {

            $res = $this->sqlite->query("select count(1) from redirections");
            if (!$res) {
                throw new RuntimeException('Errors during delete of all redirections');
            }
            $value = $this->sqlite->res2single($res);
            return $value;

        } else {

            return count($this->pageRedirections);

        }
    }

    public function getDataStoreType()
    {
        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }
        return $this->dataStoreType;
    }

    /**
     * @return array
     */
    private function getRedirections()
    {
        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {

            $res = $this->sqlite->query("select * from redirections");
            if (!$res) {
                throw new RuntimeException('Errors during select of all redirections');
            }
            return $this->sqlite->res2arr($res);

        } else {

            return $this->pageRedirections;

        }
    }


    /**
     * Migrate from a conf file to sqlite
     */
    function dataStoreMigration()
    {
        if (!file_exists(self::DATA_STORE_CONF_FILE_PATH)) {
            $this->throwRuntimeException("The file to migrate does not exist (" . self::DATA_STORE_CONF_FILE_PATH . ")");
        }
        // We cannot use the getRedirections method because this is a sqlite data store
        // it will return nothing
        $pageRedirections = unserialize(io_readFile(self::DATA_STORE_CONF_FILE_PATH, false));
        foreach ($pageRedirections as $key => $row) {


            $sourcePageId = $key;
            $targetPageId = $row['TargetPage'];
            $creationDate = $row['CreationDate'];
            $isValidate = $row['IsValidate'];

            if ($isValidate == 'Y') {
                $this->addRedirectionWithDate($sourcePageId, $targetPageId, $creationDate);
            }
        }

        rename(self::DATA_STORE_CONF_FILE_PATH, self::DATA_STORE_CONF_FILE_PATH . '.migrated');

    }


}
