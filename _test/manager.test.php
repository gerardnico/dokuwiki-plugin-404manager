<?php
/**
 * Integration Tests for the 404manager plugin through Dokuwiki Request
 *
 * @group plugin_404manager
 * @group plugins
 *
 */
require_once(__DIR__ . '/constant_parameters.php');
require_once(__DIR__ . '/../class/UrlRedirection.php');
require_once(__DIR__ . '/../action/url_manager.php');
class redirect_plugin_404manager_test extends DokuWikiTest
{

    // Needed otherwise the plugin is not enabled
    protected $pluginsEnabled = array('404manager', 'sqlite');


    /**
     * A data provider to create parametrized test
     * @return array
     */
    public function providerDataStoreTypeData()
    {
        return array(
            array(null),
            array(UrlRedirection::DATA_STORE_TYPE_CONF_FILE),
            array(UrlRedirection::DATA_STORE_TYPE_SQLITE)
        );
    }


    /**
     * Test a redirect to an external Web Site
     *
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     * @throws Exception
     */
    public function test_externalRedirect($dataStoreType)
    {

        $redirectManager = UrlRedirection::get()
            ->setDataStoreType($dataStoreType);

        if ($redirectManager->isRedirectionPresent(constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE)) {
            $redirectManager->deleteRedirection(constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE);
        }

        $externalURL = 'http://gerardnico.com';
        $redirectManager->addRedirection(constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE, $externalURL);

        // Read only otherwise you are redirected to the Edit Mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE), '/doku.php');
        $response = $request->execute();

        $locationHeader = $response->getHeader("Location");

        $this->assertEquals("Location: " . $externalURL, $locationHeader, "The page was redirected");

    }




