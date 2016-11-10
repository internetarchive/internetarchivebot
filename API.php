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
 * API object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2016, Maximilian Doerr
 */

/**
 * API class
 * Manages the core functions of IABot including communication to external APIs
 * The API class initialized per page, and destoryed at the end of it's use.
 * It also manages the page data for every thread, and handles DB and parser calls.
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2016, Maximilian Doerr
 */

class API {

	/**
	 * Stores the global curl handle for the bot.
	 *
	 * @var resource
	 * @access protected
	 * @static
	 * @staticvar
	 */
	protected static $globalCurl_handle = null;

	/**
	* Configuration variables as set on Wikipedia, as well as page and page id variables.
	*
	* @var mixed
	* @access public
	*/
	public $page, $pageid, $config;

	/**
	* Stores the page content for the page being analyzed
	*
	* @var string
	* @access public
	*/
	public $content = "";

	/**
	* Stores the revids of the page's history
	*
	* @var array
	* @access public
	*/
	public $history = array();

	/**
	* Stores the bot's DB class
	*
	* @access public
	* @var DB
	*/
	public $db;

	/**
	* Constructor function for the API class.
	* Initializes the DB class and loads the page
	* contents of the page.
	*
	* @param string $page
	* @param int $pageid
	* @param array $config associative array of config key/values, as specified in deadlink.php
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __construct( $page, $pageid, $config ) {
		$this->page = $page;
		$this->pageid = $pageid;
		$this->config = $config;
		$this->content = self::getPageText( $page );

		$this->db = new DB( $this );
	}

	/**
	* Create and setup a global curl handle
	*
	* @access protected
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected static function initGlobalCurlHandle() {
		self::$globalCurl_handle = curl_init();
		curl_setopt( self::$globalCurl_handle, CURLOPT_COOKIEFILE, COOKIE );
		curl_setopt( self::$globalCurl_handle, CURLOPT_COOKIEJAR, COOKIE );
		curl_setopt( self::$globalCurl_handle, CURLOPT_USERAGENT, USERAGENT );
		curl_setopt( self::$globalCurl_handle, CURLOPT_MAXCONNECTS, 100 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_ENCODING, 'gzip' );
		curl_setopt( self::$globalCurl_handle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_TIMEOUT, 100 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( self::$globalCurl_handle, CURLOPT_SAFE_UPLOAD, true );
	}

	/**
	* Verify tokens and keys and authenticate as defined user, USERNAME
	* Uses OAuth
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool Successful login
	*
	*/
	public static function botLogon() {
		echo "Logging on as ".USERNAME."...";

		$error = "";
		$url = OAUTH . '/identify';

		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', $url ) ) );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		$data = curl_exec( self::$globalCurl_handle );
		if ( !$data ) {
			$error = 'Curl error: ' . htmlspecialchars( curl_error( self::$globalCurl_handle ) );
			goto loginerror;
		}
		$err = json_decode( $data );
		if ( is_object( $err ) && isset( $err->error ) && $err->error === 'mwoauthdatastore-access-token-not-found' ) {
			// We're not authorized!
			$error = "Missing authorization or authorization failed";
			goto loginerror;
		}

		// There are three fields in the response
		$fields = explode( '.', $data );
		if ( count( $fields ) !== 3 ) {
			$error = 'Invalid identify response: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		// Validate the header. MWOAuth always returns alg "HS256".
		$header = base64_decode( strtr( $fields[0], '-_', '+/' ), true );
		if ( $header !== false ) {
			$header = json_decode( $header );
		}
		if ( !is_object( $header ) || $header->typ !== 'JWT' || $header->alg !== 'HS256' ) {
			$error = 'Invalid header in identify response: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		// Verify the signature
		$sig = base64_decode( strtr( $fields[2], '-_', '+/' ), true );
		$check = hash_hmac( 'sha256', $fields[0] . '.' . $fields[1], CONSUMERSECRET, true );
		if ( $sig !== $check ) {
			$error = 'JWT signature validation failed: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		// Decode the payload
		$payload = base64_decode( strtr( $fields[1], '-_', '+/' ), true );
		if ( $payload !== false ) {
			$payload = json_decode( $payload );
		}
		if ( !is_object( $payload ) ) {
			$error = 'Invalid payload in identify response: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		if( USERNAME == $payload->username ) {
			echo "Success!!\n\n";
			return true;
		}
		else {
loginerror: echo "Failed!!\n";
			if( !empty( $error ) ) echo "ERROR: $error\n";
			else echo "ERROR: The bot logged into the wrong username.\n";
			return false;
		}
	}

	/**
	* Generates a header field to be sent during MW
	* API BOT Requests
	*
	* @param string $method CURL Method being used
	* @param string $url URL being CURLed to.
	* @access protected
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string Header field
	*/
	protected static function generateOAuthHeader( $method = 'GET', $url ) {
		$headerArr = array(
					// OAuth information
							'oauth_consumer_key' => CONSUMERKEY,
							'oauth_token' => ACCESSTOKEN,
							'oauth_version' => '1.0',
							'oauth_nonce' => md5( microtime() . mt_rand() ),
							'oauth_timestamp' => time(),

							// We're using secret key signatures here.
							'oauth_signature_method' => 'HMAC-SHA1',
						);
		$signature = self::generateSignature( $method, $url, $headerArr  );
		$headerArr['oauth_signature'] = $signature;

		$header = array();
		foreach ( $headerArr as $k => $v ) {
			$header[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
		}
		$header = 'Authorization: OAuth ' . join( ', ', $header );
		unset( $headerArr );
		return $header;
	}

	/**
	* Signs the OAuth header field
	*
	* @param string $method CURL method being used
	* @param string $url URL being CURLed to
	* @param array $params parameters of the OAUTH header and the URL parameters
	* @access protected
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return base64 encoded signature
	*/
	protected static function generateSignature( $method, $url, $params = array() ) {
		$parts = parse_url( $url );

		// We need to normalize the endpoint URL
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
		$host = isset( $parts['host'] ) ? $parts['host'] : '';
		$port = isset( $parts['port'] ) ? $parts['port'] : ( $scheme == 'https' ? '443' : '80' );
		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( ( $scheme == 'https' && $port != '443' ) ||
			( $scheme == 'http' && $port != '80' )
		) {
			// Only include the port if it's not the default
			$host = "$host:$port";
		}

		// Also the parameters
		$pairs = array();
		parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
		$query += $params;
		unset( $query['oauth_signature'] );
		if ( $query ) {
			$query = array_combine(
				// rawurlencode follows RFC 3986 since PHP 5.3
				array_map( 'rawurlencode', array_keys( $query ) ),
				array_map( 'rawurlencode', array_values( $query ) )
			);
			ksort( $query, SORT_STRING );
			foreach ( $query as $k => $v ) {
				$pairs[] = "$k=$v";
			}
		}

		$toSign = rawurlencode( strtoupper( $method ) ) . '&' .
			rawurlencode( "$scheme://$host$path" ) . '&' .
			rawurlencode( join( '&', $pairs ) );
		$key = rawurlencode( CONSUMERSECRET ) . '&' . rawurlencode( ACCESSSECRET );
		return base64_encode( hash_hmac( 'sha1', $toSign, $key, true ) );
	}

	/**
	 * Fetches the onwiki configuration JSON values.
	 *
	 * @access Public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Loaded configuration from on wiki.
	 */
	public static function fetchConfiguration() {
		$config = [
			'link_scan' => 0,
			'dead_only' => 2,
			'tag_override' => 1,
			'page_scan' => 0,
			'archive_by_accessdate' => 1,
			'touch_archive' => 0,
			'notify_on_talk' => 1,
			'notify_on_talk_only' => 0,
			'notify_error_on_talk' => 1,
			'talk_message_header' => "Links modified on main page",
			'talk_message' => "Please review the links modified on the main page...",
			'talk_error_message' => "There were problems archiving a few links on the page.",
			'talk_error_message_header' => "Notification of problematic links",
			'deadlink_tags' => array( "{{dead-link}}" ),
			'citation_tags' => array( "{{cite web}}" ),
			'wayback_tags' => array( "{{wayback}}" ),
			'archiveis_tags' => array( "{{archiveis}}" ),
			'memento_tags' => array( "{{memento}}" ),
			'webcite_tags' => array( "{{webcite}}" ),
			'ignore_tags' => array( "{{cbignore}}" ),
			'paywall_tags' => array( "{{paywall}}" ),
			'archive_tags' => array(),
			'ic_tags' => array(),
			'verify_dead' => 1,
			'archive_alive' => 1,
			'convert_archives' => 1,
			'mladdarchive' => "{link}->{newarchive}",
			'mlmodifyarchive' => "{link}->{newarchive}<--{oldarchive}",
			'mlfix' => "{link}",
			'mltagged' => "{link}",
			'mltagremoved' => "{link}",
			'mldefault' => "{link}",
			'plerror' => "{problem}: {error}",
			'maineditsummary' => "Fixing dead links",
			'errortalkeditsummary' => "Errors encountered during archiving",
			'talkeditsummary' => "Links have been altered"
		];

		$config_text = API::getPageText( "User:".USERNAME."/Dead-links.js" );

		$config = array_merge( $config, json_decode( $config_text, true ) );
		return $config;
	}

	//Submit archive requests
	/**
	* Submit URLs to be archived
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array results of the archive process including errors
	* @param array $urls A collection of URLs to be archived.  Index keys are preserved.
	*/
	public function requestArchive( $urls ) {
		$getURLs = array();
		$returnArray = array( 'result'=>array(), 'errors'=>array() );
		foreach( $urls as $id=>$url ) {
			//See if we already attempted this in the DB, or if a snapshot already exists.  We don't want to keep hammering the server.
			if( $this->db->dbValues[$id]['archived'] == 1 || ( isset( $this->db->dbValues[$id]['archivable'] ) && $this->db->dbValues[$id]['archivable'] == 0 ) ) {
				$returnArray['result'][$id] = null;
				continue;
			}
			//If it doesn't then proceed.
			$getURLs[$id] = array( 'url' => "http://web.archive.org/save/$url", 'type' => "get" );
		}
		$i = 0;
		while( !empty( $getURLs ) && $i <= 500 ) {
			$i++;
			$res = $this->multiquery( $getURLs );
			foreach( $res['headers'] as $id=>$item ) {
				if( ($res['code'][$id] != 502 && $res['code'][$id] != 503) || isset( $res['headers'][$id]['X-Archive-Wayback-Liveweb-Error']) || isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error']) ) unset( $getURLs[$id] );
				elseif( $i != 500 ) continue;
				if( isset( $item['X-Archive-Wayback-Liveweb-Error'] ) ) {
					$this->db->dbValues[$id]['archive_failure'] = $returnArray['errors'][$id] = $item['X-Archive-Wayback-Liveweb-Error'];
					$returnArray['result'][$id] = false;
					$this->db->dbValues[$id]['archivable'] = 0;
				} elseif( isset( $item['X-Archive-Wayback-Runtime-Error'] ) ) {
					$this->db->dbValues[$id]['archive_failure'] = $returnArray['errors'][$id] = $item['X-Archive-Wayback-Runtime-Error'];
					$returnArray['result'][$id] = false;
					$this->db->dbValues[$id]['archivable'] = 0;
				} else $returnArray['result'][$id] = true;
			}
		}
		if( !empty( $getURLs ) ) {
			$body = "";
			foreach( $getURLs as $id=>$item ) {
				$body .= "Error running URL ".$item['url']."\r\n";
				$body .= "	Response Code: ".$res['code'][$id]."\r\n";
				$body .= "	Headers:\r\n";
				foreach( $res['headers'][$id] as $header=>$value ) $body .= "		$header: $value\r\n";
				$body .= "	Curl Errors Encountered: ".$res['errors'][$id]."\r\n";
				$body .= "	Body:\r\n";
				$body .= $res['results'][$id]."\r\n\r\n";
			}

			self::sendMail( TO, FROM, "Errors encountered while submitting URLs for archiving!!", $body );
		}
		$res = null;
		unset( $res );
		return $returnArray;
	}

	/**
	* Checks whether the given URLs have respective archives
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array containing result data and errors.  Index keys are preserved.
	* @param array $urls A collection of URLs to checked.
	*/
	public function isArchived( $urls ) {
		$getURLs = array();
		$returnArray = array( 'result'=>array(), 'errors'=>array() );
		foreach( $urls as $id=>$url ) {
			//See if the DB can already tell us.
			if( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 1 ) {
				if( $this->db->dbValues[$id]['archivable'] != 1 ) {
					$this->db->dbValues[$id]['archivable'] = 1;
				}
				$returnArray['result'][$id] = true;
				continue;
			} elseif( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 0 ) {
				$returnArray['result'][$id] = false;
				continue;
			}
			//If not, proceed to the API call.  We're looking to see if an archive exists with codes 200, 203, and 206.
			$url = urlencode( $url );
			$getURLs[$id] = "url=$url&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
		}
		$res = $this->CDXQuery( $getURLs );
		foreach( $res['results'] as $id=>$data ) {
			if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
			if( $data['available'] === true ) {
				//It exists, return and mark it in the DB.
				$returnArray['result'][$id] = true;
				$this->db->dbValues[$id]['archived'] = 1;
				$this->db->dbValues[$id]['archivable'] = 1;
			} else {
				//It doesn't exist, return and mark it in the DB.
				$returnArray['result'][$id] = false;
				$this->db->dbValues[$id]['has_archive'] = 0;
				$this->db->dbValues[$id]['archived'] = 0;
			}
		}
		$res = null;
		unset( $res );
		return $returnArray;
	}

	/**
	* Retrieve respective archives of the given URLs
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Result data and errors encountered during the process. Index keys are preserved.
	* @param array $data A collection of URLs to search for.
	*/
	public function retrieveArchive( $data ) {
		$returnArray = array( 'result'=>array(), 'errors'=>array() );
		$getURLs = array();
		//Check to see if the DB can deliver the needed information already
		foreach( $data as $id=>$item ) {
			if( isset( $this->db->dbValues[$id]['has_archive'] ) && $this->db->dbValues[$id]['has_archive'] == 1 ) {
				$returnArray['result'][$id]['archive_url'] = $this->db->dbValues[$id]['archive_url'];
				//TODO: Remove me in the next release.  This is a hack to fix the DB entries.
				if( preg_match( '/https?\:\/\/web\./i', $returnArray['result'][$id]['archive_url'], $garbage ) && strpos( $returnArray['result'][$id]['archive_url'], "archive.org" ) === false ) {
					$returnArray['result'][$id]['archive_url'] = preg_replace( '/https?\:\/\/web\./i', "", $returnArray['result'][$id]['archive_url'] );
				}
				$returnArray['result'][$id]['archive_time'] = $this->db->dbValues[$id]['archive_time'];
				continue;
			} elseif( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 0 ) {
				$returnArray['result'][$id] = false;
				$this->db->dbValues[$id]['has_archive'] = 0;
				continue;
			}
			//If not proceed to API calls
			$url = $item[0];
			$time = $item[1];
			$url = urlencode( $url );
			//Fetch a snapshot preceeding the time a URL was accessed on wiki.
			$getURLs[$id] = "url=$url".( !is_null( $time ) ? "&timestamp=".date( 'YmdHis', $time ) : "" )."&closest=before&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
		}
		$res = $this->CDXQuery( $getURLs );
		foreach( $res['results'] as $id=>$data2 ) {
			if( $data2['available'] === true ) {
				//We have a result.  Save it in the DB, and return the value.
				$this->db->dbValues[$id]['archive_url'] = $returnArray['result'][$id]['archive_url'] = preg_replace( '/https?/i', "https", $data2['url'], 1 );
				$this->db->dbValues[$id]['archive_time'] = $returnArray['result'][$id]['archive_time'] = strtotime( $data2['timestamp'] );
				$this->db->dbValues[$id]['has_archive'] = 1;
				$this->db->dbValues[$id]['archived'] = 1;
				$this->db->dbValues[$id]['archivable'] = 1;
			} else {
				//We don't see if we can get an archive from after the access time.
				$url = $data[$id][0];
				$time = $data[$id][1];
				$getURLs2[$id] = "url=$url".( !is_null( $time ) ? "&timestamp=".date( 'YmdHis', $time ) : "" )."&closest=after&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
				$this->db->dbValues[$id]['has_archive'] = 0;
			}
		}
		$res = null;
		unset( $res );
		if( !empty( $getURLs2 ) ) {
			$res = $this->CDXQuery( $getURLs2 );
			foreach( $res['results'] as $id=>$data ) {
				if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
				if( !empty($data) ) {
					//We have a result.  Save it in the DB,a nd return the value.
					$this->db->dbValues[$id]['archive_url'] = $returnArray['result'][$id]['archive_url'] = preg_replace( '/https?/i', "https", $data['url'], 1 );
					$this->db->dbValues[$id]['archive_time'] = $returnArray['result'][$id]['archive_time'] = strtotime( $data['timestamp'] );
					$this->db->dbValues[$id]['has_archive'] = 1;
					$this->db->dbValues[$id]['archived'] = 1;
					$this->db->dbValues[$id]['archivable'] = 1;
				} else {
					//No results.  Mark so in the DB and return it.
					$returnArray['result'][$id] = false;
					$this->db->dbValues[$id]['has_archive'] = 0;
					$this->db->dbValues[$id]['archived'] = 0;
				}
			}
			$res = null;
			unset( $res );
		}
		return $returnArray;
	}

	/**
	* Run a query on the wayback API version 2
	*
	* @param array $post a bunch of post parameters for each URL
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Result data and errors encountered during the process.  Index keys are preserved.
	*/
	protected function CDXQuery( $post = array() ) {
		$returnArray = array( 'error' => false, 'results' => array(), 'headers' => "", 'code' => array() );
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, "http://archive.org/wayback/available" );
		//We are using the second version of wayback, specifically built for IABot
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( "Wayback-Api-Version: 2" ) );
		$i = 0;
		while( !empty( $post ) && $i <= 50 ) {
			$i++;
			$tpost = implode( "\n", $post );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HEADER, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $tpost );
			$data = curl_exec( self::$globalCurl_handle );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HEADER, 0 );
			$header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
			$returnArray['headers'] = self::http_parse_headers( substr( $data, 0, $header_size ) );
			$returnArray['error'] = curl_error( self::$globalCurl_handle );
			$returnArray['code'] = curl_getinfo( self::$globalCurl_handle, CURLINFO_HTTP_CODE );
			$t = trim( substr( $data, $header_size ) );
			$data = json_decode( $t, true );
			foreach( $data['results'] as $result ) {
				if( isset( $result['archived_snapshots'] ) ) {
					if( isset( $result['archived_snapshots']['closest'] ) ) $returnArray['results'][$result['tag']]	= $result['archived_snapshots']['closest'];
					else $returnArray['results'][$result['tag']] = null;
					unset( $post[$result['tag']] );
				} else {
					$returnArray['results'][$result['tag']] = null;
				}
			}
		}
		$body = "";
		if( (!empty( $getURLs ) || !empty( $returnArray['errors'] )) && $returnArray['code'] != 200 ) {
			foreach( $getURLs as $item ) {
				$body .= "Error running POST:\r\n".implode( "\r\n", $post )."\r\n";
				$body .= "	Response Code: ".$returnArray['code']."\r\n";
				$body .= "	Headers:\r\n";
				foreach( $returnArray['headers'] as $header=>$value ) $body .= "		$header: $value\r\n";
				$body .= "	Curl Errors Encountered: ".$returnArray['errors']."\r\n";
				$body .= "	Body:\r\n";
				$body .= "$t\r\n\r\n";
			}
			self::sendMail( TO, FROM, "Errors encountered while querying the availability API!!", $body );
		}
		return $returnArray;
	}

	/**
	* Execute multiple CURL requests simultaneously
	*
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Result data and errors encountered during the process.  Index keys are preserved.
	* @param mixed $data A collection of URLs, data, and CURL methods to perform the desired requests.
	*/
	protected function multiquery( $data ) {
		$multicurl_resource = curl_multi_init();
		if( $multicurl_resource === false ) {
			return false;
		}
		$curl_instances = array();
		$returnArray = array( 'headers' => array(), 'code' => array(), 'results' => array(), 'errors' => array() );
		foreach( $data as $id=>$item ) {
			$curl_instances[$id] = curl_init();
			if( $curl_instances[$id] === false ) {
				return false;
			}

			//Setup options for all handles.
			curl_setopt( $curl_instances[$id], CURLOPT_USERAGENT, USERAGENT );
			curl_setopt( $curl_instances[$id], CURLOPT_MAXCONNECTS, 100 );
			curl_setopt( $curl_instances[$id], CURLOPT_MAXREDIRS, 10 );
			curl_setopt( $curl_instances[$id], CURLOPT_ENCODING, 'gzip' );
			curl_setopt( $curl_instances[$id], CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $curl_instances[$id], CURLOPT_HEADER, 1 );
			curl_setopt( $curl_instances[$id], CURLOPT_TIMEOUT, 100 );
			curl_setopt( $curl_instances[$id], CURLOPT_CONNECTTIMEOUT, 10 );
			if( $item['type'] == "post" ) {
				curl_setopt( $curl_instances[$id], CURLOPT_FOLLOWLOCATION, 0 );
				curl_setopt( $curl_instances[$id], CURLOPT_HTTPGET, 0 );
				curl_setopt( $curl_instances[$id], CURLOPT_POST, 1 );
				curl_setopt( $curl_instances[$id], CURLOPT_POSTFIELDS, $item['data'] );
				curl_setopt( $curl_instances[$id], CURLOPT_URL, $item['url'] );
			} elseif( $item['type'] == "get" ) {
				curl_setopt( $curl_instances[$id], CURLOPT_FOLLOWLOCATION, 1 );
				curl_setopt( $curl_instances[$id], CURLOPT_HTTPGET, 1 );
				curl_setopt( $curl_instances[$id], CURLOPT_POST, 0 );
				if( isset( $item['data'] ) && !is_null( $item['data'] ) && is_array( $item['data'] ) ) {
					$item['url'] .= '?' . http_build_query( $item['data'] );
				}
				curl_setopt( $curl_instances[$id], CURLOPT_URL, $item['url'] );
			} else {
				return false;
			}
			curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
		}

		//Perform the multiquery
		$active = null;
		do {
			//Execute the multicurl handle.  Since this does asynchronous transfers, curl_multi_exec also serves as a status indicator.
			//We wait until the CURLM_CALL_MULTI_PERFORM state switches to the CURLM_OK state, which also flips the active flag on.
			$mrc = curl_multi_exec($multicurl_resource, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			//The active flag is passed which signals that multicurl is still running.

			//If we cannot select a curl handle yet, sleep for 100 us.
			if (curl_multi_select($multicurl_resource) == -1) {
				//Without this, CPU usage may spike.
				usleep(100);
			}

			//Update the status, and keep updating until CURLM_OK returns.  We want active to be false before continuing.
			do {
				$mrc = curl_multi_exec($multicurl_resource, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		}

		//Loop through each curl handle
		foreach( $data as $id=>$item ) {
			$returnArray['errors'][$id] = curl_error( $curl_instances[$id] );
			if( ($returnArray['results'][$id] = curl_multi_getcontent( $curl_instances[$id] ) ) !== false ) {
				$header_size = curl_getinfo( $curl_instances[$id], CURLINFO_HEADER_SIZE );
				$returnArray['code'][$id] = curl_getinfo( $curl_instances[$id], CURLINFO_HTTP_CODE );
				$returnArray['headers'][$id] = self::http_parse_headers( substr( $returnArray['results'][$id], 0, $header_size ) );
				$returnArray['results'][$id] = trim( substr( $returnArray['results'][$id], $header_size ) );
			}
			//When closing curl handles that were used in multicurl, you need this instead of curl_close, otherwise you get a memory leak.
			//Never use curl_close with multicurl.
			curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
		}
		//Close the multicurl handle.
		curl_multi_close( $multicurl_resource );
		return $returnArray;
	}

	/**
	* Parse the http headers returned in a request
	*
	* @param string $header header string returned from a web request.
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Associative array of the header
	*/
	protected function http_parse_headers( $header ) {
		$header = preg_replace( '/http\/\d\.\d\s\d{3}.*?\n/i', "", $header );
		$header = explode( "\n", $header );
		$returnArray = array();
		foreach( $header as $id=>$item) $header[$id] = explode( ":", $item, 2 );
		foreach( $header as $id=>$item) if( count( $item ) == 2 ) $returnArray[trim($item[0])] = trim($item[1]);
		return $returnArray;
	}

	/**
	* Retrieves a batch of articles from Wikipedia
	*
	* @param int $limit How many articles to return in a batch
	* @param array $resume Where to resume in the batch retrieval process
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array A list of pages with respective page IDs.
	*/
	public static function getAllArticles( $limit, array $resume ) {
		$returnArray = array();
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		while( true ) {
			$get = array(
				'action' => 'query',
				'list' => 'allpages',
				'format' => 'php',
				'apnamespace' => 0,
				'apfilterredir' => 'nonredirects',
				'aplimit' => $limit-count($returnArray)
			);
			if( defined( 'APPREFIX' ) ) $get['apprefix'] = APPREFIX;
			$get = array_merge( $get, $resume );
			$get = http_build_query( $get );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			$data = curl_exec( self::$globalCurl_handle );
			$data = unserialize( $data );
			$returnArray = array_merge( $returnArray, $data['query']['allpages'] );
			if( isset( $data['continue'] ) ) $resume = $data['continue'];
			else {
				$resume = array();
				break;
			}
			if( $limit <= count( $returnArray ) ) break;
		}
		return array( $returnArray, $resume );
	}

	/**
	* Edit a page on Wikipedia
	*
	* @param string $page Page name of page to edit
	* @param string $text Content of edit to post to the page
	* @param string $summary Edit summary to print for the revision
	* @param bool $minor Mark as a minor edit
	* @param string $timestamp Timestamp to check for edit conflicts
	* @param bool $bot Mark as a bot edit
	* @param mixed $section Edit a specific section or create a "new" section
	* @param string $title Title of new section being created
	* @param string $error Error message passback, if error occured.
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return mixed Revid if successful, else false
	*/
	public static function edit( $page, $text, $summary, $minor = false, $timestamp = false, $bot = true, $section = false, $title = "", &
		$error = null ) {
		if( TESTMODE ) {
			echo $text;
			return false;
		}
		if( !self::isEnabled() || DISABLEEDITS === true ) {
			$error = "BOT IS DISABLED";
			echo "ERROR: BOT IS DISABLED!!\n";
			return false;
		}
		if( NOBOTS === true && self::nobots( $text ) ) {
			$error = "RESTRICTED BY NOBOTS";
			echo "ERROR: RESTRICTED BY NOBOTS!!\n";
			DB::logEditFailure( $page, $text, $error );
			return false;
		}
		$summary .= " #IABot (v".VERSION.")";
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$post = array( 'action'=>'edit', 'title'=>$page, 'text'=>$text, 'format'=>'php', 'summary'=>$summary, 'md5'=>md5($text), 'nocreate'=>'yes' );
		if( $minor ) {
			$post['minor'] = 'yes';
		} else {
			$post['notminor'] = 'yes';
		}
		if( $timestamp ) {
			$post['basetimestamp'] = $timestamp;
			$post['starttimestamp'] = $timestamp;
		}
		if( $bot ) {
			$post['bot'] = 'yes';
		}
		if( $section == "new" ) {
			$post['section'] = "new";
			$post['sectiontitle'] = $title;
			$post['redirect'] = "yes";
		} elseif( $section == "append" ) {
			$post['appendtext'] = $text;
			$post['redirect'] = "yes";
		}
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		$get = http_build_query( array(
			'action' => 'query',
			'meta' => 'tokens',
			'format' => 'php'
		) );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
		$data = curl_exec( self::$globalCurl_handle );
		$data = unserialize( $data );
		$post['token'] = $data['query']['tokens']['csrftoken'];
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $post );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'POST', API  ) ) );
		$data2 = curl_exec( self::$globalCurl_handle );
		$data = unserialize( $data2 );
		if( isset( $data['edit'] ) && $data['edit']['result'] == "Success" && !isset( $data['edit']['nochange']) ) {
			return $data['edit']['newrevid'];
		} elseif( isset( $data['error'] ) ) {
			$error = "{$data['error']['code']}: {$data['error']['info']}";
			echo "EDIT ERROR: $error\n";
			DB::logEditFailure( $page, $text, $error );
			return false;
		} elseif( isset( $data['edit'] ) && isset( $data['edit']['nochange'] ) ) {
			$error = "article remained unchanged";
			echo "EDIT ERROR: The article remained unchanged!!\n";
			DB::logEditFailure( $page, $text, $error );
			return false;
		} elseif( isset( $data['edit'] ) && $data['edit']['result'] != "Success" ) {
			$error = "";
			if( isset( $data['edit']['code'] ) ) $error .= $data['edit']['code'];
			if( isset( $data['edit']['info'] ) ) {
				if( !empty( $error ) ) $error .= ": ".$data['edit']['info'];
				else $error .= $data['edit']['info'];
			}
			if( empty( $error ) ) {
				$error = "unknown error";
				echo "EDIT ERROR: The edit was unsuccessful for some unknown reason!\n";
			} else {
				echo "EDIT ERROR: $error\n";
			}
			DB::logEditFailure( $page, $text, $error );
			return false;
		} else {
			$error = "bad response";
			echo "EDIT ERROR: Received a bad response from the API.\nResponse: $data2\n";
			DB::logEditFailure( $page, $text, $error );
			return false;
		}
	}

