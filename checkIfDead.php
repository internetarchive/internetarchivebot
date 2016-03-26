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
		curl_setopt( $ch, CURLOPT_TIMEOUT, 3 ); // Set 3 seconds timeout for the entire curl operation to take place
		$data = curl_exec( $ch );
		$headers = curl_getinfo( $ch );
		curl_close( $ch );
		// Get HTTP code returned
		$httpCode = $headers['http_code'];
		// Get final URL
		$effectiveUrl = $headers['url'];
		// Clean final url, removing scheme and 'www'
		$effectiveUrlClean = $this->cleanUrl( $effectiveUrl );
		// Get an array of possible root urls
		$possibleRoots = $this->getDomainRoots( $url );

		if ( $httpCode >= 400 && $httpCode < 600 ) {
			if ( $httpCode == 401 || $httpCode == 503 || $httpCode == 507 ) {
				return false;
			} else {
				return true;
			}
			// Check if there was a redirect
		} elseif ( $effectiveUrl != $url ) {
			// Check against possible roots
			foreach ( $possibleRoots as $root ) {
				// We found a match with final url and a possible root url
				if ( $root == $effectiveUrlClean ) {
					return true;
				}
			}
			return false;
		} else {
			return false;
		}
	}

	// Compile an array of "possible" root URLs. With subdomain, without subdomain etc.
	private function getDomainRoots ( $url ) {
		$roots = array();
		$pieces = parse_url( $url );
		$roots[] = $pieces['host'];
		$roots[] = $pieces['host'] . '/';
		$domain = isset( $pieces['host'] ) ? $pieces['host'] : '';
		if ( preg_match( '/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs ) ) {
			$roots[] = $regs['domain'];
			$roots[] = $regs['domain'] . '/';
		}
		$parts = explode( '.', $pieces['host'] );
		if ( count( $parts ) >= 3 ) {
			$roots[] = implode('.', array_slice( $parts, -2 ) );
			$roots[] = implode('.', array_slice( $parts, -2 ) ) . '/';
		}
		return $roots;
	}

	// Remove scheme and 'www'
	private function cleanUrl( $input ) {
		// Remove scheme and www, if present
		$url = preg_replace( '/https?:\/\/|www./', '', $input );
		return $url;
	}

}
