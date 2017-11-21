<?php
//
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// This file defines the interface iXHProfRuns and also provides a default
// implementation of the interface (class XHProfRuns).
//

/**
 * iXHProfRuns interface for getting/saving a XHProf run.
 *
 * Clients can either use the default implementation,
 * namely XHProfRuns_Default, of this interface or define
 * their own implementation.
 *
 * @author Kannan
 */
interface iXHProfRuns {

	/**
	 * Returns XHProf data given a run id ($run) of a given
	 * type ($type).
	 *
	 * Also, a brief description of the run is returned via the
	 * $run_desc out parameter.
	 */
	public function get_run( $run_id, $type, &$run_desc );

	/**
	 * Save XHProf data for a profiler run of specified type
	 * ($type).
	 *
	 * The caller may optionally pass in run_id (which they
	 * promise to be unique). If a run_id is not passed in,
	 * the implementation of this method must generated a
	 * unique run id for this saved XHProf run.
	 *
	 * Returns the run id for the saved XHProf run.
	 *
	 */
	public function save_run( $xhprof_data, $type, $run_id = null );
}

class XHProfRuns_Default implements iXHProfRuns {

	private $db = false;

	public function __construct() {
		$this->db = mysqli_connect( HOST, USER, PASS, DB, PORT );
		$this->checkTable();
	}

	private function checkTable() {
		if( !mysqli_query( $this->db, "CREATE TABLE IF NOT EXISTS `externallinks_profiledata` (
								  `run_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `type` VARCHAR(255) NOT NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `profile_data` LONGBLOB NULL,
								  PRIMARY KEY (`run_id`),
								  INDEX `TYPE` (`type` ASC))
							  "
		)
		) {
			echo "Failed to create an XHProf table to use.\nThis table is vital for the operation of this interface. Exiting...";
			exit( 10000 );
		}
	}

	public function get_run( $run_id, $type = "", &$run_desc ) {
		$sql =
			"SELECT * FROM externallinks_profiledata WHERE `run_id` = " . mysqli_escape_string( $this->db, $run_id ) .
			";";

		if( !( $res = mysqli_query( $this->db, $sql ) ) || !( $result = mysqli_fetch_assoc( $res ) ) ) {
			xhprof_error( "Could not find run $run_id" );
			$run_desc = "Invalid Run Id = $run_id";

			return null;
		}

		$contents = $result['profile_data'];
		$type = $result['type'];
		$run_desc = "Parser::AnalyzePage on $type";

		return unserialize( $contents );
	}

	public function save_run( $xhprof_data, $type, $run_id = null ) {

		// Use PHP serialize function to store the XHProf's
		// raw profiler data.
		$xhprof_data = serialize( $xhprof_data );

		if( !is_null( $run_id ) ) $sql = "REPLACE INTO externallinks_profiledata (`type`, `profile_data`) VALUES ('" .
		                                 mysqli_escape_string( $this->db, $type ) . "', '" .
		                                 mysqli_escape_string( $this->db, $xhprof_data ) . "') WHERE `run_id` = " .
		                                 mysqli_escape_string( $this->db, $run_id ) . ";";
		else $sql = "INSERT INTO externallinks_profiledata (`type`, `profile_data`) VALUES ('" .
		            mysqli_escape_string( $this->db, $type ) . "', '" .
		            mysqli_escape_string( $this->db, $xhprof_data ) . "');";

		if( mysqli_query( $this->db, $sql ) === false ) {
			xhprof_error( "Could not save " . ( is_null( $run_id ) ? "new run" : $run_id ) . "\n" );
		}

		if( $run_id === null ) {
			$run_id = mysqli_insert_id( $this->db );
		}

		return $run_id;
	}

	function list_runs() {
		$returnText = "";

		$sql = "SELECT `run_id`, `type`, `timestamp` FROM externallinks_profiledata ORDER BY `run_id` DESC LIMIT 500;";
		if( $res = mysqli_query( $this->db, $sql ) ) {
			$runs = [];
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$runs[$result['run_id']] = [ 'type' => $result['type'], 'timestamp' => $result['timestamp'] ];
			}
			mysqli_free_result( $res );
			ksort( $runs );
			$returnText .= "<hr/><div class='col-md-8'>Existing runs:\n<ul>\n";
			foreach( $runs as $run => $data ) {
				$returnText .= '<li><a href="' . htmlentities( $_SERVER['SCRIPT_NAME'] )
				               . '?page=performancemetrics&run=' . htmlentities( $run ) . '&source='
				               . htmlentities( $data['type'] ) . '">'
				               . "Run " . htmlentities( $run ) . ": " . htmlentities( $data['type'] ) . "</a><small> "
				               . $data['timestamp'] . "</small></li>\n";
			}
			$returnText .= "</ul></div>\n";

			$returnText .= "<div class='col-md-4'><form name='compare' id='compare' method='get' action='index.php'>Compare:\n<ul>\n";
			$returnText .= "<div class='form-inline'><li>Run 1: <input type=\"text\" class=\"form-control\" name=\"run1\" id=\"run1\" placeholder=\"Run ID for first run\"></li></div>";
			$returnText .= "<div class='form-inline'><li>Run 2: <input type=\"text\" class=\"form-control\" name=\"run2\" id=\"run2\" placeholder=\"Run ID for second run\"></li></div></ul>";
			$returnText .= "<button type=\"submit\" class=\"btn btn-primary\" id=\"submitdata\">Compare</button>\n";
			$returnText .= "<input type=\"hidden\" value=\"performancemetrics\" name=\"page\"></form></div>";
		}

		return $returnText;
	}

	function __destruct() {
		mysqli_close( $this->db );
	}
}
