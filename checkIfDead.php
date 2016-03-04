<?php

/*
	Copyright (c) 2016, Niharika Kohli

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
	along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
	* checkIfDead class
	* Checks if a link is dead
	* @author Niharika Kohli (Niharika29)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Niharika Kohli
*/

class checkIfDead {

	/**
	 * Function to check whether a given link is dead
	 * @param string $url URL of the link to be checked
	 * @return bool True if dead; False if not
	 * @access public
	 * @author Niharika Kohli (Niharika29)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Niharika Kohli
	 */
	public function checkDeadlink( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		$data = curl_exec( $ch );
		$headers = curl_getinfo( $ch );
		curl_close( $ch );

		$parsedUrl = parse_url( $url );
		$root = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/';
		$httpCode = $headers['http_code'];
		$effectiveUrl = $headers['url'];

		if ( $httpCode >= 400 && $httpCode < 600 ) {
			if ( $httpCode == 401 || $httpCode == 503 || $httpCode == 507 ) {
				return false;
			} elseif ( $effectiveUrl == $root ) {
				return true;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

}
