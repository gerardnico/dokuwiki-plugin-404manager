<?php
/**
 * DokuWiki function tests for the 404manager plugin
 *
 * @group plugin_404manager
 * @group plugins
 */
require_once(__DIR__.'/constant_parameters.php');
class manager_plugin_404manager_test extends DokuWikiTest {


    public function test_redirect()
    {

        $pageId = constant_parameters::MANAGER404_NAMESPACE. constant_parameters::PATH_SEPARATOR.'pageDoesntexist';
        $this->assertFalse(page_exists($pageId));

        $request = new TestRequest();
        $request->get(array('id' => $pageId), '/doku.php');
        $request->execute();

        global $INFO;
        $this->assertFalse($INFO['exists']);


    }
}
