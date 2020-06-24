<?php

/**
 * Class urlCanonical with all canonical methodology
 */
require_once(__DIR__ . '/UrlStatic.php');
class UrlCanonical
{
    /**
     * @var helper_plugin_sqlite $sqlite
     */
    private $sqlite;

    static $urlCanonical;


    /**
     * UrlCanonical constructor.
     */
    public function __construct()
    {
        $this->sqlite = UrlStatic::getSqlite();
    }

    public static function get()
    {
        if (self::$urlCanonical == null){
            self::$urlCanonical = new UrlCanonical();
        }
        return self::$urlCanonical;
    }

    /**
     * Does the page is known in the pages table
     * @param string $id
     * @return array
     */
    function getPage($id)
    {
        $id = strtolower($id);


        $res = $this->sqlite->query("SELECT * FROM pages where id = ?", $id);
        if (!$res) {
            throw new RuntimeException("An exception has occurred with the select pages query");
        }
        return $this->sqlite->res2arr($res);


    }

    /**
     * Delete Redirection
     * @param string $id
     */
    function deletePage($id)
    {

        $res = $this->sqlite->query('delete from pages where id = ?', $id);
        if (!$res) {
            UrlStatic::throwRuntimeException("Something went wrong when deleting a page");
        }

    }

    /**
     * Does the page is known in the pages table
     * @param string $id
     * @return int
     */
    function pageExist($id)
    {
        $id = strtolower($id);
        $res = $this->sqlite->query("SELECT * FROM pages where id = ?", $id);
        return $this->sqlite->res2count($res);

    }

    private function persistPageAlias(string $canonical, string $alias)
    {

        $row = array(
            "CANONICAL" => $canonical,
            "ALIAS" => $alias
        );

        // Page has change of location
        // Creation of an alias
        $res = $this->sqlite->query("select * from pages_alias where CANONICAL = ? and ALIAS = ?", $row);
        if (!$res) {
            throw new RuntimeException("An exception has occurred with the alia selection query");
        }
        $aliasInDb = $this->sqlite->res2count($res);
        if ($aliasInDb == 0) {

            $res = $this->sqlite->storeEntry('pages_alias', $row);
            if (!$res) {
                $this->throwRuntimeException("There was a problem during pages_alias insertion");
            }
        }

    }

    /**
     * @param $canonical
     * @return string|bool - an id of an existent page
     */
    function getPageIdFromCanonical($canonical)
    {

        // Canonical
        $res = $this->sqlite->query("select * from pages where CANONICAL = ? ", $canonical);
        if (!$res) {
            throw new RuntimeException("An exception has occurred with the pages selection query");
        }
        if ($this->sqlite->res2count($res) > 0) {
            foreach ($this->sqlite->res2arr($res) as $row) {
                $id = $row['ID'];
                if (page_exists($id)) {
                    return $id;
                }
            }
        }

        // If the function comes here, it means that the page id was not found in the pages table
        // Alias ?
        // Canonical
        $res = $this->sqlite->query("select p.ID from pages p, PAGES_ALIAS pa where p.CANONICAL = pa.CANONICAL and pa.ALIAS = ? ", $canonical);
        if (!$res) {
            throw new RuntimeException("An exception has occurred with the alias selection query");
        }
        if ($this->sqlite->res2count($res) > 0) {
            foreach ($this->sqlite->res2arr($res) as $row) {
                $id = $row['ID'];
                if (page_exists($id)) {
                    return $id;
                }
            }
        }

        return false;

    }

    /**
     * Process metadata
     */
    function processCanonicalMeta()
    {


        global $ID;
        $canonical = p_get_metadata($ID, "canonical");
        if ($canonical != "") {

            // Do we have a page attached to this canonical
            $res = $this->sqlite->query("select ID from pages where CANONICAL = ?", $canonical);
            if (!$res) {
                throw new RuntimeException("An exception has occurred with the search id from canonical");
            }
            $idInDb = $this->sqlite->res2single($res);
            if ($idInDb && $idInDb != $ID) {
                // If the page does not exist anymore we delete it
                if (!page_exists($idInDb)) {
                    $res = $this->sqlite->query("delete from pages where ID = ?", $idInDb);
                    if (!$res) {
                        throw new RuntimeException("An exception has occurred during the deletion of the page");
                    }

                } else {
                    msg("The page (" . $ID . ") and the page (" . $idInDb . ") have the same canonical.", MANAGER404_MSG_ERROR, $allow = MSG_MANAGERS_ONLY);
                }
                $this->persistPageAlias($canonical, $idInDb);
            }

            // Do we have a canonical on this page
            $res = $this->sqlite->query("select canonical from pages where ID = ?", $ID);
            if (!$res) {
                throw new RuntimeException("An exception has occurred with the query");
            }
            $canonicalInDb = $this->sqlite->res2single($res);

            $row = array(
                "CANONICAL" => $canonical,
                "ID" => $ID
            );
            if ($canonicalInDb && $canonicalInDb != $canonical) {

                // Persist alias
                $this->persistPageAlias($canonical, $ID);

                // Update
                $statement = 'update pages set canonical = ? where id = ?';
                $res = $this->sqlite->query($statement, $row);
                if (!$res) {
                    UrlStatic::throwRuntimeException("There was a problem during page update");
                }

            } else {

                if ($canonicalInDb == false) {
                    $res = $this->sqlite->storeEntry('pages', $row);
                    if (!$res) {
                        UrlStatic::throwRuntimeException("There was a problem during pages insertion");
                    }
                }

            }


        }

    }


}
