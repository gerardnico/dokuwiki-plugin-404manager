<?php
/**
 *
 * Manager test
 *
 * @group plugin_404manager
 * @group plugins
 *
 */
require_once(__DIR__ . '/../class/UrlStatic.php');
require_once(__DIR__ . '/../action/urlmanager.php');
require_once(__DIR__ . '/../action/message.php');
require_once(__DIR__ . '/constant_parameters.php');
class manager_plugin_404manager_test extends DokuWikiTest
{

    // Needed otherwise the plugin is not enabled
    protected $pluginsEnabled = array('404manager', 'sqlite');

    /**
     * Test a redirect to the search engine
     */
    public function test_HttpRedirectToSearchEngine()
    {


        global $conf;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_urlmanager::GO_TO_SEARCH_ENGINE;

        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        $request = new TestRequest();
        $id = "This:Page:Does:Not:Exist:At:All";
        $response = $request->get(array('id' => $id), '/doku.php');


        $locationHeader = $response->getHeader("Location");

        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals('search', $queryKeys['do'] , "The page was redirected to the search page");
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(strtolower($id), $queryKeys['id'], "The Id of the source page is the asked page");
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(str_replace(":"," ",strtolower($id)),$queryKeys['q'], "The query must be not null");

        /**
         * The  {@link TestRequest::get} function reset the $_SESSION to an empty array
         * We can't test the session data then on a automated way (visual to see the message)
         */

    }

}