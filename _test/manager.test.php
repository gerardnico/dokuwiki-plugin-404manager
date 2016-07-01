<?php
/**
 * DokuWiki function tests for the 404manager plugin
 *
 * @group plugin_404manager
 * @group plugins
 */
require_once(__DIR__ . '/constant_parameters.php');

class manager_plugin_404manager_test extends DokuWikiTest
{

    // Needed otherwise the plugin is not enabled
    protected $pluginsEnabled = array('404manager');

    /**
     * Test a redirect to the search engine
     */
    public function test_internalRedirectToSearchEngine()
    {

        global $conf;
        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = 'GoToSearchEngine';

        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID), '/doku.php');
        $request->execute();

        global $QUERY;
        global $ACT;
        $this->assertEquals(str_replace(':', ' ', constant_parameters::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID), $QUERY);
        $this->assertEquals('search', $ACT);


    }

    /**
     * Test a redirect to an external Web Site
     */
    public function test_externalRedirect()
    {

        $redirectManager = new admin_plugin_404manager();
        if ($redirectManager->isRedirectionPresent(constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE)) {
            $redirectManager->deleteRedirection(constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE);
        }
        $externalURL = 'http://gerardnico.com';
        $redirectManager->addRedirection(constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE, $externalURL);


        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$PAGE_REDIRECTED_TO_EXTERNAL_WEBSITE), '/doku.php');
        $response = $request->execute();

        $locationHeader = $response->getHeader("Location");
        $this->assertEquals("Location: ".$externalURL,$locationHeader,"The page was redirected");


    }

    /**
     * Test a redirect to an internal page that doesn't exist
     */
    public function test_internalRedirectToNonExistingPage()
    {

        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = 'GoToSearchEngine';

        $redirectManager = new admin_plugin_404manager();
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
        $request->execute();

        global $ACT;
        $this->assertEquals('search', $ACT);


    }

    /**
     * Test a redirect to an internal page that exist
     */
    public function test_internalRedirectToExistingPage()
    {


        $redirectManager = new admin_plugin_404manager();
        if ($redirectManager->isRedirectionPresent(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE);
        }
        $redirectManager->addRedirection(constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE, constant_parameters::$EXPLICIT_REDIRECT_PAGE_TARGET);

        // Create the target Page
        saveWikiText(constant_parameters::$EXPLICIT_REDIRECT_PAGE_TARGET, 'EXPLICIT_REDIRECT_PAGE_TARGET', 'Test initialization');

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);



        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$EXPLICIT_REDIRECT_PAGE_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        // assertContains is for an array
        $this->assertRegexp('/'.constant_parameters::$EXPLICIT_REDIRECT_PAGE_TARGET.'/',$locationHeader,"The page was redirected");


    }

}
