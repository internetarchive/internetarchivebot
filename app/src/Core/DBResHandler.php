<?php

class DBResHandler {
	private $resObjects;

	public function addResultObject( mysqli_result $res ) {
		$this->resObjects[] = $res;
	}

	public function num_rows() {
		$num = 0;
		foreach( $this->resObjects as $res ) {
			$num += mysqli_num_rows( $res );
		}

		return $num;
	}

	public function fetch_assoc() {
		foreach( $this->resObjects as $res ) {
			if( ($return = mysqli_fetch_assoc( $res )) !== false && !is_null( $return ) ) {
				return $return;
			} else {
				$this->free_object( $res );
			}
		}

		return $return;
	}

	public function fetch_all( $mode = MYSQLI_NUM ) {
		$return = [];
		foreach( $this->resObjects as $res ) {
			$value = mysqli_fetch_all( $res, $mode );
			if( $value !== false && !is_null( $value ) ) $return = array_merge( $return, $value );
			$this->free_object( $res );
		}

		return $return;
	}

	private function free_object( $res ) {
		$key = array_search( $res, $this->resObjects );
		mysqli_free_result( $res );
		unset( $this->resObjects[$key] );
	}

	public function free() {
		foreach( $this->resObjects as $res ) {
			mysqli_free_result( $res );
		}
		$this->resObjects = [];
	}
}



