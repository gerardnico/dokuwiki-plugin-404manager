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
     * Test a redirect to an external Web Site
     *

     */
    public function test_externalRedirect()
    {

        $redirectManager = (new UrlRewrite(UrlStatic::getSqlite()));

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

     */
    public function test_internalRedirectToExistingPage()
    {

        $redirectManager = new UrlRewrite(UrlStatic::getSqlite());

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
     */
    public function testRedirectionsOperations()
    {
        $targetPage = 'testRedirectionsOperations:test';
        saveWikiText($targetPage, 'Test ', 'but without any common name (namespace) in the path');
        idx_addPage($targetPage);

        $redirectManager = new UrlRewrite(UrlStatic::getSqlite());


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
