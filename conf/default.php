<?php
require_once(dirname(__FILE__) . '/../action.php');
$conf['ActionReaderFirst']  = action_plugin_404manager::GO_TO_BEST_END_PAGE_NAME;
$conf['ActionReaderSecond'] = action_plugin_404manager::GO_TO_BEST_PAGE_NAME;
$conf['ActionReaderThird']  = action_plugin_404manager::GO_TO_SEARCH_ENGINE;
$conf['GoToEditMode'] = 1;
$conf['ShowPageNameIsNotUnique'] = 1;
$conf['ShowMessageClassic'] = 1;
$conf['WeightFactorForSamePageName'] = 4;
$conf['WeightFactorForStartPage'] = 3;
// If the page has the same namespace in its path, it gets more weight
$conf['WeightFactorForSameNamespace'] = 5;

/*
 * Does canonical processing is on
 */
$conf[action_plugin_404manager_canonical::CANONICAL_ENABLED_CONF] = 1;
$conf[action_plugin_404manager_canonical::CANONICAL_LAST_NAMES_COUNT_CONF] = 2;

?>