	/**
	* Checks if the bot is enabled
	*
	* @access protected
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool Whether bot is enabled on the runpage.
	*/
	protected static function isEnabled() {
		if( RUNPAGE === false ) return true;
		$text = self::getPageText( RUNPAGE, WIKIRUNPAGEURL );
		if( $text == "enable" ) return true;
		else return false;
	}

	/**
	* Check if the bot is being repelled from a nobots template
	*
	* @param string $text Page text to check.
	* @access protected
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool Whether it should follow nobots exception.
	*/
	protected static function nobots( $text ) {
		if( strpos( $text, "{{nobots}}" ) !== false ) return true;
		if( strpos( $text, "{{bots}}" ) !== false ) return false;

		if( preg_match( '/\{\{bots\s*\|\s*allow\s*=\s*(.*?)\s*\}\}/i', $text, $allow ) ) {
			if( $allow[1] == "all" ) return false;
			if( $allow[1] == "none" ) return true;
			$allow = array_map( 'trim', explode( ',', $allow[1] ) );
			if( !is_null( USERNAME ) && in_array( trim( USERNAME ), $allow ) ) {
				return false;
			}
			return true;
		}

		if( preg_match( '/\{\{(no)?bots\s*\|\s*deny\s*=\s*(.*?)\s*\}\}/i', $text, $deny ) ) {
			if( $deny[2] == "all" ) return true;
			if( $deny[2] == "none" ) return false;
			$allow = array_map( 'trim', explode( ',', $deny[2] ) );
			if( ( !is_null( USERNAME ) && in_array( trim( USERNAME ), $allow ) ) || ( !is_null( TASKNAME ) && in_array( trim( TASKNAME ), $allow ) ) ) {
				return true;
			}
			return false;
		}
		return false;
	}

