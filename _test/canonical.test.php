<?php
/**
 * Integration Tests for the 404manager plugin through Dokuwiki Request
 *
 * @group plugin_404manager
 * @group plugins
 *
 */
require_once(__DIR__ . '/constant_parameters.php');
require_once(__DIR__ . '/../action.php');

class canonical_plugin_404manager_test extends DokuWikiTest
{

    // Needed otherwise the plugin is not enabled
    protected $pluginsEnabled = array('404manager', 'sqlite', 'webcomponent');


    /**
     * A data provider to create parametrized test
     * @return array
     */
    public function providerDataStoreTypeData()
    {
        return array(
            array(null),
            array(admin_plugin_404manager::DATA_STORE_TYPE_CONF_FILE),
            array(admin_plugin_404manager::DATA_STORE_TYPE_SQLITE)
        );
    }


    /**
     * Test a redirect to an external Web Site
     *
     * @dataProvider providerDataStoreTypeData
     * @param $dataStoreType
     * @throws Exception
     */
    public function test_canonical($dataStoreType)
    {

        $redirectManager = admin_plugin_404manager::get()
            ->setDataStoreType($dataStoreType);

        // Data
        $pageId = "web:javascript:variable";
        $newPageId="lang:javascript:variable";
        $pageCanonical = "javascript:variable";


        // Reproducible test
        if ($redirectManager->pageExist($pageId)) {
            $redirectManager->deletePage($pageId);
        }

        if ($redirectManager->pageExist($newPageId)) {
            $redirectManager->deletePage($newPageId);
        }

        if ($dataStoreType == admin_plugin_404manager::DATA_STORE_TYPE_SQLITE) {
            $this->assertEquals($redirectManager->pageExist($pageId), 0, "The page was deleted");
        }

        // Save a page
        $text = DOKU_LF . '---json' . DOKU_LF
            . '{' . DOKU_LF
            . '   "canonical":"' . $pageCanonical . '"' . DOKU_LF
            . '}' . DOKU_LF
            . '---' . DOKU_LF
            . 'Content';
        saveWikiText($pageId, $text, 'Page creation');

        // In a request
        $request = new TestRequest();
        $request->get(array('id' => $pageId), '/doku.php');
        $request->execute();

        if ($dataStoreType == admin_plugin_404manager::DATA_STORE_TYPE_SQLITE) {
            $this->assertEquals($redirectManager->pageExist($pageId), 1, "The page was added");
        }

        // Page move
        saveWikiText($pageId, "", 'Page deletion');
        saveWikiText($newPageId, $text, 'Page creation');

        // A request
        $request = new TestRequest();
        $request->get(array('id' => $newPageId), '/doku.php');
        $request->execute();

        if ($dataStoreType == admin_plugin_404manager::DATA_STORE_TYPE_SQLITE) {
            $this->assertEquals(0, $redirectManager->pageExist($pageId), "The old page does not exist");
            $this->assertEquals(1, $redirectManager->pageExist($newPageId), "The new page exist");
            $pageRow = $redirectManager->getPage($newPageId);
            $this->assertEquals($pageCanonical, $pageRow[0]['CANONICAL'], "The canonical is the same");
        }

        // One assertion is needed for the other type of data store
        $this->assertEquals(true, true);


    }


}
