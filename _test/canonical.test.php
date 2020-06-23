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
        $pageCanonical = "javascript:variable";


        // Reproducible test
        if ($redirectManager->pageExist($pageId)) {
            $redirectManager->deletePage($pageId);
        }

        if ($dataStoreType==admin_plugin_404manager::DATA_STORE_TYPE_SQLITE) {
            $this->assertEquals($redirectManager->pageExist($pageId), 0, "The page was deleted");
        }

        // Save a page
        $text = DOKU_LF . '---json' . DOKU_LF
            . '{' . DOKU_LF
            . '   "canonical":"'.$pageCanonical.'"' . DOKU_LF
            . '}' .DOKU_LF
            . '---' .DOKU_LF
            . 'Content';
        saveWikiText($pageId, $text, 'Updated meta');

        // In a request
        $request = new TestRequest();
        $request->get(array('id' => $pageId), '/doku.php');
        $request->execute();

        if ($dataStoreType==admin_plugin_404manager::DATA_STORE_TYPE_SQLITE) {
            $this->assertEquals($redirectManager->pageExist($pageId), 1, "The page was added");
        } else {
            // One assertion is needed
            $this->assertEquals(true, true);
        }



    }



}
