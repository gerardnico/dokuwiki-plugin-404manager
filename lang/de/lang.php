<?php
/**
 * English language file
 *
 * @license      GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author       Nicolas GERARD <gerardnico@gmail.com>
 * @translation	 Dominik Reichardt <dominik@reichardt-online.it>
 */
 
// ##################################
// ############ Admin Page ##########
// ##################################

$lang['AdminPageName'] = '404Manager Plugin';

//Error Message
$lang['SameSourceAndTargetAndPage'] = 'Die Quell und die Zielseite sind identisch.';
$lang['NotInternalOrUrlPage'] = 'Die Zielseite existiert nicht oder besitzt keine gültige URL';
$lang['SourcePageExist'] = 'Die Quellseite existiert.';

//FeedBack Message
$lang['Saved']	= 'Gespeichert';
$lang['Deleted'] = 'Gelöscht';
$lang['Validated'] = 'Validiert';

//Array Header of the Admin Page
$lang['SourcePage'] = 'Quell Seite';
$lang['TargetPage'] = 'Ziel Seite';
$lang['Valid'] = 'Validieren';
$lang['CreationDate'] = 'Erstellungs Datum';
$lang['LastRedirectionDate'] = 'Datum letzte Weiterleitung';
$lang['LastReferrer'] = 'Last Referrer';
$lang['Never'] = 'Niemals';
$lang['Direct Access'] = 'Direkter Zugriff';
$lang['TargetPageType'] = 'Ziel Seiten Typ';
$lang['CountOfRedirection'] = 'Anzahl der Weiterleitungen';

// Head Titles
$lang['AddModifyRedirection'] = "Hinzufügen/Bearbeiten von Weiterleitungen";
$lang['ListOfRedirection'] = 'Liste der Weiterleitungen';

//Explication Message
$lang['ExplicationValidateRedirection'] = 'A validate redirection don\'t show any warning message. A unvalidate redirection is a proposition which comes from an action "Go to best page".';
$lang['ValidateToSuppressMessage'] = "You must approve (validate) the redirection to suppress the message of redirection.";

// Forms Add/Modify Value
$lang['source_page'] = 'Quell Seite';
$lang['target_page'] = 'Ziel Seite';
$lang['redirection_valid'] = 'Weiterleitung Validiert';
$lang['yes'] = 'Ja';
$lang['Field'] = 'Feld' ;
$lang['Value'] = 'Wert';
$lang['btn_addmodify'] = 'Hinzufügen/Bearbeiten';

// ##################################
// ######### Action Message #########
// ##################################

$lang['message_redirected_by_redirect'] = 'The page ($ID) doesn\'t exist. You have been redirected automatically to the redirect page.';
$lang['message_redirected_to_edit_mode'] = 'This page doesn\'t exist. You have been redirected automatically in the edit mode.';
$lang['message_pagename_exist_one'] = 'The following page(s) exists already in other namespace(s) with the same name part: ';
$lang['message_redirected_to_startpage'] = 'The page ($ID) doesn\'t exist. You have been redirected automatically to the start page of the namespace.';
$lang['message_redirected_to_bestpagename'] = 'The page ($ID) doesn\'t exist. You have been redirected automatically to the best page.';
$lang['message_redirected_to_bestnamespace'] = 'The page ($ID) doesn\'t exist. You have been redirected automatically to the best namespace.';
$lang['message_redirected_to_searchengine'] = 'The page ($ID) doesn\'t exist. You have been redirected automatically to the search engine.';
$lang['message_come_from'] = 'This message was fired by ';

?>
