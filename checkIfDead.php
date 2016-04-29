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
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
	* checkIfDead class
	* Checks if a link is dead
	* @author Niharika Kohli (Niharika29)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Niharika Kohli
*/

class checkIfDead {

	const UserAgent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36";

	/**
	 * Function to check whether the given links are dead asyncronously
	 * @param array $urls URLs of the links to be checked
	 * @return array containing the results; True if dead; False if not
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 */
	public function checkDeadlinks( $urls ) {
		$multicurl_resource = curl_multi_init();
		if( $multicurl_resource === false ) {
			return false;
		}
		$curl_instances = array();
		$returnArray = array( 'results' => array(), 'errors' => array() );
		foreach( $urls as $id=>$url ) {
			$curl_instances[$id] = curl_init();
			if( $curl_instances[$id] === false ) {
				return false;
			}

			curl_setopt( $curl_instances[$id], CURLOPT_URL, $url );
			curl_setopt( $curl_instances[$id], CURLOPT_HEADER, 1 );
			curl_setopt( $curl_instances[$id], CURLOPT_NOBODY, 1 );
			curl_setopt( $curl_instances[$id], CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl_instances[$id], CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $curl_instances[$id], CURLOPT_TIMEOUT, 60 ); // Set 60 second timeout for the entire curl operation to take place
			curl_setopt( $curl_instances[$id], CURLOPT_USERAGENT, self::UserAgent );
			curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
		}
		$active = null;
		do {
			$mrc = curl_multi_exec( $multicurl_resource, $active );
		} while ( $mrc == CURLM_CALL_MULTI_PERFORM );

		while ( $active && $mrc == CURLM_OK ) {
			if ( curl_multi_select( $multicurl_resource ) == -1 ) {
				usleep( 100 );
			}
			do {
				$mrc = curl_multi_exec( $multicurl_resource, $active );
			} while ( $mrc == CURLM_CALL_MULTI_PERFORM );
		}

		foreach( $urls as $id=>$url ) {
			$returnArray['errors'][$id] = curl_error( $curl_instances[$id] );
			$headers = curl_getinfo( $curl_instances[$id] );
			$error = curl_errno( $curl_instances[$id] );
			curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
			$returnArray['results'][$id] = $this->processResult( $headers, $error, $url );
		}
		curl_multi_close( $multicurl_resource );
		return $returnArray;
	}


	/**
	 * Process the returned headers
	 * @param array $headers Returned headers
	 * @param array $curlerrno Error number
	 * @param array $url Url checked for
	 * @return bool true if dead; false if not
	 */
	protected function processResult( $headers, $curlerrno, $url ) {
		//Possible curl error numbers that can indicate a server failure, and conversly, a badlink
		$curlerrors = array( 3, 5, 6, 7, 8, 10, 11, 12, 13, 19, 28, 31, 47, 51, 52, 60, 61, 64, 68, 74, 83, 85, 86, 87 );
		// Get HTTP code returned
		$httpCode = $headers['http_code'];
		// Get final URL
		$effectiveUrl = $headers['url'];
		// Clean final url, removing scheme, 'www', and trailing slash
		$effectiveUrlClean = $this->cleanUrl( $effectiveUrl );
		// Get an array of possible root urls
		$possibleRoots = $this->getDomainRoots( $url );
		if ( $httpCode >= 400 && $httpCode < 600 ) {
			if ( $httpCode == 401 || $httpCode == 503 || $httpCode == 507 ) {
				return false;
			} else {
				// Perform a GET request because some servers don't support HEAD requests
				return $this->checkWithoutHeadRequest( $url );
			}
			// Check for error messages in redirected URL string
		} elseif ( strpos( $effectiveUrlClean, '404.htm' ) !== false ||
				   strpos( $effectiveUrlClean, '/404/' ) !== false ||
				   stripos( $effectiveUrlClean, 'notfound' ) !== false ) {
			return true;
		// Check if there was a redirect by comparing final URL with original URL
		} elseif ( $effectiveUrlClean != $this->cleanUrl( $url ) ) {
			// Check against possible roots
			foreach ( $possibleRoots as $root ) {
				// We found a match with final url and a possible root url
				if ( $root == $effectiveUrlClean ) {
					return true;
				}
			}
			return false;
		} elseif( in_array( $curlerrno, $curlerrors ) ) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Check returned http code by making a usual GET request instead of HEAD request
	 * @param string $url URL we are checking as a deadlink for
	 * @return bool true if dead; false if not
	 */
	public function checkWithoutHeadRequest( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		$response = curl_exec( $ch );
		/* Check for 404 (file not found). */
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( $httpCode == 401 || $httpCode == 503 || $httpCode == 507 ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Compile an array of "possible" root URLs. With subdomain, without subdomain etc.
	 * @param string $url Initial url
	 * @return array Possible root domains (strings)
	 */
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

	/**
	 * Remove scheme, 'www', and trailing slash
	 * @param string $input
	 * @return Cleaned string
	 */
	private function cleanUrl( $input ) {
		// Remove scheme and www, if present
		$url = preg_replace( '/https?:\/\/|www./', '', $input );
		// Remove trailing slash, if present
		$url = preg_replace('{/$}', '', $url );
		return $url;
	}

}
