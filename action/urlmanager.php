<?php

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
// Needed for the page lookup
//require_once(DOKU_INC . 'inc/fulltext.php');
// Needed to get the redirection manager
// require_once(DOKU_PLUGIN . 'action.php');

require_once(__DIR__ . '/../class/UrlRedirection.php');
require_once(__DIR__ . '/../class/UrlCanonical.php');

/**
 * Class action_plugin_404manager_url
 *
 * The actual URL manager
 *
 *
 */
class action_plugin_404manager_urlmanager extends DokuWiki_Action_Plugin
{


    // The redirect type
    const REDIRECT_HTTP_EXTERNAL = 'External';
    const REDIRECT_HTTP_INTERNAL = 'HttpInternal';
    const REDIRECT_REWRITE = 'Rewrite';

    // Where the target id value comes from
    const TARGET_ORIGIN_DATA_STORE = 'dataStore';
    const TARGET_ORIGIN_CANONICAL = 'canonical';
    const TARGET_ORIGIN_START_PAGE = 'startPage';
    const TARGET_ORIGIN_BEST_PAGE_NAME = 'bestPageName';
    const TARGET_ORIGIN_BEST_NAMESPACE = 'bestNamespace';
    const TARGET_ORIGIN_SEARCH_ENGINE = 'searchEngine';

    // The constant parameters
    const GO_TO_SEARCH_ENGINE = 'GoToSearchEngine';
    const GO_TO_BEST_NAMESPACE = 'GoToBestNamespace';
    const GO_TO_BEST_END_PAGE_NAME = 'GoToBestEndPageName';
    const GO_TO_BEST_PAGE_NAME = 'GoToBestPageName';
    const GO_TO_NS_START_PAGE = 'GoToNsStartPage';
    const GO_TO_EDIT_MODE = 'GoToEditMode';
    const NOTHING = 'Nothing';


    /*
     * The event scope is made object
     */
    var $event;

    // The action name is used as check / communication channel between function hooks.
    // It will comes in the global $ACT variable
    const ACTION_NAME = '404manager';

    // The name in the session variable
    const MANAGER404_MSG = '404manager_msg';

    // Query String variable name to send the redirection message
    const QUERY_STRING_ORIGIN_PAGE = '404id';
    const QUERY_STRING_REDIR_TYPE = '404type';

    // Message
    private $message;


    function __construct()
    {
        // enable direct access to language strings
        $this->setupLocale();
        require_once(__DIR__ . '/../class/message.model.php');
        $this->message = new Message404();
    }


    function register(Doku_Event_Handler $controller)
    {


        /* This will call the function _handle404 */
        $controller->register_hook('DOKUWIKI_STARTED',
            'AFTER',
            $this,
            '_handle404',
            array());

        /* This will call the function _displayRedirectMessage */
        $controller->register_hook(
            'TPL_ACT_RENDER',
            'BEFORE',
            $this,
            '_displayRedirectMessage',
            array()
        );


    }

