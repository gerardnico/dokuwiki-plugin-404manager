<?php

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
// Needed for the page lookup
//require_once(DOKU_INC . 'inc/fulltext.php');
// Needed to get the redirection manager
// require_once(DOKU_PLUGIN . 'action.php');

require_once(__DIR__ . '/../class/UrlRedirection.php');
require_once(__DIR__ . '/../class/UrlCanonical.php');
require_once(__DIR__ . '/urlmanager.php');
require_once(__DIR__ . '/../class/message.model.php');

/**
 *
 * To show a message after redirection or rewriting
 *
 *
 *
 */
require_once(__DIR__ . '/../class/message.model.php');

class action_plugin_404manager_message extends DokuWiki_Action_Plugin
{

    // Property key
    const ORIGIN_PAGE = '404id';
    const ORIGIN_TYPE = '404type';

    function __construct()
    {
        // enable direct access to language strings
        // ie $this->lang
        $this->setupLocale();
    }

    private static function sessionClose()
    {
        // Close the session
        $result = session_write_close();
        if (!$result) {
            UrlStatic::throwRuntimeException("Failure to write the session");
        }

    }

    private static function sessionStart()
    {
        $sessionStatus = session_status();
        switch ($sessionStatus){
            case PHP_SESSION_DISABLED:
                throw new RuntimeException("Sessions are disabled");
                break;
            case PHP_SESSION_NONE:
                $result = @session_start();
                if(!$result){
                    throw new RuntimeException("The session was not successfully started");
                }
                break;
            case PHP_SESSION_ACTIVE:
                break;
        }
    }

