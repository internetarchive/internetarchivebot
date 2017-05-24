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

class DB2 {

	protected $db = false;

	public function __construct() {
		$this->db = mysqli_connect( HOST, USER, PASS, DB, PORT );
		mysqli_autocommit( $this->db, true );

		$this->createUserLogTable();
		$this->createUserTable();
		$this->createUserFlagsTable();
		$this->createBotQueueTable();
		$this->createFPReportTable();
	}

	protected function createUserLogTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_userlog` (
								  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(45) NOT NULL,
								  `locale` VARCHAR(45) NOT NULL,
								  `log_type` VARCHAR(45) NOT NULL,
								  `log_action` VARCHAR(45) NOT NULL,
								  `log_object` BIGINT(12) NOT NULL,
								  `log_object_text` BLOB,
								  `log_from` BLOB DEFAULT NULL,
								  `log_to` BLOB DEFAULT NULL,
								  `log_user` INT NOT NULL,
								  `log_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `log_reason` VARCHAR(255) NOT NULL DEFAULT '',
								  PRIMARY KEY (`log_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `LOCALE` (`locale` ASC),
								  INDEX `LOGTYPE` (`log_type` ASC),
								  INDEX `LOGACTION` (`log_action` ASC),
								  INDEX `LOGOBJECT` (`log_object` ASC),
								  INDEX `LOGUSER` (`log_user` ASC),
								  INDEX `LOGTIMESTAMP` (`log_timestamp` ASC),
								  INDEX `LOGSPECIFIC` (`log_type` ASC, `log_action` ASC, `log_object` ASC, `log_user` ASC ))
								AUTO_INCREMENT = 0;
							  "
		)
		) {
			echo "Failed to create a user log table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	protected function createUserTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_user` (
								  `user_id` INT UNSIGNED NOT NULL,
								  `wiki` VARCHAR(45) NOT NULL,
								  `user_name` VARCHAR(255) NOT NULL,
								  `last_login` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
								  `last_action` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
								  `blocked` INT NOT NULL DEFAULT 0,
								  `language` INT NOT NULL,
								  `data_cache` BLOB NOT NULL,
								  `user_link_id` INT UNSIGNED NOT NULL,
								  PRIMARY KEY (`wiki`, `user_id`),
								  INDEX `USERNAME` (`user_name` ASC),
								  INDEX `LASTLOGIN` (`last_login` ASC),
								  INDEX `LASTACTION` (`last_action` ASC),
								  INDEX `BLOCKED` (`blocked` ASC),
								  INDEX `LINKID` (`user_link_id` ASC))
							  "
		)
		) {
			echo "Failed to create a user table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	protected function createUserFlagsTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_userflags` (
								  `user_id` INT UNSIGNED NOT NULL,
								  `wiki` VARCHAR(45) NOT NULL,
								  `user_flag` VARCHAR(255) NOT NULL,
								  INDEX `USERID` (`wiki` ASC, `user_id` ASC),
								  INDEX `FLAGS` (`user_flag` ASC))
							  "
		)
		) {
			echo "Failed to create a user flags table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	protected function createBotQueueTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_botqueue` (
								  `queue_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(45) NOT NULL,
								  `queue_user` INT UNSIGNED NOT NULL,
								  `queue_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
								  `status_timestamp` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
								  `queue_status` INT NOT NULL DEFAULT 0,
								  `queue_pages` LONGBLOB NOT NULL,
								  `run_stats` BLOB NOT NULL,
								  `assigned_worker` VARCHAR(100),
								  `worker_finished` INT NOT NULL DEFAULT 0,
								  `worker_target` INT NOT NULL,
								  PRIMARY KEY (`queue_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `USER` (`queue_user` ASC),
								  INDEX `QUEUED` (`queue_timestamp` ASC),
								  INDEX `STATUSCHANGE` (`status_timestamp` ASC),
								  INDEX `STATUS` (`queue_status` ASC),
								  INDEX `RUNSIZE` (`worker_target` ASC),
								  INDEX `WORKER` (`assigned_worker` ASC))
							  "
		)
		) {
			echo "Failed to create a bot queue table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	protected function createFPReportTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_fpreports` (
								  `report_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(45) NOT NULL,
								  `report_user_id` INT UNSIGNED NOT NULL,
								  `report_url_id` INT UNSIGNED NOT NULL,
								  `report_error` BLOB NOT NULL DEFAULT '',
								  `report_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
								  `status_timestamp` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
								  `report_status` INT NOT NULL DEFAULT 0,
								  `report_version` VARCHAR(15) NOT NULL,
								  PRIMARY KEY (`report_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `USER` (`report_user_id` ASC),
								  INDEX `REPORTED` (`report_timestamp` ASC),
								  INDEX `STATUSCHANGE` (`status_timestamp` ASC),
								  INDEX `STATUS` (`report_status` ASC),
								  INDEX `VERSION` (`report_version` ASC))
							  "
		)
		) {
			echo "Failed to create a fp report table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	public function getUser( $userID, $wiki ) {
		$returnArray = [];

		$res = mysqli_query( $this->db,
		                     "SELECT * FROM externallinks_user LEFT JOIN externallinks_userpreferences ON externallinks_user.user_link_id=externallinks_userpreferences.user_link_id WHERE `user_id` = $userID AND `wiki` = '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "';"
		);

		if( $res && ( $result = mysqli_fetch_assoc( $res ) ) ) {
			$returnArray = $result;
			mysqli_free_result( $res );
		} else return $returnArray;

		$res = mysqli_query( $this->db,
		                     "SELECT * FROM externallinks_userflags WHERE `user_id` = " . $returnArray['user_link_id'] .
		                     " AND (`wiki` = '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "' OR `wiki` = 'global');"
		);

		$returnArray['rights'] = [ 'local' => [], 'global' => [] ];
		if( $res ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				if( $result['wiki'] == "global" ) $returnArray['rights']['global'][] = $result['user_flag'];
				else $returnArray['rights']['local'][] = $result['user_flag'];
			}
			mysqli_free_result( $res );
		}

		return $returnArray;
	}

	public function createUser( $userID, $wiki, $username, $logon, &$language, $cache ) {
		$sql =
			"SELECT * FROM externallinks_user LEFT JOIN externallinks_userpreferences ON externallinks_user.user_link_id=externallinks_userpreferences.user_link_id WHERE `user_name` = '" .
			$this->sanitize( $username ) . "';";
		if( $res = mysqli_query( $this->db, $sql ) ) {
			if( $result = mysqli_fetch_assoc( $res ) ) {
				mysqli_free_result( $res );
				$linkID = $result['user_link_id'];
				if( !is_null( $result['user_default_language'] ) ) $language = $result['user_default_language'];
			} else {
				$sql = "INSERT INTO externallinks_userpreferences (`user_link_id`) VALUES (DEFAULT);";
				if( mysqli_query( $this->db, $sql ) ) {
					$linkID = mysqli_insert_id( $this->db );
				} else return false;
			}
		} else return false;

		return mysqli_query( $this->db, "INSERT INTO externallinks_user ( `user_id`, `wiki`, `user_name`, 
		`last_login`, `language`, `data_cache`, `user_link_id` ) VALUES ( $userID, '" .
		                                mysqli_escape_string( $this->db, $wiki ) .
		                                "', '"
		                                . mysqli_escape_string(
			                                $this->db, $username
		                                ) . "', '" . date( 'Y-m-d H:i:s', $logon ) . "', '" .
		                                mysqli_escape_string( $this->db,
		                                                      $language
		                                ) . "', '"
		                                . mysqli_escape_string( $this->db, $cache ) . "', '$linkID' );"
		);
	}

	public function sanitize( $text ) {
		return mysqli_escape_string( $this->db, $text );
	}

	public function changeUser( $userID, $wiki, $values ) {
		$query = "UPDATE externallinks_user SET ";

		foreach( $values as $column => $value ) {
			$query .= "`" . mysqli_escape_string( $this->db, $column ) . "`='" .
			          mysqli_escape_string( $this->db, $value ) . "', ";
		}

		$query = substr( $query, 0, strlen( $query ) - 2 );
		$query .= " WHERE `user_id` = $userID AND `wiki` = '" . mysqli_escape_string( $this->db, $wiki ) . "';";

		return mysqli_query( $this->db, $query );
	}

	public function removeFlags( $userID, $wiki, $flags ) {
		foreach( $flags as $flag ) {
			$res = mysqli_query( $this->db,
			                     "DELETE FROM externallinks_userflags WHERE `user_id` = $userID AND `wiki` = '" .
			                     mysqli_escape_string( $this->db, $wiki ) . "' AND `user_flag` = '" .
			                     mysqli_escape_string( $this->db, $flag ) . "';"
			);
			if( !$res ) return false;
		}

		return true;
	}

	public function addFlags( $userID, $wiki, $flags ) {
		foreach( $flags as $flag ) {
			$res = mysqli_query( $this->db,
			                     "INSERT INTO externallinks_userflags ( `user_id`, `wiki`, `user_flag` ) VALUES ( $userID, '" .
			                     mysqli_escape_string( $this->db, $wiki ) . "', '" .
			                     mysqli_escape_string( $this->db, $flag ) . "' );"
			);
			if( !$res ) return false;
		}

		return true;
	}

	public function insertLogEntry( $wiki, $locale, $type, $action, $object, $objectText, $user, $from = null,
	                                $to = null, $reason = ""
	) {
		return mysqli_query( $this->db,
		                     "INSERT INTO externallinks_userlog ( `wiki`, `locale`, `log_type`, `log_action`, `log_object`, `log_object_text`, `log_user`, `log_from`, `log_to`, `log_reason` ) VALUES ( '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "', '" .
		                     mysqli_escape_string( $this->db, $locale ) . "', '" .
		                     mysqli_escape_string( $this->db, $type ) . "', '" .
		                     mysqli_escape_string( $this->db, $action ) . "', '" .
		                     mysqli_escape_string( $this->db, $object ) . "', '" .
		                     mysqli_escape_string( $this->db, $objectText ) . "', '" .
		                     mysqli_escape_string( $this->db, $user ) . "', " .
		                     ( !is_null( $from ) ? "'" . mysqli_escape_string( $this->db, $from ) . "'" : "DEFAULT" ) .
		                     ", " .
		                     ( !is_null( $to ) ? "'" . mysqli_escape_string( $this->db, $to ) . "'" : "DEFAULT" ) .
		                     ", '" .
		                     mysqli_escape_string( $this->db, $reason ) . "' );"
		);
	}

	public function insertFPReport( $wiki, $user, $urlID, $version, $error = "" ) {
		return mysqli_query( $this->db,
		                     "INSERT INTO externallinks_fpreports ( `wiki`, `report_url_id`, `report_user_id`, `report_version`, `report_error` ) VALUES ( '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "', '" .
		                     mysqli_escape_string( $this->db, $urlID ) . "', '" .
		                     mysqli_escape_string( $this->db, $user ) . "', '" .
		                     mysqli_escape_string( $this->db, $version ) . "', '" .
		                     mysqli_escape_string( $this->db, $error ) . "' );"
		);
	}

	public function queueBot( $wiki, $user, $articles ) {
		return mysqli_query( $this->db,
		                     "INSERT INTO externallinks_botqueue ( `wiki`, `queue_user`, `queue_pages` ) VALUES ( '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "', '" .
		                     mysqli_escape_string( $this->db, $user ) . "', '" .
		                     mysqli_escape_string( $this->db, serialize( $articles ) ) . "' );"
		);
	}

	public function queryDB( $query ) {
		$response = mysqli_query( $this->db, $query );
		if( $response === false && $this->getError() == 2006 ) {
			$this->reconnect();
			$response = mysqli_query( $this->db, $query );
		}
		return $response;
	}

	public function getInsertID() {
		return mysqli_insert_id( $this->db );
	}

	public function getAffectedRows() {
		return mysqli_affected_rows( $this->db );
	}

	protected function createUserPreferencesTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_userpreferences` (
								  `user_link_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `user_email` BLOB NULL,
								  `user_email_confirmed` INT NOT NULL DEFAULT 0,
								  `user_email_confirm_hash` VARCHAR(32) NULL,
								  `user_email_fpreport` INT NOT NULL DEFAULT 0,
								  `user_email_blockstatus` INT NOT NULL DEFAULT 1,
								  `user_email_permissions` INT NOT NULL DEFAULT 1,
								  `user_email_fpreportstatusfixed` INT NOT NULL DEFAULT 1,
								  `user_email_fpreportstatusdeclined` INT NOT NULL DEFAULT 1,
								  `user_email_fpreportstatusopened` INT NOT NULL DEFAULT 1,
								  `user_email_bqstatuscomplete` INT NOT NULL DEFAULT 1,
								  `user_email_bqstatuskilled` INT NOT NULL DEFAULT 1,
								  `user_email_bqstatussuspended` INT NOT NULL DEFAULT 1,
								  `user_email_bqstatusresume` INT NOT NULL DEFAULT 1,
								  `user_default_wiki` VARCHAR(45) NULL,
								  `user_default_language` VARCHAR(45) NULL,
								  `user_default_theme` VARCHAR(45) NULL,
								  PRIMARY KEY (`user_link_id`),
								  INDEX `HASEMAIL` (`user_email_confirmed` ASC))
							  "
		)
		) {
			echo "Failed to create a user table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	public function getError( $text = false ) {
		if( $text === false ) return mysqli_errno( $this->db );
		else return mysqli_error( $this->db );
	}

	public function reconnect() {
		mysqli_close( $this->db );
		$this->db = mysqli_connect( HOST, USER, PASS, DB, PORT );
		mysqli_autocommit( $this->db, true );
	}

	public function __destruct() {
		mysqli_close( $this->db );
	}
}