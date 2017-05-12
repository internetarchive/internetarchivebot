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
//$_SERVER['HTTP_AUTHORIZATION'] = "Authorization: OAuth oauth_consumer_key=\"ad8e33572688dd300d2b726bee409f5d\", oauth_token=\"147e94d316131e029a70db90bda94940\", oauth_version=\"1.0\", oauth_nonce=\"9a45cabbbab983e55dcd3c841a3e5c6e\", oauth_timestamp=\"1494542119\", oauth_signature_method=\"HMAC-SHA1\", oauth_signature=\"WNZrHcNp6AoqqCUyW4xToUmSK8w%3D\"";

$oauthObject = new OAuth( true );
//$oauthObject->logout();
$dbObject = new DB2();
$userObject = new User( $dbObject, $oauthObject );
$userCache = [];
if( !is_null( $userObject->getDefaultWiki() ) && $userObject->getDefaultWiki() !== WIKIPEDIA &&
    isset( $_GET['returnedfrom'] )
) {
	header( "Location: " . $_SERVER['REQUEST_URI'] . "&wiki=" . $userObject->getDefaultWiki() );
	exit( 0 );
}

use Wikimedia\DeadlinkChecker\CheckIfDead;

$checkIfDead = new CheckIfDead();

$_POST = []; //workaround for broken PHPstorm
parse_str( file_get_contents( 'php://input' ), $_POST );

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

if( isset( $locales[$userObject->getLanguage()] ) ) setlocale( LC_ALL, $locales[$userObject->getLanguage()] );

if( file_exists( "gui.maintenance.json" ) || $disableInterface === true ) {
	$jsonOut['noaccess'] = "disabledinterface";
	if( file_exists( "gui.maintenance.json" ) ) {
		$jsonOut['noaccess'] = "maintenance";
	}
	die( json_encode( $jsonOut ) );
}

if( !$oauthObject->isLoggedOn() ) {
	$jsonOut['noaccess'] = "Missing authorization headers";
	if( $oauthObject->getOAuthError() !== false ) {
		$jsonOut['noaccess'] = $oauthObject->getOAuthError();
	}
	header( "HTTP/1.1 401 Unauthorized", true, 401 );
	die( json_encode( $jsonOut ) );
}
$jsonOut = [];
if( !is_null( $oauthObject->getJWT() ) ) $jsonOut = array_merge( $jsonOut, $oauthObject->getJWT() );

die( json_encode( $jsonOut ) );