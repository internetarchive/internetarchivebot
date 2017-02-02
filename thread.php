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

/**
 * @file
 * thread object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * AsyncFunctionCall class
 * Allows for asyncronous function calls
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class AsyncFunctionCall extends Thread {

	/**
	 * Returned function values
	 *
	 * @var mixed
	 * @access public
	 */
	public $result;
	/**
	 * Function being called
	 *
	 * @var string
	 * @access protected
	 */
	protected $method;
	/**
	 * Function parameters being passed
	 *
	 * @var array
	 * @access protected
	 */
	protected $params;

	/**
	 * Contstructs the class
	 *
	 * @param string $method Name of function being called
	 * @param array $params array of parameters being passed into the function
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	public function __construct( $method, $params ) {
		$this->method = $method;
		$this->params = $params;
		$this->result = null;
	}

	/**
	 * Call the thread class to execute to execute an
	 * asyncronous function call
	 *
	 * @param string $method Function name
	 * @param array $params Function parameters
	 *
	 * @return AsyncFunctionCall on success, false on failure
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 */
	public static function call( $method, $params ) {
		$thread = new AsyncFunctionCall( $method, $params );
		if( $thread->start() ) {
			return $thread;
		} else {
			echo "Unable to initiate background function $method!\n";

			return false;
		}
	}

	/**
	 * Call the function in the seperate thread
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return bool True on success
	 */
	public function run() {
		if( ( $this->result = call_user_func_array( $this->method, $this->params ) ) ) {
			return true;
		} else return false;
	}
}

/**
 * ThreadedBot class
 * Allows the bot to analyze multiple pages simultaneously
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class ThreadedBot extends Collectable {

	/**
	 * Page analysis statistic
	 *
	 * @var array
	 * @access public
	 */
	public $result;
	/**
	 * Container variables to be passed in the thread
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $id, $page, $pageid, $config;

	/**
	 * Constructor class of the thread engine
	 *
	 * @param string $page
	 * @param int $pageid
	 * @param array $config Configuration options, as specified in deadlink.php
	 * @param mixed $i
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	public function __construct( $page, $pageid, $config, $i ) {
		$this->page = $page;
		$this->pageid = $pageid;
		$this->conifg = $config;
		$this->id = $i;
	}

	/**
	 * Code to run in the thread
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	public function run() {
		$commObject = new API( $this->page, $this->pageid, $this->config );
		$tmp = PARSERCLASS;
		$parser = new $tmp( $commObject );
		$this->result = $parser->analyzePage();
		if( !file_exists( IAPROGRESS . WIKIPEDIA . UNIQUEID . "workers/" ) ) mkdir( IAPROGRESS . WIKIPEDIA . UNIQUEID .
		                                                                            "workers", 0777
		);
		file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID . "workers/worker{$this->id}", serialize( $this->result )
		);
		$this->setGarbage();
		$this->page = null;
		$this->pageid = null;
		$configKeys = array_keys( $this->config );
		$this->config = array_fill_keys( $configKeys, null );

		$commObject->closeResources();
		$parser = $commObject = null;
		unset( $this->page, $this->pageid, $this->config, $commObject );
	}

	public function isGarbage() {
		return $this->garbage;
	}
}