    /**
     * Verify if there is a 404
     * Inspiration comes from <a href="https://github.com/splitbrain/dokuwiki-plugin-notfound/blob/master/action.php">Not Found Plugin</a>
     * @param $event Doku_Event
     * @param $param
     * @return bool not required
     * @throws Exception
     */
    function _handle404(&$event, $param)
    {

        global $INFO;
        if ($INFO['exists']) {
            // Check if there is a canonical meta
            UrlCanonical::get()->processCanonicalMeta();
            return false;
        }


        global $ACT;
        if ($ACT != 'show') return false;


        // Event is also used in some sub-function, we make it them object scope
        $this->event = $event;


        // Global variable needed in the process
        global $ID;
        global $conf;

        // Do we have a canonical ?
        $targetPage = UrlCanonical::get()->getPageIdFromCanonical($ID);
        if ($targetPage) {

            $this->internalRedirect($targetPage, self::TARGET_ORIGIN_CANONICAL);
            return true;

        }


        // Get the page from redirection data
        $targetPage = UrlRedirection::get()->getRedirectionTarget($ID);


        // If this is an external redirect (other domain)
        if (UrlStatic::isValidURL($targetPage) && $targetPage) {

            $this->httpRedirect($targetPage, self::TARGET_ORIGIN_DATA_STORE);
            return true;

        }

        // Internal redirect

        // There is one action for a writer:
        //   * edit mode direct
        // If the user is a writer (It have the right to edit).
        if ($this->userCanWrite() && $this->getConf(self::GO_TO_EDIT_MODE) == 1) {

            $this->gotToEditMode($event);
            // Stop here
            return true;

        }

        // This is a reader
        // Their are only three actions for a reader:
        //   * redirect to a page (show another page id)
        //   * go to the search page
        //   * do nothing

        // If the page exist
        if (page_exists($targetPage)) {

            $this->internalRedirect($targetPage, self::TARGET_ORIGIN_DATA_STORE);
            return true;

        }

        /*
         *  We are still a reader, the redirection does not exist the user is not allowed to edit the page (public of other)
         */
        if ($this->getConf('ActionReaderFirst') == self::NOTHING) {
            return true;
        }

        // We are reader and their is no redirection set, we apply the algorithm
        $readerAlgorithms = array();
        $readerAlgorithms[0] = $this->getConf('ActionReaderFirst');
        $readerAlgorithms[1] = $this->getConf('ActionReaderSecond');
        $readerAlgorithms[2] = $this->getConf('ActionReaderThird');

        $i = 0;
        while (isset($readerAlgorithms[$i])) {

            switch ($readerAlgorithms[$i]) {

                case self::NOTHING:
                    return true;
                    break;

                case self::GO_TO_BEST_END_PAGE_NAME:

                    break;

                case self::GO_TO_NS_START_PAGE:

                    // Start page with the conf['start'] parameter
                    $startPage = getNS($ID) . ':' . $conf['start'];
                    if (page_exists($startPage)) {
                        $this->httpRedirect($startPage, self::TARGET_ORIGIN_START_PAGE);
                        return true;
                    }

                    // Start page with the same name than the namespace
                    $startPage = getNS($ID) . ':' . curNS($ID);
                    if (page_exists($startPage)) {
                        $this->httpRedirect($startPage, self::TARGET_ORIGIN_START_PAGE);
                        return true;
                    }
                    break;

                case self::GO_TO_BEST_PAGE_NAME:

                    $bestPageId = null;

                    $bestPage = $this->getBestPage($ID);
                    $bestPageId = $bestPage['id'];
                    $scorePageName = $bestPage['score'];

                    // Get Score from a Namespace
                    $bestNamespace = $this->scoreBestNamespace($ID);
                    $bestNamespaceId = $bestNamespace['namespace'];
                    $namespaceScore = $bestNamespace['score'];

                    // Compare the two score
                    if ($scorePageName > 0 or $namespaceScore > 0) {
                        if ($scorePageName > $namespaceScore) {
                            $this->internalRedirect($bestPageId, self::TARGET_ORIGIN_BEST_PAGE_NAME);
                        } else {
                            $this->internalRedirect($bestNamespaceId, self::TARGET_ORIGIN_BEST_PAGE_NAME);
                        }
                        return true;
                    }
                    break;

                case self::GO_TO_BEST_NAMESPACE:

                    $scoreNamespace = $this->scoreBestNamespace($ID);
                    $bestNamespaceId = $scoreNamespace['namespace'];
                    $score = $scoreNamespace['score'];

                    if ($score > 0) {
                        $this->internalRedirect($bestNamespaceId, self::TARGET_ORIGIN_BEST_NAMESPACE);
                        return true;
                    }
                    break;

                case self::GO_TO_SEARCH_ENGINE:

                    $this->redirectToSearchEngine();

                    return true;
                    break;

                // End Switch Action
            }

            $i++;
            // End While Action
        }
        // End if not connected

        return true;

    }


