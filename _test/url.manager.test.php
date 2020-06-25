<?php
/**
 *
 * Test the re
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



    /**
     * Test a redirect to an internal page that was chosen through BestNamePage
     * with a relocation to the same branch (the minimum target Id length)
     * even if there is another page with the same name in an other branch
     */
    public function test_HttpInternalRedirectToBestNamePage()
    {

        // The page path component
        $pathSeparator = ":";
        $secondLevelName = "a_second_level_name";
        $firstLevelName = "a_first_level_name";
        $name = 'redirect_best_page_name';

        // The source id
        $sourceId = $secondLevelName . $pathSeparator . $firstLevelName . $pathSeparator . $name;
        // A page without the second level
        $firstLevelPage = $firstLevelName . $pathSeparator . $name;
        // A page in another branch on the same level
        $secondLevelPage = "otherBranch" . $pathSeparator . $firstLevelName . $pathSeparator . $name;

        $redirectManager = UrlRedirection::get();
        if ($redirectManager->isRedirectionPresent($sourceId)) {
            $redirectManager->deleteRedirection($sourceId);
        }


        // Create the target Pages and add the pages to the index, otherwise, they will not be find by the ft_lookup
        saveWikiText($firstLevelPage, 'REDIRECT Best Page Name Same Branch', 'Test initialization');
        idx_addPage($firstLevelPage);
        saveWikiText($secondLevelPage, 'REDIRECT Best Page Name Other Branch', 'Test initialization');
        idx_addPage($secondLevelPage);


        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        global $conf;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_urlmanager::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSameNamespace'] = 5;

        $request = new TestRequest();
        $response = $request->get(array('id' => $sourceId), '/doku.php');

        // Check the canonical value
        $canonical = $response->queryHTML('link[rel="canonical"]')->attr('href');
        $canonicalPageId = UrlCanonical::toDokuWikiId($canonical);
        $this->assertEquals($firstLevelPage, $canonicalPageId, "The page was redirected");


        $messageBox = $response->queryHTML('.'.action_plugin_404manager_message::REDIRECT_MANAGER_BOX_CLASS)->count();
        $this->assertEquals(1, $messageBox, "The message has fired");


    }

    /**
     * Test a redirect to a namespace start page (that begins with start)
     * It must happens when a page exists within another namespace that is completely not related to the old one.
     */
    public function test_HttpInternalRedirectToNamespaceStartPage()
    {

        global $conf;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_urlmanager::GO_TO_BEST_NAMESPACE;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSameNamespace'] = 5;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WordsSeparator'] = ':';


        $redirectManager = UrlRedirection::get();
        if ($redirectManager->isRedirectionPresent(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE);
        }

        // Set of 3 pages, when a page has an homonym (same page name) but within another completly differents path (the name of the path have nothing in common)
        // the 404 manager must redirect to the start page of the namespace.
        $namespace1 = 'namespace1';
        $namespace2 = 'namespace2';
        $pathSeparator = ":";
        global $conf;
        $sourceId = $namespace1 . $pathSeparator .'redirect_to_namespace_start_page';

        // got a score of 8 ( the same namspace 5 + start page 3)
        $goodTargetId =  $namespace1. $pathSeparator  . $conf['start'];
        // got a score of 4 (The same page name 4)
        $badTargetId =  $namespace2. $pathSeparator  .'redirect_to_namespace_start_page';

        // Create the target Pages and add the pages to the index, otherwise, they will not be find by the ft_lookup
        saveWikiText($badTargetId, 'Page with the same name', 'but without any common name (namespace) in the path');
        idx_addPage($badTargetId);
        saveWikiText($goodTargetId, 'The start page of the 404 page namespace', 'Test initialization');
        idx_addPage($goodTargetId);

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);


        $request = new TestRequest();
        $response = $request->get(array('id' => $sourceId), '/doku.php');

        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);

        $this->assertNull($queryKeys['do'], "The page was only shown");

        // $REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET
        // $REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET
        $this->assertEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET, $queryKeys['id'], "The Id of the source page is the asked page");
        $this->assertNotEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET, $queryKeys['id'], "The Id of the source page is the asked page");

        // 404 Params
        $this->assertEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE, $queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], "The 404 id must be present");
        $this->assertEquals(UrlRedirection::TARGET_ORIGIN_BEST_NAMESPACE, $queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], "The redirect type is known");


    }


}