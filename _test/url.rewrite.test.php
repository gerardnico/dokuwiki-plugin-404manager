<?php
/**
 * This tests are testing the function that uses the redirect tables
 * that describes rewrite rules
 *
 * ie the {@link UrlRewrite} class
 *
 * @group plugin_404manager
 * @group plugins
 *
 */
require_once(__DIR__ . '/../class/UrlRewrite.php');
require_once(__DIR__ . '/../action/urlmanager.php');
class plugin_404manager_url_rewrite_test extends DokuWikiTest
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
            array(UrlRewrite::DATA_STORE_TYPE_CONF_FILE),
            array(UrlRewrite::DATA_STORE_TYPE_SQLITE)
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

        $redirectManager = UrlRewrite::get()->setDataStoreType($dataStoreType);

        $pageIdRedirected = "ToBeRedirected";
        $externalURL = 'http://gerardnico.com';

        // The redirection should not be present because the test framework create a new database each time
        if ($redirectManager->isRedirectionPresent($pageIdRedirected)) {
            $redirectManager->deleteRedirection($pageIdRedirected);
        }
        $redirectManager->addRedirection($pageIdRedirected, $externalURL);

        $isRedirectionPresent = $redirectManager->isRedirectionPresent($pageIdRedirected);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(true, $isRedirectionPresent,"The redirection is present");
        $redirectionTarget = $redirectManager->getRedirectionTarget($pageIdRedirected);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertNotEquals(false, $redirectionTarget,"The redirection is present - not false");
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($externalURL, $redirectionTarget,"The redirection is present");

        // Read only otherwise you are redirected to the Edit Mode
        global $AUTH_ACL;
        $aclReadOnlyFile = UrlStatic::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageIdRedirected), '/doku.php');

        $locationHeader = $response->getHeader("Location");

        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals("Location: " . $externalURL, $locationHeader, "The page was redirected");

    }


    /**
     * Test a redirect to an internal page that exist
     *
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     */
    public function test_internalRedirectToExistingPage($dataStoreType)
    {

        $redirectManager = UrlRewrite::get()->setDataStoreType($dataStoreType);

        // in the $ID value, the first : is suppressed
        $sourcePageId = "an:page:that:does:not:exist";
        saveWikiText($sourcePageId, "", 'Without content the page is deleted');
        $targetPage = "an:existing:page";
        saveWikiText($targetPage, 'EXPLICIT_REDIRECT_PAGE_TARGET', 'Test initialization');


        // Clean test state
        if ($redirectManager->isRedirectionPresent($sourcePageId)) {
            $redirectManager->deleteRedirection($sourcePageId);
        }
        $redirectManager->addRedirection($sourcePageId, $targetPage);


        // Set to search engine first but because of order of precedence, this should not happens
        $conf ['plugin'][UrlStatic::$PLUGIN_BASE_NAME]['ActionReaderFirst'] = action_plugin_404manager_urlmanager::GO_TO_SEARCH_ENGINE;

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = UrlStatic::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);


        $request = new TestRequest();
        $response = $request->get(array('id' => $sourcePageId), '/doku.php');

        // Check the canonical value
        $canonical = $response->queryHTML('link[rel="canonical"]')->attr('href');
        $canonicalPageId = UrlCanonical::toDokuWikiId($canonical);
        $this->assertEquals($targetPage, $canonicalPageId, "The page was redirected");


        $messageBox = $response->queryHTML('.'.action_plugin_404manager_message::REDIRECT_MANAGER_BOX_CLASS)->count();
        $this->assertEquals(1, $messageBox, "The message has fired");

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
        /** @var UrlRewrite $redirectManager */
        $redirectManager = UrlRewrite::get()
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
        /** @var UrlRewrite $redirectManager */
        $redirectManager = UrlRewrite::get()
            ->setDataStoreType(UrlRewrite::DATA_STORE_TYPE_SQLITE);
        $redirectManager->deleteAllRedirections();
        $filenameMigrated = UrlRewrite::DATA_STORE_CONF_FILE_PATH . '.migrated';
        if (file_exists($filenameMigrated)){
            unlink($filenameMigrated);
        }

        // Create a conf file
        $redirectManager->setDataStoreType(UrlRewrite::DATA_STORE_TYPE_CONF_FILE);
        $redirectManager->deleteAllRedirections();
        $sourcePageIdValidated = "doesNotExistValidateRedirections";
        $redirectManager->addRedirection($sourcePageIdValidated, $targetPage);
        $redirectManager->validateRedirection($sourcePageIdValidated);
        $sourcePageIdNotValidated = "doesNotExistNotValidateRedirections";
        $redirectManager->addRedirection($sourcePageIdNotValidated, $targetPage);

        $count = $redirectManager->countRedirections();
        $this->assertEquals(2, $count, "The number of redirection is 2 in the conf file");

        $this->assertEquals(true, file_exists(UrlRewrite::DATA_STORE_CONF_FILE_PATH), "The file was created");

        // Settings the store will trigger the migration
        $redirectManager->setDataStoreType(UrlRewrite::DATA_STORE_TYPE_SQLITE);

        $count = $redirectManager->countRedirections();
        $this->assertEquals(1, $count, "The number of redirection is 1");

        $this->assertEquals(false, file_exists(UrlRewrite::DATA_STORE_CONF_FILE_PATH), "The file does not exist anymore");
        $this->assertEquals(true, file_exists($filenameMigrated), "The file migrated exist");



    }

    /**
     * Test if an expression is a regular expression pattern
     */
    public function test_expressionIsRegular()
    {

        // Not an expression
        $inputExpression = "Hallo";
        $isRegularExpression = UrlRewrite::isRegularExpression($inputExpression);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(0,$isRegularExpression,"The term (".$inputExpression.") is not a regular expression");

        // A basic expression
        $inputExpression = "/Hallo/";
        $isRegularExpression = UrlRewrite::isRegularExpression($inputExpression);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(true,$isRegularExpression,"The term (".$inputExpression.") is a regular expression");

        // A complicated expression
        $inputExpression = "/(/path1/path2/)(.*)/";
        $isRegularExpression = UrlRewrite::isRegularExpression($inputExpression);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(true,$isRegularExpression,"The term (" . $inputExpression . ") is a regular expression");

    }

}