    /**
     * Main function; dispatches the visual comment actions
     * @param   $event Doku_Event
     */
    function _displayRedirectMessage(&$event, $param)
    {

        // After a redirect to another page via query string ?
        global $INPUT;
        // Comes from method redirectToDokuwikiPage
        $pageIdOrigin = $INPUT->str(self::QUERY_STRING_ORIGIN_PAGE);

        if ($pageIdOrigin) {

            $redirectSource = $INPUT->str(self::QUERY_STRING_REDIR_TYPE);

            switch ($redirectSource) {

                case self::TARGET_ORIGIN_DATA_STORE:
                    $this->message->addContent(sprintf($this->lang['message_redirected_by_redirect'], hsc($pageIdOrigin)));
                    $this->message->setType(Message404::TYPE_CLASSIC);
                    break;

                case self::TARGET_ORIGIN_START_PAGE:
                    $this->message->addContent(sprintf($this->lang['message_redirected_to_startpage'], hsc($pageIdOrigin)));
                    $this->message->setType(Message404::TYPE_WARNING);
                    break;

                case  self::TARGET_ORIGIN_BEST_PAGE_NAME:
                    $this->message->addContent(sprintf($this->lang['message_redirected_to_bestpagename'], hsc($pageIdOrigin)));
                    $this->message->setType(Message404::TYPE_WARNING);
                    break;

                case self::TARGET_ORIGIN_BEST_NAMESPACE:
                    $this->message->addContent(sprintf($this->lang['message_redirected_to_bestnamespace'], hsc($pageIdOrigin)));
                    $this->message->setType(Message404::TYPE_WARNING);
                    break;

                case self::TARGET_ORIGIN_SEARCH_ENGINE:
                    $this->message->addContent(sprintf($this->lang['message_redirected_to_searchengine'], hsc($pageIdOrigin)));
                    $this->message->setType(Message404::TYPE_WARNING);
                    break;

            }

            // Add a list of page with the same name to the message
            // if the redirections is not planned
            if ($redirectSource != self::TARGET_ORIGIN_DATA_STORE) {
                $this->addToMessagePagesWithSameName($pageIdOrigin);
            }

        }

        if ($event->data == 'show' || $event->data == 'edit' || $event->data == 'search') {

            $this->printMessage($this->message);

        }
    }


    /**
     * getBestNamespace
     * Return a list with 'BestNamespaceId Score'
     * @param $id
     * @return array
     */
    private
    function scoreBestNamespace($id)
    {

        global $conf;

        // Parameters
        $pageNameSpace = getNS($id);

        // If the page has an existing namespace start page take it, other search other namespace
        $startPageNameSpace = $pageNameSpace . ":";
        $dateAt = '';
        // $startPageNameSpace will get a full path (ie with start or the namespace
        resolve_pageid($pageNameSpace, $startPageNameSpace, $exists, $dateAt, true);
        if (page_exists($startPageNameSpace)) {
            $nameSpaces = array($startPageNameSpace);
        } else {
            $nameSpaces = ft_pageLookup($conf['start']);
        }

        // Parameters and search the best namespace
        $pathNames = explode(':', $pageNameSpace);
        $bestNbWordFound = 0;
        $bestNamespaceId = '';
        foreach ($nameSpaces as $nameSpace) {

            $nbWordFound = 0;
            foreach ($pathNames as $pathName) {
                if (strlen($pathName) > 2) {
                    $nbWordFound = $nbWordFound + substr_count($nameSpace, $pathName);
                }
            }
            if ($nbWordFound > $bestNbWordFound) {
                // Take only the smallest namespace
                if (strlen($nameSpace) < strlen($bestNamespaceId) or $nbWordFound > $bestNbWordFound) {
                    $bestNbWordFound = $nbWordFound;
                    $bestNamespaceId = $nameSpace;
                }
            }
        }

        $startPageFactor = $this->getConf('WeightFactorForStartPage');
        $nameSpaceFactor = $this->getConf('WeightFactorForSameNamespace');
        if ($bestNbWordFound > 0) {
            $bestNamespaceScore = $bestNbWordFound * $nameSpaceFactor + $startPageFactor;
        } else {
            $bestNamespaceScore = 0;
        }


        return array(
            'namespace' => $bestNamespaceId,
            'score' => $bestNamespaceScore
        );

    }

    /**
     * @param $event
     */
    private
    function gotToEditMode(&$event)
    {
        global $ID;
        global $conf;


        global $ACT;
        $ACT = 'edit';

        // If this is a side bar no message.
        // There is always other page with the same name
        $pageName = noNS($ID);
        if ($pageName != $conf['sidebar']) {

            if ($this->getConf('ShowMessageClassic') == 1) {
                $this->message->addContent($this->lang['message_redirected_to_edit_mode']);
                $this->message->setType(Message404::TYPE_CLASSIC);
            }

            // If Param show page name unique and it's not a start page
            $this->addToMessagePagesWithSameName($ID);


        }


    }

