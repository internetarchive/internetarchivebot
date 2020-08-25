<?php

$sessionUseDB = false;

$sessionHttpOnly = false;

$sessionSecure = false;

$sessionLifeTime = "30 days";

//DB setup
$sessionHost = "";
$sessionUser = "";
$sessionPass = "";
$sessionPort = "";
$sessionDB = "";

if( file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'sessions.config.local.inc.php'
) ) {
	require_once( 'sessions.config.local.inc.php' );
}

if( !defined( "SESSIONSECRET" ) ) define( "SESSIONSECRET", "" );