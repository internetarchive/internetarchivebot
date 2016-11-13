<?php

/*
	Copyright (c) 2016, Maximilian Doerr

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	IABot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once( 'loader.php' );

$oauthObject = new OAuth();
$dbObject = new DB2();
$userObject = new User( $dbObject, $oauthObject );
$mainHTML = new HTMLLoader( "main", $userObject->getLanguage() );
$userCache = array();

use Wikimedia\DeadlinkChecker\CheckIfDead;
$checkIfDead = new CheckIfDead();

$_POST = array(); //workaround for broken PHPstorm
parse_str(file_get_contents('php://input'), $_POST);

if( empty( $_GET ) && empty( $_POST ) ) {
	$oauthObject->storeArguments();
	$loadedArguments = [];
} else {
	$oauthObject->mergeArguments();
	$loadedArguments = array_merge( $_GET, $_POST );
}

quickreload:
if( isset( $loadedArguments['page'] ) ) {
	if( $oauthObject->isLoggedOn() === true ) {
		if( $userObject->getLastAction() <= 0 ) {
			if( loadToSPage() === true ) goto quickreload;
		} else {
			switch( $loadedArguments['page'] ) {
				case "runbotsingle":
				case "runbotqueue":
				case "runbotanalysis":
				case "manageurlsingle":
				case "manageurldomain":
				case "reportfalsepositive":
				case "reportbug":
				case "metalogs":
				case "metausers":
				case "metainfo":
				case "metafpreview":
				case "metabotqueue":
					loadConstructionPage();
					break;
				case "user":
					loadUserPage();
					break;
				default:
					load404Page();
					break;
			}
		}
	} else {
		loadLoginNeededPage();
	}
} else {
	loadHomePage();
}

$mainHTML->setUserMenuElement( $oauthObject->getUsername(), $oauthObject->getUserID() );
$mainHTML->assignElement( "consoleversion", INTERFACEVERSION );
$mainHTML->assignElement( "botversion", VERSION );
$mainHTML->assignElement( "cidversion", CHECKIFDEADVERSION );
$mainHTML->assignAfterElement( "csrftoken", $oauthObject->getCSRFToken() );
$mainHTML->finalize();
echo $mainHTML->getLoadedTemplate();

function loadConstructionPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "construction", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{underconstruction}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function load404Page() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "404", $userObject->getLanguage() );
	header( "HTTP/1.1 404 Not Found", true, 404 );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{404}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function load404UserPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "404User", $userObject->getLanguage() );
	header( "HTTP/1.1 404 Not Found", true, 404 );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{404User}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadHomePage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "home", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{startpagelabel}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadLoginNeededPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "loginneeded", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{loginrequired}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadToSPage() {
	global $mainHTML, $userObject, $oauthObject, $loadedArguments, $dbObject;
	if( isset( $loadedArguments['tosaccept'] ) ) {
		if( isset( $loadedArguments['token'] ) ) {
			if( $loadedArguments['token'] == $oauthObject->getCSRFToken() ) {
				if( $loadedArguments['tosaccept'] == "yes" ) {
					$dbObject->insertLogEntry( WIKIPEDIA, "tos", "accept", 0, "", $userObject->getUserID() );
					$userObject->setLastAction( time() );
					return true;
				} else {
					$dbObject->insertLogEntry( WIKIPEDIA, "tos", "decline", 0, "", $userObject->getUserID() );
					$oauthObject->logout();
					return true;
				}
			} else {
				$mainHTML->setMessageBox( "danger", "{{{tokenerrorheader}}}:", "{{{tokenerrormessage}}}" );
			}
		} else {
			$mainHTML->setMessageBox( "danger", "{{{tokenneededheader}}}:", "{{{tokenneededmessage}}}" );
		}
	}
	$bodyHTML = new HTMLLoader( "tos", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{tosheader}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	return false;
}

function loadUserPage() {
	global $mainHTML, $oauthObject, $loadedArguments, $dbObject, $userGroups, $userObject;
	if( $oauthObject->getUserID() == $loadedArguments['id'] ) $userObject2 = $userObject;
	else $userObject2 = new User( $dbObject, $oauthObject, $loadedArguments['id'] );
	if( is_null( $userObject->getUsername() ) ) {
		load404UserPage();
		return;
	}
	$bodyHTML = new HTMLLoader( "user", $userObject->getLanguage() );
	$bodyHTML->assignElement( "userid", $userObject2->getUserID() );
	$bodyHTML->assignElement( "username", $userObject2->getUsername() );
	$bodyHTML->assignAfterElement( "username", $userObject2->getUsername() );
	if( $userObject2->getLastAction() > 0 ) $bodyHTML->assignElement( "lastactivitytimestamp", date( 'G\:i j F Y \(\U\T\C\)', $userObject2->getLastAction() ) );
	if( $userObject2->getAuthTimeEpoch() > 0 )$bodyHTML->assignElement( "lastlogontimestamp", date( 'G\:i j F Y \(\U\T\C\)', $userObject2->getAuthTimeEpoch() ) );
	$text = "";
	foreach( $userObject2->getGroups() as $group ) {
		$text .= "<span class=\"label label-{$userGroups[$group]['labelclass']}\">$group</span>";
	}
	$bodyHTML->assignElement( "groupmembers", $text );
	if( $userObject2->isBlocked() === true ) {
		$bodyHTML->assignElement( "blockstatus", "{{{yes}}}</li>\n<li>{{{blocksource}}}: {{{{blocksource}}}}" );
		switch( $userObject2->getBlockSource() ) {
			case "internal":
				$bodyHTML->assignElement( "blocksource", "{{{blockedinternally}}}" );
				break;
			case "wiki":
				$bodyHTML->assignElement( "blocksource", "{{{blockedonwiki}}}" );
				break;
			default:
				$bodyHTML->assignElement( "blocksource", "{{{blockedunknown}}}" );
		}
	} else {
		$bodyHTML->assignElement( "blockstatus", "{{{no}}}" );
	}
	$bodyHTML->assignElement( "userflags", implode( ", ", $userObject2->getFlags() ) );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'pagerescue';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "pagesrescued", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'botqueue' AND `log_action` ='queue';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "botsstarted", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'urldata';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "urlschanged", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'domaindata';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "domainschanged", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'fpreport';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "fpreported", $res['count'] );
	}
	mysqli_free_result( $result );
	if( $userObject->validatePermission( "changepermissions" ) !== true ) {
		$bodyHTML->assignElement( "permissionscontrol", "{{{permissionscontrolnopermission}}}" );
	} else {
		$bodyHTML->assignElement( "permissionscontrol", "In development" );
	}
	$result = $dbObject->queryDB( "SELECT * FROM externallinks_userlog WHERE `wiki` = '".WIKIPEDIA."' AND `log_user` = '{$loadedArguments['id']}' ORDER BY `log_timestamp` DESC LIMIT 0,100;" );
	$text = "<ol>";
	if( $res = mysqli_fetch_all( $result, MYSQLI_ASSOC ) ) {
		loadLogUsers( $res );
		foreach( $res as $entry ) {
			$text .= "<li>".getLogText( $entry )."</li>\n";
		}
	}
	mysqli_free_result( $result );
	$text .= "</ol>";
	$bodyHTML->assignElement( "last100userlogs", $text );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{userheader}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	$mainHTML->assignAfterElement( "username", $userObject->getUsername() );
}

function getLogText( $logEntry ) {
	global $userObject, $userCache;
	$logText = new HTMLLoader( date( 'G\:i\, j F Y', strtotime($logEntry['log_timestamp']) )." <a href=\"index.php?page=user&id=".$logEntry['log_user']."\">".$userCache[$logEntry['log_user']]['user_name']."</a> {{{".$logEntry['log_type'].$logEntry['log_action']."}}}", $userObject->getLanguage() );
	$logText->finalize();
	return $logText->getLoadedTemplate();
}

function loadLogUsers( $logEntries ) {
	global $userCache, $dbObject;
	foreach( $logEntries as $logEntry ) {
		if( !isset( $userCache[$logEntry['log_user']] ) ) {
			$toFetch[] = $logEntry['log_user'];
		}
	}
	$res = $dbObject->queryDB( "SELECT * FROM `externallinks_user` WHERE `user_id` IN (".implode( ", ", $toFetch ).") AND `wiki` = '".WIKIPEDIA."';" );
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$userCache[$result['user_id']] = $result;
	}
}