    /**
     * Return if the user has the right/permission to create/write an article
     * @return bool
     */
    private
    function userCanWrite()
    {
        global $ID;

        if ($_SERVER['REMOTE_USER']) {
            $perm = auth_quickaclcheck($ID);
        } else {
            $perm = auth_aclcheck($ID, '', null);
        }

        if ($perm >= AUTH_EDIT) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Redirect to an internal page ie:
     *   * on the same domain
     *   * no HTTP redirect
     *   * id rewrite
     * @param string $targetPage - target page id or an URL
     * @param string $redirectSource the source of the redirect
     * @throws Exception
     */
    private
    function internalRedirect($targetPage, $redirectSource = 'Not Known')
    {

        global $ID;

        //If the user have right to see the target page
        if ($_SERVER['REMOTE_USER']) {
            $perm = auth_quickaclcheck($targetPage);
        } else {
            $perm = auth_aclcheck($targetPage, '', null);
        }
        if ($perm <= AUTH_NONE) {
            return;
        }

        // Redirection
        $this->redirectManager->logRedirection($ID, $targetPage, $redirectSource);

        $this->storeRedirectInCookie($ID, $redirectSource);

        // Change the id
        global $ID;
        $ID = $targetPage;
        // Change the info id for the sidebar
        $INFO['id'] = $targetPage;


    }

    /**
     * An HTTP Redirect to an internal page, no external resources
     * @param string $target - a dokuwiki id or an url
     * @param $targetOrigin - the origin of the target
     */
    private
    function httpRedirect($target, $targetOrigin)
    {

        global $ID;

        // No message can be shown because this is an external URL

        // Update the redirections
        $this->logRedirection($ID, $target, $targetOrigin);

        // Cookie
        $this->storeRedirectInCookie($ID, $targetOrigin);

        // An url ?
        if (UrlStatic::isValidURL($target)) {

            $targetUrl = $target;

        } else {

            // Explode the page ID and the anchor (#)
            $link = explode('#', $target, 2);

            $targetUrl = wl($link[0], array(), true, '&');
            if ($link[1]) {
                $targetUrl .= '#' . rawurlencode($link[1]);
            }

        }

        /*
         * The function below send a 302
         *
         * FYI: to send a 301
         * header('HTTP/1.1 301 Moved Permanently');
         */
        send_redirect($targetUrl);


        if (defined('DOKU_UNITTEST')) return; // no exits during unit tests
        exit();

    }

    /**
     * @param $id
     * @return array
     */
    private
    function getBestPage($id)
    {

        // The return parameters
        $bestPageId = null;
        $scorePageName = null;

        // Get Score from a page
        $pageName = noNS($id);
        $pagesWithSameName = ft_pageLookup($pageName);
        if (count($pagesWithSameName) > 0) {

            // Search same namespace in the page found than in the Id page asked.
            $bestNbWordFound = 0;


            $wordsInPageSourceId = explode(':', $id);
            foreach ($pagesWithSameName as $targetPageId => $title) {

                // Nb of word found in the target page id
                // that are in the source page id
                $nbWordFound = 0;
                foreach ($wordsInPageSourceId as $word) {
                    $nbWordFound = $nbWordFound + substr_count($targetPageId, $word);
                }

                if ($bestPageId == null) {

                    $bestNbWordFound = $nbWordFound;
                    $bestPageId = $targetPageId;

                } else {

                    if ($nbWordFound >= $bestNbWordFound && strlen($bestPageId) > strlen($targetPageId)) {

                        $bestNbWordFound = $nbWordFound;
                        $bestPageId = $targetPageId;

                    }

                }

            }
            $scorePageName = $this->getConf('WeightFactorForSamePageName') + ($bestNbWordFound - 1) * $this->getConf('WeightFactorForSameNamespace');
            return array(
                'id' => $bestPageId,
                'score' => $scorePageName);
        }
        return array(
            'id' => $bestPageId,
            'score' => $scorePageName
        );

    }

    /**
     * Add the page with the same page name but in an other location
     * @param $pageId
     */
    private
    function addToMessagePagesWithSameName($pageId)
    {

        global $conf;

        $pageName = noNS($pageId);
        if ($this->getConf('ShowPageNameIsNotUnique') == 1 && $pageName <> $conf['start']) {

            //Search same page name
            $pagesWithSameName = ft_pageLookup($pageName);

            if (count($pagesWithSameName) > 0) {

                $this->message->setType(Message404::TYPE_WARNING);

                // Assign the value to a variable to be able to use the construct .=
                if ($this->message->getContent() <> '') {
                    $this->message->addContent('<br/><br/>');
                }
                $this->message->addContent($this->lang['message_pagename_exist_one']);
                $this->message->addContent('<ul>');

                $i = 0;
                foreach ($pagesWithSameName as $PageId => $title) {
                    $i++;
                    if ($i > 10) {
                        $this->message->addContent('<li>' .
                            tpl_link(
                                wl($pageId) . "&do=search&q=" . rawurldecode($pageName),
                                "More ...",
                                'class="" rel="nofollow" title="More..."',
                                $return = true
                            ) . '</li>');
                        break;
                    }
                    if ($title == null) {
                        $title = $PageId;
                    }
                    $this->message->addContent('<li>' .
                        tpl_link(
                            wl($PageId),
                            $title,
                            'class="" rel="nofollow" title="' . $title . '"',
                            $return = true
                        ) . '</li>');
                }
                $this->message->addContent('</ul>');
            }
        }
    }

    /**
     * Print a message to show that the user was redirected
     */
    private
    function printMessage(): void
    {
        if ($this->message->getContent() <> "") {
            $pluginInfo = $this->getInfo();
            // a class can not start with a number then 404manager is not a valid class name
            $redirectManagerClass = "redirect-manager";

            if ($this->message->getType() == Message404::TYPE_CLASSIC) {
                ptln('<div class="alert alert-success ' . $redirectManagerClass . '" role="alert">');
            } else {
                ptln('<div class="alert alert-warning ' . $redirectManagerClass . '" role="alert">');
            }

            print $this->message->getContent();


            print '<div class="managerreference">' . $this->lang['message_come_from'] . ' <a href="' . $pluginInfo['url'] . '" class="urlextern" title="' . $pluginInfo['desc'] . '"  rel="nofollow">' . $pluginInfo['name'] . '</a>.</div>';
            print('</div>');
        }
    }

    /**
     * Redirect to the search engine
     */
    private
    function redirectToSearchEngine()
    {

        global $ID;

        $replacementPart = array(':', '_', '-');
        $query = str_replace($replacementPart, ' ', $ID);

        $urlParams = array(
            "do" => "search",
            "q" => $query
        );

        $url = wl($ID, $urlParams, true, '&');

        $this->httpRedirect($url, self::TARGET_ORIGIN_SEARCH_ENGINE);

    }

    /**
     * Set the redirect in a cookie that will be be read after the redirect
     * in order to show a message to the user
     * @param string $id
     * @param string $redirectSource
     */
    private function storeRedirectInCookie(string $id, string $redirectSource)
    {
        // Msg via cookie
        if (!defined('NOSESSION')) {
            //reopen session, store data and close session again
            @session_start();
            $_SESSION[DOKU_COOKIE][self::QUERY_STRING_ORIGIN_PAGE] = $id;
            $_SESSION[DOKU_COOKIE][self::QUERY_STRING_REDIR_TYPE] = $redirectSource;
            session_write_close();
        }
    }


    /**
     *
     *   * For a conf file, it will update the Redirection Action Data as Referrer, Count Of Redirection, Redirection Date
     *   * For a SQlite database, it will add a row into the log
     *
     * @param string $sourcePageId
     * @param $targetPageId
     * @param $targetOrigin
     */
    function logRedirection($sourcePageId, $targetPageId, $targetOrigin)
    {

        $row = array(
            "TIMESTAMP" => date("c"),
            "SOURCE" => $sourcePageId,
            "TARGET" => $targetPageId,
            "REFERRER" => $_SERVER['HTTP_REFERER'],
            "TYPE" => $targetOrigin
        );
        $res = UrlStatic::getSqlite()->storeEntry('redirections_log', $row);

        if (!$res) {
            throw new RuntimeException("An error occurred");
        }

    }

}
