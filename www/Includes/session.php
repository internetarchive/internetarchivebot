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

class Session {

	protected $sessionDBObject = false;

	protected static $cookieSent = false;

	public function __construct() {
		global $sessionHttpOnly, $sessionLifeTime, $sessionSecure, $sessionUseDB;

		ini_set( "session.gc_maxlifetime", strtotime( $sessionLifeTime ) - time() );
		ini_set( "session.cookie_lifetime", strtotime( $sessionLifeTime ) - time() );
		ini_set( "session.cookie_httponly", $sessionHttpOnly );

		if( $sessionUseDB !== false ) {
			// set our custom session functions.
			session_set_save_handler( [ $this, 'open' ], [ $this, 'close' ], [ $this, 'read' ], [ $this, 'write' ],
			                          [ $this, 'destroy' ], [ $this, 'gc' ]
			);

			// the following prevents unexpected effects when using objects as save handlers
			register_shutdown_function( 'session_write_close' );

			// Hash algorithm to use for the session. (use hash_algos() to get a list of available hashes.)
			$session_hash = 'sha512';

			// Check if hash is available
			if( in_array( $session_hash, hash_algos() ) ) {
				// Set the has function.
				ini_set( 'session.hash_function', $session_hash );
			}
			// How many bits per character of the hash.
			// The possible values are '4' (0-9, a-f), '5' (0-9, a-v), and '6' (0-9, a-z, A-Z, "-", ",").
			ini_set( 'session.hash_bits_per_character', 5 );

			// Force the session to only use cookies, not URL variables.
			ini_set( 'session.use_only_cookies', 1 );
		}

		session_name( "IABotManagementConsole" );

		// The Cache-Control header affects whether form data is saved, and whether changes to the HTML are re-fetched.
		session_cache_limiter( '' );
		header( 'Cache-Control: private, no-store, must-revalidate' );

		// Get session cookie parameters
		$cookieParams = session_get_cookie_params();
		//session_regenerate_id( true );
		session_set_cookie_params( strtotime( $sessionLifeTime ) - time(), dirname( $_SERVER['SCRIPT_NAME'] ),
		                           $cookieParams["domain"], $sessionSecure, $sessionHttpOnly
		);
	}

	public function start() {
		global $sessionHttpOnly, $sessionLifeTime, $sessionSecure;

		session_start();

		header( 'Cache-Control: no-store, must-revalidate', true );

		$cookieParams = session_get_cookie_params();
		if( self::$cookieSent === false ) {
			setcookie( session_name(), session_id(), strtotime( $sessionLifeTime ), dirname( $_SERVER['SCRIPT_NAME'] ),
			           $cookieParams["domain"], $sessionSecure, $sessionHttpOnly
			);
			self::$cookieSent = true;
		}
	}

	public function open() {
		global $sessionDB, $sessionHost, $sessionPort, $sessionPass, $sessionUser;

		$this->sessionDBObject = mysqli_connect( $sessionHost, $sessionUser, $sessionPass, $sessionDB, $sessionPort );

		return $this->createSessionsTable();;
	}

	protected function createSessionsTable() {
		if( !mysqli_query( $this->sessionDBObject, "CREATE TABLE IF NOT EXISTS `externallinks_sessions` (
								  `id` CHAR(128) NOT NULL,
								  `set_time` CHAR(10) NOT NULL,
								  `data` LONGBLOB NOT NULL,
								  `session_key` CHAR(128) NOT NULL,
								  PRIMARY KEY (`id`),
								  INDEX `TIME` (`set_time` ASC) )
							"
		)
		) {
			return false;
		}

