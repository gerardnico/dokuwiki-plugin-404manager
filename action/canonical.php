<?php

if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_webcomponent_css
 * Delete Backend CSS for front-end
 *
 * Bug:
 *   * https://gerardnico.com/web/browser/lighthouse - no interwiki
 *
 *   * The name of the file should be the last name of the class
 *   * There should be only one name
 */
class action_plugin_404manager_canonical extends DokuWiki_Action_Plugin
{

    /**
     * The conf
     */
    const CANONICAL_ENABLED_CONF = 'CanonicalEnabled';
    const CANONICAL_LAST_NAMES_COUNT_CONF = 'LastNamesCanonicalCount';

    function __construct()
    {
        // enable direct access to language strings
        // ie $this->lang
        $this->setupLocale();
    }

    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'metaCanonicalProcessing', array());
    }

    /**
     * Dokuwiki has already a canonical methodology
     * https://www.dokuwiki.org/canonical
     *
     * @param $event
     */
    function metaCanonicalProcessing($event)
    {
        global $ID;
        global $conf;


        /**
         * Where do we pick the canonical URL
         */
        $canonicalMetaEnabled = $this->getConf(self::CANONICAL_ENABLED_CONF);
        if ($canonicalMetaEnabled) {

            /**
             * Canonical from meta
             *
             * FYI: The creation of the link was extracted from
             * {@link wl()} that call {@link idfilter()} that performs just a replacement
             * Calling the wl function will not work because
             * {@link wl()} use the constant DOKU_URL that is set before any test via getBaseURL(true)
             */
            $canonical = p_get_metadata($ID, syntax_plugin_webcomponent_frontmatter::CANONICAL_PROPERTY);

            /**
             * The last part of the id as canonical
             */
            // How many last parts are taken into account in the canonical processing (2 by default)
            $canonicalLastNamesCount = $this->getConf(self::CANONICAL_LAST_NAMES_COUNT_CONF);
            if ($canonical == "" && $canonicalLastNamesCount > 0) {
                /**
                 * Split the id by :
                 */
                $names = preg_split("/:/", $ID);
                /**
                 * Takes the last names part
                 */
                $namesLength = sizeOf($names);
                if ($namesLength >$canonicalLastNamesCount){
                    array_slice ( $names , $namesLength-1-$canonicalLastNamesCount );
                }
                /**
                 * If this is a start page, delete the name
                 * ie javascript:start will become javascript
                 */
                if ($names[$namesLength-1] == $conf['start']){
                    array_slice ( $names , $namesLength-1);
                }
                $canonical = implode(":", $names);
            }
            $canonicalUrl = getBaseURL(true) . strtr($canonical, ':', '/');


        } else {

            /**
             * Dokuwiki Methodology taken from {@link tpl_metaheaders()}
             */
            $canonicalUrl = wl($ID, '', true, '&');
            if ($ID == $conf['start']) {
                $canonicalUrl = DOKU_URL;
            }

        }

        // Search the key
        $canonicalKey = "";
        $canonicalRelArray = array("rel" => "canonical", "href" => $canonicalUrl);
        foreach ($event->data['link'] as $key => $link) {
            if ($link["rel"] == "canonical") {
                $canonicalKey = $key;
            }
        }
        if ($canonicalKey != "") {
            // Update
            $event->data['link'][$canonicalKey] = $canonicalRelArray;
        } else {
            // Add
            $event->data['link'][] = $canonicalRelArray;
        }

        /**
         * Add the Og canonical meta
         * https://developers.facebook.com/docs/sharing/webmasters/getting-started/versioned-link/
         */
        $canonicalOgKeyKey = "";
        $canonicalPropertyKey = "og:url";
        $canonicalOgArray = array("property" => $canonicalPropertyKey, "content" => $canonicalUrl);
        foreach ($event->data['meta'] as $key => $meta) {
            if ($meta["property"] == $canonicalPropertyKey) {
                $canonicalOgKeyKey = $key;
            }
        }
        if ($canonicalOgKeyKey != "") {
            // Update
            $event->data['meta'][$canonicalOgKeyKey] = $canonicalOgArray;
        } else {
            // Add
            $event->data['meta'][] = $canonicalOgArray;
        }

    }

}