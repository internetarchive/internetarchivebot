<?php
class DB {
	
	/**
	 * Stores the cached database for a fetched page
	 *
	 * @var array
	 * @access public
	 */	
	public $dbValues = array();
	
	protected $db;
	
	protected $cachedPageResults;
	
	public $commObject;
	
	public function __construct( $commObject ) {
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
	
	protected function sanitizeValues( $values ) {
		$returnArray = array();
		foreach( $values as $id=>$value ) {
			$returnArray[mysqli_escape_string( $this->db, $id )] = mysqli_escape_string( $this->db, $value );
		}
		return $returnArray;
	}
	
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
	
	public static function getUnarchivable() {
		$out = "";
		if( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) {
	        $res = mysqli_query( $db, "SELECT * FROM externallinks_".WIKIPEDIA." WHERE `archivable` = '0' AND `reviewed` = '0';" );
	        $query = "";
	        if( $res !== false ) while( $tmp = mysqli_fetch_assoc( $res ) ) {
	            $out .= "\n*{$tmp['url']} with error '''{$tmp['archive_failure']}'''";
	            $query .= "UPDATE externallinks_".WIKIPEDIA." SET `reviewed` = '1' WHERE `url` = '{$tmp['url']}'; ";       
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
	
	//SQL related stuff
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
	
	public function retrieveDBValues( $link, $tid ) {
		foreach( $this->cachedPageResults as $i=>$value ) {
	        if( $value['url'] == $link['url']) {
	            $this->dbValues[$tid] = $value;
	            $this->cachedPageResults[$i]['nodelete'] = true;
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
	        }
	        $this->dbValues[$tid]['live_state'] = 4;
	    }
	    if( $link['has_archive'] === true && $link['archive_url'] != $this->dbValues[$tid]['archive_url'] ) {
	        $this->dbValues[$tid]['archivable'] = $this->dbValues[$tid]['archived'] = $this->dbValues[$tid]['has_archive'] = 1;
	        $this->dbValues[$tid]['archive_url'] = $link['archive_url'];
	        $this->dbValues[$tid]['archive_time'] = $link['archive_time'];
	        if( !isset( $this->dbValues[$tid]['create'] ) ) $this->dbValues[$tid]['update'] = true;
	    }		
	}
	
	public function closeResource() {
		mysqli_close( $this->db );
	}
}