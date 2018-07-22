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
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 *
 */
class admin_plugin_404manager extends DokuWiki_Admin_Plugin
{

    // A static function to hold the 404 manager
    private static $manager404 = null;


    // Variable var and not public/private because php4 can't handle this kind of variable

    // The file path of the direct redirection (from an Page to a Page or URL)
    // No more used, replaced by a sqlite database
    private const PAGE_REDIRECTION_FILE_PATH = __DIR__ . "/404managerRedirect.conf";

    // The redirection data in memory
    var $pageRedirections = array();

    // The file path of the pattern redirection (from a Pattern)
    private $pagePatternRedirectionsFilePath;
    var $pagePatternRedirections = array();

    // Use to pass parameter between the handle and the html function to keep the form data
    var $redirectionSource = '';
    var $redirectionTarget = '';
    private $redirectionType;
    var $currentDate = '';
    var $isValidate = '';
    var $targetResourceType = 'Default';
    private $infoPlugin;

    /** @var helper_plugin_sqlite $sqlite */
    private $sqlite;


    const FORM_NAME_SOURCE_PAGE = 'SourcePage';
    const FORM_NAME_TARGET_PAGE = 'TargetPage';

    /**
     * admin_plugin_404manager constructor.
     *
     * Use the get function instead
     */
    private function __construct()
    {

        // enable direct access to language strings
        // of use of $this->getLang
        $this->setupLocale();
        $this->currentDate = date("d/m/Y");
        $this->infoPlugin = $this->getInfo();


        $this->sqlite = plugin_load('helper', 'sqlite');
        if(!$this->sqlite){

            msg($this->getLang('SqliteMandatory'), MANAGER404_MSG_INFO, $allow=MSG_MANAGERS_ONLY);

            //Set the redirection data
            if (@file_exists(self::PAGE_REDIRECTION_FILE_PATH)) {
                $this->pageRedirections = unserialize(io_readFile(self::PAGE_REDIRECTION_FILE_PATH, false));
            }

        } else {

            // Migration of the old store
            if (@file_exists(self::PAGE_REDIRECTION_FILE_PATH)) {
                $pageRedirections = unserialize(io_readFile(self::PAGE_REDIRECTION_FILE_PATH, false));

            }

            // initialize the database connection
            $pluginName = $this->infoPlugin['base'];
            $init = $this->sqlite->init($pluginName, DOKU_PLUGIN . $pluginName . '/db/');
            if(!$init){
                msg($this->lang['SqliteUnableToInitialize'], MSG_MANAGERS_ONLY);
                return;
            }

        }


    }

