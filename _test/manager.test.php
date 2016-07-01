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


    public function test_internalRedirectToSearch()
    {

        global $conf;
        $conf ['plugin'][constant_parameters::$PLUGIN_BASE]['ActionReaderFirst'] = 'GoToSearchEngine';

        global $AUTH_ACL;
        $aclReadOnlyFile = constant_parameters::$DIR_RESOURCES.'/acl.auth.read_only.php';
        $AUTH_ACL = file($aclReadOnlyFile);

        $request = new TestRequest();
        $request->get(array('id' => constant_parameters::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID), '/doku.php');
        $request->execute();

        global $QUERY;
        global $ACT;
        $this->assertEquals(str_replace(':', ' ', constant_parameters::$PAGE_DOES_NOT_EXIST_NO_REDIRECTION_ID),$QUERY);
        $this->assertEquals('search', $ACT);


    }

}
