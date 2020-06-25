<?php
/**
 * Tests over DokuWiki function for the 404manager plugin
 *
 * @group plugin_404manager
 * @group plugins
 */


class plugin_404manager_dokuwiki_test extends DokuWikiTest
{




    /**
     * Simple test to make sure the plugin.info.txt is in correct format
     */
    public function test_plugininfo()
    {

        $file = __DIR__ . '/../plugin.info.txt';
        $this->assertFileExists($file);

        $info = confToHash($file);

        $this->assertArrayHasKey('base', $info);
        $this->assertArrayHasKey('author', $info);
        $this->assertArrayHasKey('email', $info);
        $this->assertArrayHasKey('date', $info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('desc', $info);
        $this->assertArrayHasKey('url', $info);

        $this->assertEquals('404manager', $info['base']);
        $this->assertRegExp('/^https?:\/\//', $info['url']);
        $this->assertTrue(mail_isvalid($info['email']));
        $this->assertRegExp('/^\d\d\d\d-\d\d-\d\d$/', $info['date']);
        $this->assertTrue(false !== strtotime($info['date']));

    }

    /** Page exist can be tested on two ways within DokuWiki
     *   * page_exist
     *   * and the $INFO global variable
     */
    public function test_pageExists()
    {

        $pageExistId = 'page_exist';
        saveWikiText($pageExistId, 'REDIRECT Best Page Name Same Branch', 'Test initialization');
        // Not in a request
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertTrue(page_exists($pageExistId));

        // In a request
        $request = new TestRequest();
        $request->get(array('id' => $pageExistId), '/doku.php');
        global $INFO;

        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertTrue($INFO['exists']);

        // Not in a request
        $pageDoesNotExist = "pageDoesNotExist";
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertFalse(page_exists($pageDoesNotExist));

        // In a request
        $request = new TestRequest();
        $request->get(array('id' => $pageDoesNotExist), '/doku.php');
        global $INFO;
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertFalse($INFO['exists']);

    }

}
