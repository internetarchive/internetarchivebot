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

//Create a file named setpath.php in the same directory as this file and set the $path to the root directory containing IABot's library.
$path = "../";

if( file_exists( 'setpath.php' ) ) require_once( 'setpath.php' );

session_start();
if( isset( $_GET['wiki'] ) ) {
	$_SESSION['setwiki'] = $_GET['wiki'];
}
if( isset( $_GET['lang'] ) ) {
	$_SESSION['setlang'] = $_GET['lang'];
}
if( isset( $_SESSION['setwiki'] ) ) {
	define( 'WIKIPEDIA', $_SESSION['setwiki'] );
}
session_write_close();
date_default_timezone_set( "UTC" );
define( 'USEWEBINTERFACE', 1 );
error_reporting( E_ALL );

require_once( $path . 'deadlink.config.inc.php' );
require_once( 'Includes/OAuth.php' );
require_once( 'Includes/DB2.php' );
require_once( 'Includes/User.php' );
require_once( 'Includes/HTMLLoader.php' );
require_once( 'Includes/pagefunctions.php' );
require_once( 'Includes/actionfunctions.php' );
