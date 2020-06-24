<?php

$actionChoices = array('multichoice', '_choices' => array(
    action_plugin_404manager::NOTHING,
    action_plugin_404manager::GO_TO_BEST_END_PAGE_NAME,
    action_plugin_404manager::GO_TO_NS_START_PAGE,
    action_plugin_404manager::GO_TO_BEST_PAGE_NAME,
    action_plugin_404manager::GO_TO_BEST_NAMESPACE,
    action_plugin_404manager::GO_TO_SEARCH_ENGINE
));

$meta['ActionReaderFirst']  = $actionChoices;
$meta['ActionReaderSecond'] = $actionChoices;
$meta['ActionReaderThird']  = $actionChoices;
$meta['GoToEditMode'] = array('onoff');
$meta['ShowPageNameIsNotUnique'] = array('onoff');
$meta['ShowMessageClassic'] = array('onoff');
$meta['WeightFactorForSamePageName'] = array('string');
$meta['WeightFactorForStartPage'] = array('string');
$meta['WeightFactorForSameNamespace'] = array('string');

$meta[] = array('onoff');
?>
