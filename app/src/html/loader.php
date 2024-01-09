<?php
/*
	Copyright (c) 2015-2024, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	InternetArchiveBot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with InternetArchiveBot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

date_default_timezone_set( "UTC" );

//Create a file named setpath.php in the same directory as this file and set the $path to the root directory containing IABot's library.
$path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . "../";

@header( 'Content-Type: text/html', true );

if( file_exists( 'setpath.php' ) ) require_once( 'setpath.php' );

require_once( $path . 'sessions.config.inc.php' );

if( $sessionSecure === true && isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] != "https" ) {
	$redirect = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
	header( "HTTP/2 101 Switching Protocols", true, 101 );
	header( "Location: $redirect" );
	exit( 0 );
}

require_once( 'Includes/session.php' );

$sessionObject = new Session();

$sessionObject->start();
$clearChecksum = false;
if( isset( $_GET['wiki'] ) ) {
	if( !isset( $_SESSION['setwiki'] ) || $_GET['wiki'] != $_SESSION['setwiki'] ) $clearChecksum = true;
	$_SESSION['setwiki'] = $_GET['wiki'];
}
if( isset( $_GET['lang'] ) ) {
	$_SESSION['setlang'] = $_GET['lang'];
}
if( isset( $_SESSION['setwiki'] ) ) {
	define( 'WIKIPEDIA', $_SESSION['setwiki'] );
}
if( !empty( $_GET['debug'] ) ) {
	$_SESSION['debug'] = true;
}
if( isset( $_SESSION['debug'] ) ) {
	define( 'IAVERBOSE', true );
}

$setWikiFromReferal = false;
if( !defined( 'WIKIPEDIA' ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
	$setWikiFromReferal = true;
}

date_default_timezone_set( "UTC" );
if( !defined( 'USEWEBINTERFACE' ) ) define( 'USEWEBINTERFACE', 1 );
if( isset( $_SESSION['debug'] ) ) {
	error_reporting( E_ALL );
} else {
	error_reporting( E_COMPILE_ERROR | E_ERROR );
}

require_once( $path . 'Core/init.php' );

ini_set( 'memory_limit', '512M' );

if( $setWikiFromReferal === true && WIKIPEDIA != $defaultWiki ) {
	$_SESSION['setwiki'] = WIKIPEDIA;
}

if( $_SESSION['wiki'] != $_SESSION['setwiki'] ) $_SESSION['previouswiki'] = $_SESSION['wiki'];

session_write_close();
require_once( 'Includes/OAuth.php' );
require_once( 'Includes/DB2.php' );
require_once( 'Includes/User.php' );
require_once( 'Includes/HTMLLoader.php' );
require_once( 'Includes/pagefunctions.php' );
require_once( 'Includes/actionfunctions.php' );

setlocale( LC_ALL, unserialize( BOTLOCALE ) );

define( 'NOCHECKPOINT', 1 );
