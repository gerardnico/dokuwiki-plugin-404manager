<?php
/**
 * Integration Tests for the handling of the canonical
 *
 * plugin_404manager
 * @group plugins
 *
 */

require_once(__DIR__ . '/../class/UrlCanonical.php');
class plugin_404manager_canonical_test extends DokuWikiTest
{

    // Needed otherwise the plugin is not enabled
    protected $pluginsEnabled = array('404manager', 'sqlite', 'webcomponent');


    /**
     * Test a internal canonical rewrite redirect
     *
     */
    public function test_canonical()
    {

        $urlCanonicalManager = new UrlCanonical(UrlStatic::getSqlite());

        // Data
        $pageId = "web:javascript:variable";
        $newPageId = "lang:javascript:variable";
        $pageCanonical = "javascript:variable";


        // Reproducible test
        if ($urlCanonicalManager->pageExist($pageId)) {
            $urlCanonicalManager->deletePage($pageId);
        }

        if ($urlCanonicalManager->pageExist($newPageId)) {
            $urlCanonicalManager->deletePage($newPageId);
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(0,$urlCanonicalManager->pageExist($pageId), "The page was deleted");

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

        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(1, $urlCanonicalManager->pageExist($pageId), "The page was added to the table");

        // Page move
        saveWikiText($pageId, "", 'Page deletion');
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(false, page_exists($pageId), "The old page does not exist on disk");
        saveWikiText($newPageId, $text, 'Page creation');

        // A request
        $request = new TestRequest();
        $request->get(array('id' => $newPageId), '/doku.php');

        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(0, $urlCanonicalManager->pageExist($pageId), "The old page does not exist in db");
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(1, $urlCanonicalManager->pageExist($newPageId), "The new page exist");
        $pageRow = $urlCanonicalManager->getPage($newPageId);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($pageCanonical, $pageRow['CANONICAL'], "The canonical is the same");




    }

    /**
     * Test the canonical
     * Actually it just add the og
     * When the rendering of the canonical value will be supported by
     * 404 manager, we can switch
     * TODO: move this to 404 manager ?
     */
    public function test_canonical_meta()
    {

        $metaKey = syntax_plugin_webcomponent_frontmatter::CANONICAL_PROPERTY;
        $pageId = 'description:test';
        $canonicalValue = "javascript:variable";
        $text = DOKU_LF . '---json' . DOKU_LF
            . '{' . DOKU_LF
            . '   "' . $metaKey . '":"' . $canonicalValue . '"' . DOKU_LF
            . '}' . DOKU_LF
            . '---' . DOKU_LF
            . 'Content';
        saveWikiText($pageId, $text, 'Created');

        $canonicalMeta = p_get_metadata($pageId, $metaKey, METADATA_RENDER_UNLIMITED);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($canonicalValue, $canonicalMeta);

        // It should never occur but yeah
        $canonicalValue = "js:variable";
        $text = DOKU_LF . '---json' . DOKU_LF
            . '{' . DOKU_LF
            . '   "' . $metaKey . '":"' . $canonicalValue . '"' . DOKU_LF
            . '}' . DOKU_LF
            . '---' . DOKU_LF
            . 'Content';
        saveWikiText($pageId, $text, 'Updated meta');
        $canonicalMeta = p_get_metadata($pageId, $metaKey, METADATA_RENDER_UNLIMITED);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($canonicalValue, $canonicalMeta);

        // Do we have the description in the meta
        $request = new TestRequest(); // initialize the request
        $response = $request->get(array('id' => $pageId), '/doku.php');



        // Query
        $canonicalHrefLink = $response->queryHTML('link[rel="' . $metaKey . '"]')->attr('href');
        $canonicalId = UrlCanonical::toDokuWikiId($canonicalHrefLink);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($canonicalValue, $canonicalId, "The link canonical meta should be good");
        // Facebook: https://developers.facebook.com/docs/sharing/webmasters/getting-started/versioned-link/
        $canonicalHrefMetaOg = $response->queryHTML('meta[property="og:url"]')->attr('content');
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals($canonicalHrefLink, $canonicalHrefMetaOg, "The meta canonical property should be good");

    }


}