    public static function get()
    {
        if (self::$manager404==null){
            self::$manager404 = new admin_plugin_404manager();
        }
        return self::$manager404;
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
            $this->redirectionType = $_POST['Type'];

            if ($this->redirectionSource == $this->redirectionTarget) {
                msg($this->lang['SameSourceAndTargetAndPage'] . ': ' . $this->redirectionSource . '', -1);
                return;
            }

            // Is this a regular expression pattern ?
            if ($this->redirectionType == 'Pattern') {

                $matches = self::isRegularExpression($this->redirectionSource);
                if ($matches) {

                    $pattern = $this->redirectionSource;
                    $substitution = $this->redirectionTarget;
                    $applyAlwaysSubstitution = $_POST['ApplyAlwaysSubstitution'];
                    $this->addPatternRedirection($pattern, $substitution, $applyAlwaysSubstitution);
                    msg($this->lang['Saved'], MSG_MANAGERS_ONLY);
                    return;

                }

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
            $redirectionType = $_POST['RedirectionType'];
            if ($redirectionType=='pattern') {
                $this->deletePatternRedirection($redirectionId);
            } else {
                $this->deleteRedirection($redirectionId);
            }
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
        ptln('		<tr><td><label for="add_sourcepage" >' . $this->lang['source_page'] . ': </label></td><td><input type="text" id="add_sourcepage" name="'.self::FORM_NAME_SOURCE_PAGE.'" value="' . $this->redirectionSource . '" class="edit" /></td><td>' . $this->lang['source_page_info'] . '</td></td></tr>');
        ptln('		<tr><td><label for="add_targetpage" >' . $this->lang['target_page'] . ': </label></td><td><input type="text" id="add_targetpage" name="'.self::FORM_NAME_TARGET_PAGE.'" value="' . $this->redirectionTarget . '" class="edit" /></td><td></td></tr>');
        ptln('		<tr><td><label for="add_valid" >' . $this->lang['redirection_valid'] . ': </label></td><td>' . $this->lang['yes'] . '</td><td>'.$this->lang['ExplicationValidateRedirection'] .'</td></tr>');
        ptln('		<tr>');
        ptln('			<td colspan="3">');
        ptln('				<input type="hidden" name="do"    value="admin" />');
        ptln('				<input type="hidden" name="page"  value="404manager" />');
        ptln('				<input type="submit" name="Add" class="button" value="'.$this->lang['btn_addmodify'].'" />');
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
        ptln('			<th>' . $this->lang['TargetPageType'] . '</th>');
        ptln('			<th>' . $this->lang['Valid'] . '</th>');
        ptln('			<th>' . $this->lang['CreationDate'] . '</th>');
        ptln('			<th>' . $this->lang['LastRedirectionDate'] . '</th>');
        ptln('			<th>' . $this->lang['LastReferrer'] . '</th>');
        ptln('			<th>' . $this->lang['CountOfRedirection'] . '</th>');
        ptln('	    </tr>');
        ptln('	</thead>');

        ptln('	<tbody>');
        foreach ($this->pageRedirections as $sourcePageId => $Vl_Attributes) {

            $title = false;
            if ($conf['useheading']) {
                $title = p_get_first_heading($Vl_Attributes['TargetPage']);
            }
            if (!$title) $title = $Vl_Attributes['TargetPage'];


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
            tpl_link(wl($Vl_Attributes['TargetPage']), $this->truncateString($Vl_Attributes['TargetPage'], 30), 'title="' . hsc($title) . ' (' . $Vl_Attributes['TargetPage'] . ')"');
            ptln('		</td>');
            ptln('		<td>' . $Vl_Attributes['TargetPageType'] . '</td>');
            if ($Vl_Attributes['IsValidate'] == 'N') {
                ptln('		<td><form action="" method="post">');
                ptln('				<input type="image" src="' . DOKU_BASE . 'lib/plugins/404manager/images/validate.jpg" name="validate" title="' . $this->lang['ValidateToSuppressMessage'] . '" alt="Validate" />');
                ptln('				<input type="hidden" name="Validate"  value="Yes" />');
                ptln('				<input type="hidden" name="SourcePage"  value="' . $sourcePageId . '" />');
                ptln('		</form></td>');
            } else {
                ptln('		<td>Yes</td>');
            }
            ptln('		<td>' . $Vl_Attributes['CreationDate'] . '</td>');
            ptln('		<td>' . $Vl_Attributes['LastRedirectionDate'] . '</td>');
            if ($this->isValidURL($Vl_Attributes['LastReferrer'])) {
                print('	<td>');
                tpl_link($Vl_Attributes['LastReferrer'], $this->truncateString($Vl_Attributes['LastReferrer'], 30), 'title="' . $Vl_Attributes['LastReferrer'] . '" class="urlextern" rel="nofollow"');
                print('	</td>');
            } else {
                ptln('		<td>' . $Vl_Attributes['LastReferrer'] . '</td>');
            }
            ptln('		<td>' . $Vl_Attributes['CountOfRedirection'] . '</td>');
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
     * @param    string $sourcePageId
     */
    function deleteRedirection($sourcePageId)
    {
        unset($this->pageRedirections[strtolower($sourcePageId)]);
        $this->savePageRedirections();
    }

    /**
     * Is Redirection of a page Id Present
     * @param  string $sourcePageId
     * @return int
     */
    function isRedirectionPresent($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);
        if ($this->sqlite==null){
            if (isset($this->pageRedirections[$sourcePageId])) {
                return 1;
            } else {
                return 0;
            }
        }

    }

    /**
     * Add Redirection
     * @param string $sourcePageId
     * @param string $targetPageId
     * @throws Exception if the redirection already exist
     */
    function addRedirection($sourcePageId, $targetPageId)
    {

        // Lower page name is the dokuwiki Id
        $sourcePageId = strtolower($sourcePageId);

        if (isset($this->pageRedirections[$sourcePageId])) {
            throw new Exception('Redirection for page (' . $sourcePageId . 'already exist');
        }

        $this->pageRedirections[$sourcePageId]['TargetPage'] = $targetPageId;
        $this->pageRedirections[$sourcePageId]['CreationDate'] = $this->currentDate;
        // If the call come from the admin page and not from the process function
        if (substr_count($_SERVER['HTTP_REFERER'], 'admin.php')) {

            $this->pageRedirections[$sourcePageId]['IsValidate'] = 'Y';
            $this->pageRedirections[$sourcePageId]['CountOfRedirection'] = 0;
            $this->pageRedirections[$sourcePageId]['LastRedirectionDate'] = $this->lang['Never'];
            $this->pageRedirections[$sourcePageId]['LastReferrer'] = 'Never';

        } else {

            $this->pageRedirections[$sourcePageId]['IsValidate'] = 'N';
            $this->pageRedirections[$sourcePageId]['CountOfRedirection'] = 1;
            $this->pageRedirections[$sourcePageId]['LastRedirectionDate'] = $this->currentDate;
            if ($_SERVER['HTTP_REFERER'] <> '') {
                $this->pageRedirections[$sourcePageId]['LastReferrer'] = $_SERVER['HTTP_REFERER'];
            } else {
                $this->pageRedirections[$sourcePageId]['LastReferrer'] = $this->lang['Direct Access'];
            }

        }

        if (!$this->isValidURL($targetPageId)) {
            $this->pageRedirections[$sourcePageId]['TargetPageType'] = 'Internal Page';
        } else {
            $this->pageRedirections[$sourcePageId]['TargetPageType'] = 'Url';
        }

        $this->savePageRedirections();
    }

    /**
     * Validate a Redirection
     * @param    string $sourcePageId
     */
    function validateRedirection($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);
        $this->pageRedirections[$sourcePageId]['IsValidate'] = 'Y';
        $this->savePageRedirections();
    }

    /**
     * Get IsValidate Redirection
     * @param    string $sourcePageId
     * @return string
     */
    function getIsValidate($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);
        if ($this->pageRedirections[$sourcePageId]['IsValidate'] == null) {
            return 'N';
        } else {
            return $this->pageRedirections[$sourcePageId]['IsValidate'];
        }
    }

