<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

// Surprisingly there is no constant for the info level
if (!defined('MANAGER404_MSG_ERROR')) define('MANAGER404_MSG_ERROR', -1);
if (!defined('MANAGER404_MSG_INFO')) define('MANAGER404_MSG_INFO', 0);
if (!defined('MANAGER404_MSG_SUCCESS')) define('MANAGER404_MSG_SUCCESS', 1);
if (!defined('MANAGER404_MSG_NOTIFY')) define('MANAGER404_MSG_NOTIFY', 2);

require_once(DOKU_PLUGIN . 'admin.php');
require_once(DOKU_INC . 'inc/parser/xhtml.php');

/**
 * The admin pages
 * need to inherit from this class
 *
 */
class admin_plugin_404manager extends DokuWiki_Admin_Plugin
{




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


    // Name of the variable in the HTML form
    const FORM_NAME_SOURCE_PAGE = 'SourcePage';
    const FORM_NAME_TARGET_PAGE = 'TargetPage';

    /**
     * @var array|string[]
     */
    private $infoPlugin;

    /**
     * @var UrlRedirection|null
     */
    private $urlManager;


    /**
     * admin_plugin_404manager constructor.
     *
     * Use the get function instead
     */
    public function __construct()
    {

        // enable direct access to language strings
        // of use of $this->getLang
        $this->setupLocale();
        $this->currentDate = date("c");
        $this->infoPlugin = $this->getInfo();
        $this->urlManager = UrlRedirection::get();


    }


    /**
     * Access for managers allowed
     */
    function forAdminOnly()
    {
        return false;
    }

    /**
     * return sort order for position in admin menu
     */
    function getMenuSort()
    {
        return 140;
    }

    /**
     * return prompt for admin menu
     * @param string $language
     * @return string
     */
    function getMenuText($language)
    {
        $menuText = $this->lang['AdminPageName'];
        if ($menuText == '') {
            $menuText = $this->infoPlugin['name'];
        }
        return $menuText;
    }

    /**
     * handle user request
     */
    function handle()
    {

        if ($_POST['Add']) {

            $this->redirectionSource = $_POST[self::FORM_NAME_SOURCE_PAGE];
            $this->redirectionTarget = $_POST[self::FORM_NAME_TARGET_PAGE];

            if ($this->redirectionSource == $this->redirectionTarget) {
                msg($this->lang['SameSourceAndTargetAndPage'] . ': ' . $this->redirectionSource . '', -1);
                return;
            }


            // This a direct redirection
            // If the source page exist, do nothing
            if (page_exists($this->redirectionSource)) {

                $title = false;
                global $conf;
                if ($conf['useheading']) {
                    $title = p_get_first_heading($this->redirectionSource);
                }
                if (!$title) $title = $this->redirectionSource;
                msg($this->lang['SourcePageExist'] . ' : <a href="' . wl($this->redirectionSource) . '">' . hsc($title) . '</a>', -1);
                return;

            } else {

                // Is this a direct redirection to a valid target page
                if (!page_exists($this->redirectionTarget)) {

                    if ($this->isValidURL($this->redirectionTarget)) {

                        $this->targetResourceType = 'Url';

                    } else {

                        msg($this->lang['NotInternalOrUrlPage'] . ': ' . $this->redirectionTarget . '', -1);
                        return;

                    }

                } else {

                    $this->targetResourceType = 'Internal Page';

                }
                $this->addRedirection($this->redirectionSource, $this->redirectionTarget);
                msg($this->lang['Saved'], 1);

            }


        }

        if ($_POST['Delete']) {

            $redirectionId = $_POST['SourcePage'];
            $this->deleteRedirection($redirectionId);
            msg($this->lang['Deleted'], 1);

        }
        if ($_POST['Validate']) {
            $redirectionId = $_POST['SourcePage'];
            $this->validateRedirection($redirectionId);
            msg($this->lang['Validated'], 1);
        }
    }