    function register(Doku_Event_Handler $controller)
    {

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
     * Main function; dispatches the visual comment actions
     * @param   $event Doku_Event
     */
    function _displayRedirectMessage(&$event, $param)
    {

        // Message
        $message = new Message404();
        
        if (!defined('NOSESSION')) {
            
            list($pageIdOrigin,$redirectSource) = self::getNotification();


            if ($pageIdOrigin) {

                switch ($redirectSource) {

                    case action_plugin_404manager_urlmanager::TARGET_ORIGIN_DATA_STORE:
                        $message->addContent(sprintf($this->lang['message_redirected_by_redirect'], hsc($pageIdOrigin)));
                        $message->setType(Message404::TYPE_CLASSIC);
                        break;

                    case action_plugin_404manager_urlmanager::TARGET_ORIGIN_START_PAGE:
                        $message->addContent(sprintf($this->lang['message_redirected_to_startpage'], hsc($pageIdOrigin)));
                        $message->setType(Message404::TYPE_WARNING);
                        break;

                    case  action_plugin_404manager_urlmanager::TARGET_ORIGIN_BEST_PAGE_NAME:
                        $message->addContent(sprintf($this->lang['message_redirected_to_bestpagename'], hsc($pageIdOrigin)));
                        $message->setType(Message404::TYPE_WARNING);
                        break;

                    case action_plugin_404manager_urlmanager::TARGET_ORIGIN_BEST_NAMESPACE:
                        $message->addContent(sprintf($this->lang['message_redirected_to_bestnamespace'], hsc($pageIdOrigin)));
                        $message->setType(Message404::TYPE_WARNING);
                        break;

                    case action_plugin_404manager_urlmanager::TARGET_ORIGIN_SEARCH_ENGINE:
                        $message->addContent(sprintf($this->lang['message_redirected_to_searchengine'], hsc($pageIdOrigin)));
                        $message->setType(Message404::TYPE_WARNING);
                        break;

                }

                // Add a list of page with the same name to the message
                // if the redirections is not planned
                if ($redirectSource != action_plugin_404manager_urlmanager::TARGET_ORIGIN_DATA_STORE) {
                    $this->addToMessagePagesWithSameName($message, $pageIdOrigin);
                }

            }

            if ($event->data == 'show' || $event->data == 'edit' || $event->data == 'search') {

                $this->printMessage($message);

            }
        }
    }


    /**
     * Add the page with the same page name but in an other location
     * @param $message
     * @param $pageId
     */
    private
    function addToMessagePagesWithSameName($message, $pageId)
    {

        global $conf;

        $pageName = noNS($pageId);
        if ($this->getConf('ShowPageNameIsNotUnique') == 1 && $pageName <> $conf['start']) {

            //Search same page name
            $pagesWithSameName = ft_pageLookup($pageName);

            if (count($pagesWithSameName) > 0) {

                $message->setType(Message404::TYPE_WARNING);

                // Assign the value to a variable to be able to use the construct .=
                if ($message->getContent() <> '') {
                    $message->addContent('<br/><br/>');
                }
                $message->addContent($this->lang['message_pagename_exist_one']);
                $message->addContent('<ul>');

                $i = 0;
                foreach ($pagesWithSameName as $PageId => $title) {
                    $i++;
                    if ($i > 10) {
                        $message->addContent('<li>' .
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
                    $message->addContent('<li>' .
                        tpl_link(
                            wl($PageId),
                            $title,
                            'class="" rel="nofollow" title="' . $title . '"',
                            $return = true
                        ) . '</li>');
                }
                $message->addContent('</ul>');
            }
        }
    }

    /**
     * Print a message to show that the user was redirected
     * @param $message
     */
    private
    function printMessage($message): void
    {
        if ($message->getContent() <> "") {
            $pluginInfo = $this->getInfo();
            // a class can not start with a number then 404manager is not a valid class name
            $redirectManagerClass = "redirect-manager";

            if ($message->getType() == Message404::TYPE_CLASSIC) {
                ptln('<div class="alert alert-success ' . $redirectManagerClass . '" role="alert">');
            } else {
                ptln('<div class="alert alert-warning ' . $redirectManagerClass . '" role="alert">');
            }

            print $message->getContent();


            print '<div class="managerreference">' . $this->lang['message_come_from'] . ' <a href="' . $pluginInfo['url'] . '" class="urlextern" title="' . $pluginInfo['desc'] . '"  rel="nofollow">' . $pluginInfo['name'] . '</a>.</div>';
            print('</div>');
        }
    }


    /**
     * Set the redirect in a session that will be be read after the redirect
     * in order to show a message to the user
     * @param string $id
     * @param string $redirectSource
     */
    static function notify(string $id, string $redirectSource)
    {
        // Msg via session
        if (!defined('NOSESSION')) {
            //reopen session, store data and close session again
            self::sessionStart();
            $_SESSION[DOKU_COOKIE][self::ORIGIN_PAGE] = $id;
            $_SESSION[DOKU_COOKIE][self::ORIGIN_TYPE] = $redirectSource;
            self::sessionClose();

        }
    }

    /**
     * Return notification data or an empty array
     * @return array - of the source id and of the type of redirect if a redirect has occurs otherwise an empty array
     */
    static function getNotification()
    {
        $returnArray = array();
        if (!defined('NOSESSION')) {

            $pageIdOrigin = null;
            $redirectSource = null;

            // Open session
            self::sessionStart();


            // Read the data and unset
            if(isset($_SESSION[DOKU_COOKIE][self::ORIGIN_PAGE])) {
                $pageIdOrigin = $_SESSION[DOKU_COOKIE][self::ORIGIN_PAGE];
                unset($_SESSION[DOKU_COOKIE][self::ORIGIN_PAGE]);
            }
            if (isset($_SESSION[DOKU_COOKIE][self::ORIGIN_TYPE])) {
                $redirectSource = $_SESSION[DOKU_COOKIE][self::ORIGIN_TYPE];
                unset($_SESSION[DOKU_COOKIE][self::ORIGIN_TYPE]);
            }

            self::sessionClose();


            if ($pageIdOrigin) {
                $returnArray = array($pageIdOrigin, $redirectSource);
            }

        }
        return $returnArray;

    }


}
