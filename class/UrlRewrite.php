<?php


/**
 * The manager that handles the redirection metadata
 * @deprecated
 */
require_once(__DIR__ . '/UrlStatic.php');

class UrlRewrite
{

    // Use to pass parameter between the handle and the html function to keep the form data
    var $currentDate = '';


    /** @var helper_plugin_sqlite $sqlite */
    private $sqlite;

    /**
     * UrlRewrite constructor.
     * The sqlite path is dependent of the dokuwiki data
     * and for each new class, the dokuwiki helper just delete it
     * We need to pass it then
     * @param helper_plugin_sqlite $sqlite
     */
    public function __construct(helper_plugin_sqlite $sqlite)
    {
        $this->sqlite = $sqlite;
    }


    /**
     * Delete Redirection
     * @param string $sourcePageId
     */
    function deleteRedirection($sourcePageId)
    {

        $res = $this->sqlite->query('delete from redirections where source = ?', $sourcePageId);
        if (!$res) {
            UrlStatic::throwRuntimeException("Something went wrong when deleting the redirections");
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


        $res = $this->sqlite->query("SELECT count(*) FROM redirections where SOURCE = ?", $sourcePageId);
        $exists = null;
        if ($this->sqlite->res2single($res) == 1) {
            $exists = true;
        } else {
            $exists = false;
        }
        $this->sqlite->res_close($res);
        return $exists;


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

        // Note the order is important
        // because it's used in the bin of the update statement
        $entry = array(
            'target' => $targetPageId,
            'creation_timestamp' => $creationDate,
            'source' => $sourcePageId
        );

        $res = $this->sqlite->query('select count(*) from redirections where source = ?', $sourcePageId);
        $count = $this->sqlite->res2single($res);
        $this->sqlite->res_close($res);
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

        $res = $this->sqlite->query("select target from redirections where source = ?", $sourcePageId);
        if (!$res) {
            throw new RuntimeException("An exception has occurred with the query");
        }
        $target = $this->sqlite->res2single($res);
        $this->sqlite->res_close($res);
        return $target;


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
     * Delete all redirections
     * Use with caution
     */
    function deleteAllRedirections()
    {


        $res = $this->sqlite->query("delete from redirections");
        if (!$res) {
            UrlStatic::throwRuntimeException('Errors during delete of all redirections');
        }

    }

    /**
     * Return the number of redirections
     * @return integer
     */
    function countRedirections()
    {

        $res = $this->sqlite->query("select count(1) from redirections");
        if (!$res) {
            UrlStatic::throwRuntimeException('Errors during delete of all redirections');
        }
        $value = $this->sqlite->res2single($res);
        $this->sqlite->res_close($res);
        return $value;

    }


    /**
     * @return array
     */
    function getRedirections()
    {

        $res = $this->sqlite->query("select * from redirections");
        if (!$res) {
            throw new RuntimeException('Errors during select of all redirections');
        }
        return $this->sqlite->res2arr($res);


    }


}
