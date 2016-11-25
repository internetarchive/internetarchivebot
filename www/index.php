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
} elseif( isset( $_GET['returnedfrom'] ) ) {
	$oauthObject->recallArguments();
	$loadedArguments = array_merge( $_GET, $_POST );
} else {
	$oauthObject->storeArguments();
	$loadedArguments = array_merge( $_GET, $_POST );
}

if( isset( $loadedArguments['action'] ) ) {
	if( $oauthObject->isLoggedOn() === true ) {
		if( $userObject->getLastAction() <= 0 ) {
			if( loadToSPage() === true ) goto quickreload;
			else exit(0);
		} else {
			switch( $loadedArguments['action'] ) {
				case "changepermissions":
					if( changeUserPermissions() ) goto quickreload;
					break;
				case "toggleblock":
					if( toggleBlockStatus() ) goto quickreload;
					break;
				case "togglefpstatus":
					if( toggleFPStatus() ) goto quickreload;
					break;
				case "reviewreportedurls":
					if( runCheckIfDead() ) goto quickreload;
					break;
				case "massbqchange":
					if( massChangeBQJobs() ) goto quickreload;
					break;
				case "togglebqstatus":
					if( toggleBQStatus() ) goto quickreload;
					break;
				case "killjob":
					if( toggleBQStatus( true ) ) goto quickreload;
					break;
				case "submitfpreport":
					if( reportFalsePositive() ) goto quickreload;
					break;
			}
		}
	} else {
		loadLoginNeededPage();
	}
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
					loadConstructionPage();
					break;
				case "reportfalsepositive":
					loadFPReporter();
					break;
				case "reportbug":
				case "metalogs":
					loadConstructionPage();
					break;
				case "metausers":
					loadUserSearch();
					break;
				case "metainfo":
					loadInterfaceInfo();
					break;
				case "metafpreview":
					loadFPReportMeta();
					break;
				case "metabotqueue":
					loadBotQueue();
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

$sql = "SELECT COUNT(*) AS count FROM externallinks_user WHERE `last_action` >= '".date( 'Y-m-d H:i:s', time()-300 )."' OR `last_login` >= '".date( 'Y-m-d H:i:s', time()-300 )."';";
$res = $dbObject->queryDB( $sql );
if( $result = mysqli_fetch_assoc( $res ) ) {
	$mainHTML->assignAfterElement( "activeusers5", $result['count'] );
	mysqli_free_result( $res );
}

$mainHTML->setUserMenuElement( $oauthObject->getUsername(), $oauthObject->getUserID() );
$mainHTML->assignAfterElement( "consoleversion", INTERFACEVERSION );
$mainHTML->assignAfterElement( "botversion", VERSION );
$mainHTML->assignAfterElement( "cidversion", CHECKIFDEADVERSION );
$mainHTML->assignAfterElement( "csrftoken", $oauthObject->getCSRFToken() );
$mainHTML->assignAfterElement( "checksum", $oauthObject->getChecksumToken() );
$mainHTML->finalize();
echo $mainHTML->getLoadedTemplate();