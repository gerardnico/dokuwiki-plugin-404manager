<?php
/**
 * DokuWiki function tests for the 404manager plugin
 *
 * @group plugin_404manager
 * @group plugins
 */
require_once(__DIR__ . '/constant_parameters.php');
require_once(__DIR__ . '/../action.php');

class manager_plugin_404manager_test extends DokuWikiTest
{

    // Needed otherwise the plugin is not enabled
    protected $pluginsEnabled = array('404manager');


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
     * Test a redirect to the search engine
     */
    public function test_internalRedirectToSearchEngine()
    {

        global $conf;
        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_SEARCH_ENGINE;

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
     * Test a redirect to an internal page that doesn't exist
     */
    public function test_internalRedirectToNonExistingPage()
    {

        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_SEARCH_ENGINE;

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

        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_SEARCH_ENGINE;

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

    /**
     * Test a redirect to an internal page that was chosen through BestNamePage
     * with a relocation in the same branch
     */
    public function test_internalRedirectToBestNamePageSameBranch()
    {


        $redirectManager = new admin_plugin_404manager();
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

        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSameNamespace'] = 5;

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $this->assertNotEquals(0,count($locationHeader),"Their must be an redirection header.");
        // assertContains is for an array
        $this->assertRegexp('/'.constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH.'/',$locationHeader,"The page was redirected");


    }

    /**
     * Test a redirect to an internal page that was chosen through BestNamePage
     * with a relocation to the same branch (the minimum target Id length)
     * even if there is another page with the same name in an other branch
     */
    public function test_internalRedirectToBestNamePageOtherBranch()
    {


        $redirectManager = new admin_plugin_404manager();
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

        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSameNamespace'] = 5;

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_BEST_PAGE_NAME_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $this->assertNotEquals(0,count($locationHeader),"Their must be an redirection header.");
        // assertContains is for an array
        $this->assertRegexp('/'.constant_parameters::$REDIRECT_BEST_PAGE_NAME_TARGET_SAME_BRANCH.'/',$locationHeader,"The page was redirected");


    }

    /**
     * Test a redirect to a namespace start page (that begins with start)
     * It must happens when a page exists within another namespace that is completely not related to the old one.
     *
     */
    public function test_internalRedirectToNamespaceStartPage()
    {


        $redirectManager = new admin_plugin_404manager();
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

        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSameNamespace'] = 5;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WordsSeparator'] = ':';

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $this->assertNotEquals(0,count($locationHeader),"Their must be minimal a redirection header.");

        // $REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET got a score of 9 (The base namespace 5 + same page name 4)
        // $REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET got a score of 13 (The base namespace 5 + the same namspace 5 + start page 3)
        $this->assertNotRegexp('/'.constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET.'/',$locationHeader,"The page was not redirected to the bad namespace");
        $this->assertRegexp('/'.constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET.'/',$locationHeader,"The page was redirected to the start page");


    }

    /**
     * Test a redirect to a namespace start page (ie the start page has the name of its parent, not start as in the conf['start'] parameters )
     * It must happens when a page exists within another namespace that is completely not related to the old one.
     *
     */
    public function test_internalRedirectToNamespaceStartPageWithParentName()
    {


        $redirectManager = new admin_plugin_404manager();
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

        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = action_plugin_404manager::GO_TO_BEST_PAGE_NAME;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSamePageName'] = 4;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForStartPage'] = 3;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WeightFactorForSameNamespace'] = 5;
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['WordsSeparator'] = ':';
        $conf['plugin'][constant_parameters::$PLUGIN_BASE]['ShowPageNameIsNotUnique'] = 1;

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $this->assertNotEquals(0,count($locationHeader),"Their must be minimal a redirection header.");

        // $REDIRECT_TO_NAMESPACE_START_PAGE_BAD_TARGET got a score of 9 (The base namespace 5 + same page name 4)
        // $REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET got a score of 13 (The base namespace 5 + the same namspace 5 + start page 3)
        $this->assertNotRegexp('/'.constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_BAD_TARGET.'/',$locationHeader,"The page was not redirected to the bad namespace");
        $this->assertRegexp('/'.constant_parameters::$REDIRECT_TO_NAMESPACE_START_PAGE_PARENT_GOOD_TARGET.'/',$locationHeader,"The page was redirected to the start page");

    }


    public function test_internalRedirectWithPattern()
    {

        $redirectManager = new admin_plugin_404manager();
        if ($redirectManager->isRedirectionPresent(constant_parameters::$REDIRECT_WITH_PATTERN_DIRECTLY_SOURCE)) {
            $redirectManager->deleteRedirection(constant_parameters::$REDIRECT_WITH_PATTERN_DIRECTLY_SOURCE);
        }

        // Create the target Pages and add the pages to the index, otherwise, they will not be find by the ft_lookup
        saveWikiText(constant_parameters::$REDIRECT_WITH_PATTERN_DIRECTLY_TARGET, 'Target for pattern replacement', 'Text');
        idx_addPage(constant_parameters::$REDIRECT_WITH_PATTERN_DIRECTLY_TARGET);

        // Read only otherwise, you go in edit mode
        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES . '/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);


        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$REDIRECT_WITH_PATTERN_DIRECTLY_SOURCE), '/doku.php');
        $response = $request->execute();


        $locationHeader = $response->getHeader("Location");
        $this->assertNotEquals(0,count($locationHeader),"Their must be minimal a redirection header.");

        // $REDIRECT_TO_NAMESPACE_START_PAGE_GOOD_TARGET got a score of 13 (The base namespace 5 + the same namspace 5 + start page 3)
        $this->assertRegexp('/'.constant_parameters::$REDIRECT_WITH_PATTERN_DIRECTLY_TARGET.'/',$locationHeader,"The page was redirected to the target page");

    }

}