		return true;
	}

	public function close() {
		mysqli_close( $this->sessionDBObject );
		unset( $this->read_stmt, $this->key_stmt, $this->w_stmt, $this->delete_stmt, $this->gc_stmt );

		return true;
	}

	public function read( $id ) {
		if( !isset( $this->read_stmt ) ) {
			$this->read_stmt =
				mysqli_prepare( $this->sessionDBObject, "SELECT data FROM externallinks_sessions WHERE id = ? LIMIT 1"
				);
		}
		mysqli_stmt_bind_param( $this->read_stmt, 's', $id );
		mysqli_stmt_execute( $this->read_stmt );
		mysqli_stmt_store_result( $this->read_stmt );
		mysqli_stmt_bind_result( $this->read_stmt, $data );
		mysqli_stmt_fetch( $this->read_stmt );

		$key = $this->getkey( $id );
		$data = $this->decrypt( $data, $key );

		return $data;
	}

	private function getkey( $id ) {
		if( !isset( $this->key_stmt ) ) {
			$this->key_stmt =
				mysqli_prepare( $this->sessionDBObject,
				                "SELECT session_key FROM externallinks_sessions WHERE id = ? LIMIT 1"
				);
		}
		mysqli_stmt_bind_param( $this->key_stmt, 's', $id );
		mysqli_stmt_execute( $this->key_stmt );
		mysqli_stmt_store_result( $this->key_stmt );
		if( $this->key_stmt->num_rows == 1 ) {
			mysqli_stmt_bind_result( $this->key_stmt, $key );
			mysqli_stmt_fetch( $this->key_stmt );
			$this->sessionRawKey = $key;
			$key = hash( 'sha512', $this->sessionRawKey . SESSIONSECRET );

			return $key;
		} else {
			if( !isset( $this->sessionRawKey ) ) $this->sessionRawKey = uniqid( mt_rand( 1, mt_getrandmax() ), true );
			$random_key = hash( 'sha512', $this->sessionRawKey . SESSIONSECRET );

			return $random_key;
		}
	}

	private function decrypt( $data, $key ) {
		$salt = 'cH!swe!retReGu7W6bEDRup7usuDUh9THeD2CHeGE*ewr4n39=E@rAsp7c-Ph@pH';
		$iv_size = openssl_cipher_iv_length( "AES-256-CBC" );
		$hash = hash( 'sha256', $salt . $key . $salt );
		$iv = substr( $hash, strlen( $hash ) - $iv_size );
		$key = substr( $hash, 0, 32 );
		$decrypted = openssl_decrypt( base64_decode( $data ), "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv );
		$decrypted = rtrim( $decrypted, "\0" );

		return $decrypted;
	}

	public function write( $id, $data ) {
		// Get unique key
		$key = $this->getkey( $id );
		// Encrypt the data
		$data = $this->encrypt( $data, $key );

		$time = time();

		if( !isset( $this->w_stmt ) ) {
			$this->w_stmt = mysqli_prepare( $this->sessionDBObject,
			                                "REPLACE INTO externallinks_sessions (id, set_time, data, session_key) VALUES (?, ?, ?, ?)"
			);
		}

		mysqli_stmt_bind_param( $this->w_stmt, 'siss', $id, $time, $data, $this->sessionRawKey );
		mysqli_stmt_execute( $this->w_stmt );

		return true;
	}

	private function encrypt( $data, $key ) {
		$salt = 'cH!swe!retReGu7W6bEDRup7usuDUh9THeD2CHeGE*ewr4n39=E@rAsp7c-Ph@pH';
		$iv_size = openssl_cipher_iv_length( "AES-256-CBC" );
		$hash = hash( 'sha256', $salt . $key . $salt );
		$iv = substr( $hash, strlen( $hash ) - $iv_size );
		$key = substr( $hash, 0, 32 );
		$encrypted = base64_encode( openssl_encrypt( $data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv ) );

		return $encrypted;
	}

	public function destroy( $id ) {
		if( !isset( $this->delete_stmt ) ) {
			$this->delete_stmt =
				mysqli_prepare( $this->sessionDBObject, "DELETE FROM externallinks_sessions WHERE id = ?" );
		}
		mysqli_stmt_bind_param( $this->delete_stmt, 's', $id );
		mysqli_stmt_execute( $this->delete_stmt );

		return true;
	}

	public function gc( $max ) {
		if( !isset( $this->gc_stmt ) ) {
			$this->gc_stmt =
				mysqli_prepare( $this->sessionDBObject, "DELETE FROM externallinks_sessions WHERE set_time < ?" );
		}
		$old = time() - $max;
		mysqli_stmt_bind_param( $this->gc_stmt, 's', $old );
		mysqli_stmt_execute( $this->gc_stmt );

		return true;
	}
}