    /**
     * Get TargetPageType
     * @param    string $sourcePageId
     * @return
     */
    function getTargetPageType($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);
        return $this->pageRedirections[$sourcePageId]['TargetPageType'];

    }

    /**
     * Get TargetResource (It can be an external URL as an intern page id
     * @param    string $sourcePageId
     * @return
     */
    function getRedirectionTarget($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);
        return $this->pageRedirections[strtolower($sourcePageId)]['TargetPage'];
    }

    /**
     * Update Redirection Action Data as Referrer, Count Of Redirection, Redirection Date
     * @param    string $sourcePageId
     */
    function updateRedirectionMetaData($sourcePageId)
    {
        $sourcePageId = strtolower($sourcePageId);
        $this->pageRedirections[$sourcePageId]['LastRedirectionDate'] = $this->currentDate;
        $this->pageRedirections[$sourcePageId]['LastReferrer'] = $_SERVER['HTTP_REFERER'];
        $this->pageRedirections[$sourcePageId]['CountOfRedirection'] += 1;
        $this->savePageRedirections();
    }

    /**
     * Serialize and save the redirection data file
     */
    function savePageRedirections()
    {
        io_saveFile(self::PAGE_REDIRECTION_FILE_PATH, serialize($this->pageRedirections));
    }

    /**
     * Validate URL
     * Allows for port, path and query string validations
     * @param    string $url string containing url user input
     * @return   boolean     Returns TRUE/FALSE
     */
    function isValidURL($url)
    {
        // of preg_match('/^https?:\/\//',$url) ? from redirect plugin
        return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
    }

    function addPatternRedirection($pattern, $substitution, $applyAlwaysSubstitution)
    {
        $hash = hash($pattern.$substitution.$applyAlwaysSubstitution);
        $this->pagePatternRedirections[$hash]['pattern'] = $pattern;
        $this->pagePatternRedirections[$hash]['substitution'] = $substitution;
        $this->pagePatternRedirections[$hash]['substitution'] = $applyAlwaysSubstitution;
        $this->savePagePatternRedirections();
    }

    function savePagePatternRedirections()
    {
        io_saveFile($this->pagePatternRedirectionsFilePath, serialize($this->pagePatternRedirections));
    }

    function deletePatternRedirection($redirectionId)
    {
        unset($this->pagePatternRedirections[strtolower($redirectionId)]);
        $this->savePagePatternRedirections();
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


}
