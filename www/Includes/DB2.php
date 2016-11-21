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

class DB2 {

	protected $db = false;

	public function __construct() {
		$this->db = mysqli_connect( HOST, USER, PASS, DB, PORT );

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
								  PRIMARY KEY (`wiki`, `user_id`),
								  INDEX `USERNAME` (`user_name` ASC),
								  INDEX `LASTLOGIN` (`last_login` ASC),
								  INDEX `LASTACTION` (`last_action` ASC),
								  INDEX `BLOCKED` (`blocked` ASC))
							  "
		)
		) {
			echo "Failed to create a user table to use.\nThis table is vital for the operation of this interface. Exiting...";
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
								  `queue_pages` BLOB NOT NULL,
								  `assigned_worker` VARCHAR(100),
								  `worker_finished` INT NOT NULL DEFAULT 0,
								  `worker_target` INT NOT NULL,
								  PRIMARY KEY (`queue_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `USER` (`queue_user` ASC),
								  INDEX `QUEUED` (`queue_timestamp` ASC),
								  INDEX `STATUSCHANGE` (`status_timestamp` ASC),
								  INDEX `STATUS` (`queue_status` ASC),
								  INDEX `RUNSIZE` (`worker_target` ASC))
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

	public function getUser( $userID, $wiki ) {
		$returnArray = [];

		$res = mysqli_query( $this->db, "SELECT * FROM externallinks_user WHERE `user_id` = $userID AND `wiki` = '" .
		                                mysqli_escape_string( $this->db, $wiki ) . "';"
		);

		if( $res && ( $result = mysqli_fetch_assoc( $res ) ) ) {
			$returnArray = $result;
			mysqli_free_result( $res );
		} else return $returnArray;

		$res = mysqli_query( $this->db,
		                     "SELECT user_flag FROM externallinks_userflags WHERE `user_id` = $userID AND `wiki` = '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "';"
		);

		$returnArray['rights'] = [];
		if( $res ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$returnArray['rights'][] = $result['user_flag'];
			}
			mysqli_free_result( $res );
		}

		return $returnArray;
	}

	public function createUser( $userID, $wiki, $username, $logon, $language, $cache ) {
		return mysqli_query( $this->db, "INSERT INTO externallinks_user ( `user_id`, `wiki`, `user_name`, 
		`last_login`, `language`, `data_cache` ) VALUES ( $userID, '" . mysqli_escape_string( $this->db, $wiki ) .
		                                "', '"
		                                . mysqli_escape_string(
			                                $this->db, $username
		                                ) . "', '" . date( 'Y-m-d H:i:s', $logon ) . "', '" .
		                                mysqli_escape_string( $this->db,
		                                                      $language
		                                ) . "', '"
		                                . mysqli_escape_string( $this->db, $cache ) . "' );"
		);
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

	public function insertLogEntry( $wiki, $type, $action, $object, $objectText, $user, $from = null, $to = null, $reason = "" ) {
		return mysqli_query( $this->db,
		                     "INSERT INTO externallinks_userlog ( `wiki`, `log_type`, `log_action`, `log_object`, `log_object_text`, `log_user`, `log_from`, `log_to`, `log_reason` ) VALUES ( '" .
		                     mysqli_escape_string( $this->db, $wiki ) . "', '" .
		                     mysqli_escape_string( $this->db, $type ) . "', '" .
		                     mysqli_escape_string( $this->db, $action ) . "', '" .
		                     mysqli_escape_string( $this->db, $object ) . "', '" .
		                     mysqli_escape_string( $this->db, $objectText ) . "', '" .
		                     mysqli_escape_string( $this->db, $user ) . "', " .
		                     ( !is_null( $from ) ? "'" . mysqli_escape_string( $this->db, $from ) . "'" : "DEFAULT" ) .
		                     ", " .
		                     ( !is_null( $to ) ? "'" . mysqli_escape_string( $this->db, $to ) . "'" : "DEFAULT" ) .
		                     ", '" .
							 mysqli_escape_string( $this->db, $reason ) ."' );"
		);
	}

	public function insertFPReport( $wiki, $user, $urlID, $version ) {
		return mysqli_query( $this->db,
							 "INSERT INTO externallinks_fpreports ( `wiki`, `report_url_id`, `report_user_id`, `report_version` ) VALUES ( '" .
							 mysqli_escape_string( $this->db, $wiki ) . "', '" .
							 mysqli_escape_string( $this->db, $urlID ) . "', '" .
							 mysqli_escape_string( $this->db, $user ) . "', '" .
							 mysqli_escape_string( $this->db, $version ) . "' );"
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
		return mysqli_query( $this->db, $query );
	}

	public function sanitize( $text ) {
		return mysqli_escape_string( $this->db, $text );
	}
}