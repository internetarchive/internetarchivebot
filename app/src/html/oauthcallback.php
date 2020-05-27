<?php

/*
	Copyright (c) 2015-2018, Maximilian Doerr

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

$dbObject = new DB2();
$oauth = new OAuth( false, $dbObject );

if( isset( $_GET['oauth_verifier'] ) && $_GET['oauth_verifier'] && $oauth->getOAuthError() === false ) {
	redirector:
	if( isset( $_GET['returnto'] ) ) {
		$url = $_GET['returnto'];
	} elseif( isset( $_POST['returnto'] ) ) {
		$url = $_GET['returnto'];
	} elseif( isset( $_SESSION['get']['returnto'] ) ) {
		$url = $_SESSION['get']['returnto'];
		unset( $_SESSION['get']['returnto'] );
	} elseif( isset( $_SESSION['post']['returnto'] ) ) {
		$url = $_SESSION['post']['returnto'];
		unset( $_SESSION['post']['returnto'] );
	} else $url = "oauthcallback.php";
	if( strpos( $url, "?" ) !== false ) {
		$url .= "&returnedfrom=oauthcallback";
	} else {
		$url .= "?returnedfrom=oauthcallback";
	}
	header( "Location: $url" );
	exit( 0 );
}

if( isset( $_GET['action'] ) || isset( $_POST['action'] ) ) {
	if( isset( $_GET['action'] ) ) {
		$action = $_GET['action'];
		unset( $_GET['action'] );
	}
	if( isset( $_GET['action'] ) ) {
		$action = $_POST['action'];
		unset( $_POST['action'] );
	}

	if( isset( $_GET['returnto'] ) || isset( $_POST['returnto'] ) ) {
		$oauth->mergeArguments();
	}

	switch( $action ) {
		case "login":
			if( $oauth->isLoggedOn() === false ) $oauth->authenticate( false );
			else goto redirector;
			break;
		case "logout":
			$oauth->logout();
			goto redirector;
			break;
	}
}

if( $oauth->getOAuthError() === false ) {
	echo "Logged on: ";
	var_dump( $oauth->isLoggedOn() );
	if( $oauth->isLoggedOn() === true ) echo "<a href=\"oauthcallback.php?action=logout\">Logout</a><br><br>";
	else echo "<a href=\"oauthcallback.php?action=login\">Login</a><br><br>";
	echo "Blocked: ";
	var_dump( $oauth->isBlocked() );
	echo "Is bot: ";
	var_dump( $oauth->isBot() );
	echo "User groups: ";
	var_dump( $oauth->getGroupRights() );
	echo "User name: ";
	var_dump( $oauth->getUsername() );
	echo "CSRF Session Token: ";
	var_dump( $oauth->getCSRFToken() );
	echo "OAuth Errors encountered: ";
	var_dump( $oauth->getOAuthError() );
	if( isset( $action ) && $action == "displaysessiondata" && isset( $_GET['token'] ) &&
	    $_GET['token'] == $oauth->getCSRFToken()
	) {
		echo "Session Data: ";
		if( isset( $_GET['format'] ) ) {
			switch( $_GET['format'] ) {
				case "php":
					echo serialize( $_SESSION );
					break;
				case "json":
					echo json_encode( $_SESSION );
					break;
				default:
					var_dump( $_SESSION );
					break;
			}
		} else {
			var_dump( $_SESSION );
		}
	}
	//file_put_contents( 'testSession', serialize( $_SESSION ) );
} else {
	echo "An error occurred connecting to your account: <br>";
	echo $oauth->getOAuthError();
	echo "<br><br><a href=\"oauthcallback.php?action=login\">Click here to try to login again!!!</a>";
}
