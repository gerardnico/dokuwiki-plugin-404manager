<?php

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_404manager extends DokuWiki_Action_Plugin
{

    var $message = '';
    var $messageType = 'Classic';


    var $targetId = '';
    var $sourceId = '';

    // The redirect source
    const REDIRECT_SOURCE_REDIRECT = 'redirect';
    const REDIRECT_SOURCE_START_PAGE = 'startPage';
    const REDIRECT_SOURCE_BEST_PAGE_NAME = 'bestPageName';
    const REDIRECT_SOURCE_BEST_NAMESPACE = 'bestNamespace';
    const GO_TO_SEARCH_ENGINE = 'GoToSearchEngine';
    const GO_TO_BEST_NAMESPACE = 'GoToBestNamespace';
    const GO_TO_BEST_PAGE_NAME = 'GoToBestPageName';
    const GO_TO_NS_START_PAGE = 'GoToNsStartPage';
    const NOTHING = 'Nothing';

    /**
     * The object that holds all management function
     * @var admin_plugin_404manager
     */
    var $redirectManager;

    /*
     * The event scope is made object
     */
    var $event;

    // The action name is used as check / communication channel between function hooks.
    // It will comes in the global $ACT variable
    const ACTION_NAME = '404manager';

    function action_plugin_404manager()
    {
        // enable direct access to language strings
        $this->setupLocale();
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
     * Verify it their is a 404
     * Code comes from <a href="https://github.com/splitbrain/dokuwiki-plugin-notfound/blob/master/action.php">Not Found Plugin</a>
     * @param $event Doku_Event
     * @param $param
     * @return bool
     */
    function _handle404(&$event, $param)
    {

        global $ACT;
        if ($ACT != 'show') return false;

        global $INFO;
        if ($INFO['exists']) return false;

        // We instantiate the redirect manager because it's use overall
        // it holds the function and methods
        require_once(dirname(__FILE__) . '/admin.php');
        $this->redirectManager = new admin_plugin_404manager();
        // Event is also used in some subfunction, we make it them object scope
        $this->event = $event;


        // Global variable needed in the process
        global $ID;
        global $conf;
        $targetPage = $this->redirectManager->getTargetResource($ID);

        // If this is an external redirect
        if ($this->redirectManager->isValidURL($targetPage) && $targetPage) {

            $this->redirectToExternalPage($targetPage);
            return true;

        }

        // Internal redirect

        // Their is one action for a writer:
        //   * edit mode direct
        // If the user is a writer (It have the right to edit).
        If ($this->userCanWrite() && $this->getConf('GoToEditMode') == 1) {

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

            $this->redirectToDokuwikiPage($targetPage, self::REDIRECT_SOURCE_REDIRECT);
            return true;

        }

        // We are still a reader, the redirection does not exist the user not allowed to edit the page (public of other)
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
                    return;
                    break;

                case self::GO_TO_NS_START_PAGE:

                    // Start page with the conf['start'] parameter
                    $startPage = getNS($ID) . ':' . $conf['start'];
                    if (page_exists($startPage)) {
                        $this->redirectToDokuwikiPage($startPage, self::REDIRECT_SOURCE_START_PAGE);
                        return;
                    }
                    // Start page with the same name than the namespace
                    $startPage = getNS($ID) . ':' . curNS($ID);
                    if (page_exists($startPage)) {
                        $this->redirectToDokuwikiPage($startPage, self::REDIRECT_SOURCE_START_PAGE);
                        return;
                    }
                    break;

                case self::GO_TO_BEST_PAGE_NAME:

                    $scorePageName = 0;
                    $bestPageId = null;

                    //Search same page name
                    require_once(DOKU_INC . 'inc/fulltext.php');
                    $pageName = noNS($ID);
                    $pagesWithSameName = ft_pageLookup($pageName);

                    //Get Score from a page
                    if (count($pagesWithSameName) > 0) {

                        // Search same namespace in the page found than in the Id page asked.
                        $bestNbWordFound = 0;
                        $bestPageId = null;

                        $pageSourceIdToExplode = str_replace('_', ':', $ID);
                        $wordsInPageSourceId = explode(':', $pageSourceIdToExplode);
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
                        $scorePageName = $this->getConf('WeightFactorForSamePageName') + $bestNbWordFound * $this->getConf('WeightFactorForSameNamespace');
                    }

                    // Get Score from a Namespace
                    list($bestNamespaceId, $Vl_ScoreNamespace) = explode(" ", $this->getBestNamespace($ID));

                    // Compare the two score
                    if ($scorePageName > 0 or $Vl_ScoreNamespace > 0) {
                        if ($scorePageName > $Vl_ScoreNamespace) {
                            $this->redirectToDokuwikiPage($bestPageId, self::REDIRECT_SOURCE_BEST_PAGE_NAME);
                        } else {
                            $this->redirectToDokuwikiPage($bestNamespaceId, self::REDIRECT_SOURCE_BEST_PAGE_NAME);
                        }
                        return;
                    }
                    break;

                case self::GO_TO_BEST_NAMESPACE:

                    list($bestNamespaceId, $score) = explode(" ", $this->getBestNamespace($ID));

                    if ($score > 0) {
                        $this->redirectToDokuwikiPage($bestNamespaceId, self::REDIRECT_SOURCE_BEST_NAMESPACE);
                        return true;
                    }
                    break;

                case self::GO_TO_SEARCH_ENGINE:

                    //do fulltext search
                    $this->message = sprintf($this->lang['message_redirected_to_searchengine'], hsc($ID));
                    $this->messageType = 'Warning';

                    global $QUERY;
                    $QUERY = str_replace(':', ' ', $ID);
                    $ACT = 'search';

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

        if ($event->data == 'show' || $event->data == 'edit' || $event->data == 'search') {


            // load left over messages from redirect
            // See method redirectToDokuwikiPage
            if (isset($_SESSION[DOKU_COOKIE]['404manager_msg'])) {
                $msg = $_SESSION[DOKU_COOKIE]['404manager_msg'];
                $this->message = $msg['content'];
                $this->messageType = $msg['type'];
                // Session start seems important if we want to unset the variable
                @session_start();
                unset($_SESSION[DOKU_COOKIE]['404manager_msg']);

            }

            if ($this->message) {

                $pluginInfo = $this->getInfo();
                // a class can not start with a number then 404manager is not a valid class name
                $redirectManagerClass = "redirect-manager";

                if ($this->messageType == 'Classic') {
                    ptln('<div class="alert alert-success ' . $redirectManagerClass . '" role="alert">');
                } else {
                    ptln('<div class="alert alert-warning ' . $redirectManagerClass . '" role="alert">');
                }
                print $this->message;


                print '<div class="managerreference">' . $this->lang['message_come_from'] . ' <a href="' . $pluginInfo['url'] . '" class="urlextern" title="' . $pluginInfo['desc'] . '"  rel="nofollow">' . $pluginInfo['name'] . '</a>.</div>';
                print('</div>');

            }

        }
    }


    /**
     * getBestNamespace
     * Return a list with 'BestNamespaceId Score'
     */
    function getBestNamespace($id)
    {

        global $conf;

        $Vl_ListNamespaceId = ft_pageLookup($conf['start']);

        $Vl_BestNbWordFound = 0;
        $Vl_BestNamespaceId = '';

        $Vl_IdToExplode = str_replace('_', ':', $id);
        $Vl_WordsInId = explode(':', $Vl_IdToExplode);

        foreach ($Vl_ListNamespaceId as $Vl_NamespaceId) {
            $Vl_NbWordFound = 0;
            foreach ($Vl_WordsInId as $Vl_Word) {
                if (strlen($Vl_Word) > 2) {
                    $Vl_NbWordFound = $Vl_NbWordFound + substr_count($Vl_NamespaceId, $Vl_Word);
                }
            }
            if ($Vl_NbWordFound > $Vl_BestNbWordFound) {
                //Take only the smallest namespace
                if (strlen($Vl_NamespaceId) < strlen($Vl_BestNamespaceId) or $Vl_NbWordFound > $Vl_BestNbWordFound) {
                    $Vl_BestNbWordFound = $Vl_NbWordFound;
                    $Vl_BestNamespaceId = $Vl_NamespaceId;
                }
            }
        }
        $Vl_WfForStartPage = $this->getConf('WeightFactorForStartPage');
        $Vl_WfForSameNamespace = $this->getConf('WeightFactorForSameNamespace');
        if ($Vl_BestNbWordFound > 0) {
            $Vl_Score = $Vl_BestNbWordFound * $Vl_WfForSameNamespace + $Vl_WfForStartPage;
        } else {
            $Vl_Score = 0;
        }
        return $Vl_BestNamespaceId . " " . $Vl_Score;
    }

    /**
     * @param $event
     */
    private function gotToEditMode(&$event)
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
                $this->message = $this->lang['message_redirected_to_edit_mode'];
                $this->messageType = 'Classic';
            }

            // If Param show page name unique and it's not a start page
            if ($this->getConf('ShowPageNameIsNotUnique') == 1 && $pageName <> $conf['start']) {

                //Search same page name
                require_once(DOKU_INC . 'inc/fulltext.php');
                $pagesWithSameName = ft_pageLookup($pageName);

                if (count($pagesWithSameName) > 0) {
                    $this->messageType = 'Warning';
                    if ($this->message <> '') {
                        $this->message .= '<br/><br/>';
                    }
                    $this->message .= $this->lang['message_pagename_exist_one'];
                    $this->message .= '<ul>';
                    foreach ($pagesWithSameName as $PageId => $title) {
                        if ($title == null) {
                            $title = $PageId;
                        }
                        $this->message .= '<li>' .
                            tpl_link(
                                wl($PageId),
                                $title,
                                'class="" rel="nofollow" title="' . $title . '"',
                                $return = true
                            ) . '</li>';
                    }
                    $this->message .= '</ul>';
                }
            }
        }


    }

    /**
     * Return if the user has the right/permission to create/write an article
     * @return bool
     */
    private function userCanWrite()
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
     * Redirect to an internal page, no external resources
     * @param $targetPage the target page id or an URL
     * @param string|the $redirectSource the source of the redirect
     */
    private function redirectToDokuwikiPage($targetPage, $redirectSource = 'Not Known')
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

        switch ($redirectSource) {

            case self::REDIRECT_SOURCE_REDIRECT:
                // This is an internal ID
                if ($this->redirectManager->getIsValidate($ID) == 'N') {
                    $this->message = sprintf($this->lang['message_redirected_by_redirect'], hsc($ID));
                    $this->messageType = 'Warning';
                };
                break;

            case self::REDIRECT_SOURCE_START_PAGE:
                $this->message = sprintf($this->lang['message_redirected_to_startpage'], hsc($ID));
                $this->messageType = 'Warning';
                break;

            case  self::REDIRECT_SOURCE_BEST_PAGE_NAME:
                $this->message = sprintf($this->lang['message_redirected_to_bestpagename'], hsc($ID));
                $this->messageType = 'Warning';
                break;

            case self::REDIRECT_SOURCE_BEST_NAMESPACE:
                $this->message = sprintf($this->lang['message_redirected_to_bestnamespace'], hsc($ID));
                $this->messageType = 'Warning';
                break;

        }

        // Add or update the redirections
        if ($this->redirectManager->isRedirectionPresent($ID)) {
            $this->redirectManager->updateRedirectionMetaData($ID);
        } else {
            $this->redirectManager->addRedirection($ID, $targetPage);
        }

        // Keep the message in session for display
        //reopen session, store data and close session again
        @session_start();
        $msg['content'] = $this->message;
        $msg['type'] = $this->messageType;
        $_SESSION[DOKU_COOKIE]['404manager_msg'] = $msg;
        // always close the session
        session_write_close();

        $link = explode('#', $targetPage, 2);
        // TODO: Status code
        // header('HTTP/1.1 301 Moved Permanently');
        send_redirect(wl($link[0], '', true) . '#' . rawurlencode($link[1]));

        if (defined('DOKU_UNITTEST')) return; // no exits during unit tests
        exit();

    }

    /**
     * Redirect to an internal page, no external resources
     * @param $url the target page id or an URL
     * @param string|the $redirectSource the source of the redirect
     */
    private function redirectToExternalPage($url)
    {

        global $ID;

        // No message can be shown because this is an external URL

        // Update the redirections
        $this->redirectManager->updateRedirectionMetaData($ID);

        // TODO: Status code
        // header('HTTP/1.1 301 Moved Permanently');
        send_redirect($url);

        if (defined('DOKU_UNITTEST')) return; // no exits during unit tests
        exit();

    }


}
