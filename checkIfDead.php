<?php

/**
* @file
* checkIfDead object
* @author Niharika Kohli (Niharika29)
* @license http://www.gnu.org/licenses/gpl-3.0.html
* @copyright Copyright (c) 2016, Niharika Kohli
*/
/**
* checkIfDead class
* Checks if a link is dead
* @author Niharika Kohli (Niharika29)
* @license http://www.gnu.org/licenses/gpl-3.0.html
* @copyright Copyright (c) 2016, Niharika Kohli
*/
class checkIfDead {

	/**
	 * Function to check whether a given link is dead
	 * @param string $url URL of the link to be checked
	 * @return bool True if dead; False if not
	 * @access public
     * @author Niharika Kohli (Niharika29)
     * @license http://www.gnu.org/licenses/gpl-3.0.html
     * @copyright Copyright (c) 2016, Niharika Kohli
	 */
	public function checkDeadlink( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		$data = curl_exec( $ch );
		$headers = curl_getinfo( $ch );
		curl_close( $ch );

		$httpCode = $headers['http_code'];
		if ( $httpCode >= 400 && $httpCode < 600 ) {
			if ( $httpCode == 401 || $httpCode == 503 || $httpCode == 507 ) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

}