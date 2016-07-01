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
	public function checkDeadlinks( $urls, $full = false ) {
		$fullCheckURLs = array();
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

			//In case the protocol is missing, assume it goes to HTTPS
			if( is_null( parse_url( $url, PHP_URL_SCHEME ) ) ) $url = "https:$url";

			//Determine if we are using FTP or HTTP
			if( strtolower( parse_url( $url, PHP_URL_SCHEME ) ) == "ftp" ) {
				$method = "FTP";
			} else {
				$method = "HTTP";
			}

			if( $method == "FTP" ) {
				curl_setopt( $curl_instances[$id], CURLOPT_FTP_USE_EPRT, 1 );
				curl_setopt( $curl_instances[$id], CURLOPT_FTP_USE_EPSV, 1 );
				curl_setopt( $curl_instances[$id], CURLOPT_FTPSSLAUTH, CURLFTPAUTH_DEFAULT );
				curl_setopt( $curl_instances[$id], CURLOPT_FTP_FILEMETHOD, CURLFTPMETHOD_SINGLECWD );
			}

			curl_setopt( $curl_instances[$id], CURLOPT_URL, $url );
			curl_setopt( $curl_instances[$id], CURLOPT_HEADER, 1 );
			if( $full !== true ) curl_setopt( $curl_instances[$id], CURLOPT_NOBODY, 1 );
			curl_setopt( $curl_instances[$id], CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl_instances[$id], CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $curl_instances[$id], CURLOPT_TIMEOUT, 30 ); // Set 30 second timeout for the entire curl operation to take place
			if( $full === true ) curl_setopt( $curl_instances[$id], CURLOPT_TIMEOUT, 60 ); // Set 60 second timeout for the entire curl operation to take place
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
			if( $full !== true && is_null( $returnArray['results'][$id] ) ) $fullCheckURLs[$id] = $url;
			elseif( is_null( $returnArray['results'][$id] ) ) $returnArray['results'][$id] = true;
		}
		curl_multi_close( $multicurl_resource );
		if( !empty( $fullCheckURLs ) ) {
			$results = $this->checkDeadlinks( $fullCheckURLs, true );
			foreach( $results['results'] as $id=>$result ) {
				$returnArray['results'][$id] = $result;
				$returnArray['errors'][$id] = $results['errors'][$id];
			}
		}
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
		//Determine if we are using FTP or HTTP
		if( strtolower( parse_url( $url, PHP_URL_SCHEME ) ) == "ftp" ) {
			$method = "FTP";
		} else {
			$method = "HTTP";
		}
		//Possible curl error numbers that can indicate a server failure, and conversly, a badlink
		$curlerrors = array( 3, 5, 6, 7, 8, 10, 11, 12, 13, 19, 28, 31, 47, 51, 52, 60, 61, 64, 68, 74, 83, 85, 86, 87 );
		//Official HTTP codes that aren't an indication of errors.
		$httpCodes = array( 100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207, 208, 226, 300, 301, 302, 303, 304, 305, 306, 307, 308, 103 );
		//FTP codes that aren't an indication of errors.
		$ftpCodes = array( 100, 110, 120, 125, 150, 200, 202, 211, 212, 213, 214, 215, 220, 221, 225, 226, 227, 228, 229, 230, 231, 232, 234, 250, 257, 300, 331, 332, 350, 600, 631, 633 );
		// Get HTTP code returned
		$httpCode = $headers['http_code'];
		// Get final URL
		$effectiveUrl = $headers['url'];
		// Clean final url, removing scheme, 'www', and trailing slash
		$effectiveUrlClean = $this->cleanUrl( $effectiveUrl );
		// Get an array of possible root urls
		$possibleRoots = $this->getDomainRoots( $url );
		if ( $httpCode >= 400 && $httpCode < 600 ) {
			// Perform a GET request because some servers don't support HEAD requests
			return null;
		}
		// Check for error messages in redirected URL string
		if ( strpos( $effectiveUrlClean, '404.htm' ) !== false ||
			strpos( $effectiveUrlClean, '/404/' ) !== false ||
			stripos( $effectiveUrlClean, 'notfound' ) !== false ) {
			return true;
		}
		// Check if there was a redirect by comparing final URL with original URL
		if ( $effectiveUrlClean != $this->cleanUrl( $url ) ) {
			// Check against possible roots
			foreach ( $possibleRoots as $root ) {
				// We found a match with final url and a possible root url
				if ( $root == $effectiveUrlClean ) {
					return true;
				}
			}
		}
		//If there was an error during the CURL process, check if the code returned is a server side problem
		if( in_array( $curlerrno, $curlerrors ) ) {
			return true;
		}
		//Check for valid non-error codes for HTTP or FTP
		if( $method == "HTTP" && !in_array( $httpCode, $httpCodes ) ) {
			return true;
		//Check for valid non-error codes for FTP
		} elseif( $method == "FTP" && !in_array( $httpCode, $ftpCodes ) ) {
			return true;
		}
		//Yay, the checks passed, and the site is alive.
		return false;
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
	 * Remove scheme, 'www', URL fragment, leading forward slashes and trailing slash
	 * @param string $input
	 * @return Cleaned string
	 */
	private function cleanUrl( $input ) {
		// scheme and www
		$url = preg_replace( '/^((https?:|ftp:)?(\/\/))?(www\.)?/', '', $input );
		// fragment
		$url = preg_replace( '/#.*/' , '', $url );
		// trailing slash
		$url = preg_replace('{/$}', '', $url );
		return $url;
	}
}