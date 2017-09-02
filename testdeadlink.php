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

/* This is a CID frontend wrapper meant to be installed on an external server. */

ini_set( 'memory_limit', '256M' );
header( 'Content-Type: application/json' );

require_once( '../accessConfig.php' );
require_once( __DIR__ . '/../vendor/autoload.php' );

use Wikimedia\DeadlinkChecker\CheckIfDead;

$checkIfDead = new CheckIfDead();

if( empty( $_POST ) ) {
	//workaround for broken PHPstorm
	//Do some POST cleanup to convert everything to a newline.
	$_POST = str_ireplace( "%0D%0A", "%0A", file_get_contents( 'php://input' ) );
	$_POST = str_ireplace( "%0A", "\n", $_POST );
	$_POST = trim( $_POST );
	$_POST = str_replace( "\n", "%0A", $_POST );
	parse_str( $_POST, $_POST );
}

if( empty( $_POST ) ) {
	$jsonOut['error'] = "nocommand";
	$jsonOut['errormessage'] = "You must provide inputs.";
} else {
	if( empty( $_POST['authcode'] ) || array_search( $_POST['authcode'], $accessCodes ) ) {
		dieAuthError();
	}
	if( empty( $_POST['urls'] ) ) {
		$jsonOut['missingvalue'] = "urls";
		$jsonOut['errormessage'] = "You need to provide URLs to check.";
	} else {
		$urls = explode( "\n", $_POST['urls'] );
		$results = $checkIfDead->areLinksDead( $urls );
		$errors = $checkIfDead->getErrors();
		$out = [];
		foreach( $results as $url => $result ) {
			$out[$url] = $result;
			if( $result === true ) $out['errors'][$url] = $errors[$url];
		}
		$jsonOut['results'] = $out;
	}
}

$jsonOut['servetime'] = round( microtime( true ) - $_SERVER["REQUEST_TIME_FLOAT"], 4 );

if( ( $out = json_encode( $jsonOut, JSON_PRETTY_PRINT ) ) === false ) {
	$jsonOut = json_prepare_array( $jsonOut );
	$out = json_encode( $jsonOut, JSON_PRETTY_PRINT );
	if( json_last_error() !== 0 ) die( json_encode( [
		                                                "apierror"     => "jsonerror", "jsonerror" => json_last_error(),
		                                                "errormessage" => json_last_error_msg()
	                                                ], JSON_PRETTY_PRINT
	)
	);
}

die( $out );


function json_prepare_array( $dat ) {
	if( is_string( $dat ) )
		return Encoding::toUTF8( $dat );
	if( !is_array( $dat ) )
		return $dat;
	$ret = [];
	foreach( $dat as $i => $d )
		$ret[$i] = json_prepare_array( $d );

	return $ret;
}

function dieAuthError() {
	global $jsonOut, $oauthObject;
	$jsonOut['noaccess'] = "Missing/invalid authorization";
	header( "HTTP/1.1 401 Unauthorized", true, 401 );
	die( json_encode( $jsonOut ) );
}