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

/**
* @file
* DB object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* DB class
* Manages all DB related parts of the bot
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class DB {
	
	/**
	 * Stores the cached database for a fetched page
	 *
	 * @var array
	 * @access public
	 */	
	public $dbValues = array();
	
	/**
	* Stores the mysqli db resource
	* 
	* @var mysqli
	* @access protected
	*/
	protected $db;
	
	/**
	* Stores the cached DB values for a given page
	* 
	* @var array
	* @access protected
	*/
	protected $cachedPageResults;
	
	/**
	* Stores the API object
	* 
	* @var API
	* @access public
	*/
	public $commObject;
	
	/**
	* Constructor of the DB class
	* 
	* @param API $commObject
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __construct( API $commObject ) {
		$this->db = mysqli_connect( HOST, USER, PASS, DB, PORT );
		$this->commObject = $commObject;
		if( $this->db === false ) {
			echo "Unable to connect to the database.  Exiting...";
			exit(20000);
		}
		$res = mysqli_query( $this->db, "SELECT * FROM externallinks_".WIKIPEDIA." WHERE `pageid` = '{$this->commObject->pageid}';" ); 
		if( $res !== false ) {
			$this->cachedPageResults = mysqli_fetch_all( $res, MYSQLI_ASSOC );
			mysqli_free_result( $res );
		}
	}
	
	/**
	* mysqli escape an array of values including the keys
	* 
	* @param array $values Values of the mysqli query
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Sanitized values
	*/
	protected function sanitizeValues( $values ) {
		$returnArray = array();
		foreach( $values as $id=>$value ) {
			if( !is_null( $value ) ) $returnArray[mysqli_escape_string( $this->db, $id )] = mysqli_escape_string( $this->db, $value );
		}
		return $returnArray;
	}
	
	/**
	* Insert contents of self::dbValues back into the DB
	* and delete the unused cached values
	* 
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function updateDBValues() {
		$query = "";
		if( !empty( $this->dbValues ) ) {
			foreach( $this->dbValues as $id=>$values ) {
				if( isset( $values['create'] ) ) {
					unset( $values['create'] );
					$values = $this->sanitizeValues( $values );
					$query .= "INSERT INTO `externallinks_".WIKIPEDIA."` (`";
					$query .= implode( "`, `", array_keys( $values ) );
					$query .= "`, `pageid`) VALUES ('";
					$query .= implode( "', '", $values );
					$query .= "', '{$this->commObject->pageid}'); ";
				} elseif( isset( $values['update'] ) ) {
					unset( $values['update'] );
					$values = $this->sanitizeValues( $values );
					$query .= "UPDATE `externallinks_".WIKIPEDIA."` SET ";
					foreach( $values as $column=>$value ) {
						if( $column == 'url' ) continue;
						$first = false; 
						$query .= "`$column` = '$value'";
						$query .= ", ";
					}
					$query .= "`pageid` = '{$this->commObject->pageid}' WHERE `url` = '{$values['url']}'; ";
				}
			}
		}
		if( !empty( $this->cachedPageResults ) ) {
			foreach( $this->cachedPageResults as $id=>$values ) {
				$values = $this->sanitizeValues( $values );
				if( !isset( $values['nodelete'] ) ) {
					$query .= "DELETE FROM `externallinks_".WIKIPEDIA."` WHERE `url` = '{$values['url']}'; ";
				}
			}
		}
		if( $query !== "" ) {
			$res = mysqli_multi_query( $this->db, $query );
			if( $res === false ) {
				echo "ERROR: ".mysqli_errno( $this->db ).": ".mysqli_error( $this->db )."\n";
			}
			while( mysqli_more_results( $this->db ) ) {
				$res = mysqli_next_result( $this->db );
				if( $res === false ) {
					echo "ERROR: ".mysqli_errno( $this->db ).": ".mysqli_error( $this->db )."\n";
				}
			}
		}
	}
	
	/**
	* Retrieve all unchecked links that failed to archive
	* and generate an error list for onwiki usage
	* 
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string Error list formatted in wiki markup
	*/
	public static function getUnarchivable() {
		$out = "";
		if( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) {
			$res = mysqli_query( $db, "SELECT * FROM externallinks_".WIKIPEDIA." WHERE `archivable` = '0' AND `reviewed` = '0';" );
			$query = "";
			if( $res !== false ) while( $tmp = mysqli_fetch_assoc( $res ) ) {
				$out .= "\n*{$tmp['url']} with error '''{$tmp['archive_failure']}'''";
				$query .= "UPDATE externallinks_".WIKIPEDIA." SET `reviewed` = '1' WHERE `url` = '".mysqli_escape_string( $db, $tmp['url'] )."'; ";	   
			}
			mysqli_free_result( $res );
			if( !empty( $query ) ) {
				$res = mysqli_multi_query( $db, $query );
				if( $res === false ) echo "Error ".mysqli_errno( $db ).": ".mysqli_error( $db )."\n";
				while( mysqli_more_results( $db ) ) {
					$res = mysqli_next_result( $db );
					if( $res === false ) echo "Error ".mysqli_errno( $db ).": ".mysqli_error( $db )."\n";
				}
			}
			mysqli_close( $db );
		} else {
			echo "Unable to connect to the database.  Exiting...";
			exit(20000);
		}
		return $out;
	}
	
	/**
	* Checks for the existence of the needed tables
	* and creates them if they don't exist.
	* Program dies on failure.
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public static function checkDB() {
		if( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) {
			if( $res = mysqli_query( $db, "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".DB."' AND  TABLE_NAME = 'externallinks_".WIKIPEDIA."';" ) ) {
				if( mysqli_num_rows( $res ) == 0 ) {
					echo "Creating the necessary table for use...\n";
					createELTable();
				}
				mysqli_free_result( $res );
			} else {
				echo "Unable to query the database.  Exiting...";
				exit(30000);
			}
			mysqli_close( $db );
			unset( $res, $db );
		} else {
			echo "Unable to connect to the database.  Exiting...";
			exit(20000);
		}
	}
	
	/**
	* Create the wiki specific externallinks table
	* Kills the program on failure
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public static function createELTable() {
		if( ( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) !== false ) {
			if( mysqli_query( $db, "CREATE TABLE `externallinks_".WIKIPEDIA."` (
								  `pageid` INT(12) UNSIGNED NOT NULL,
								  `url` VARCHAR(767) NOT NULL,
								  `archive_url` BLOB NULL,
								  `has_archive` INT(1) UNSIGNED NOT NULL DEFAULT 0,
								  `live_state` INT(1) UNSIGNED NOT NULL DEFAULT 4,
								  `archivable` INT(1) UNSIGNED NOT NULL DEFAULT 1,
								  `archived` INT(1) UNSIGNED NOT NULL DEFAULT 2,
								  `archive_failure` BLOB NULL,
								  `access_time` INT(10) UNSIGNED NOT NULL,
								  `archive_time` INT(10) UNSIGNED NULL,
								  `reviewed` INT(1) UNSIGNED NOT NULL DEFAULT 0,
								  UNIQUE INDEX `url_UNIQUE` (`url` ASC),
								  PRIMARY KEY (`url`, `pageid`, `live_state`, `archived`, `reviewed`, `archivable`),
								  INDEX `LIVE_STATE` (`live_state` ASC),
								  INDEX `REVIEWED` (`reviewed` ASC),
								  INDEX `ARCHIVED` (`archived` ASC, `archivable` ASC),
								  INDEX `URL` (`url` ASC),
								  INDEX `PAGEID` (`pageid` ASC));
								  ") ) echo "Successfully created an external links table for ".WIKIPEDIA."\n\n";
			else {
				echo "Failed to create an externallinks table to use.\nThis table is vital for the operation of this bot. Exiting...";
				exit( 10000 );
			}  
		} else {
			echo "Failed to establish a database connection.  Exiting...";
			exit( 20000 );
		}
		mysqli_close( $db );
		unset( $db );
	}
	
	/**
	* Retrieves specific information regarding a link and stores it in self::dbValues
	* Attempts to retrieve it from cache first
	* 
	* @param string $link URL to fetch info about
	* @param int $tid Key ID to preserve array keys
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function retrieveDBValues( $link, $tid ) {
		foreach( $this->cachedPageResults as $i=>$value ) {
			if( $value['url'] == $link['url']) {
				$this->dbValues[$tid] = $value;
				$this->cachedPageResults[$i]['nodelete'] = true;
				if( isset( $this->dbValues[$tid]['nodelete'] ) ) unset( $this->dbValues[$tid]['nodelete'] );
				break;
			}
		}
		
		if( !isset( $this->dbValues[$tid] ) ) {
			$res = mysqli_query( $this->db, "SELECT * FROM externallinks_".WIKIPEDIA." WHERE `url` = '".mysqli_escape_string( $this->db, $link['url'] )."';" );
			if( mysqli_num_rows( $res ) > 0 ) {
				$this->dbValues[$tid] = mysqli_fetch_assoc( $res );
			}
			mysqli_free_result( $res );
		}
		
		if( !isset( $this->dbValues[$tid] ) ) {
			$this->dbValues[$tid]['create'] = true;
			$this->dbValues[$tid]['url'] = $link['url'];
			if( $link['has_archive'] === true ) {
				$this->dbValues[$tid]['archivable'] = $this->dbValues[$tid]['archived'] = $this->dbValues[$tid]['has_archive'] = 1;
				$this->dbValues[$tid]['archive_url'] = $link['archive_url'];
				$this->dbValues[$tid]['archive_time'] = $link['archive_time'];
				$this->dbValues[$tid]['archivable'] = 1;
				$this->dbValues[$tid]['archived'] = 1;
			}
			$this->dbValues[$tid]['live_state'] = 4;
		}
		if( $link['has_archive'] === true && $link['archive_url'] != $this->dbValues[$tid]['archive_url'] ) {
			$this->dbValues[$tid]['archivable'] = $this->dbValues[$tid]['archived'] = $this->dbValues[$tid]['has_archive'] = 1;
			$this->dbValues[$tid]['archive_url'] = $link['archive_url'];
			$this->dbValues[$tid]['archive_time'] = $link['archive_time'];
			$this->dbValues[$tid]['archivable'] = 1;
			$this->dbValues[$tid]['archived'] = 1;
			if( !isset( $this->dbValues[$tid]['create'] ) ) $this->dbValues[$tid]['update'] = true;
		}		
	}
	
	/**
	* close the DB handle
	* 
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function closeResource() {
		mysqli_close( $this->db );
		$this->commObject = null;
	}
}