    /**
     * Test a redirect to an internal page that does not exist
     * Where a actionReaderFirst is search
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function test_goToSearchEngineBasic($dataStoreType)
    {

        $conf ['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_url::GO_TO_SEARCH_ENGINE;

        $redirectManager = UrlRedirection::get()->setDataStoreType($dataStoreType);
        if ($redirectManager->isRedirectionPresent(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE);
        }
        $redirectManager->addRedirection(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE, constant_parameters::$PAGE_DOES_NOT_EXIST_ID);

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);
        $this->assertEquals($queryKeys['do'], 'search', "The page was redirected to the search page");
        $this->assertEquals($queryKeys['id'], constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE, "The Id of the source page is the asked page");
        $this->assertNotNull($queryKeys['q'], "The query must be not null");
        $this->assertEquals($queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE, "The 404 id must be present");
        $this->assertEquals($queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], UrlRedirection::TARGET_ORIGIN_SEARCH_ENGINE, "The redirect type is known");


    }

    /**
     * Test a redirect to an internal page that exist
     *
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function test_internalRedirectToExistingPage($dataStoreType)
    {


        $redirectManager = UrlRedirection::get()->setDataStoreType($dataStoreType);
        if ($redirectManager->isRedirectionPresent(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE);
        }
        $redirectManager->addRedirection(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE, constant_parameters::$EXPLICIT_REDIRECT_PAGE_TARGET);

        // Create the target Page
        saveWikiText(constant_parameters::$EXPLICIT_REDIRECT_PAGE_TARGET, 'EXPLICIT_REDIRECT_PAGE_TARGET', 'Test initialization');

        $conf ['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_url::GO_TO_SEARCH_ENGINE;

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);


        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);
        $this->assertNull($queryKeys['do'], "The page is only shown");

        $this->assertEquals(constant_parameters::$EXPLICIT_REDIRECT_PAGE_TARGET, $queryKeys['id'], "The Id of the page is the target page");

        $this->assertEquals(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE, $queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], "The 404 id must be present");
        $this->assertEquals(UrlRedirection::TARGET_ORIGIN_DATA_STORE, $queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], "The redirect type is known");


    }

    /**
     * Test a redirect to an internal page that was chosen through BestNamePage
     * with a relocation in the same branch
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function test_internalRedirectToBestNamePageSameBranch($dataStoreType)
    {

        $redirectManager = UrlRedirection::get()->setDataStoreType($dataStoreType);
        if ($redirectManager->isRedirectionPresent(constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE);
        }

        // Create the target Page
        saveWikiText(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH, 'REDIRECT Best Page Name Same Branch', 'Test initialization');
        // Add the page to the index, otherwise, it will not be find by the ft_lookup
        idx_addPage(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH);


        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        global $conf;
        $conf ['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_url::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSameNamespace'] = 5;

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);
        $this->assertNull($queryKeys['do'], "The page has no action than show");
        $this->assertEquals(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH, $queryKeys['id'], "The Id of the source page is the asked page");
        $this->assertEquals(constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE, $queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], "The 404 id must be present");
        $this->assertEquals(UrlRedirection::TARGET_ORIGIN_BEST_PAGE_NAME, $queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], "The redirect type is known");


    }

    /**
     * Test a redirect to an internal page that was chosen through BestNamePage
     * with a relocation to the same branch (the minimum target Id length)
     * even if there is another page with the same name in an other branch
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function test_internalRedirectToBestNamePageOtherBranch($dataStoreType)
    {


        $redirectManager = UrlRedirection::get()->setDataStoreType($dataStoreType);
        if ($redirectManager->isRedirectionPresent(constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE);
        }


        // Create the target Pages and add the pages to the index, otherwise, they will not be find by the ft_lookup
        saveWikiText(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH, 'REDIRECT Best Page Name Same Branch', 'Test initialization');
        idx_addPage(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH);
        saveWikiText(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_OTHER_BRANCH, 'REDIRECT Best Page Name Other Branch', 'Test initialization');
        idx_addPage(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_OTHER_BRANCH);


        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        global $conf;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_url::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSameNamespace'] = 5;

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);

        $this->assertNull($queryKeys['do'], "The is only shown");
        $this->assertEquals(constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH, $queryKeys['id'], "The Id of the source page is the asked page");
        $this->assertEquals(constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE, $queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], "The 404 id must be present");
        $this->assertEquals(UrlRedirection::TARGET_ORIGIN_BEST_PAGE_NAME, $queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], "The redirect type is known");


    }

    /**
     * Test a redirect to a namespace start page (that begins with start)
     * It must happens when a page exists within another namespace that is completely not related to the old one.
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function test_internalRedirectToNamespaceStartPage($dataStoreType)
    {


        $redirectManager = UrlRedirection::get()->setDataStoreType($dataStoreType);
        if ($redirectManager->isRedirectionPresent(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE);
        }

        // Create the target Pages and add the pages to the index, otherwise, they will not be find by the ft_lookup
        saveWikiText(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET, 'Page with the same name', 'but without any common name (namespace) in the path');
        idx_addPage(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET);
        saveWikiText(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET, 'The start page of the 404 page namespace', 'Test initialization');
        idx_addPage(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET);

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        global $conf;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_url::GO_TO_BEST_NAMESPACE;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSameNamespace'] = 5;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WordsSeparator'] = ':';

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);

        $this->assertNull($queryKeys['do'], "The page was only shown");

        // $REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET got a score of 9 (The base namespace 5 + same page name 4)
        // $REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET got a score of 13 (The base namespace 5 + the same namspace 5 + start page 3)
        $this->assertEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET, $queryKeys['id'], "The Id of the source page is the asked page");
        $this->assertNotEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET, $queryKeys['id'], "The Id of the source page is the asked page");

        // 404 Params
        $this->assertEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE, $queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], "The 404 id must be present");
        $this->assertEquals(UrlRedirection::TARGET_ORIGIN_BEST_NAMESPACE, $queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], "The redirect type is known");


    }

    /**
     * Test a redirect to a namespace start page (ie the start page has the name of its parent, not start as in the conf['start'] parameters )
     * It must happens when a page exists within another namespace that is completely not related to the old one.
     *
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     * @throws Exception
     */
    public function test_internalRedirectToNamespaceStartPageWithParentName($dataStoreType)
    {


        $redirectManager = UrlRedirection::get()->setDataStoreType($dataStoreType);
        if ($redirectManager->isRedirectionPresent(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_SOURCE);
        }


        // Create the target Pages and add the pages to the index, otherwise, they will not be find by the ft_lookup
        saveWikiText(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_BAD_TARGET, 'Page with the same name', 'but without any common name (namespace) in the path');
        idx_addPage(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_BAD_TARGET);
        saveWikiText(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_GOOD_TARGET, 'The start page that has the same name that it\'s parent', 'Test initialization');
        idx_addPage(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_GOOD_TARGET);

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        global $conf;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_url::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WeightFactorForSameNamespace'] = 5;
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['WordsSeparator'] = ':';
        $conf['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ShowPageNameIsNotUnique'] = 1;

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_SOURCE), '/doku.php');

        $response = $request->execute();

        $locationHeader = $response->getHeader("Location");
        $components = parse_url($locationHeader);
        parse_str($components['query'], $queryKeys);
        $this->assertNull($queryKeys['do'], "The page is only shown");

        // 404manager:ns_branch2:redirect_to_namespace_start_page = score 9
        // 404manager:ns_branch3:ns_branch3
        // $REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET got a score of 9 (The base namespace 5 + same page name 4)
        // $REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET got a score of 13 (The base namespace 5 + the same namespace 5 + start page 3)
        $this->assertEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_GOOD_TARGET, $queryKeys['id'], "The Id is the target page");
        $this->assertNotEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_BAD_TARGET, $queryKeys['id'], "The Id is not the source page");

        $this->assertEquals(constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_SOURCE, $queryKeys[UrlRedirection::QUERY_STRING_ORIGIN_PAGE], "The 404 id must be present");
        $this->assertEquals(UrlRedirection::TARGET_ORIGIN_BEST_PAGE_NAME, $queryKeys[UrlRedirection::QUERY_STRING_REDIR_TYPE], "The redirect type is known");


    }

    /**
     * Test basic redirections operations
     *
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function testRedirectionsOperations($dataStoreType)
    {
        $targetPage = 'testRedirectionsOperations:test';
        saveWikiText($targetPage, 'Test ', 'but without any common name (namespace) in the path');
        idx_addPage($targetPage);
        /** @var UrlRedirection $redirectManager */
        $redirectManager = UrlRedirection::get()
            ->setDataStoreType($dataStoreType);


        $redirectManager->deleteAllRedirections();
        $count = $redirectManager->countRedirections();
        $this->assertEquals(0, $count, "The number of redirection is zero");
        $sourcePageId = "source";
        $redirectManager->addRedirection($sourcePageId, $targetPage);
        $count = $redirectManager->countRedirections();
        $this->assertEquals(1, $count, "The number of redirection is one");
        $bool = $redirectManager->isRedirectionPresent($sourcePageId);
        $this->assertEquals(true, $bool, "The redirection is present");


    }


    /**
     * Test the migration of a data store from file to Sqlite
     */
    public function testMigrateDataStore()
    {

        $targetPage = 'testMigrateDataStore:test';
        saveWikiText($targetPage, 'Test ', 'test summary');
        idx_addPage($targetPage);

        // Cleaning
        /** @var UrlRedirection $redirectManager */
        $redirectManager = UrlRedirection::get()
            ->setDataStoreType(UrlRedirection::DATA_STORE_TYPE_SQLITE);
        $redirectManager->deleteAllRedirections();
        $filenameMigrated = UrlRedirection::DATA_STORE_CONF_FILE_PATH . '.migrated';
        if (file_exists($filenameMigrated)){
            unlink($filenameMigrated);
        }

        // Create a conf file
        $redirectManager->setDataStoreType(UrlRedirection::DATA_STORE_TYPE_CONF_FILE);
        $redirectManager->deleteAllRedirections();
        $sourcePageIdValidated = "doesNotExistValidateRedirections";
        $redirectManager->addRedirection($sourcePageIdValidated, $targetPage);
        $redirectManager->validateRedirection($sourcePageIdValidated);
        $sourcePageIdNotValidated = "doesNotExistNotValidateRedirections";
        $redirectManager->addRedirection($sourcePageIdNotValidated, $targetPage);

        $count = $redirectManager->countRedirections();
        $this->assertEquals(2, $count, "The number of redirection is 2 in the conf file");

        $this->assertEquals(true, file_exists(UrlRedirection::DATA_STORE_CONF_FILE_PATH), "The file was created");

        // Settings the store will trigger the migration
        $redirectManager->setDataStoreType(UrlRedirection::DATA_STORE_TYPE_SQLITE);

        $count = $redirectManager->countRedirections();
        $this->assertEquals(1, $count, "The number of redirection is 1");

        $this->assertEquals(false, file_exists(UrlRedirection::DATA_STORE_CONF_FILE_PATH), "The file does not exist anymore");
        $this->assertEquals(true, file_exists($filenameMigrated), "The file migrated exist");



    }


}