	/**
	* Get the revision IDs of a page
	*
	* @param string $page Page title to fetch history for
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Revision history
	*/
	public static function getPageHistory( $page ) {
		$returnArray = array();
		$resume = array();
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		while( true ) {
			$params = array(
				'action' => 'query',
				'prop' => 'revisions',
				'format' => 'php',
				'rvdir' => 'newer',
				'rvprop' => 'ids',
				'rvlimit' => 'max',
				'titles' => $page
			);
			$params = array_merge( $params, $resume );
			$get = http_build_query( $params );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
			$data = curl_exec( self::$globalCurl_handle );
			$data = unserialize( $data );
			if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
				if( isset( $template['revisions'] ) ) $returnArray = array_merge( $returnArray, $template['revisions'] );
			} 
			if( isset( $data['continue'] ) ) $resume = $data['continue'];
			else {
				$resume = array();
				break;
			}
			$data = null;
			unset($data);
		}
		return $returnArray;
	}

	/**
	* Get a batch of articles with confirmed dead links
	*
	* @param string $titles A list of dead link titles seperate with a pipe (|)
	* @param int $limit How big of a batch to return
	* @param array $resume Where to resume in the batch retrieval process
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array A list of pages with respective page IDs.
	*/
	public static function getTaggedArticles( $titles, $limit, array $resume ) {
		$returnArray = array();
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		while( true ) {
			$params = array(
				'action' => 'query',
				'prop' => 'transcludedin',
				'format' => 'php',
				'tinamespace' => 0,
				'tilimit' => $limit-count($returnArray),
				'titles' => $titles
			);
			$params = array_merge( $params, $resume );
			$get = http_build_query( $params );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
			$data = curl_exec( self::$globalCurl_handle );
			$data = unserialize( $data );
			 foreach( $data['query']['pages'] as $template ) {
				if( isset( $template['transcludedin'] ) ) $returnArray = array_merge( $returnArray, $template['transcludedin'] );
			} 
			if( isset( $data['continue'] ) ) $resume = $data['continue'];
			else {
				$resume = array();
				break;
			}
			if( $limit <= count( $returnArray ) ) break;
		}
		return array( $returnArray, $resume);
	}

	/**
	* Retrieve the page content
	*
	* @param string $page Page title to fetch
	* @param bool|string $forceURL URL to force the function to use.
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string Page content
	*/
	public static function getPageText( $page, $forceURL = false ) {
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$get = http_build_query( array(
			'action' => 'raw',
			'title' => $page
		) );
		if( $forceURL === false ) $api = str_replace( "api.php", "index.php", API );
		else $api = $forceURL;
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $api."?$get" );
 		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', $api."?$get" ) ) );
		$data = curl_exec( self::$globalCurl_handle );
		return $data;
	}

	/**
	* Checks if the user is logged on
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool Also returns false on failure
	*/
	public static function isLoggedOn() {
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$get = http_build_query( array(
			'action' => 'query',
			'meta' => 'userinfo',
			'format' => 'php'
		) );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
		$data = curl_exec( self::$globalCurl_handle );
		$data = unserialize( $data );
		if( $data['query']['userinfo']['name'] == USERNAME ) return true;
		else return false;
	}

	/**
	* Retrieves the times specific URLs were added to a wiki page
	*
	* @param array $urls A list of URLs to look up
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array A list of timestamps of when the resective URLs were added.  Array keys are preserved.
	*/
	public function getTimesAdded( $urls ) {
		$processArray = array();
		$queryArray = array();
		$returnArray = array();

		//Use the database to execute the search if available
		if( USEWIKIDB === true && ($db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT )) ) {
			foreach( $urls as $tid => $url ) {
				if( empty( $url ) ) {
					$returnArray[$tid] = time();
					continue;
				}
				$res = mysqli_query( $db, "SELECT ".REVISIONTABLE.".rev_timestamp FROM ".REVISIONTABLE." JOIN ".TEXTTABLE." ON ".REVISIONTABLE.".rev_id = ".TEXTTABLE.".old_id WHERE CONTAINS(".TEXTTABLE.".old_id, '".mysqli_escape_string( $db, $url )."') ORDER BY ".REVISIONTABLE.".rev_timestamp ASC LIMIT 0,1;" );
				//$res = mysqli_query( $db, "SELECT ".REVISIONTABLE.".rev_timestamp FROM ".REVISIONTABLE." JOIN ".TEXTTABLE." ON ".REVISIONTABLE.".rev_id = ".TEXTTABLE.".old_id WHERE ".TEXTTABLE.".old_id LIKE '%".mysqli_escape_string( $db, $url )."%') ORDER BY ".REVISIONTABLE.".rev_timestamp ASC LIMIT 0,1;" );
				$tmp = mysqli_fetch_assoc( $res );
				mysqli_free_result( $res );
				unset( $res );
				if( $tmp !== false ) {
					mysqli_close( $db );
					unset( $db );
					$returnArray[$tid] = strtotime( $tmp['rev_timestamp'] );
				}
				if( !is_resource( $db ) ) {
					mysqli_close( $db );
					unset( $db );
					echo "ERROR: Wiki database usage failed.  Defaulting to API Binary search...\n";
					break;
				}
			}
		}

		//Retrieve page history of page if not already saved.  No page text is saved.
		if( empty( $this->history ) ) $this->history = self::getPageHistory( $this->page );
		$range = count( $this->history );

		foreach( $urls as $tid => $url ) {
			if( empty( $url ) ) {
				$returnArray[$tid] = time();
				continue;
			}
			$processArray[$tid]['upper'] = $range - 1;
			$processArray[$tid]['lower'] = 0;
			$processArray[$tid]['needle'] = round( $range/2 ) - 1;
			$processArray[$tid]['time'] = time();
			$processArray[$tid]['useQuery'] = -1;
		}

		//Do a binary sweep of the page history with all the URLs at once.  This minimizes the bandwidth and time consumed.
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		if( $range >= 100 ) {
			for( $stage = 2; $stage <= 16; $stage++ ) {
				$revs = array();
				foreach( $urls as $tid => $url ) {
					if( empty( $url ) ) {
						$returnArray[$tid] = time();
						continue;
					}
					$revs[$processArray[$tid]['needle']] = $this->history[$processArray[$tid]['needle']]['revid'];
				}
				$get = http_build_query( array(
					'action' => 'query',
					'prop' => 'revisions',
					'format' => 'php',
					'rvprop' => 'timestamp|content|ids',
					'revids' => implode( '|', $revs )
				) );

				//Fetch revisions of needle location in page history.  Scan for the presence of URL.
				curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
				curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
				curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
				curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
				$data = curl_exec( self::$globalCurl_handle );
				$data = unserialize( $data );

				//The scan of each URL happens here
				foreach( $urls as $tid => $url ) {
					if( empty( $url ) ) {
						$returnArray[$tid] = time();
						continue;
					}
					//Do an error check for the proper revisions.
					if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
						if( isset( $template['revisions'] ) ) {
							foreach( $template['revisions'] as $revision ) {
								if( $revision['revid'] == $this->history[$processArray[$tid]['needle']]['revid'] ) break;
								else $revision = false;
							}
						} else $revision = false;
					} else $revision = false;
					if( $revision === false ) continue;
					else {
						//Look for the URL in the fetched revisions
						if( isset( $revision['*'] ) ) {
							if( strpos( $revision['*'], $url ) === false ) {
								//URL not found, move needle forward half the distance of the last jump
								$processArray[$tid]['lower'] = $processArray[$tid]['needle'] + 1;
								$processArray[$tid]['needle'] += round( $range/(pow( 2, $stage )) );
							} else {
								//URL found, move needle back half the distance of the last jump
								$processArray[$tid]['upper'] = $processArray[$tid]['needle'];
								$processArray[$tid]['needle'] -= round( $range/(pow( 2, $stage )) ) - 1;
							}
						} else continue;
					}
				}
				//If we narrowed it to a sufficiently low amount or if the needle isn't changing, why continue?
				if( $processArray[$tid]['upper'] - $processArray[$tid]['lower'] <= 20 || $processArray[$tid]['needle'] == $processArray[$tid]['upper'] || ($processArray[$tid]['lower'] + 1) == $processArray[$tid]['lower'] ) break;
			}
		}

		//Group each URL into a revision group.  Some may share the same revision range group.  No need to pull from the API more than once.
		foreach( $processArray as $tid=>$link ) {
			$tid2 = -1;
			foreach( $queryArray as $tid2=>$query ) {
				if( $query['lower'] == $link['lower'] && $query['upper'] == $link['upper'] ) {
					$processArray[$tid]['useQuery'] = $tid2;
					break;
				}
			}
			if( $processArray[$tid]['useQuery'] === -1 ) {
				$queryArray[$tid2 + 1] = array( 'lower' => $link['lower'], 'upper' => $link['upper'] );
				$processArray[$tid]['useQuery'] = $tid2 + 1;
			}
		}

		//Run each revision group range
		foreach( $queryArray as $tid=>$bounds ) {
			$get = http_build_query( array(
				'action' => 'query',
				'prop' => 'revisions',
				'format' => 'php',
				'rvdir' => 'newer',
				'rvprop' => 'timestamp|content',
				'rvlimit' => 'max',
				'rvstartid' => $this->history[$bounds['lower']]['revid'],
				'rvendid' => $this->history[$bounds['upper']]['revid'],
				'titles' => $this->page
			) );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
			$data = curl_exec( self::$globalCurl_handle );
			$data = unserialize( $data );
			//Another error check
			if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
				if( isset( $template['revisions'] ) ) $revisions = $template['revisions'];
				else $revisions = null;
			} else $revisions = null;
			//Run through each URL from within the range group.
			foreach( $processArray as $tid2=>$tmp ) {
				if( $tmp['useQuery'] !== $tid ) continue;
				if( is_null( $revisions ) ) {
					$returnArray[$tid2] = time();
					continue;
				}
				$time = time();
				foreach( $revisions as $revision ) {
					if( !isset( $revision['*'] ) ) continue;
					if( strpos( $revision['*'], $urls[$tid2] ) !== false ) {
						$time = strtotime( $revision['timestamp'] );
						break;
					}
				}
				//We have the timestamp of the URL's addition.
				$returnArray[$tid2] = $time;
			}
		}

		return $returnArray;
	}

	/**
	* Get a list of templates that redirect to the given titles
	*
	* @param array $titles A list of pages titles to look up
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array A list of templates that redirect to the given titles
	*/
	public static function getRedirects( $titles ) {
		$returnArray = array();
		$resume = array();
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();	
		while( true ) {
			$params = array(
				'action' => 'query',
				'format' => 'php',
				'prop' => 'redirects',
				'list' => '',
				'meta' => '',
				'rdprop' => 'title',
				'rdnamespace' => 10,
				'rdshow' => '',
				'rdlimit' => 5000,
				'titles' => implode( '|', $titles )
			);
			$params = array_merge( $params, $resume );
			$get = http_build_query( $params );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $params );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'POST', API ) ) );
			$data = curl_exec( self::$globalCurl_handle );
			$data = unserialize( $data );
			if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
				if( isset( $template['redirects'] ) ) $returnArray = array_merge( $returnArray, $template['redirects'] );
			} 
			if( isset( $data['continue'] ) ) $resume = $data['continue'];
			else {
				$resume = array();
				break;
			}
		}
		return $returnArray;
	}

	/**
	* Resolves a template into an external link
	*
	* @param string $template Template to resolve
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return mixed URL if successful, false on failure.
	*/
	public static function resolveExternalLink( $template ) {
		$url = false;
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$get = http_build_query( array(
			'action' => 'parse',
			'format' => 'php',
			'text' => $template,
			'contentmodel' => 'wikitext'
		) );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $get );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'POST', API."?$get" ) ) );
		$data = curl_exec( self::$globalCurl_handle );
		$data = unserialize( $data );
		if( isset( $data['parse']['externallinks'] ) && !empty( $data['parse']['externallinks'] ) ) {
			$url = $data['parse']['externallinks'][0];
		}
		return $url;
	}

	/**
	 * Resolves the output of the given wikitext
	 *
	 * @param string $text Template/text to resolve
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return mixed URL if successful, false on failure.
	 */
	public static function resolveWikitext( $text ) {
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$get = http_build_query( array(
			'action' => 'parse',
			'format' => 'php',
			'prop' => 'text',
			'text' => $text,
			'contentmodel' => 'wikitext'
		) );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $get );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'POST', API."?$get" ) ) );
		$data = curl_exec( self::$globalCurl_handle );
		$data = unserialize( $data );
		if( isset( $data['parse']['text']['*'] ) && !empty( $data['parse']['text']['*'] ) ) {
			$text = $data['parse']['text']['*'];
			$text = preg_replace( '/\<\!\-\-(?:.|\n)*?\-\-\>/i', "", $text );
			$text = trim( $text );
			if( substr( $text, 0, 3 ) == "<p>" && substr( $text, -4, 4 ) == "</p>" ) {
				$text = substr( $text, 3, strlen( $text )-7 );
			}
			return $text;
		}
		return false;
	}

	/**
	* Send an email
	*
	* @param string $to Who to send it to
	* @param string $from Who to mark it from
	* @param string $subject Subject line to set
	* @param string $email Body of email
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool True on successful
	*/
	public static function sendMail( $to, $from, $subject, $email ) {
		if( !ENABLEMAIL ) return false;
		echo "Sending a message to $to...";
		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-type: text/plain; charset=iso-8859-1";
		$headers[] = "From: $from";
		$headers[] = "Reply-To: <>";
		$headers[] = "X-Mailer: PHP/".phpversion();
		$headers[] = "Useragent: ".USERAGENT;
		$headers[] = "X-Accept-Language: en-us, en";

		$success = mail($to, $subject, $email, implode("\r\n", $headers));
		if( $success ) echo "Success!!\n";
		else echo "Failed!!\n";

		return $success;
	}

	/**
	* Replaces magic word place holders with actual values.
	* Uses a parameter string or returns the complete given string
	* if the parameter doesn't match
	*
	* @param string $value A parameter or string to handle.
	* @param array $magicwords A list of magic words and associative values to replace with.
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string Completed string
	*/
	public function getConfigText( $value, $magicwords = array() ) {
		if( isset( $this->config[$value] ) ) $string = $this->config[$value];
		else $string = $value;
		$string = str_replace( "\\n", "\n", $string );
		foreach( $magicwords as $magicword=>$value ) {
			$string = str_ireplace( "{{$magicword}}", $value, $string );
		}
		return $string;
	}

	/**
	* Creates a log entry at the central API as specified in the configuration file.
	*
	* @param array $magicwords A list of words to replace the API call with.
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool True on success, false on failure, null if disabled
	*/
	public function logCentralAPI( $magicwords ) {
		if( LOGAPI === true ) {
			$url = $this->getConfigText( APICALL, $magicwords );
			if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
			$data = curl_exec( self::$globalCurl_handle );
			$function = DECODEMETHOD;
			$data = $function( $data, true );
			if( $data == EXPECTEDRETURN ) return true;
			else return false;
		} else return null;
	}

	/**
	 * Escape the regex for all the tags and get redirect tags
	 *
	 * @param array $config Configuration array
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return void
	 */
	public static function escapeTags ( &$config ) {
		$marray = $tarray = array();
		$toEscape = array();
		$toEscape[] = $config['deadlink_tags'];
		$toEscape[] = $config['wayback_tags'];
		$toEscape[] = $config['archiveis_tags'];
		$toEscape[] = $config['memento_tags'];
		$toEscape[] = $config['webcite_tags'];
		$toEscape[] = $config['ignore_tags'];
		$toEscape[] = $config['citation_tags'];
		$toEscape[] = $config['ic_tags'];
		$toEscape[] = $config['paywall_tags'];
		foreach( $toEscape as $id=>$escapee ) {
			$tarray = array();
			$marray = array();
			foreach( $escapee as $tag ) {
				$marray[] = "Template:".str_replace( "{", "", str_replace( "}", "", $tag ) );
				$tarray[] = preg_quote( $tag, '/' );
				if( strpos( $tag, " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", $tag ), '/' );
			}
			do {
				$redirects = API::getRedirects( $marray );
				$marray = array();
				foreach( $redirects as $tag ) {
					$marray[] = $tag['title'];
					$tarray[] = preg_quote( str_replace( "Template:", "{{", $tag['title'] )."}}", '/' );
					if( strpos( $tag['title'], " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", str_replace( "Template:", "{{", $tag['title'] )."}}" ), '/' );
				}
			} while( !empty( $redirects ) );
			$toEscape[$id] = $tarray;
		}
		unset( $marray, $tarray );
		$config['deadlink_tags'] = $toEscape[0];
		$config['wayback_tags'] = $toEscape[1];
		$config['archiveis_tags'] = $toEscape[2];
		$config['memento_tags'] = $toEscape[3];
		$config['webcite_tags'] = $toEscape[4];
		$config['ignore_tags'] = $toEscape[5];
		$config['citation_tags'] = $toEscape[6];
		$config['ic_tags'] = $toEscape[7];
		$config['paywall_tags'] = $toEscape[8];
	}

	/**
	 * Determine if the URL is a common archive, and attempts to resolve to original URL.
	 *
	 * @param string $url The URL to test
	 * @param array $data The data about the URL to pass back
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return bool True if it is an archive
	 */
	public function isArchive( $url, &$data ) {
		if( strpos( $url, "archive.org" ) !== false ) {
			$resolvedData = self::resolveWaybackURL( $url );
		} elseif( strpos( $url, "archive.is" ) !== false || strpos( $url, "archive.today" ) !== false ) {
			$resolvedData = self::resolveArchiveIsURL( $url );
		} elseif( strpos( $url, "mementoweb.org" ) !== false ) {
			$resolvedData = self::resolveMementoURL( $url );
		} elseif( strpos( $url, "webcitation.org" ) !== false ) {
			$resolvedData = self::resolveWebCiteURL( $url );
		} elseif( strpos( $url, "webcache.googleusercontent.com" ) !== false ) {
			$resolvedData = self::resolveGoogleURL( $url );
			$data['archive_type'] = "invalid";
			$data['iarchive_url'] = $resolvedData['archive_url'];
			$data['invalid_archive'] = true;
		} else return false;
		if( !isset( $resolvedData['url'] ) ) return false;
		if( !isset( $resolvedData['archive_url'] ) ) return false;
		if( !isset( $resolvedData['archive_time'] ) ) return false;
		if( !isset( $resolvedData['archive_host'] ) ) return false;
		if( isset( $resolvedData['convert_archive_url'] ) ) {
			$data['convert_archive_url'] = $resolvedData['convert_archive_url'];
		}
		if( $this->isArchive( $resolvedData['url'], $temp ) ) {
			$data['url'] = $temp['url'];
			$data['archive_url'] = $resolvedData['archive_url'];
			$data['archive_time'] = $resolvedData['archive_time'];
			$data['archive_host'] = $resolvedData['archive_host'];
		} else {
			$data['url'] = $resolvedData['url'];
			$data['archive_url'] = $resolvedData['archive_url'];
			$data['archive_time'] = $resolvedData['archive_time'];
			$data['archive_host'] = $resolvedData['archive_host'];
		}
		$data['old_archive'] = $url;
		return true;
	}

	/**
	 * Retrieves URL information given a webcite URL
	 *
	 * @access public
	 * @param string $url A webcite URL that goes to an archive.
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Details about the archive.
	 */
	public static function resolveWebCiteURL( $url ) {
		$returnArray = array();
		//Try and decode the information from the URL first
		if( preg_match( '/\/\/(?:www\.)?webcitation.org\/(query|\S*)\?(\S+)/i', $url, $match ) ) {
			$args = explode( '&', $match[2] );
			foreach( $args as $arg ) {
				$arg = explode( '=', $arg, 2 );
				$temp[urldecode($arg[0])] = urldecode($arg[1]);
			}
			$args = $temp;
			if( $match[1] != "query" ) {
				if( strlen( $match[1] ) === 9 ) $timestamp = substr( (string)self::to10( $match[1], 62 ), 0, 10 );
				else $timestamp = substr( $match[1], 0, 10 );
			} else {
				if( isset( $args['id'] ) ) {
					if( strlen( $args['id'] ) === 9 ) $timestamp = substr( (string)self::to10( $args['id'], 62 ), 0, 10 );
					else $timestamp = substr( $args['id'], 0, 10 );
				} elseif( isset( $args['date'] ) ) $timestamp = strtotime( $args['date'] );
			}
			if( isset( $args['url'] ) ) {
				$oldurl = urldecode( $args['url'] );
			}
			if( isset( $oldurl ) && isset( $timestamp ) && $timestamp !== false ) {
				$returnArray['archive_time'] = $timestamp;
				$returnArray['url'] = $oldurl;
				$returnArray['archive_url'] = "http:".$match[0];
				$returnArray['archive_host'] = "webcite";
				if( $returnArray['archive_url'] != $url ) $returnArray['convert_archive_url'] = true;
				return $returnArray;
			}
		}

		if( preg_match('/\/\/(?:www\.)?webcitation.org\/query\?(\S*)/i', $url, $match ) ) {
			$query = "http:".$match[0]."&returnxml=true";
		} elseif( preg_match( '/\/\/(?:www\.)?webcitation.org\/(\S*)/i', $url, $match ) ) {
			$query = "http://www.webcitation.org/query?id=".$match[1]."&returnxml=true";
		} else return $returnArray;
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $query );
		$data = curl_exec( self::$globalCurl_handle );
		$data = preg_replace( '/\<br\s\/\>\n\<b\>.*? on line \<b\>\d*\<\/b\>\<br\s\/\>/i', "", $data );
		$data = trim( $data );
		$xml_parser = xml_parser_create();
		xml_parse_into_struct($xml_parser, $data, $vals);
		xml_parser_free($xml_parser);
		foreach( $vals as $val ) {
			if( $val['tag'] == "TIMESTAMP" && isset( $val['value'] ) ) $returnArray['archive_time'] = strtotime( $val['value'] );
			if( $val['tag'] == "ORIGINAL_URL" && isset( $val['value'] ) ) $returnArray['url'] = $val['value'];
			if( $val['tag'] == "REDIRECTED_TO_URL" && isset( $val['value'] ) ) $returnArray['url'] = $val['value'];
			if( $val['tag'] == "WEBCITE_URL" && isset( $val['value'] ) ) $returnArray['archive_url'] = $val['value']."?url=".$returnArray['url'];
			if( $val['tag'] == "RESULT" && $val['type'] == "close" ) break;
		}
		$returnArray['archive_host'] = "webcite";
		$returnArray['convert_archive_url'] = true;
		return $returnArray;
	}

	/**
	 * Retrieves URL information given a memento URL
	 *
	 * @access public
	 * @param string $url A memento URL that goes to an archive.
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Details about the archive.
	 */
	public static function resolveMementoURL( $url ) {
		$returnArray = array();
		if( preg_match( '/\/\/timetravel\.mementoweb\.org\/(?:memento|api\/json)\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url'] = "https://timetravel.mementoweb.org/memento/".$match[1]."/".$match[2];
			$returnArray['url'] = urldecode( $match[2] );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "memento";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}
		return $returnArray;
	}

	/**
	 * Retrieves URL information given a google web cache URL
	 *
	 * @access public
	 * @param string $url A google web cache URL that goes to an archive.
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Details about the archive.
	 */
	public static function resolveGoogleURL( $url ) {
		$returnArray = array();
		if( preg_match( '/(https?\:)?\/\/webcache\.googleusercontent\.com\/.*?\:.*?\:(.*?)\+.*?/i',$url, $match ) ) {
			$returnArray['archive_url'] = $url;
			$url = urldecode( $match[2] );
			$returnArray['url'] = "https://".$url;
			$returnArray['archive_time'] = "x";
			$returnArray['archive_host'] = "google";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}
		return $returnArray;
	}

	/**
	 * Retrieves URL information given an archive.is URL
	 *
	 * @access public
	 * @param string $url An archive.is URL that goes to an archive.
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Details about the archive.
	 */

	public static function resolveArchiveIsURL( $url ) {
		$returnArray = array();
		if( preg_match( '/\/\/(?:www\.)?archive.(?:is|today)\/(\S*?)\/(\S+)/i', $url, $match ) ) {
			if( ($timestamp = strtotime( $match[1] )) === false ) $timestamp = strtotime( preg_replace( '/[\.\-\s]/i', "", $match[1] ) );
			$oldurl = urldecode( $match[2] );
			$returnArray['archive_time'] = $timestamp;
			$returnArray['url'] = $oldurl;
			$returnArray['archive_url'] = "https:".$match[0];
			$returnArray['archive_host'] = "archiveis";
			if( $returnArray['archive_url'] != $url ) $returnArray['convert_archive_url'] = true;
			return $returnArray;
		}

		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
		curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 1 );
		$data = curl_exec( self::$globalCurl_handle );
		if( preg_match( '/name\=\"q\" value\=\"(.*?)\"\/\>/i', $data, $match ) ) {
			$returnArray['url'] = $match[1];
		}
		if( preg_match( '/archived (.*?) UTC/i', $data, $match ) ) {
			$returnArray['archive_time'] = strtotime( $match[1] );
		}
		if( isset( $returnArray['url'] ) && isset( $returnArray['archive_time'] ) ) $returnArray['archive_url'] = "https://archive.is/".date( 'YmdHis', $returnArray['archive_time'] )."/".$returnArray['url'];
		elseif( preg_match( '/archiveurl  \= (\S*?)\n/i', $data, $match ) ) {
			$returnArray['archive_url' ] = trim( $match[1] );
		}
		$returnArray['archive_host'] = "archiveis";
		$returnArray['convert_archive_url'] = true;
		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Wayback URL
	 *
	 * @access public
	 * @param string $url A Wayback URL that goes to an archive.
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Details about the archive.
	 */
	public static function resolveWaybackURL( $url ) {
		$returnArray = array();
		if( preg_match( '/\/\/(?:web\.)?archive\.org(?:\/web)?\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url'] = "https://web.archive.org/web/".$match[1]."/".$match[2];
			$returnArray['url'] = urldecode( $match[2] );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "wayback";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}
		return $returnArray;
	}

	/**
	 * Convert a base 10 number to any base up to 62.  Only does whole numbers.
	 *
	 * @access private
	 * @static
	 * @param $num Decimal to convert
	 * @param int $b Base to convert to
	 * @return string New base number
	 */
	private static function toBase($num, $b=62) {
		$base='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$r = $num  % $b ;
		$res = $base[$r];
		$q = floor($num/$b);
		while ($q) {
			$r = $q % $b;
			$q =floor($q/$b);
			$res = $base[$r].$res;
		}
		return $res;
	}

	/**
	 * Convert any base number, up to 62, to base 10.  Only does whole numbers.
	 *
	 * @access private
	 * @static
	 * @param $num Based number to convert
	 * @param int $b Base to convert from
	 * @return string New base 10 number
	 */
	private static function to10( $num, $b=62) {
		$base='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$limit = strlen($num);
		$res=strpos($base,$num[0]);
		for($i=1;$i<$limit;$i++) {
			$res = $b * $res + strpos($base,$num[$i]);
		}
		return $res;
	}

	/**
	* Close the resource handles
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function closeResources() {
		$this->db->closeResource();
		curl_close( self::$globalCurl_handle );
		self::$globalCurl_handle = null;
		$this->db = null;
	}
}
