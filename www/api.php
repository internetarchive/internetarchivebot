<?php

/*
	Copyright (c) 2015-2017, Maximilian Doerr

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

ini_set( 'memory_limit', '256M' );
require_once( 'loader.php' );

$oauthObject = new OAuth( true );
$dbObject = new DB2();
$userObject = new User( $dbObject, $oauthObject );
$userCache = [];

use Wikimedia\DeadlinkChecker\CheckIfDead;

$checkIfDead = new CheckIfDead();

//workaround for broken PHPstorm
//Do some POST cleanup to convert everything to a newline.
$_POST = str_ireplace( "%0D%0A", "%0A", file_get_contents( 'php://input' ) );
$_POST = str_ireplace( "%0A", "\n", $_POST );
$_POST = trim( $_POST );
$_POST = str_replace( "\n", "%0A", $_POST );
parse_str( $_POST, $_POST );

if( empty( $_GET ) && empty( $_POST ) ) {
	$oauthObject->storeArguments();
	$loadedArguments = [];
} elseif( isset( $_GET['returnedfrom'] ) ) {
	$oauthObject->recallArguments();
	$loadedArguments = array_replace( $_GET, $_POST );
} else {
	$oauthObject->storeArguments();
	$loadedArguments = array_replace( $_GET, $_POST );
}

$jsonOut = [];

if( isset( $locales[$userObject->getLanguage()] ) ) setlocale( LC_ALL, $locales[$userObject->getLanguage()] );

if( file_exists( "gui.maintenance.json" ) || $disableInterface === true ) {
	$jsonOut['noaccess'] = "disabledinterface";
	if( file_exists( "gui.maintenance.json" ) ) {
		$jsonOut['noaccess'] = "maintenance";
	}
	die( json_encode( $jsonOut ) );
}

if( !empty( $loadedArguments['action'] ) ) {
	if( isset( $_SESSION['apiratelimit'] ) ) foreach( $_SESSION['apiratelimit'] as $time => $action ) {
		if( time() - $time > 60 ) unset( $_SESSION['apiratelimit'][$time] );
	}
	if( isset( $_SESSION['apiratelimit'] ) && count( $_SESSION['apiratelimit'] ) > 5 ) {
		if( $oauthObject->isLoggedOn() ) {
			if( count( $_SESSION['apiratelimit'] ) > 500 ) {
				if( !validatePermission( "highapilimit", false ) ) {
					dieRateLimit( 500 );
				} elseif( count( $_SESSION['apiratelimit'] ) > 5000 ) {
					dieRateLimit( 5000 );
				}
			}
		} else {
			dieRateLimit( 5 );
		}
	}

	$_SESSION['apiratelimit'][time()] = $loadedArguments['action'];

	if( $oauthObject->isLoggedOn() && !$userObject->validateGroup( "bot" ) && $userObject->getLastAction() <= 0 ) {
	    $jsonOut['requesterror'] = "accepttos";
	    $jsonOut['errormessage'] = "As a non-bot user, you are required to accept the Terms of Service.  Please log in to the graphical interface first before using the API.";
	    die( json_encode( $jsonOut, true ) );
    }

	switch( $loadedArguments['action'] ) {
		case "getfalsepositives":
			if( !$oauthObject->isLoggedOn() ) dieAuthError();
			loadFPReportMeta( $jsonOut );
			break;
		case "getbotqueue":
			if( !$oauthObject->isLoggedOn() ) dieAuthError();
			loadBotQueue( $jsonOut );
			break;
		case "reportfp":
			if( !$oauthObject->isLoggedOn() ) dieAuthError();
			reportFalsePositive( $jsonOut );
			break;
		case "searchurldata":
			loadURLData( $jsonOut );
			break;
		case "searchpagefromurl":
			loadPagesFromURL( $jsonOut );
			break;
		case "modifyurl":
			if( !$oauthObject->isLoggedOn() ) dieAuthError();
			changeURLData( $jsonOut );
			break;
		case "analyzepage":
			if( !$oauthObject->isLoggedOn() ) dieAuthError();
			if( isset( $_SESSION['apiaccess'] ) ) {
				$jsonOut['notavailable'] = "functionnotavailable";
				$jsonOut['errormessage'] =
					"This function requires the full OAuth authorization to work.  API authorization is not sufficient.";
				die( json_encode( $jsonOut, true ) );
			}
			analyzePage( $jsonOut );
			break;
		case "submitbotjob":
			if( !$oauthObject->isLoggedOn() ) dieAuthError();
			submitBotJob( $jsonOut );
			break;
		case "getbotjob":
			loadJobViewer( $jsonOut );
			break;
		case "logout":
			$oauthObject->logout();
			break;
	}
} else {
	$jsonOut['noaction'] = "To use the API, use the action parameter to tell the tool what to do.";
}

$jsonOut['loggedon'] = $oauthObject->isLoggedOn();

if( isset( $loadedArguments['returnpayload'] ) && $oauthObject->isLoggedOn() && is_null( $oauthObject->getPayload() && isset( $_SESSION['apiaccess'] ) )
) {
	if( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$oauthObject->identify( false, $_SERVER['HTTP_AUTHORIZATION'] );
	} else {
		$jsonOut['validationerror'] = "noheader";
		$jsonOut['usedheader'] = $oauthObject->getLastUsedHeader();
		$jsonOut['errormessage'] = "The header is missing for validation.";
	}
}

if( $oauthObject->isLoggedOn() ) {
	if( !is_null( $oauthObject->getPayload() ) && $oauthObject->getOAuthError() === false ) $jsonOut['payload'] =
		$oauthObject->getPayload();
	elseif( $oauthObject->getOAuthError() !== false ) {
		$jsonOut['validationerror'] = "invalidheader";
		$jsonOut['usedheader'] = $oauthObject->getLastUsedHeader();
		$jsonOut['errormessage'] = $oauthObject->getOAuthError();
	}
	$jsonOut['username'] = $userObject->getUsername();
	$jsonOut['checksum'] = $oauthObject->getChecksumToken();
	$jsonOut['csrf'] = $oauthObject->getCSRFToken();
} else {
	if( $oauthObject->getOAuthError() !== false ) {
		$jsonOut['autherror'] = "Invalid header received";
		$jsonOut['usedheader'] = $oauthObject->getLastUsedHeader();
		$jsonOut['errormessage'] = $oauthObject->getOAuthError();
	}
}

die( json_encode($jsonOut ) );


function array_utf8_encode( $dat ) {
	if( is_string( $dat ) )
		return utf8_encode( $dat );
	if( !is_array( $dat ) )
		return $dat;
	$ret = [];
	foreach( $dat as $i => $d )
		$ret[$i] = array_utf8_encode( $d );

	return $ret;
}

function dieAuthError() {
	global $jsonOut, $oauthObject;
	$jsonOut['noaccess'] = "Missing authorization";
	if( $oauthObject->getOAuthError() !== false ) {
		$jsonOut['noaccess'] = $oauthObject->getOAuthError();
		$jsonOut['usedheader'] = $oauthObject->getLastUsedHeader();
	}
	header( "HTTP/1.1 401 Unauthorized", true, 401 );
	die( json_encode( $jsonOut ) );
}

function dieRateLimit( $limit ) {
	global $jsonOut;
	$jsonOut['ratelimit'] = "$limit/minute";
	$jsonOut['errormessage'] = "You have exceeded the max number of requests allowed per minute.";
	header( "HTTP/1.1 429 Too Many Requests", true, 429 );
	die( json_encode( $jsonOut ) );
}