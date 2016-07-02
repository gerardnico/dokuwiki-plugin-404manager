<?php
require_once(dirname(__FILE__) . '/../action.php');
$conf['ActionReaderFirst']  = action_plugin_404manager::GO_TO_BEST_PAGE_NAME;
$conf['ActionReaderSecond'] = action_plugin_404manager::GO_TO_SEARCH_ENGINE;
$conf['ActionReaderThird']  = action_plugin_404manager::NOTHING;
$conf['GoToEditMode'] = 1;
$conf['ShowPageNameIsNotUnique'] = 1;
$conf['ShowMessageClassic'] = 1;
$conf['WeightFactorForSamePageName'] = 4;
$conf['WeightFactorForStartPage'] = 3;
// If the page has the same namespace in its path, it gets more weight
$conf['WeightFactorForSameNamespace'] = 5;
?>
