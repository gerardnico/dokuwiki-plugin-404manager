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
        $request->get(array('id' => "ThisPageDoesNotExistAtAll"), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");

        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($queryKeys['do'], 'search', "The page was redirected to the search page");
        $this->assertEquals($queryKeys['id'], constant_parameters::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID, "The Id of the source page is the asked page");
        $this->assertNotNull($queryKeys['q'], "The query must be not null");
        $this->assertEquals($queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], constant_parameters::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID, "The 404 id must be present");
        $this->assertEquals($queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], UrlRedirection::TARGET_ORIGIN_SEARCH_ENGINE, "The redirect type is known");


    }

}