    /**
     * output appropriate html
     */
    function html()
    {

        global $conf;

        echo $this->locale_xhtml('intro');

        // Add a redirection
        ptln('<h2><a name="add_redirection" id="add_redirection">' . $this->lang['AddModifyRedirection'] . '</a></h2>');
        ptln('<div class="level2">');
        ptln('<form action="" method="post">');
        ptln('<table class="inline">');

        ptln('<thead>');
        ptln('		<tr><th>' . $this->lang['Field'] . '</th><th>' . $this->lang['Value'] . '</th> <th>' . $this->lang['Information'] . '</th></tr>');
        ptln('</thead>');

        ptln('<tbody>');
        ptln('		<tr><td><label for="add_sourcepage" >' . $this->lang['source_page'] . ': </label></td><td><input type="text" id="add_sourcepage" name="' . self::FORM_NAME_SOURCE_PAGE . '" value="' . $this->redirectionSource . '" class="edit" /></td><td>' . $this->lang['source_page_info'] . '</td></td></tr>');
        ptln('		<tr><td><label for="add_targetpage" >' . $this->lang['target_page'] . ': </label></td><td><input type="text" id="add_targetpage" name="' . self::FORM_NAME_TARGET_PAGE . '" value="' . $this->redirectionTarget . '" class="edit" /></td><td>' . $this->lang['target_page_info'] . '</td></tr>');
        ptln('		<tr>');
        ptln('			<td colspan="3">');
        ptln('				<input type="hidden" name="do"    value="admin" />');
        ptln('				<input type="hidden" name="page"  value="404manager" />');
        ptln('				<input type="submit" name="Add" class="button" value="' . $this->lang['btn_addmodify'] . '" />');
        ptln('			</td>');
        ptln('		</tr>');
        ptln('</tbody>');
        ptln('</table>');
        ptln('</form>');

        // Add the file add from the lang directory
        echo $this->locale_xhtml('add');
        ptln('</div>');


//      List of redirection
        ptln('<h2><a name="list_redirection" id="list_redirection">' . $this->lang['ListOfRedirection'] . '</a></h2>');
        ptln('<div class="level2">');

        ptln('<div class="table-responsive">');

        ptln('<table class="table table-hover">');
        ptln('	<thead>');
        ptln('		<tr>');
        ptln('			<th>&nbsp;</th>');
        ptln('			<th>' . $this->lang['SourcePage'] . '</th>');
        ptln('			<th>' . $this->lang['TargetPage'] . '</th>');
        ptln('			<th>' . $this->lang['CreationDate'] . '</th>');
        ptln('	    </tr>');
        ptln('	</thead>');

        ptln('	<tbody>');


        foreach ($this->getRedirections() as $key => $row) {

            if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {
                $sourcePageId = $row['SOURCE'];
                $targetPageId = $row['TARGET'];
                $creationDate = $row['CREATION_TIMESTAMP'];
            } else {
                $sourcePageId = $key;
                $targetPageId = $row['TargetPage'];
                $creationDate = $row['CreationDate'];
            }
            $title = false;
            if ($conf['useheading']) {
                $title = p_get_first_heading($targetPageId);
            }
            if (!$title) $title = $targetPageId;


            ptln('	  <tr class="redirect_info">');
            ptln('		<td>');
            ptln('			<form action="" method="post">');
            ptln('				<input type="image" src="' . DOKU_BASE . 'lib/plugins/404manager/images/delete.jpg" name="Delete" title="Delete" alt="Delete" value="Submit" />');
            ptln('				<input type="hidden" name="Delete"  value="Yes" />');
            ptln('				<input type="hidden" name="SourcePage"  value="' . $sourcePageId . '" />');
            ptln('			</form>');

            ptln('		</td>');
            print('	<td>');
            tpl_link(wl($sourcePageId), $this->truncateString($sourcePageId, 30), 'title="' . $sourcePageId . '" class="wikilink2" rel="nofollow"');
            ptln('		</td>');
            print '		<td>';
            tpl_link(wl($targetPageId), $this->truncateString($targetPageId, 30), 'title="' . hsc($title) . ' (' . $targetPageId . ')"');
            ptln('		</td>');
            ptln('		<td>' . $creationDate . '</td>');
            ptln('    </tr>');
        }
        ptln('  </tbody>');
        ptln('</table>');
        ptln('</div>'); //End Table responsive
        ptln('</div>'); // End level 2


    }

    /**
     * Generate a text with a max length of $length
     * and add ... if above
     */
    function truncateString($myString, $length)
    {
        if (strlen($myString) > $length) {
            $myString = substr($myString, 0, $length) . ' ...';
        }
        return $myString;
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
     * Delete Redirection
     * @param string $id
     */
    function deletePage($id)
    {

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {
            $res = $this->sqlite->query('delete from pages where id = ?', $id);
            if (!$res) {
                $this->throwRuntimeException("Something went wrong when deleting a page");
            }
        }

    }

    /**
     * Is Redirection of a page Id Present
     * @param string $sourcePageId
     * @return int
     */
    function isRedirectionPresent($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_CONF_FILE) {

            if (isset($this->pageRedirections[$sourcePageId])) {
                return 1;
            } else {
                return 0;
            }

        } else {

            $res = $this->sqlite->query("SELECT * FROM redirections where SOURCE = ?", $sourcePageId);
            return $this->sqlite->res2count($res);

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

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {

            $res = $this->sqlite->query("SELECT * FROM pages where id = ?", $id);
            return $this->sqlite->res2count($res);

        } else {

            return 0;

        }

    }

    /**
     * Does the page is known in the pages table
     * @param string $id
     * @return array
     */
    function getPage($id)
    {
        $id = strtolower($id);

        if ($this->dataStoreType == null) {
            $this->initDataStore();
        }

        if ($this->dataStoreType == self::DATA_STORE_TYPE_SQLITE) {

            $res = $this->sqlite->query("SELECT * FROM pages where id = ?", $id);
            if (!$res) {
                throw new RuntimeException("An exception has occurred with the select pages query");
            }
            return $this->sqlite->res2arr($res);

        } else {

            return [];

        }

    }






}
