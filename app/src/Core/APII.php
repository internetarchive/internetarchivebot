<?php

/*
	Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	InternetArchiveBot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with InternetArchiveBot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

/**
 * @file
 * API object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */

use Wikimedia\DeadlinkChecker\CheckIfDead;

/**
 * API class
 * Manages the core functions of IABot including communication to external APIs
 * The API class initialized per page, and destroyed at the end of it's use.
 * It also manages the page data for every thread, and handles DB and parser calls.
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */
class API
{

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
	 * A flag that determines if the profiler is enabled.
	 *
	 * @var resource
	 * @access protected
	 * @static
	 * @staticvar
	 */
	protected static $profiling_enabled = false;

	/**
	 * Stores cached data on users
	 *
	 * @var array
	 * @access protected
	 * @static
	 * @staticvar
	 */
	protected static $userAPICache = [];
	/**
	 * Stores the the limit on the number titles that can be passed to the API
	 *
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $titlesLimit = false;
	/**
	 * Stores the local name of the namespaces
	 *
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $namespaces = false;
	/**
	 * Stores the page redirects to the final destination
	 *
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $redirects = false;
	/**
	 * Temporarily stores the resolved value of a template.  Values remain for 1 hour.
	 *
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $templateURLCache = [];
	/**
	 * Stores the list of Categories to lookup
	 *
	 * @access public
	 * @var DB
	 */
	protected static $categories = false;
	/**
	 * Stores the last edits made within the edit period, if applicable
	 *
	 * @access protected
	 * @var DB
	 */
	protected static $lastEdits = [];
	/**
	 * Stores the rate limit
	 *
	 * @access protected
	 * @var mixed
	 */
	protected static $rateLimit = false;
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
	public $history = [];
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
	 *
	 * @access public
	 * @throws Exception
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function __construct( $page, $pageid, $config )
	{
		$this->page    = $page;
		$this->pageid  = $pageid;
		$this->config  = $config;
		$this->content = self::getPageText( $page );
		if( $config['rate_limit'] != 0 ) self::$rateLimit = $config['rate_limit'];

		$tmp      = DBCLASS;
		$this->db = new $tmp( $this );
	}

	/**
	 * Retrieve the page content
	 *
	 * @param string $page Page title to fetch
	 * @param bool|string $forceURL URL to force the function to use.
	 *
	 * @access public
	 * @static
	 * @return string Page content
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getPageText( $page, $forceURL = false, $revID = false )
	{
		$params = [
			'action' => 'raw',
			'title' => $page
		];
		if( $revID !== false && is_numeric( $revID ) ) $params['oldid'] = $revID;
		$get = http_build_query( $params );
		if( $forceURL === false ) {
			$api = str_replace( "api.php", "index.php", API );
		} else $api = $forceURL;
		if( IAVERBOSE ) echo "Making query: $api?$get\n";
		$data = self::makeHTTPRequest( $api, $params );

		$headers = curl_getinfo( self::$globalCurl_handle );

		if( IAVERBOSE && $headers['http_code'] >= 400 ) {
			echo "ERROR: {$headers['http_code']} while retrieving '$page'\n";

			return false;
		}

		return $data;
	}

	/**
	 * Create and setup a global curl handle
	 *
	 * @access protected
	 * @static
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected static function initGlobalCurlHandle()
	{
		self::$globalCurl_handle = curl_init();
		curl_setopt( self::$globalCurl_handle, CURLOPT_COOKIEFILE, COOKIE );
		curl_setopt( self::$globalCurl_handle, CURLOPT_COOKIEJAR, COOKIE );
		curl_setopt( self::$globalCurl_handle, CURLOPT_USERAGENT, USERAGENT );
		curl_setopt( self::$globalCurl_handle, CURLOPT_MAXCONNECTS, 100 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_MAXREDIRS, 20 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_ENCODING, 'gzip' );
		curl_setopt( self::$globalCurl_handle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_TIMEOUT, 100 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( self::$globalCurl_handle, CURLOPT_SAFE_UPLOAD, true );
		@curl_setopt( self::$globalCurl_handle, CURLOPT_DNS_USE_GLOBAL_CACHE, true );
		curl_setopt( self::$globalCurl_handle, CURLOPT_DNS_CACHE_TIMEOUT, 60 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		if( PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION >= 7.3 ) {
			curl_setopt( self::$globalCurl_handle,
			             CURLOPT_SSLVERSION,
			             CURL_SSLVERSION_TLSv1_3
			);
		}
	}

	/**
	 * Check if a list of pages exist locally
	 *
	 * @access private
	 * @static
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @param $url Endpoint to query
	 * @param array $query Params to send
	 * @param bool $usePOST How to send those params
	 * @param bool $useOAuth Authenticate with OAuth
	 * @param array $keys Optional OAuth keys to pass
	 * @return bool|string Query results
	 * @throws Exception
	 */
	private static function makeHTTPRequest( $url, $query = [], $usePOST = false, $useOAuth = true, $keys = [],
	                                         $headers = []
	) {
		global $accessibleWikis;

		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();

		if( in_array( $url, [ API, $accessibleWikis[WIKIPEDIA]['i18nsource'] ] ) || strpos( $url, OAUTH ) !== false ) {
			curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 0 );
		} else {
			curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 1 );
		}

		if( $usePOST ) {
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $query );
		} else {
			if( !empty( $query ) ) {
				$get = http_build_query( $query );
				curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url . "?$get" );
			} else {
				curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
			}
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		}

		if( $useOAuth ) {
			if( $usePOST ) {
				curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER,
				             [ self::generateOAuthHeader( 'POST', $url, $keys ) ]
				);
			} else {
				if( isset( $get ) ) {
					curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER,
					             [ self::generateOAuthHeader( 'GET', $url . "?$get", $keys ) ]
					);
				} else {
					curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER,
					             [ self::generateOAuthHeader( 'GET', $url, $keys ) ]
					);
				}
			}
		}

		if( !empty( $headers ) ) {
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, $headers );
		}

		$data = curl_exec( self::$globalCurl_handle );

		$curlData = curl_getinfo( self::$globalCurl_handle );

		if( !empty( $curlData['redirect_url'] ) ) {
			if( $url == API ) {
				echo "Config Error: The API is located elsewhere.  Updating configuration and terminating!\n";

				$accessibleWikis[WIKIPEDIA]['apiurl'] = $curlData['redirect_url'];

				DB::setConfiguration( 'global', 'systemglobals-allwikis', WIKIPEDIA, $accessibleWikis[WIKIPEDIA] );

				exit( 1 );
			}
			if( strpos( $url, OAUTH ) !== false ) {
				echo "Config Error: The OAuth module is located elsewhere.  Updating configuration and terminating!\n";

				$piece = str_replace( OAUTH, '', $url );

				$accessibleWikis[WIKIPEDIA]['oauthurl'] = str_replace( $piece, '', $curlData['redirect_url'] );

				DB::setConfiguration( 'global', 'systemglobals-allwikis', WIKIPEDIA, $accessibleWikis[WIKIPEDIA] );

				exit( 1 );
			}
			if( $url == $accessibleWikis[WIKIPEDIA]['i18nsource'] ) {
				echo "Config Error: The meta wiki is located elsewhere.  Updating configuration and terminating!\n";

				$accessibleWikis[WIKIPEDIA]['i18nsource'] = $curlData['redirect_url'];

				DB::setConfiguration( 'global', 'systemglobals-allwikis', WIKIPEDIA, $accessibleWikis[WIKIPEDIA] );

				exit( 1 );
			}
		}

		return $data;
	}

	/**
	 * Check if a list of pages exist locally
	 *
	 * @access public
	 * @static
	 * @param array List of pages to check for
	 * @return array Whether or not each page exists
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function pagesExist( $pageList )
	{
		$returnArray = [];
		$sliced      = true;
		$pageLists   = array_chunk( $pageList, self::getTitlesLimit() );

		do {
			if( !$sliced ) $pageLists = [ $pageList ];
			foreach( $pageLists as $subList ) {
				$params = [
					'action' => "query",
					'format' => "json",
					'titles' => implode( '|', $subList )
				];

				$out = http_build_query( $params );
				if( IAVERBOSE ) echo "Making query: $out\n";
				$data = self::makeHTTPRequest( API, $params, true );
				$data = json_decode( $data, true );

				if( isset( $data['error']['code'] ) && $data['error']['code'] == 'toomanyvalues' ) {
					$sliced    = true;
					$pageLists = array_chunk( $pageList, $data['error']['limit'] );
					break;
				}

				if( !empty( $data['query']['pages'] ) ) {
					foreach( $data['query']['pages'] as $pid => $pageInfo ) {
						$pageListID = array_search( $pageInfo['title'], $pageList );
						if( $pageListID === false ) {
							foreach( $data['query']['normalized'] as $normalized ) {
								if( $pageInfo['title'] == $normalized['to'] ) break;
							}
							$pageListID = array_search( $normalized['from'], $pageList );
						}

						$returnArray[$pageList[$pageListID]] = !isset( $pageInfo['missing'] );
						unset( $pageList[$pageListID] );
					}
				}
				$sliced = false;
			}
		} while( !empty( $pageList ) );

		return $returnArray;
	}

	/**
	 * Generates a header field to be sent during MW
	 * API BOT Requests
	 *
	 * @param string $method CURL Method being used
	 * @param string $url URL being CURLed to.
	 *
	 * @access public
	 * @static
	 * @return string Header field
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function generateOAuthHeader( $method = 'GET', $url, $keys = [] )
	{
		if( !empty( $keys['consumerkey'] ) && !empty( $keys['consumersecret'] ) && !empty( $keys['accesstoken'] ) &&
		    !empty( $keys['accesssecret'] ) ) {
			$headerArr = [
				// OAuth information
				'oauth_consumer_key' => $keys['consumerkey'],
				'oauth_token' => $keys['accesstoken'],
				'oauth_version' => '1.0',
				'oauth_nonce' => md5( microtime() . mt_rand() ),
				'oauth_timestamp' => time(),

				// We're using secret key signatures here.
				'oauth_signature_method' => 'HMAC-SHA1',
			];
			$signature =
				@self::generateSignature( $method, $url, $headerArr, $keys['consumersecret'], $keys['accesssecret'] );
		} elseif( defined( 'CONSUMERKEY' ) && defined( 'ACCESSTOKEN' ) ) {
			$headerArr = [
				// OAuth information
				'oauth_consumer_key' => CONSUMERKEY,
				'oauth_token' => ACCESSTOKEN,
				'oauth_version' => '1.0',
				'oauth_nonce' => md5( microtime() . mt_rand() ),
				'oauth_timestamp' => time(),

				// We're using secret key signatures here.
				'oauth_signature_method' => 'HMAC-SHA1',
			];
			$signature = @self::generateSignature( $method, $url, $headerArr );
		} else return "";
		$headerArr['oauth_signature'] = $signature;

		$header = [];
		foreach( $headerArr as $k => $v ) {
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
	 *
	 * @access protected
	 * @static
	 * @return base64 encoded signature
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected static function generateSignature( $method, $url, $params = [], $consumerSecret = false,
	                                             $accessSecret = false
	) {
		$parts = parse_url( $url );

		// We need to normalize the endpoint URL
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$port   = isset( $parts['port'] ) ? $parts['port'] : ( $scheme == 'https' ? '443' : '80' );
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';
		if( ( $scheme == 'https' && $port != '443' ) ||
		    ( $scheme == 'http' && $port != '80' )
		) {
			// Only include the port if it's not the default
			$host = "$host:$port";
		}

		// Also the parameters
		$pairs = [];
		parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
		$query += $params;
		unset( $query['oauth_signature'] );
		if( $query ) {
			$query = array_combine(
			// rawurlencode follows RFC 3986 since PHP 5.3
				array_map( 'rawurlencode', array_keys( $query ) ),
				array_map( 'rawurlencode', array_values( $query ) )
			);
			ksort( $query, SORT_STRING );
			foreach( $query as $k => $v ) {
				$pairs[] = "$k=$v";
			}
		}

		$toSign = rawurlencode( strtoupper( $method ) ) . '&' .
		          rawurlencode( "$scheme://$host$path" ) . '&' .
		          rawurlencode( join( '&', $pairs ) );
		if( $accessSecret && $consumerSecret ) {
			$key = rawurlencode( $consumerSecret ) . '&' . rawurlencode( $accessSecret );
		} else $key = rawurlencode( CONSUMERSECRET ) . '&' . rawurlencode( ACCESSSECRET );

		return base64_encode( hash_hmac( 'sha1', $toSign, $key, true ) );
	}

	/**
	 * Find out who reverted the text and why
	 *
	 * @param int userID The user ID to look up
	 *
	 * @access public
	 * @static
	 * @return array User information
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getUser( $userID )
	{
		if( isset( self::$userAPICache[$userID] ) ) return self::$userAPICache[$userID];

		$params = [
			'action' => 'query',
			'list' => 'users',
			'ususerids' => $userID,
			'usprop' => 'groups|rights|registration|editcount|blockinfo|centralids',
			'format' => 'json'
		];
		$get    = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";
		$data = self::makeHTTPRequest( API, $params );
		$data = json_decode( $data, true );

		self::$userAPICache[$userID] = $data['query']['users'][0];

		return $data['query']['users'][0];
	}

	/**
	 * Verify tokens and keys and authenticate as defined user, USERNAME
	 * Uses OAuth
	 * @access public
	 * @static
	 * @return bool Successful login
	 *
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function botLogon()
	{
		echo "Logging on as " . USERNAME . "...";

		$error = "";
		$url   = OAUTH . '/identify';

		if( IAVERBOSE ) echo "Making query: $url\n";

		$data = self::makeHTTPRequest( $url, [], true );
		if( !$data ) {
			$error = 'Curl error: ' . htmlspecialchars( curl_error( self::$globalCurl_handle ) );
			goto loginerror;
		}
		$err = json_decode( $data );
		if( is_object( $err ) && isset( $err->error ) && $err->error === 'mwoauthdatastore-access-token-not-found' ) {
			// We're not authorized!
			$error = "Missing authorization or authorization failed";
			goto loginerror;
		}

		// There are three fields in the response
		$fields = explode( '.', $data );
		if( count( $fields ) !== 3 ) {
			$error = 'Invalid identify response: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		// Validate the header. MWOAuth always returns alg "HS256".
		$header = base64_decode( strtr( $fields[0], '-_', '+/' ), true );
		if( $header !== false ) {
			$header = json_decode( $header );
		}
		if( !is_object( $header ) || $header->typ !== 'JWT' || $header->alg !== 'HS256' ) {
			$error = 'Invalid header in identify response: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		// Verify the signature
		$sig   = base64_decode( strtr( $fields[2], '-_', '+/' ), true );
		$check = hash_hmac( 'sha256', $fields[0] . '.' . $fields[1], CONSUMERSECRET, true );
		if( $sig !== $check ) {
			$error = 'JWT signature validation failed: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		// Decode the payload
		$payload = base64_decode( strtr( $fields[1], '-_', '+/' ), true );
		if( $payload !== false ) {
			$payload = json_decode( $payload );
		}
		if( !is_object( $payload ) ) {
			$error = 'Invalid payload in identify response: ' . htmlspecialchars( $data );
			goto loginerror;
		}

		if( USERNAME == $payload->username ) {
			echo "Success!!\n\n";

			return true;
		} else {
			loginerror:
			echo "Failed!!\n";
			if( !empty( $error ) ) {
				echo "ERROR: $error\n";
			} else echo "ERROR: The bot logged into the wrong username.\n";

			return false;
		}
	}

	/**
	 * Fetches the wiki configuration values.
	 *
	 * @access Public
	 * @static
	 * @return array Loaded configuration from on wiki.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function fetchConfiguration( &$isDefined = false, $getCiteDefinitions = true, $force = false )
	{
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
			'talk_message_header_talk_only' => "Links needing modification on main page",
			'talk_message_talk_only' => "Please review and fix the links I found needing fixing...",
			'talk_error_message' => "There were problems archiving a few links on the page.",
			'talk_error_message_header' => "Notification of problematic links",
			'talk_message_verbose' => 0,
			'deadlink_tags' => [],
			'dateformat' => [],
			'templatebehavior' => "append",
			'ignore_tags' => [ "{{cbignore}}" ],
			'talk_only_tags' => [ "{{cbtalkonly}}" ],
			'no_talk_tags' => [ "{{cbnotalk}}" ],
			'ref_bounds' => [],
			'paywall_tags' => [],
			'archive_tags' => [],
			'sarchive_tags' => [],
			'aarchive_tags' => [],
			'notify_domains' => [],
			'verify_dead' => 1,
			'archive_alive' => 1,
			'convert_archives' => 1,
			'convert_archives_encoding' => 1,
			'convert_to_cites' => 1,
			'mladdarchivetalkonly' => "{link}->{newarchive}",
			'mltaggedtalkonly' => "{link}",
			'mltagremovedtalkonly' => "{link}",
			'mladdarchive' => "{link}->{newarchive}",
			'mlmodifyarchive' => "{link}->{newarchive}<--{oldarchive}",
			'mlfix' => "{link}",
			'mltagged' => "{link}",
			'mltagremoved' => "{link}",
			'mldefault' => "{link}",
			'plerror' => "{problem}: {error}",
			'maineditsummary' => "Fixing dead links",
			'errortalkeditsummary' => "Errors encountered during archiving",
			'talkeditsummary' => "Links have been altered",
			'tag_cites' => 1,
			'rate_limit' => false
		];

		$configDB = DB::getConfiguration( WIKIPEDIA, "wikiconfig" );

		$archiveTemplates = CiteMap::getMaps( WIKIPEDIA, $force, 'archive' );

		if( $getCiteDefinitions === true ) {
			$tmp = CiteMap::getMaps( WIKIPEDIA, $force );
			foreach( $tmp as $key => $object ) {
				if( is_null( $object ) ) continue;
				if( !$object->isDisabled() ) $templateList[] = "{{{$key}}}";
			}

			unset( $tmp );

		}

		if( isset( $configDB['deadlink_tags_data'] ) && !( $configDB['deadlink_tags_data'] instanceof CiteMap ) ) {
			$configDB['deadlink_tags_data'] = CiteMap::getMaps( WIKIPEDIA, $force, 'dead' );
		}

		$configDB['archive_tags']  = [];
		$configDB['sarchive_tags'] = [];
		$configDB['aarchive_tags'] = [];

		foreach( $archiveTemplates as $name => $template ) {
			$name                            = str_replace( " ", "_", $name );
			$configDB['all_archives'][$name] = $template;
			if( isset( $configDB["darchive_$name"] ) ) {
				$configDB['using_archives'][] = $name;
				$configDB['archive_tags']     = array_merge( $configDB['archive_tags'], $configDB["darchive_$name"] );
				if( $template['templatebehavior'] == 'swallow' ) {
					$configDB['sarchive_tags'] = array_merge( $configDB['sarchive_tags'], $configDB["darchive_$name"] );
				} else {
					$configDB['aarchive_tags'] = array_merge( $configDB['aarchive_tags'], $configDB["darchive_$name"] );
				}
			}
		}
		if( !isset( $configDB['deprecated_archives'] ) ) {
			$configDB['deprecated_archives'] = [];
		}

		$isDefined = true;

		foreach( $config as $name => $defaultValue ) {
			if( !isset( $configDB[$name] ) ) {
				if( !in_array( $name, [ 'ref_bounds', 'rate_limit' ] ) ) $isDefined = false;
				$configDB[$name] = $config[$name];
			}
		}

		$config = $configDB;

		self::$rateLimit = $config['rate_limit'];

		if( isset( $templateList ) ) {
			$config['citation_tags'] = $templateList;
		} else $config['citation_tags'] = [];

		return $config;
	}

	/**
	 * Fetches the final redirect location of a redirecting page.
	 *
	 * @param string $pageTitle The title of the page to follow, including the namespace
	 *
	 * @access Public
	 * @static
	 * @return string Final page destination or false on failure
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getRedirectRoot( $pageTitle )
	{
		if( isset( self::$redirects[$pageTitle] ) ) return self::$redirects[$pageTitle];

		$params = [
			'action' => "query",
			'format' => "json",
			'redirects' => "1",
			'titles' => $pageTitle
		];

		$get = http_build_query( $params );

		if( IAVERBOSE ) echo "Making query: $get\n";
		$data = self::makeHTTPRequest( API, $params );
		$data = json_decode( $data, true );

		$endTarget = $pageTitle;

		if( !empty( $data['query']['normalized'] ) ) {
			foreach( $data['query']['normalized'] as $redirect ) {
				if( $redirect['from'] == $endTarget ) $endTarget = $redirect['to'];
			}
		}

		if( !empty( $data['query']['redirects'] ) ) {
			foreach( $data['query']['redirects'] as $redirect ) {
				if( $redirect['from'] == $endTarget ) $endTarget = $redirect['to'];
			}
		}

		self::$redirects[$pageTitle] = $endTarget;

		return $endTarget;
	}

	/**
	 * Fetches the onwiki citoid JSON values.
	 *
	 * @access Public
	 * @static
	 * @return array Fetched citoid and respective template data from the wiki.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function retrieveCitoidDefinitions()
	{
		$returnArray = [];

		$citoidMapTypes = self::getPageText( "MediaWiki:Citoid-template-type-map.json" );
		if( !empty( $citoidMapTypes ) && ( $citoidMapTypes = json_decode( $citoidMapTypes, true ) ) ) {
			$returnArray['mapped_templates'] = $citoidMapTypes;
			$returnArray['unique_templates'] = array_unique( $citoidMapTypes, SORT_REGULAR );
			foreach( $returnArray['unique_templates'] as $cite ) {
				$templateData = self::getTemplateData( $cite );
				if( !empty( $templateData ) ) $returnArray['template_data'][$cite] = $templateData;
			}
		}

		return $returnArray;
	}

	/**
	 * Fetches the templatedata JSON values.
	 *
	 * @access Public
	 * @static
	 * @return array Fetched template data from the wiki.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getTemplateData( $template )
	{
		$template = trim( $template, "{}" );

		$pageNameTemplate = self::getTemplateNamespaceName() . ":$template";

		$params = [
			'action' => "templatedata",
			'format' => "json",
			'titles' => $pageNameTemplate,
			'includeMissingTitles' => "1",
			'lang' => "en",
			'redirects' => "1"
		];

		$get = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";

		$data = self::makeHTTPRequest( API, $params );
		$data = json_decode( $data, true );

		if( !empty( $data['pages'] ) ) {
			foreach( $data['pages'] as $pageData ) {
				if( isset( $pageData['missing'] ) ) return false;

				return $pageData;
			}
		} else return false;
	}

	/**
	 * Get name of a namespace
	 *
	 * @access public
	 * @static
	 * @return string The name of the Template namespace
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getNamespaceName( $namespace )
	{
		if( self::$namespaces === false ) {
			$params = [
				'action' => 'query',
				'meta' => 'siteinfo',
				'format' => 'json',
				'siprop' => 'namespaces'
			];
			$get    = http_build_query( $params );
			if( IAVERBOSE ) echo "Making query: $get\n";
			$tried = 0;
			do {

				$data = self::makeHTTPRequest( API, $params );
				$data = json_decode( $data, true );

				$tried++;
			} while( empty( $data['query']['namespaces'] ) && $tried < 10 );

			if( empty( $data['query']['namespaces'] ) ) return false;

			self::$namespaces = $data['query']['namespaces'];
		}

		if( isset( self::$namespaces[$namespace] ) ) {
			return self::$namespaces[$namespace]['*'];
		} else return false;
	}

	/**
	 * Get name of Template namespace
	 *
	 * @access public
	 * @static
	 * @return string The name of the Template namespace
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getTemplateNamespaceName()
	{
		return self::getNamespaceName( 10 );
	}

	/**
	 * Get name of Module namespace
	 *
	 * @access public
	 * @static
	 * @return string The name of the Module namespace
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getModuleNamespaceName()
	{
		return self::getNamespaceName( 828 );
	}

	/**
	 * Retrieves a batch of articles from Wikipedia
	 *
	 * @param int $limit How many articles to return in a batch
	 * @param array $resume Where to resume in the batch retrieval process
	 * @param int $namespace Which namespace the bot should operate in
	 *
	 * @access public
	 * @static
	 * @return array A list of pages with respective page IDs.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getAllArticles( $limit, array $resume, $namespace = 0 )
	{
		$returnArray = [];
		while( true ) {
			$params = [
				'action' => 'query',
				'list' => 'allpages',
				'format' => 'json',
				'apnamespace' => $namespace,
				'apfilterredir' => 'nonredirects',
				'aplimit' => $limit - count( $returnArray )
			];
			if( defined( 'APPREFIX' ) ) $params['apprefix'] = APPREFIX;
			$params = array_merge( $params, $resume );
			$get    = http_build_query( $params );
			if( IAVERBOSE ) echo "Making query: $get\n";

			$data        = self::makeHTTPRequest( API, $params );
			$data        = json_decode( $data, true );
			$returnArray = array_merge( $returnArray, $data['query']['allpages'] );
			if( isset( $data['continue'] ) ) {
				$resume = $data['continue'];
			} else {
				$resume = [];
				break;
			}
			if( $limit <= count( $returnArray ) ) break;
		}

		return [ $returnArray, $resume ];
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
	 * @param array $keys Pass custom keys to make the edit from a different account
	 *
	 * @access public
	 * @static
	 * @return mixed Revid if successful, else false
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 */
	public static function edit( $page, $text, $summary, $minor = false, $timestamp = false, $bot = true,
	                             $section = false, $title = "", &$error = null, $keys = []
	) {
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
		$summary .= ") #IABot (v" . VERSION;
		if( defined( "REQUESTEDBY" ) ) {
			global $jobID;
			$summary .= ") ([[User:" . REQUESTEDBY . "|" . REQUESTEDBY . "]]";
			if( !empty( $jobID ) ) $summary .= " - $jobID";
		}

		//Enforce set rate limits
		if( !empty( self::$lastEdits ) ) {
			if( self::$rateLimit !== false ) {
				do {
					$rate            = explode( " per ", self::$rateLimit );
					$number          = $rate[0];
					$period          = $rate[1];
					$expired         = time() - $period;
					$numberLastEdits = 0;
					unset( $sleepPeriod, $lastTime );
					foreach( self::$lastEdits as $time => $revIDs ) {
						if( !isset( $sleepPeriod ) && isset( $lastTime ) ) $sleepPeriod = $lastTime - $expired;
						if( $time < $expired ) {
							unset( self::$lastEdits[$time] );
						} else $numberLastEdits += count( $revIDs );
						$lastTime = $time;
					}
					if( $numberLastEdits >= $number ) {
						echo "RATE LIMIT: Sleeping for $sleepPeriod second(s)...\n";
						sleep( $sleepPeriod );
					}
				} while( $numberLastEdits >= $number );
			} else {
				self::$lastEdits = [];
			}
		}

		$text = UtfNormal\Validator::cleanUp( $text );

		$post = [
			'action' => 'edit', 'title' => $page, 'text' => $text, 'format' => 'json', 'summary' => $summary,
			'md5' => md5( $text ), 'maxlag' => '5'
		];
		if( $minor ) {
			$post['minor'] = 'yes';
		} else {
			$post['notminor'] = 'yes';
		}
		if( $timestamp ) {
			$post['basetimestamp']  = $timestamp;
			$post['starttimestamp'] = $timestamp;
		}
		if( $bot ) {
			$post['bot'] = 'yes';
		}
		if( $section == "new" ) {
			$post['section']      = "new";
			$post['sectiontitle'] = $title;
			$post['redirect']     = "yes";
		} elseif( $section == "append" ) {
			$post['appendtext'] = $text;
			$post['redirect']   = "yes";
		}
		$params = [
			'action' => 'query',
			'meta' => 'tokens',
			'format' => 'json'
		];
		$get    = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";

		$data          = self::makeHTTPRequest( API, $params, false, true, $keys );
		$data          = json_decode( $data, true );
		$post['token'] = $data['query']['tokens']['csrftoken'];
		repeatEditRequest:
		$data2 = self::makeHTTPRequest( API, $post, true, true, $keys );
		if( IAVERBOSE ) echo "Posting to: " . API . "\n";
		$data = json_decode( $data2, true );
		if( isset( $data['edit'] ) && $data['edit']['result'] == "Success" && !isset( $data['edit']['nochange'] ) ) {
			if( self::$rateLimit !== false ) {
				self::$lastEdits[time()][] = $data['edit']['newrevid'];
			}

			return $data['edit']['newrevid'];
		} elseif( isset( $data['error'] ) ) {
			$error = "{$data['error']['code']}: {$data['error']['info']}";
			echo "EDIT ERROR: $error\n";
			DB::logEditFailure( $page, $text, $error );
			if( $data['error']['code'] == "maxlag" ) {
				sleep( 5 );
				goto repeatEditRequest;
			}

			return false;
		} elseif( isset( $data['edit'] ) && isset( $data['edit']['nochange'] ) ) {
			$error = "article remained unchanged";
			echo "EDIT ERROR: The article remained unchanged!!\n";
			DB::logEditFailure( $page, $text, $error );

			return false;
		} elseif( isset( $data['edit'] ) && isset( $data['edit']['spamblacklist'] ) ) {
			$error = "triggered blacklist: " . $data['edit']['spamblacklist'];
			echo "EDIT ERROR: Triggered the spam blacklist: {$data['edit']['spamblacklist']}\n";
			DB::logEditFailure( $page, $text, $error );

			return false;
		} elseif( isset( $data['edit']['captcha'] ) ) {
			$error = "Need CAPTCHA: This edit requires a CAPTCHA input which is not supported.";
			echo "EDIT ERROR: The edit was unsuccessful because a CAPTCHA is required for this edit.";
		} elseif( isset( $data['edit'] ) && $data['edit']['result'] != "Success"
		) {
			$error = "";
			if( isset( $data['edit']['code'] ) ) $error .= $data['edit']['code'];
			if( isset( $data['edit']['info'] ) ) {
				if( !empty( $error ) ) {
					$error .= ": " . $data['edit']['info'];
				} else $error .= $data['edit']['info'];
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
	 * @return bool Whether bot is enabled on the runpage.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function isEnabled()
	{
		if( RUNPAGE === true ) {
			$runpage = DB::getConfiguration( WIKIPEDIA, "wikiconfig", "runpage" );
			if( $runpage == "enable" ) {
				return true;
			} else return false;
		}
		if( RUNPAGE === false ) return true;
	}

	/**
	 * Check if the bot is being repelled from a nobots template
	 *
	 * @param string $text Page text to check.
	 *
	 * @access protected
	 * @static
	 * @return bool Whether it should follow nobots exception.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected static function nobots( $text )
	{
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
			if( ( !is_null( USERNAME ) && in_array( trim( USERNAME ), $allow ) ) ||
			    ( !is_null( TASKNAME ) && in_array( trim( TASKNAME ), $allow ) )
			) {
				return true;
			}

			return false;
		}

		return false;
	}

	/**
	 * Get a batch of articles with confirmed dead links
	 *
	 * @param string $titles A list of dead link titles separate with a pipe (|)
	 * @param int $limit How big of a batch to return
	 * @param array $resume Where to resume in the batch retrieval process
	 *
	 * @access public
	 * @static
	 * @return array A list of pages with respective page IDs.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getTaggedArticles( &$titles, $limit, array $resume )
	{
		$returnArray = [];
		foreach( array_chunk( $titles, self::getTitlesLimit(), true ) as $cutTitles ) {
			while( true ) {
				$params = [
					'action' => 'query',
					'prop' => 'transcludedin',
					'format' => 'json',
					'tinamespace' => 0,
					'tilimit' => $limit - count( $returnArray ),
					'titles' => implode( '|', $cutTitles )
				];
				$params = array_merge( $params, $resume );
				$get    = http_build_query( $params );
				if( IAVERBOSE ) echo "Making query: $get\n";

				$data = self::makeHTTPRequest( API, $params );
				$data = json_decode( $data, true );
				if( !empty( $data['query']['pages'] ) ) {
					foreach( $data['query']['pages'] as $template ) {
						if( isset( $template['transcludedin'] ) ) {
							$returnArray =
								array_merge( $returnArray, $template['transcludedin'] );
						}
					}
				}
				if( isset( $data['continue'] ) ) {
					$resume = $data['continue'];
				} else {
					$resume = [];
					$titles = array_slice( $titles, self::getTitlesLimit() );
					break;
				}
				if( $limit <= count( $returnArray ) ) break;
			}
		}

		return [ $returnArray, $resume ];
	}

	/**
	 * Get the limit of titles that can be passed in the titles parameter
	 *
	 * @access public
	 * @static
	 * @return int The number of titles that can be passed without errors
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getTitlesLimit()
	{
		if( self::$titlesLimit === false ) {
			$params = [
				'action' => 'paraminfo',
				'modules' => 'query',
				'format' => 'json'
			];
			$get    = http_build_query( $params );
			if( IAVERBOSE ) echo "Making query: $get\n";
			$data              = self::makeHTTPRequest( API, $params );
			$data              = json_decode( $data, true );
			self::$titlesLimit = $data['paraminfo']['modules'][0]['parameters'];
			foreach( self::$titlesLimit as $params ) {
				if( $params['name'] == "titles" ) {
					self::$titlesLimit = $params['limit'];
					break;
				}
			}
		}

		return self::$titlesLimit;
	}

	/**
	 * Get a batch of articles from a category and its sub categories
	 *
	 * @param string $titles A list of categories separate with a pipe (|)
	 * @param array $resume Where to resume in the batch retrieval process
	 *
	 * @access public
	 * @static
	 * @return array A list of pages with respective page IDs. False if one of the pages isn't a category.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getArticlesFromCategory( array $titles, array $resume = [], $recurse = false )
	{
		$returnArray = [];

		if( self::$categories === false || $recurse === true ) {
			if( $recurse === false ) self::$categories = [];
			foreach( $titles as $title ) {
				self::$categories[] = $title;

				while( true ) {
					$params = [
						'action' => 'query',
						'list' => 'categorymembers',
						'format' => 'json',
						'cmnamespace' => 14,
						'cmlimit' => 'max',
						'cmtitle' => $title,
					];
					$params = array_merge( $params, $resume );
					$get    = http_build_query( $params );
					if( IAVERBOSE ) echo "Making query: $get\n";
					$data = self::makeHTTPRequest( API, $params );
					$data = json_decode( $data, true );
					if( !isset( $data['query']['categorymembers'] ) ) return false;
					foreach( $data['query']['categorymembers'] as $categorymember ) {
						self::getArticlesFromCategory( [ $categorymember['title'] ], [], true );
					}
					if( isset( $data['continue'] ) ) {
						$resume = $data['continue'];
					} else {
						$resume = [];
						break;
					}
				}
			}
			if( $recurse === true ) return;
		}

		foreach( self::$categories as $category ) {
			while( true ) {
				$params = [
					'action' => 'query',
					'list' => 'categorymembers',
					'format' => 'json',
					'cmnamespace' => 0,
					'cmlimit' => 'max',
					'cmtitle' => $category,
				];
				$params = array_merge( $params, $resume );
				$get    = http_build_query( $params );
				if( IAVERBOSE ) echo "Making query: $get\n";
				$data = self::makeHTTPRequest( API, $params );
				$data = json_decode( $data, true );
				if( isset( $data['query']['categorymembers'] ) ) {
					$returnArray =
						array_merge( $returnArray, $data['query']['categorymembers'] );
				}
				if( isset( $data['continue'] ) ) {
					$resume = $data['continue'];
				} else {
					$resume = [];
					break;
				}
			}
		}

		self::$categories = false;

		return $returnArray;
	}

	/**
	 * Checks if the user is logged on
	 *
	 * @access public
	 * @static
	 * @return bool Also returns false on failure
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function isLoggedOn()
	{
		$params = [
			'action' => 'query',
			'meta' => 'userinfo',
			'format' => 'json'
		];
		$get    = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";
		$data = self::makeHTTPRequest( API, $params );
		$data = json_decode( $data, true );
		if( $data['query']['userinfo']['name'] == USERNAME ) {
			return true;
		} else return false;
	}

	/**
	 * Resolves a template into an external link
	 *
	 * @param string $template Template to resolve
	 *
	 * @access public
	 * @static
	 * @return mixed URL if successful, false on failure.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveExternalLink( $template )
	{
		foreach( self::$templateURLCache as $minuteEpoch => $set ) {
			$expired = round( ( time() - 3600 ) / 60, 0 );
			if( $minuteEpoch <= $expired ) {
				unset( self::$templateURLCache[$minuteEpoch] );
				continue;
			}
			if( isset( $set[$template] ) ) return $set[$template];
		}

		$url = false;

		$params = [
			'action' => 'parse',
			'format' => 'json',
			'text' => $template,
			'contentmodel' => 'wikitext'
		];
		$get    = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";
		$data = self::makeHTTPRequest( API, $params, true );
		$data = json_decode( $data, true );
		if( isset( $data['parse']['externallinks'] ) && !empty( $data['parse']['externallinks'] ) ) {
			$url = $data['parse']['externallinks'][0];
		}

		self::$templateURLCache[round( time() / 60, 0 )][$template] = $url;

		return $url;
	}

	/**
	 * Resolves a template into an external link
	 *
	 * @param string $wikitext Wikitext to parse
	 *
	 * @access public
	 * @static
	 * @return mixed Parser output
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function wikitextToHTML( $wikitext )
	{
		$params = [
			'action' => 'parse',
			'format' => 'json',
			'text' => $wikitext,
			'contentmodel' => 'wikitext',
			'disablelimitreport' => 1,
			'disableeditsection' => 1,
			'disabletoc' => 1,
			'prop' => 'text'
		];
		$get    = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";
		$data = self::makeHTTPRequest( API, $params, true );
		$data = json_decode( $data, true );
		if( isset( $data['parse']['text'] ) && !empty( $data['parse']['text'] ) ) {
			return $data['parse']['text']['*'];
		}

		return false;
	}

	/**
	 * Resolves the output of the given wikitext
	 *
	 * @param string $text Template/text to resolve
	 *
	 * @access public
	 * @static
	 * @return mixed URL if successful, false on failure.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWikitext( $text )
	{
		$params = [
			'action' => 'parse',
			'format' => 'json',
			'prop' => 'text',
			'text' => $text,
			'contentmodel' => 'wikitext'
		];
		$get    = http_build_query( $params );
		if( IAVERBOSE ) echo "Making query: $get\n";
		$data = self::makeHTTPRequest( API, $params, true );
		$data = json_decode( $data, true );
		if( isset( $data['parse']['text']['*'] ) && !empty( $data['parse']['text']['*'] ) ) {
			$text = $data['parse']['text']['*'];
			$text = preg_replace( '/\<\!\-\-(?:.|\n)*?\-\-\>/i', "", $text );
			$text = str_ireplace( "<div class=\"mw-parser-output\"><p>", "", $text );
			$text = preg_replace( "/\<\/p\>(.|\n)*?\<\/div\>/i", "", $text );
			$text = trim( $text );
			if( substr( $text, 0, 3 ) == "<p>" && substr( $text, -4, 4 ) == "</p>" ) {
				$text = substr( $text, 3, strlen( $text ) - 7 );
			}

			return $text;
		}

		return false;
	}

	/**
	 * Escape the regex for all the tags and get redirect tags
	 *
	 * @param array $config Configuration array
	 *
	 * @access public
	 * @static
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function escapeTags( &$config )
	{
		$marray   = $tarray = [];
		$toEscape = [];
		foreach( $config as $id => $value ) {
			if( strpos( $id, "tags" ) !== false && strpos( $id, "tags_data" ) === false ) {
				$toEscape[$id] = $value;
			}
		}
		foreach( $config['all_archives'] as $archiveName => $junk ) {
			$archiveName = str_replace( " ", "_", $archiveName );
			if( !empty( $config['darchive_' . $archiveName] ) ) {
				$toEscape['darchive_' . $archiveName] = $config['darchive_' . $archiveName];
			}
		}
		foreach( $toEscape as $id => $escapee ) {
			$tarray  = [];
			$tarray1 = [];
			$tarray2 = [];
			$marray  = [];
			$marray1 = [];
			$marray2 = [];
			if( $id != "ref_tags" ) {
				foreach( $escapee as $tag ) {
					$marray[] =
						self::getTemplateNamespaceName() . ":" . str_replace( "{", "", str_replace( "}", "", $tag ) );
					$tarray[] = str_replace( " ", '[\s\n_]+', preg_quote( trim( $tag, "\t\n\r\0\x0B{}" ), '/' ) );
				}
			} else {
				foreach( $escapee as $tag ) {
					[ $start, $end ] = @explode( ";", $tag );
					if( $start === null || $end === null ) continue;
					$marray1[] =
						self::getTemplateNamespaceName() . ":" . str_replace( "{", "", str_replace( "}", "", $start ) );
					$marray2[] =
						self::getTemplateNamespaceName() . ":" . str_replace( "{", "", str_replace( "}", "", $end ) );
					$tarray1[] = str_replace( " ", '[\s\n_]+', preg_quote( trim( $start, "\t\n\r\0\x0B{}" ), '/' ) );
					$tarray2[] = str_replace( " ", '[\s\n_]+', preg_quote( trim( $end, "\t\n\r\0\x0B{}" ), '/' ) );
				}
			}
			if( $id != "ref_tags" ) {
				do {
					$redirects = API::getRedirects( $marray );
					$marray    = [];
					foreach( $redirects as $tag ) {
						$marray[] = $tag['title'];
						$tarray[] = str_replace( " ", '[\s\n_]+',
						                         preg_quote( preg_replace( '/^.*?\:/i', "", $tag['title'] ), '/' )
						);
					}
				} while( !empty( $redirects ) );
			} else {
				do {
					$redirects = API::getRedirects( $marray1 );
					$marray1   = [];
					foreach( $redirects as $tag ) {
						$marray1[] = $tag['title'];
						$tarray1[] = str_replace( " ", '[\s\n_]+',
						                          preg_quote( preg_replace( '/^.*?\:/i', "", $tag['title'] ),
						                                      '/'
						                          )
						);
					}
				} while( !empty( $redirects ) );
				do {
					$redirects = API::getRedirects( $marray2 );
					$marray2   = [];
					foreach( $redirects as $tag ) {
						$marray2[] = $tag['title'];
						$tarray2[] = str_replace( " ", '[\s_]+',
						                          preg_quote( preg_replace( '/^.*?\:/i', "", $tag['title'] ),
						                                      '/'
						                          )
						);
					}
				} while( !empty( $redirects ) );
			}
			if( $id == "ref_tags" ) {
				if( !empty( $tarray1 ) && !empty( $tarray2 ) ) {
					$toEscape['ref_bounds'][] =
						[ 'template', $tarray1, $tarray2 ];
				}
			} else {
				$toEscape[$id] = $tarray;
			}
		}
		unset( $marray, $tarray );
		foreach( $toEscape as $id => $value ) {
			$config[$id] = $value;
		}
	}

	/**
	 * Get a list of templates that redirect to the given titles
	 *
	 * @param array $titles A list of pages titles to look up
	 *
	 * @access public
	 * @return array A list of templates that redirect to the given titles
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getRedirects( &$titles )
	{
		$returnArray = [];
		$resume      = [];
		foreach( array_chunk( $titles, self::getTitlesLimit(), true ) as $cutTitles ) {
			while( true ) {
				$params = [
					'action' => 'query',
					'format' => 'json',
					'prop' => 'redirects',
					'list' => '',
					'meta' => '',
					'rdprop' => 'title',
					'rdnamespace' => 10,
					'rdshow' => '',
					'rdlimit' => 5000,
					'titles' => implode( '|', $cutTitles )
				];
				$params = array_merge( $params, $resume );
				$get    = http_build_query( $params );
				if( IAVERBOSE ) echo "Making query: $get\n";
				$data = self::makeHTTPRequest( API, $params, true );
				$data = json_decode( $data, true );
				if( isset( $data['query']['pages'] ) ) {
					foreach( $data['query']['pages'] as $template ) {
						if( isset( $template['redirects'] ) ) {
							$returnArray =
								array_merge( $returnArray, $template['redirects'] );
						}
					}
				}
				if( isset( $data['continue'] ) ) {
					$resume = $data['continue'];
				} else {
					$resume = [];
					$titles = array_slice( $titles, self::getTitlesLimit() );
					break;
				}
			}
		}

		return $returnArray;
	}

	/**
	 * Check to see if a Wikiwix archive exists
	 *
	 * @access public
	 *
	 * @param string $url A Wikiwix URL that goes to an archive.
	 *
	 * @return bool Whether it exists or no
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function WikiwixExists( $url )
	{
		$queryURL = "http://archive.wikiwix.com/cache/?url=$url&apiresponse=1";

		if( ( $exists = DB::accessArchiveCache( $queryURL ) ) !== false ) {
			return unserialize( $exists );
		}

		if( IAVERBOSE ) echo "Making query: $queryURL\n";
		$data = self::makeHTTPRequest( $queryURL, [], false, false );
		if( $data == "cant connect db" ) return false;
		$data = json_decode( $data, true );

		DB::accessArchiveCache( $url, serialize( $data['status'] < 400 ) );

		return $data['status'] < 400;
	}

	/**
	 * Retrieves URL information given a Catalonian Archive URL
	 *
	 * @access public
	 *
	 * @param string $url A Catalonian Archive URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveCatalonianArchiveURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:www\.)?padi.cat(?:\:8080)?\/wayback\/(\d*?)\/(\S*)/i', $url, $match
		) ) {
			$returnArray['archive_url']  =
				"http://padi.cat:8080/wayback/" . $match[1] . "/" .
				$match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "catalonianarchive";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Run the CheckIfDead class on an external server
	 *
	 * @param string $server URL to the server to call.
	 * @param array $toValidate A list of URLs to check.
	 *
	 * @access public
	 * @static
	 * @return array Server results.  False on failure.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function runCIDServer( $server, $toValidate = [] )
	{
		$toValidate = implode( "\n", $toValidate );

		$params = [
			'urls' => $toValidate,
			'authcode' => CIDAUTHCODE
		];
		if( IAVERBOSE ) echo "Posting to $server\n";
		$data        = self::makeHTTPRequest( $server, $params, true, false );
		$returnArray = json_decode( $data, true );

		return $returnArray;
	}

	/**
	 * Enables system profiling
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 */
	public static function enableProfiling()
	{
		if( PROFILINGENABLED === true && self::$profiling_enabled === false ) {
			$options = [
				'ignored_functions' => [ 'API::disableProfiling', 'API::enableProfiling' ]
			];
			if( function_exists( "xhprof_enable" ) ) {
				xhprof_enable( 6, $options );
				self::$profiling_enabled = true;
			} elseif( function_exists( "tideways_xhprof_enable" ) ) {
				tideways_xhprof_enable( 6, $options );
				self::$profiling_enabled = true;
			} elseif( function_exists( "tideways_enable" ) ) {
				tideways_enable( 6, $options );
				self::$profiling_enabled = true;
			} elseif( function_exists( "uprofiler_enable" ) ) {
				uprofiler_enable( 6, $options );
				self::$profiling_enabled = true;
			} else echo "Error: Profiling functions are not available!\n";
		}
	}

	/**
	 * Disables system profiling and saves result
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 */
	public static function disableProfiling( $pageid, $title )
	{
		if( self::$profiling_enabled === true ) {
			$xhprof_object = new XHProfRuns_Default();
			if( function_exists( "xhprof_disable" ) ) {
				$xhprof_data             = xhprof_disable();
				self::$profiling_enabled = false;
			} elseif( function_exists( "tideways_xhprof_disable" ) ) {
				$xhprof_data             = tideways_xhprof_disable();
				self::$profiling_enabled = false;
			} elseif( function_exists( "tideways_disable" ) ) {
				$xhprof_data             = tideways_disable();
				self::$profiling_enabled = false;
			} elseif( function_exists( "uprofiler_disable" ) ) {
				$xhprof_data             = uprofiler_disable();
				self::$profiling_enabled = false;
			} else echo "Error: Something is wrong with the installed profile modules!\n";
			if( !empty( $xhprof_data ) ) {
				$inclusiveData = xhprof_compute_inclusive_times( $xhprof_data );
				$runTime       = $inclusiveData['main()']['wt'];
				if( $runTime > 5000000 ) {
					$ignoreFunctions = [
						'Wikimedia\DeadlinkChecker\CheckIfDead::areLinksDead',
						'Wikimedia\DeadlinkChecker\CheckIfDead::performFullRequest', 'API::runCIDServer'
					];
					foreach( $ignoreFunctions as $function ) {
						if( isset( $inclusiveData[$function]['wt'] ) ) $runTime -= $inclusiveData[$function]['wt'];
					}
					if( isset( $inclusiveData['curl_exec'] ) &&
					    $inclusiveData['curl_exec']['ct'] <= 10 ) {
						$runTime -= $inclusiveData['curl_exec']['wt'];
					}

					if( $runTime > 5000000 ) {
						$xhprof_object->save_run( $xhprof_data,
						                          "botworker-" .
						                          WIKIPEDIA . "-" .
						                          $pageid . "-" .
						                          $title
						);
					}
				}

			}
		}
	}

	/**
	 * Retrieve the page contents of specific revisions
	 *
	 * @param array Revisions IDs to fetch
	 *
	 * @access public
	 * @return array API response
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getBotRevisions()
	{
		if( empty( $this->history ) ) $this->history = self::getPageHistory( $this->page );
		$returnArray = [];

		foreach( $this->history as $revision ) {
			if( isset( $revision['user'] ) && $revision['user'] == TASKNAME ) $returnArray[] = $revision['revid'];
		}

		return $returnArray;
	}

	/**
	 * Get the revision IDs of a page
	 *
	 * @param string $page Page title to fetch history for
	 *
	 * @access public
	 * @static
	 * @return array Revision history
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getPageHistory( $page )
	{
		$returnArray = [];
		$resume      = [];
		while( count( $returnArray ) < 50000 ) {
			$params = [
				'action' => 'query',
				'prop' => 'revisions',
				'format' => 'json',
				'rvdir' => 'newer',
				'rvprop' => 'ids|user|userid',
				'rvlimit' => 'max',
				'titles' => $page
			];
			$params = array_merge( $params, $resume );
			$get    = http_build_query( $params );
			if( IAVERBOSE ) echo "Making query: $get\n";
			$data = self::makeHTTPRequest( API, $params, true );
			$data = json_decode( $data, true );
			if( isset( $data['query']['pages'] ) ) {
				foreach( $data['query']['pages'] as $template ) {
					if( isset( $template['revisions'] ) ) {
						$returnArray =
							array_merge( $returnArray, $template['revisions'] );
					}
				}
			}
			if( isset( $data['continue'] ) ) {
				$resume = $data['continue'];
			} else {
				$resume = [];
				break;
			}
			$data = null;
			unset( $data );
		}

		return $returnArray;
	}

	/**
	 * Get revision text for page history after given Rev ID
	 *
	 * @param int $lastID The oldest ID to fetch from the page history
	 *
	 * @access public
	 * @return array User information or false if the reversion wasn't actually a revert
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getRevTextHistory( $lastID )
	{
		if( empty( $this->history ) ) $this->history = self::getPageHistory( $this->page );

		$revisions = [];
		$toFetch   = [];

		foreach( $this->history as $revision ) {
			if( $revision['revid'] < $lastID ) continue;
			if( !isset( $revision['*'] ) ) {
				$toFetch[] = $revision['revid'];
			} else $revisions[$revision['revid']] = $revision;
		}

		if( !empty( $toFetch ) ) {
			$data = self::getRevisionText( $toFetch );

			foreach( $data['query']['pages'][$this->pageid]['revisions'] as $revision ) {
				foreach( $this->history as $id => $hrevision ) {
					if( $hrevision['revid'] == $revision['revid'] ) {
						if( isset( $revision['texthidden'] ) ) continue;
						$this->history[$id]['*']             = $revision['slots']['main']['*'];
						$this->history[$id]['timestamp']     = $revision['timestamp'];
						$this->history[$id]['contentformat'] = $revision['slots']['main']['contentformat'];
						$this->history[$id]['contentmodel']  = $revision['slots']['main']['contentmodel'];
						$revisions[$revision['revid']]       = $this->history[$id];
						break;
					}
				}
			}
		}

		ksort( $revisions );

		return $revisions;
	}

	/**
	 * Retrieve the page contents of specific revisions
	 *
	 * @param array Revisions IDs to fetch
	 *
	 * @access public
	 * @static
	 * @return array API response
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function getRevisionText( $revisions )
	{
		$params = [
			'action' => 'query',
			'prop' => 'revisions',
			'format' => 'json',
			'rvprop' => 'timestamp|content|ids',
			'rvslots' => '*',
			'revids' => implode( '|', $revisions )
		];
		$get    = http_build_query( $params );

		if( IAVERBOSE ) echo "Making query: $get\n";

		//Fetch revisions of needle location in page history.  Scan for the presence of URL.
		$data = self::makeHTTPRequest( API, $params );
		$data = json_decode( $data, true );

		return $data;
	}

	/**
	 * Find out who reverted the text and why
	 *
	 * @param string Newstring to search
	 * @param string Old string to search
	 *
	 * @access public
	 * @return array User information or false if the reversion wasn't actually a revert or the reverter is an IP
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getRevertingUser( $newlink, $oldLinks, $lastID )
	{
		if( empty( $this->history ) ) $this->history = self::getPageHistory( $this->page );

		foreach( $oldLinks as $revID => $links ) {
			$links = $links->get( true );
			if( $lastID >= $revID ) continue;

			if( $newlink['link_type'] == "reference" ) {
				foreach( $newlink['reference'] as $tid => $link ) {
					if( !is_numeric( $tid ) ) continue;
					if( !isset( $link['newdata'] ) ) continue;

					$breakout = false;
					foreach( $links as $revLink ) {
						if( $revLink['link_type'] == "reference" ) {
							foreach( $revLink['reference'] as $ttid => $oldLink ) {
								if( !is_numeric( $ttid ) ) continue;
								if( isset( $oldLink['ignore'] ) ) continue;

								if( $oldLink['url'] == $link['url'] ) {
									$breakout = true;
									break;
								}
							}
						} else {
							if( isset( $revLink[$revLink['link_type']]['ignore'] ) ) continue;
							if( $revLink[$revLink['link_type']]['url'] == $link['url'] ) {
								$oldLink = $revLink[$revLink['link_type']];
								break;
							}
						}
						if( $breakout === true ) break;
					}
					if( is_array( $oldLink ) && self::isReverted( $oldLinks[$lastID], $link, $oldLink ) ) {
						foreach( $this->history as $revision ) {
							if( $revision['revid'] != $revID ) continue;
							if( !isset( $revision['user'] ) || !isset( $revision['userid'] ) ) return false;

							return [ 'name' => $revision['user'], 'userid' => $revision['userid'] ];
						}
					}
				}
			} else {
				$link = $newlink[$newlink['link_type']];

				$breakout = false;
				foreach( $links as $revLink ) {
					if( $revLink['link_type'] == "reference" ) {
						foreach( $revLink['reference'] as $ttid => $oldLink ) {
							if( !is_numeric( $ttid ) ) continue;
							if( isset( $oldLink['ignore'] ) ) continue;

							if( $oldLink['url'] == $link['url'] ) {
								$breakout = true;
								break;
							}
						}
					} else {
						if( isset( $revLink[$revLink['link_type']]['ignore'] ) ) continue;
						if( $revLink[$revLink['link_type']]['url'] == $link['url'] ) {
							$oldLink = $revLink[$revLink['link_type']];
							break;
						}
					}
					if( $breakout === true ) break;
				}
				if( is_array( $oldLink ) && self::isReverted( $oldLinks[$lastID], $link, $oldLink ) ) {
					foreach( $this->history as $revision ) {
						if( $revision['revid'] != $revID ) continue;
						if( !isset( $revision['user'] ) || !isset( $revision['userid'] ) ) return false;

						return [ 'name' => $revision['user'], 'userid' => $revision['userid'] ];
					}
				}
			}
		}

		return false;
	}

	/**
	 * Compare the links and determine if it was reversed
	 *
	 * @param $oldLink Link from a revision
	 * @param $link Link being changed
	 * @param $intermediateRevisionLinks A collection of links from an intermediate revision
	 *
	 * @access public
	 * @static
	 * @return bool Whether the change was reversed
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function isReverted( $oldLink, $link, $intermediateRevisionLink = false )
	{
		if( $oldLink instanceof Memory ) $oldLink = $oldLink->get( true );

		if( $intermediateRevisionLink !== false ) {
			foreach( $oldLink as $tLink ) {
				$breakout = false;
				if( $tLink['link_type'] == "reference" ) {
					foreach( $tLink['reference'] as $tid => $refLink ) {
						if( !is_numeric( $tid ) ) continue;
						if( isset( $refLink['ignore'] ) ) continue;

						if( $refLink['url'] == $intermediateRevisionLink['url'] ) {
							$oldLink  = $refLink;
							$breakout = true;
							break;
						}
					}
				} else {
					if( isset( $tLink[$tLink['link_type']]['ignore'] ) ) continue;
					if( $tLink[$tLink['link_type']]['url'] == $intermediateRevisionLink['url'] ) {
						$oldLink = $tLink[$tLink['link_type']];
						break;
					}
				}
				if( $breakout === true ) break;
			}
		}
		if( isset( $link['newdata']['has_archive'] ) && $oldLink['has_archive'] === false ) {
			return false;
		} elseif( isset( $link['newdata']['archive_url'] ) &&
		          $link['newdata']['archive_url'] != $oldLink['archive_url'] ) {
			return false;
		} elseif( isset( $link['newdata']['has_archive'] ) ) {
			if( $intermediateRevisionLink === false ) return true;
			if( $oldLink['has_archive'] === true && $intermediateRevisionLink['has_archive'] === true ) {
				return false;
			} elseif( $intermediateRevisionLink['has_archive'] === false ) return true;
		}

		if( isset( $link['newdata']['tagged_dead'] ) && $link['newdata']['tagged_dead'] === true &&
		    $oldLink['tagged_dead'] === false ) {
			return false;
		} elseif( isset( $link['newdata']['tagged_dead'] ) && $link['newdata']['tagged_dead'] === false &&
		          $oldLink['tagged_dead'] === true ) {
			return false;
		} elseif( isset( $link['newdata']['tagged_dead'] ) ) {
			if( $intermediateRevisionLink === false ) return true;
			if( $oldLink['tagged_dead'] === true && $intermediateRevisionLink['tagged_dead'] === true ) {
				return false;
			} elseif( $intermediateRevisionLink['tagged_dead'] === false ) return true;
		}
	}

	/**
	 * Submit URLs to be archived
	 *
	 * @access public
	 *
	 * @param array $urls A collection of URLs to be archived.  Index keys are preserved.
	 *
	 * @return array results of the archive process including errors
	 *
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public function requestArchive( $urls )
	{
		$getURLs     = [];
		$returnArray = [ 'result' => [], 'errors' => [] ];
		foreach( $urls as $id => $url ) {
			//Skip over archive.org URLs
			if( strpos( parse_url( $url, PHP_URL_HOST ), 'archive.org' ) !== false ) {
				$returnArray['result'][$id] = null;
				continue;
			}
			//See if we already attempted this in the DB, or if a snapshot already exists.  We don't want to keep hammering the server.
			if( $this->db->dbValues[$id]['archived'] == 1 ||
			    ( isset( $this->db->dbValues[$id]['archivable'] ) && $this->db->dbValues[$id]['archivable'] == 0 )
			) {
				$returnArray['result'][$id] = null;
				continue;
			}
			//If it doesn't then proceed.
			$getURLs[$id] = $url;
		}

		$res = $this->SavePageNow( $getURLs );

		$errorIDs = [];

		if( $res !== false ) {
			foreach( $res as $id => $result ) {
				if( $result['success'] === false ) {
					if( $result['archivable'] == 1 ) {
						$errorIDs[] = $id;
					} else {
						$this->db->dbValues[$id]['archivable']      = 0;
						$this->db->dbValues[$id]['archive_failure'] = $result['error']['status_ext'];
					}
					$returnArray['result'][$id] = false;
					$returnArray['errors'][$id] = $result['error']['status_ext'];
				} else {
					$this->db->dbValues[$id]['archived']     = 1;
					$this->db->dbValues[$id]['has_archive']  = 1;
					$this->db->dbValues[$id]['archive_url']  = $result['archive_url'];
					$this->db->dbValues[$id]['archive_time'] = $result['archive_time'];
					$returnArray['result'][$id]              = true;
				}
			}
		}

		if( !empty( $errorIDs ) ) {
			$body = "While running numerous SPN2 jobs, server-side errors were encountered:\r\n";
			foreach( $errorIDs as $tid ) {
				$body .= "Error running URL " . $getURLs[$id] . "\r\n";
				$body .= "	Exception: {$res[$tid]['error']['exception']}\r\n";
				$body .= "	Status: {$res[$tid]['error']['status_ext']}\r\n";
				$body .= "	Job ID: {$res[$tid]['error']['job_id']}\r\n";
				$body .= "	Error message: {$res[$tid]['error']['message']}\r\n\r\n";
			}

			self::sendMail( TO, FROM, "Errors encountered while submitting URLs for archiving!!", $body );
		}
		$res = null;
		unset( $res );

		return $returnArray;
	}

	/**
	 * Execute SPN2 with provided list of URLs to capture
	 *
	 * @access protected
	 *
	 * @param array $data A collection of URLs to submit to the Wayback Machine.
	 *
	 * @return array Result data and errors encountered during the process.  Index keys are preserved.
	 *
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	protected function SavePageNow( $urls )
	{

		if( !defined( 'WAYBACKACCESSKEY' ) || !defined( 'WAYBACKACCESSSECRET' ) || empty( WAYBACKACCESSKEY ) ||
		    empty( WAYBACKACCESSSECRET ) ) {
			echo "ERROR: Must have valid credentials in order to save to the Wayback Machine.\n";

			return false;
		}

		$jobQueueData = [];
		$returnArray  = [];

		$requestHeaders[]         = "Authorization: LOW " . WAYBACKACCESSKEY . ":" . WAYBACKACCESSSECRET;
		$requestHeaders[]         = "Accept: application/json";
		$post['capture_outlinks'] = 1;

		$apiURL = "https://web-beta.archive.org/save";

		foreach( $urls as $tid => $url ) {
			$post['url'] = $url;
			for( $i = 0; $i <= 21; $i++ ) {
				if( IAVERBOSE ) echo "Posting to $apiURL\n";

				$data = self::makeHTTPRequest( $apiURL, $post, true, false, [], $requestHeaders );
				$data = json_decode( $data, true );

				if( isset( $data['status'] ) ) {
					if( $data['status'] == "error" ) {
						if( $data['status_ext'] == "error:user-session-limit" ) {
							sleep( 2 );
						} else {
							$returnArray[$tid]['success'] = false;
							$returnArray[$tid]['error']   = $data;
							switch( $data['status_ext'] ) {
								case "error:user-session-limit":
								case "error:celery":
									$returnArray['archivable'] = 1;
									break;
								default:
									$returnArray['archivable'] = 0;
									break;
							}
							break;
						}
					}
				} elseif( isset( $data['job_id'] ) ) {
					$jobQueueData[$tid] = $data['job_id'];
					break;
				}
			}
		}
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, "$apiURL/status" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, $requestHeaders );
		$post = [];

		while( !empty( $jobQueueData ) ) {
			sleep( 2 );
			foreach( $jobQueueData as $tid => $job ) {

				$post['job_id'] = $job;
				curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $post );

				if( IAVERBOSE ) echo "Posting to $apiURL/status\n";

				$data = curl_exec( self::$globalCurl_handle );
				$data = json_decode( $data, true );

				if( isset( $data['status'] ) ) {
					if( $data['status'] == "success" ) {
						$returnArray[$tid]['archive_url']  =
							"https://web.archive.org/web/{$data['timestamp']}/{$data['original_url']}";
						$returnArray[$tid]['archive_time'] = strtotime( $data['timestamp'] );
						$returnArray[$tid]['success']      = true;
						unset( $jobQueueData[$tid] );
					} elseif( $data['status'] == "error" ) {
						$returnArray[$tid]['success'] = false;
						$returnArray[$tid]['error']   = $data;
						switch( $data['status_ext'] ) {
							case "error:user-session-limit":
							case "error:soft-time-limit-exceeded":
							case "error:proxy-error":
							case "error:browsing-timeout":
							case "error:no-browsers-available":
							case "error:redis-error":
							case "error:capture-location-error":
								$returnArray[$tid]['archivable'] = 1;
								break;
							default:
								$returnArray[$tid]['archivable'] = 0;
								break;
						}
						unset( $jobQueueData[$tid] );
					}
				}
			}
		}

		return $returnArray;
	}

	/**
	 * Send an email
	 *
	 * @param string $to Who to send it to
	 * @param string $from Who to mark it from
	 * @param string $subject Subject line to set
	 * @param string $email Body of email
	 *
	 * @access public
	 * @static
	 * @return bool True on successful
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function sendMail( $to, $from, $subject, $email )
	{
		if( !ENABLEMAIL ) return false;
		echo "Sending a message to $to...";
		$headers   = [];
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-type: text/plain; charset=iso-8859-1";
		$headers[] = "From: $from";
		$headers[] = "Reply-To: <>";
		$headers[] = "X-Mailer: PHP/" . phpversion();
		$headers[] = "Useragent: " . USERAGENT;
		$headers[] = "X-Accept-Language: en-us, en";

		$success = mail( $to, $subject, $email, implode( "\r\n", $headers ) );
		if( $success ) {
			echo "Success!!\n";
		} else echo "Failed!!\n";

		return $success;
	}

	/**
	 * Checks whether the given URLs have respective archives
	 *
	 * @access public
	 *
	 * @param array $urls A collection of URLs to checked.
	 *
	 * @return array containing result data and errors.  Index keys are preserved.
	 *
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public function isArchived( $urls )
	{
		$getURLs     = [];
		$loopLimit   = 10;
		$returnArray = [ 'result' => [], 'errors' => [] ];

		$cdxMaster   = !(bool) THROTTLECDXREQUESTS;
		$centralised = false;
		foreach( $urls as $id => $url ) {
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
			$url          = urlencode( $url );
			$getURLs[$id] = "url=$url&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
		}
		$counter = 0;
		while( !empty( $getURLs ) ) {
			$counter++;
			$res = self::CDXQuery( $getURLs, $cdxMaster, $centralised );
			if( !empty( $res['results'] ) ) {
				foreach( $getURLs as $id => $post ) {
					if( !is_null( $res['results'][$id] ) ) {
						unset( $getURLs[$id] );
						if( @isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) {
							$returnArray['errors'][$id] =
								$res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
						}
						if( $res['results'][$id]['available'] === true ) {
							//It exists, return and mark it in the DB.
							$returnArray['result'][$id]            = true;
							$this->db->dbValues[$id]['archived']   = 1;
							$this->db->dbValues[$id]['archivable'] = 1;
						} else {
							//It doesn't exist, return and mark it in the DB.
							$returnArray['result'][$id]             = false;
							$this->db->dbValues[$id]['has_archive'] = 0;
							$this->db->dbValues[$id]['archived']    = 0;
						}
					} else {
						$returnArray['result'][$id] = null;
						if( $counter === $loopLimit ) {
							$returnArray['errors'][$id]['post']  = $post;
							$returnArray['errors'][$id]['error'] = "Received a bad response after $loopLimit attempts";
						}
					}
				}
			} else {
				foreach( $getURLs as $id => $junk ) {
					$returnArray['result'][$id] = null;
				}

				$returnArray['errors']['query_error']     = $res['error'];
				$returnArray['errors']['query_http_code'] = $res['code'];

				return $returnArray;
			}
			$res = null;
			unset( $res );
			if( $counter === $loopLimit ) break;
		}

		return $returnArray;
	}

	/**
	 * Run a query on the wayback API version 2
	 *
	 * @param array $post a bunch of post parameters for each URL
	 *
	 * @access public
	 * @static
	 * @return array Result data and errors encountered during the process.  Index keys are preserved.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function CDXQuery( $post = [], $isMaster = true, $centralized = false )
	{
		$returnArray = [ 'error' => false, 'results' => [], 'headers' => "", 'code' => 0 ];
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		if( !defined( 'CDXENDPOINT' ) ) {
			$url = "http://archive.org/wayback/available";
		} else $url = CDXENDPOINT;
		$initialPost = $post;

		if( !$isMaster ) {
			$queuedRequests = [];
			foreach( $post as $tid => $payload ) {
				$queuedRequests[$tid] = DB::addAvailabilityRequest( $payload );
			}

			while( ( $result = DB::getAvailabilityRequestIDs( $queuedRequests, true, true ) ) === false ) {
				sleep( 2 );
			}

			foreach( $queuedRequests as $tid => $requestID ) {
				$data = unserialize( $result[$requestID]['response_data'] );
				if( isset( $data['archived_snapshots'] ) ) {
					if( isset( $data['archived_snapshots']['closest'] ) ) {
						$returnArray['results'][$data['tag']] =
							$data['archived_snapshots']['closest'];
					} else $returnArray['results'][$data['tag']] = false;
				} else {
					$returnArray['results'][$data['tag']] = null;
				}
			}
		} else {
			if( !$centralized ) {
				$limit = 50;
			} else $limit = 1;
			$i = 0;

			$bom = pack( 'H*', 'EFBBBF' );

			while( !empty( $post ) && $i < $limit ) {
				$i++;
				$tpost = implode( "\n", $post );
				$tpost = str_replace( $bom, '', $tpost );
				curl_setopt( self::$globalCurl_handle, CURLOPT_HEADER, 1 );
				if( IAVERBOSE ) echo "Posting to $url\n";
				$data = self::makeHTTPRequest( $url, $tpost, true, false, [], [ "Wayback-Api-Version: 2" ] );
				curl_setopt( self::$globalCurl_handle, CURLOPT_HEADER, 0 );
				$header_size            = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
				$returnArray['headers'] = self::http_parse_headers( substr( $data, 0, $header_size ) );
				$returnArray['error']   = curl_error( self::$globalCurl_handle );
				$returnArray['code']    = curl_getinfo( self::$globalCurl_handle, CURLINFO_HTTP_CODE );
				$t                      = trim( substr( $data, $header_size ) );
				$data                   = json_decode( $t, true );
				if( is_null( $data ) ) continue;

				foreach( $data['results'] as $result ) {
					if( isset( $result['archived_snapshots'] ) ) {
						if( !$centralized ) {
							if( isset( $result['archived_snapshots']['closest'] ) ) {
								$returnArray['results'][$result['tag']] =
									$result['archived_snapshots']['closest'];
							} else $returnArray['results'][$result['tag']] = false;
						} else {
							$returnArray['results'][$result['tag']] = $result;
						}
						unset( $post[$result['tag']] );
					} else {
						$returnArray['results'][$result['tag']] = null;
					}
				}
			}
			$body = "";
			if( ( !empty( $post ) || !empty( $returnArray['error'] ) ) &&
			    ( $returnArray['code'] != 200 || $returnArray['code'] >= 400 ) ) {
				$body .= "Executing user: " . USERNAME . "\n";
				$body .= "Public IP: " . file_get_contents( "http://ipecho.net/plain" ) . "\n";
				$body .= "Machine Host Name: " . gethostname() . "\n\n";
				$body .= "Error running POST:\r\n";
				$body .= "  Initial Payload: " . implode( "\r\n", $initialPost ) . "\r\n";
				$body .= "  Final Payload: " . implode( "\r\n", $post ) . "\r\n";
				$body .= "  On URL: $url\r\n";
				$body .= "  Using Headers: \"Wayback-Api-Version: 2\"\r\n";
				$body .= "	Response Code: " . $returnArray['code'] . "\r\n";
				$body .= "	Headers:\r\n";
				foreach( $returnArray['headers'] as $header => $value ) $body .= "		$header: $value\r\n";
				$body .= "	Curl Errors Encountered: " . $returnArray['error'] . "\r\n";
				$body .= "	Body:\r\n";
				$body .= "$t\r\n\r\n";
				self::sendMail( TO, FROM, "Errors encountered while querying the availability API!!", $body );
			}
		}

		if( !isset( $data ) || is_null( $data ) ) return false;

		return $returnArray;
	}

	/**
	 * Parse the http headers returned in a request
	 *
	 * @param string $header header string returned from a web request.
	 *
	 * @access protected
	 * @return array Associative array of the header
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected static function http_parse_headers( $header )
	{
		$header      = preg_replace( '/http\/\d\.\d\s\d{3}.*?\n/i', "", $header );
		$header      = explode( "\n", $header );
		$returnArray = [];
		foreach( $header as $id => $item ) $header[$id] = explode( ":", $item, 2 );
		foreach( $header as $id => $item ) if( count( $item ) == 2 ) $returnArray[trim( $item[0] )] = trim( $item[1] );

		return $returnArray;
	}

	/**
	 * Retrieve respective archives of the given URLs
	 *
	 * @access public
	 *
	 * @param array $data A collection of URLs to search for.
	 *
	 * @return array Result data and errors encountered during the process. Index keys are preserved.
	 *
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public function retrieveArchive( $data )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [ 'result' => [], 'errors' => [] ];
		$getURLs     = [];

		$cdxMaster   = !(bool) THROTTLECDXREQUESTS;
		$centralised = false;
		//Check to see if the DB can deliver the needed information already
		foreach( $data as $id => $item ) {
			//Skip over archive.org URLs
			if( strpos( parse_url( $item[0], PHP_URL_HOST ), 'archive.org' ) !== false ) {
				$returnArray['result'][$id]             = false;
				$this->db->dbValues[$id]['has_archive'] = 0;
				$this->db->dbValues[$id]['archived']    = 0;
				continue;
			}
			if( isset( $this->db->dbValues[$id]['has_archive'] ) && $this->db->dbValues[$id]['has_archive'] == 1 ) {
				if( API::isArchive( $this->db->dbValues[$id]['archive_url'], $metadata ) &&
				    !isset( $metadata['invalid_archive'] ) ) {
					$returnArray['result'][$id]['archive_url']  = $this->db->dbValues[$id]['archive_url'];
					$returnArray['result'][$id]['archive_time'] = $this->db->dbValues[$id]['archive_time'];
					unset( $metadata );
					continue;
				} else {
					//If we have metadata, it means we are choosing to ignore what's in the DB.
					$metadatas[$id] = $metadata;
					unset( $metadata );
				}
			} elseif( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 0 ) {
				$returnArray['result'][$id]             = false;
				$this->db->dbValues[$id]['has_archive'] = 0;
				continue;
			}
			//If not proceed to API calls
			$url  = $item[0];
			$time = $item[1];
			$url  = urlencode( $url );
			//Fetch a snapshot preceding the time a URL was accessed on wiki.
			$getURLs[$id] = "url=$url" . ( !is_null( $time ) ? "&timestamp=" . date( 'YmdHis', $time ) : "" ) .
			                "&closest=before&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
		}
		$res = self::CDXQuery( $getURLs, $cdxMaster, $centralised );
		if( !empty( $res['results'] ) ) {
			foreach( $getURLs as $id => $post ) {
				if( !is_null( $res['results'][$id] ) ) {
					if( !empty( $res['results'][$id] ) ) {
						//We have a result.  Save it in the DB, and return the value.
						preg_match( '/\/\/(?:web\.|wayback\.)?archive\.org(?:\/web)?\/(\d*?)\/(\S*)/i',
						            $res['results'][$id]['url'], $match
						);
						$this->db->dbValues[$id]['archive_url']  =
						$returnArray['result'][$id]['archive_url'] = "https://web.archive.org/web/" . $match[1] . "/" .
						                                             $checkIfDead->sanitizeURL( $match[2], true, true
						                                             );
						$this->db->dbValues[$id]['archive_time'] =
						$returnArray['result'][$id]['archive_time'] = strtotime( $res['results'][$id]['timestamp'] );
						$this->db->dbValues[$id]['has_archive']  = 1;
						$this->db->dbValues[$id]['archived']     = 1;
						$this->db->dbValues[$id]['archivable']   = 1;
						unset( $getURLs[$id] );
					} else {
						//We don't see if we can get an archive from after the access time.
						$url          = urlencode( $data[$id][0] );
						$time         = $data[$id][1];
						$getURLs[$id] =
							"url=$url" . ( !is_null( $time ) ? "&timestamp=" . date( 'YmdHis', $time ) : "" ) .
							"&closest=after&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
					}
				} else {
					$getURLs[$id] = "url={$data[$id][0]}" .
					                ( !is_null( $data[$id][1] ) ? "&timestamp=" . date( 'YmdHis', $data[$id][1] ) :
						                "" ) .
					                "&statuscodes=200&statuscodes=203&statuscodes=206&tag=$id";
				}
			}
		} else {
			foreach( $getURLs as $id => $junk ) {
				$returnArray['result'][$id] = null;
			}

			return $returnArray;
		}
		$res = null;
		unset( $res );
		if( !empty( $getURLs ) ) {
			$res = self::CDXQuery( $getURLs, $cdxMaster, $centralised );
			if( !empty( $res['results'] ) ) {
				foreach( $getURLs as $id => $post ) {
					if( !is_null( $res['results'][$id] ) ) {
						if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) {
							$returnArray['errors'][$id] =
								$res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
						}
						if( !empty( $res['results'][$id] ) ) {
							//We have a result.  Save it in the DB,a nd return the value.
							preg_match( '/\/\/(?:web\.|wayback\.)?archive\.org(?:\/web)?\/(\d*?)\/(\S*)/i',
							            $res['results'][$id]['url'], $match
							);
							$this->db->dbValues[$id]['archive_url']  =
							$returnArray['result'][$id]['archive_url'] =
								"https://web.archive.org/web/" . $match[1] . "/" .
								$checkIfDead->sanitizeURL( urldecode( $match[2] ),
								                           true
								);
							$this->db->dbValues[$id]['archive_time'] =
							$returnArray['result'][$id]['archive_time'] =
								strtotime( $res['results'][$id]['timestamp'] );
							$this->db->dbValues[$id]['has_archive']  = 1;
							$this->db->dbValues[$id]['archived']     = 1;
							$this->db->dbValues[$id]['archivable']   = 1;
						} elseif( !isset( $metadatas[$id] ) ) {
							//No results.  Mark so in the DB and return it.
							$returnArray['result'][$id]             = false;
							$this->db->dbValues[$id]['has_archive'] = 0;
							$this->db->dbValues[$id]['archived']    = 0;
						}
					} else {
						$returnArray['result'][$id] = null;
					}
				}
			} else {
				foreach( $getURLs as $id => $junk ) {
					$returnArray['result'][$id] = null;
				}

				return $returnArray;
			}
			$res = null;
			unset( $res );
		}

		return $returnArray;
	}

	/**
	 * Determine if the URL is a common archive, and attempts to resolve to original URL.
	 *
	 * @param string $url The URL to test
	 * @param array $data The data about the URL to pass back
	 *
	 * @access public
	 * @static
	 * @return bool True if it is an archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function isArchive( $url, &$data )
	{
		//A hacky check for HTML encoded pipes
		$url         = str_replace( "&#124;", "|", $url );
		$url         = preg_replace( '/#.*/', '', $url );
		$checkIfDead = new CheckIfDead();
		$parts       = $checkIfDead->parseURL( $url );
		if( empty( $parts['host'] ) ) return false;
		if( strpos( $parts['host'], "europarchive.org" ) !== false ||
		    strpos( $parts['host'], "internetmemory.org" ) !== false ) {
			$resolvedData = self::resolveEuropaURL( $url );
		} elseif( strpos( $parts['host'], "webarchive.org.uk" ) !== false ) {
			$resolvedData = self::resolveUKWebArchiveURL( $url );
		} elseif( strpos( $parts['host'], "archive.org" ) !== false ||
		          strpos( $parts['host'], "waybackmachine.org" ) !== false
		) {
			$resolvedData = self::resolveWaybackURL( $url );
			if( isset( $resolvedData['archive_time'] ) && $resolvedData['archive_time'] == "x" ) {
				$data['iarchive_url']    = $resolvedData['archive_url'];
				$data['invalid_archive'] = true;
			}
		} elseif( strpos( $parts['host'], "archive.is" ) !== false ||
		          strpos( $parts['host'], "archive.today" ) !== false ||
		          strpos( $parts['host'], "archive.fo" ) !== false ||
		          strpos( $parts['host'], "archive.li" ) !== false ||
		          strpos( $parts['host'], "archive.vn" ) !== false ||
		          strpos( $parts['host'], "archive.md" ) !== false ||
		          strpos( $parts['host'], "archive.ph" ) !== false
		) {
			$resolvedData = self::resolveArchiveIsURL( $url );
		} elseif( strpos( $parts['host'], "mementoweb.org" ) !== false ) {
			$resolvedData = self::resolveMementoURL( $url );
		} elseif( strpos( $parts['host'], "webcitation.org" ) !== false ) {
			$resolvedData = self::resolveWebCiteURL( $url );
			//$data['iarchive_url'] = $resolvedData['archive_url'];
			//$data['invalid_archive'] = true;
		} elseif( strpos( $parts['host'], "yorku.ca" ) !== false ) {
			$resolvedData = self::resolveYorkUURL( $url );
		} elseif( strpos( $parts['host'], "archive-it.org" ) !== false ) {
			$resolvedData = self::resolveArchiveItURL( $url );
		} elseif( strpos( $parts['host'], "arquivo.pt" ) !== false ) {
			$resolvedData = self::resolveArquivoURL( $url );
		} elseif( strpos( $parts['host'], "loc.gov" ) !== false ) {
			$resolvedData = self::resolveLocURL( $url );
		} elseif( strpos( $parts['host'], "webharvest.gov" ) !== false ) {
			$resolvedData = self::resolveWebharvestURL( $url );
		} elseif( strpos( $parts['host'], "bibalex.org" ) !== false ) {
			$resolvedData = self::resolveBibalexURL( $url );
		} elseif( strpos( $parts['host'], "collectionscanada" ) !== false ) {
			$resolvedData = self::resolveCollectionsCanadaURL( $url );
		} elseif( strpos( $parts['host'], "veebiarhiiv" ) !== false ) {
			$resolvedData = self::resolveVeebiarhiivURL( $url );
		} elseif( strpos( $parts['host'], "vefsafn.is" ) !== false ) {
			$resolvedData = self::resolveVefsafnURL( $url );
		} elseif( strpos( $parts['host'], "proni.gov" ) !== false ) {
			$resolvedData = self::resolveProniURL( $url );
		} elseif( strpos( $parts['host'], "uni-lj.si" ) !== false ) {
			$resolvedData = self::resolveSpletniURL( $url );
		} elseif( strpos( $parts['host'], "stanford.edu" ) !== false ) {
			$resolvedData = self::resolveStanfordURL( $url );
		} elseif( strpos( $parts['host'], "nationalarchives.gov.uk" ) !== false ) {
			$resolvedData = self::resolveNationalArchivesURL( $url );
		} elseif( strpos( $parts['host'], "parliament.uk" ) !== false ) {
			$resolvedData = self::resolveParliamentUKURL( $url );
		} elseif( strpos( $parts['host'], "nlb.gov.sg" ) !== false ) {
			$resolvedData = self::resolveWASURL( $url );
		} elseif( strpos( $parts['host'], "perma" ) !== false ) {
			$resolvedData = self::resolvePermaCCURL( $url );
		} elseif( strpos( $parts['host'], "bac-lac.gc.ca" ) !== false ) {
			$resolvedData = self::resolveLACURL( $url );
		} elseif( strpos( $parts['host'], "webcache.googleusercontent.com" ) !== false ) {
			$resolvedData            = self::resolveGoogleURL( $url );
			$data['iarchive_url']    = $resolvedData['archive_url'];
			$data['invalid_archive'] = true;
		} elseif( strpos( $parts['host'], "nla.gov.au" ) !== false ) {
			$resolvedData = self::resolveNLAURL( $url );
		} elseif( strpos( $parts['host'], "wikiwix.com" ) !== false ) {
			$resolvedData = self::resolveWikiwixURL( $url );
		} elseif( strpos( $parts['host'], "freezepage" ) !== false ) {
			$resolvedData = self::resolveFreezepageURL( $url );
		} elseif( strpos( $parts['host'], "webrecorder" ) !== false ) {
			$resolvedData = self::resolveWebRecorderURL( $url );
		} elseif( strpos( $parts['host'], "webarchive.org.uk" ) !== false ) {
			$resolvedData = self::resolveWebarchiveUKURL( $url );
		} else return false;
		if( empty( $resolvedData['url'] ) ) return false;
		if( empty( $resolvedData['archive_url'] ) ) return false;
		if( empty( $resolvedData['archive_time'] ) ) {
			return false;
		} else {
			if( $resolvedData['archive_time'] < 820454400 || $resolvedData['archive_time'] > time() ) {
				$data['iarchive_url']         = $resolvedData['archive_url'];
				$data['invalid_archive']      = true;
				$resolvedData['archive_time'] = "x";
			}
		}
		if( empty( $resolvedData['archive_host'] ) ) return false;
		if( isset( $resolvedData['convert_archive_url'] ) ) {
			$data['convert_archive_url'] = $resolvedData['convert_archive_url'];
		}
		if( isset( $resolvedData['converted_encoding_only'] ) ) {
			$data['converted_encoding_only'] = $resolvedData['converted_encoding_only'];
		}
		if( self::isArchive( $resolvedData['url'], $temp ) ) {
			$data['url'] = $checkIfDead->sanitizeURL( $temp['url'], true );
			if( !isset( $temp['invalid_archive'] ) && isset( $data['invalid_archive'] ) ) {
				$resolvedData['archive_url']  = $temp['archive_url'];
				$resolvedData['archive_time'] = $temp['archive_time'];
				$resolvedData['archive_host'] = $temp['archive_host'];
				unset( $data['invalid_archive'], $data['iarchive_url'] );
			}
			$data['archive_url']  = $resolvedData['archive_url'];
			$data['archive_time'] = $resolvedData['archive_time'];
			$data['archive_host'] = $resolvedData['archive_host'];
		} else {
			$data['url']          = $checkIfDead->sanitizeURL( $resolvedData['url'], true );
			$data['archive_url']  = $resolvedData['archive_url'];
			$data['archive_time'] = $resolvedData['archive_time'];
			$data['archive_host'] = $resolvedData['archive_host'];
		}
		$data['old_archive'] = $url;
		if( isset( $data['invalid_archive'] ) ) $data['archive_type'] = "invalid";

		return true;
	}

	/**
	 * Retrieves URL information given a Webarchive UK URL
	 *
	 * @access public
	 *
	 * @param string $url A Webarchive UK URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWebarchiveUKURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:webarchive\.org\.uk)\/wayback\/archive\/(\d*)(?:mp_)?\/(\S*)/i',
		                $url, $match
		) ) {
			$returnArray['archive_url']  = "https://www.webarchive.org/wayback/archive/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "webarchiveuk";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Europarchive URL
	 *
	 * @access public
	 *
	 * @param string $url A Europarchive URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveEuropaURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:collection\.europarchive\.org|collections\.internetmemory\.org)\/nli\/(\d*)\/(\S*)/i',
		                $url, $match
		) ) {
			/*$returnArray['archive_url'] = "http://collections.internetmemory.org/nli/" . $match[1] . "/" .
			                              $match[2];*/
			$returnArray['archive_url']  = "https://wayback.archive-it.org/10702/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			//$returnArray['archive_host'] = "europarchive";
			$returnArray['archive_host'] = "archiveit";
			$returnArray['force']        = true;
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a UK Web Archive URL
	 *
	 * @access public
	 *
	 * @param string $url A UK Web Archive URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveUKWebArchiveURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/www\.webarchive\.org\.uk\/wayback\/archive\/([^\s\/]*)(?:\/(\S*))?/i', $url, $match ) ) {
			$returnArray['archive_url']  = "https://www.webarchive.org.uk/wayback/archive/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "ukwebarchive";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Wayback URL
	 *
	 * @access public
	 *
	 * @param string $url A Wayback URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWaybackURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:www\.|(?:www\.|classic\-|replay\.?)?(?:web)?(?:\-beta|\.wayback)?\.|wayback\.|liveweb\.)?(?:archive|waybackmachine)\.org(?:\/web)?(?:\/(\d*?)(?:\-)?(?:id_|re_)?)?(?:\/_embed)?\/(\S*)/i',
		                $url,
		                $match
		) ) {
			if( empty( $match[1] ) ) {
				$nocodeAURL = "https://web.archive.org/web/" . $match[2];
				if( !preg_match( '/(?:http|ftp|www\.)/i', $match[2] ) ) return $returnArray;
				$returnArray['archive_url']  =
					"https://web.archive.org/web/" . $checkIfDead->sanitizeURL( $match[2], false, true );
				$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
				$returnArray['archive_time'] = "x";
			} else {
				$nocodeAURL                 = "https://web.archive.org/web/" . $match[1] . "/" . $match[2];
				$returnArray['archive_url'] =
					"https://web.archive.org/web/" . $match[1] . "/" .
					$checkIfDead->sanitizeURL( $match[2], false, true );
				$returnArray['url']         = $checkIfDead->sanitizeURL( $match[2], true );
				if( strlen( $match[1] ) >= 4 ) {
					$match[1] = str_pad( $match[1], 14, "0", STR_PAD_RIGHT );
				} else return [];
				$returnArray['archive_time'] = strtotime( $match[1] );
			}
			$returnArray['archive_host'] = "wayback";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
			if( $url == $nocodeAURL && $nocodeAURL != $returnArray['archive_url'] ) {
				$returnArray['converted_encoding_only'] = true;
			}
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given an archive.is URL
	 *
	 * @access public
	 *
	 * @param string $url An archive.is URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */

	public static function resolveArchiveIsURL( $url )
	{
		$checkIfDead = new CheckIfDead();

		$returnArray = [];
		archiveisrestart:
		if( preg_match( '/\/\/((?:www\.)?archive.(?:is|today|fo|li|vn|ph|md))\/(\S*?)\/(\S+)/i', $url, $match ) ) {
			if( ( $timestamp = strtotime( $match[2] ) ) === false ) {
				$timestamp =
					strtotime( $match[2] = ( is_numeric( preg_replace( '/[\.\-\s]/i', "", $match[2] ) ) ?
						preg_replace( '/[\.\-\s]/i', "", $match[2] ) : $match[2] )
					);
			}
			$oldurl                      = $match[3];
			$returnArray['archive_time'] = $timestamp;
			$returnArray['url']          = $checkIfDead->sanitizeURL( $oldurl, true );
			$returnArray['archive_url']  = "https://" . $match[1] . "/" . $match[2] . "/" . $match[3];
			$returnArray['archive_host'] = "archiveis";
			if( $returnArray['archive_url'] != $url ) $returnArray['convert_archive_url'] = true;
			if( isset( $originalURL ) ) DB::accessArchiveCache( $originalURL, $returnArray['archive_url'] );

			return $returnArray;
		}

		if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
			$url = $newURL;
			goto archiveisrestart;
		}
		if( IAVERBOSE ) echo "Making query: $url\n";
		$data = self::makeHTTPRequest( $url, [], false, false );
		if( preg_match( '/\<input id\=\"SHARE_LONGLINK\".*?value\=\"(.*?)\"\/\>/i', $data, $match ) ) {
			$originalURL = $url;
			$url         = htmlspecialchars_decode( $match[1] );
			goto archiveisrestart;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a memento URL
	 *
	 * @access public
	 *
	 * @param string $url A memento URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveMementoURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/timetravel\.mementoweb\.org\/(?:memento|api\/json)\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "https://timetravel.mementoweb.org/memento/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "memento";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a webcite URL
	 *
	 * @access public
	 *
	 * @param string $url A webcite URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWebCiteURL( $url )
	{
		$checkIfDead = new CheckIfDead();

		$returnArray = [];
		webcitebegin:
		//Try and decode the information from the URL first
		if( preg_match( '/\/\/(?:www\.)?webcitation.org\/(query|\S*?)\?(\S+)/i', $url, $match ) ) {
			if( $match[1] != "query" ) {
				$args['url'] = rawurldecode( preg_replace( "/url\=/i", "", $match[2], 1 ) );
				if( strlen( $match[1] ) === 9 ) {
					$timestamp = substr( (string) self::to10( $match[1], 62 ), 0, 10 );
				} else $timestamp = substr( $match[1], 0, 10 );
			} else {
				$args = explode( '&', $match[2] );
				foreach( $args as $arg ) {
					$arg                        = explode( '=', $arg, 2 );
					$temp[urldecode( $arg[0] )] = urldecode( $arg[1] );
				}
				$args = $temp;
				if( isset( $args['id'] ) ) {
					if( strlen( $args['id'] ) === 9 ) {
						$timestamp =
							substr( (string) self::to10( $args['id'], 62 ), 0, 10 );
					} else $timestamp = substr( $args['id'], 0, 10 );
				} elseif( isset( $args['date'] ) ) $timestamp = strtotime( $args['date'] );
			}
			if( isset( $args['url'] ) ) {
				$oldurl = $checkIfDead->sanitizeURL( $args['url'], true );
				$oldurl = str_replace( "[", "%5b", $oldurl );
				$oldurl = str_replace( "]", "%5d", $oldurl );
			}
			if( isset( $oldurl ) && isset( $timestamp ) && $timestamp !== false ) {
				$returnArray['archive_time'] = $timestamp;
				$returnArray['url']          = $oldurl;
				if( $match[1] == "query" ) {
					$returnArray['archive_url'] = "https:" . $match[0];
				} else {
					$returnArray['archive_url'] = "https://www.webcitation.org/{$match[1]}?url=$oldurl";
				}
				$returnArray['archive_host'] = "webcite";
				if( $returnArray['archive_url'] != $url ) $returnArray['convert_archive_url'] = true;

				return $returnArray;
			}
		}

		if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false && !empty( $newURL ) ) {
			$url = $newURL;
			goto webcitebegin;
		}
		if( preg_match( '/\/\/(?:www\.)?webcitation.org\/query\?(\S*)/i', $url, $match ) ) {
			$query = "https:" . $match[0] . "&returnxml=true";
		} elseif( preg_match( '/\/\/(?:www\.)?webcitation.org\/(\S*)/i', $url, $match ) ) {
			$query = "https://www.webcitation.org/query?returnxml=true&id=" . $match[1];
		} else return $returnArray;

		if( IAVERBOSE ) echo "Making query: $query\n";
		$data       = self::makeHTTPRequest( $query, [], false, false );
		$data       = preg_replace( '/\<br\s\/\>\n\<b\>.*? on line \<b\>\d*\<\/b\>\<br\s\/\>/i', "", $data );
		$data       = trim( $data );
		$xml_parser = xml_parser_create();
		xml_parse_into_struct( $xml_parser, $data, $vals );
		xml_parser_free( $xml_parser );
		$webciteID  = false;
		$webciteURL = false;
		foreach( $vals as $val ) {
			if( $val['tag'] == "TIMESTAMP" && isset( $val['value'] ) ) {
				$returnArray['archive_time'] =
					strtotime( $val['value'] );
			}
			if( $val['tag'] == "ORIGINAL_URL" && isset( $val['value'] ) ) $returnArray['url'] = $val['value'];
			if( $val['tag'] == "REDIRECTED_TO_URL" && isset( $val['value'] ) ) {
				$returnArray['url'] =
					$checkIfDead->sanitizeURL( $val['value'], true );
			}
			if( $val['tag'] == "WEBCITE_ID" && isset( $val['value'] ) ) $webciteID = $val['value'];
			if( $val['tag'] == "WEBCITE_URL" && isset( $val['value'] ) ) $webciteURL = $val['value'];
			if( $val['tag'] == "RESULT" && $val['type'] == "close" ) break;
		}
		if( $webciteURL !== false ) {
			$returnArray['archive_url'] =
				$webciteURL . "?url=" . $checkIfDead->sanitizeURL( $returnArray['url'], true );
		} elseif( $webciteID !== false ) {
			$returnArray['archive_url'] =
				"https://www.webcitation.org/" . self::toBase( $webciteID, 62 ) . "?url=" . $returnArray['url'];
		}
		$returnArray['archive_host']        = "webcite";
		$returnArray['convert_archive_url'] = true;

		DB::accessArchiveCache( $url, $returnArray['archive_url'] );

		return $returnArray;
	}

	/**
	 * Convert any base number, up to 62, to base 10.  Only does whole numbers.
	 *
	 * @access public
	 * @static
	 *
	 * @param $num Based number to convert
	 * @param int $b Base to convert from
	 *
	 * @return string New base 10 number
	 */
	public static function to10( $num, $b = 62 )
	{
		$base  = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$limit = strlen( $num );
		$res   = strpos( $base, $num[0] );
		for( $i = 1; $i < $limit; $i++ ) {
			$res = $b * $res + strpos( $base, $num[$i] );
		}

		return $res;
	}

	/**
	 * Convert a base 10 number to any base up to 62.  Only does whole numbers.
	 *
	 * @access public
	 * @static
	 *
	 * @param $num Decimal to convert
	 * @param int $b Base to convert to
	 *
	 * @return string New base number
	 */
	public static function toBase( $num, $b = 62 )
	{
		$base = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$r    = $num % $b;
		$res  = $base[$r];
		$q    = floor( $num / $b );
		while( $q ) {
			$r   = $q % $b;
			$q   = floor( $q / $b );
			$res = $base[$r] . $res;
		}

		return $res;
	}

	/**
	 * Retrieves URL information given a York University URL
	 *
	 * @access public
	 *
	 * @param string $url A York University URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveYorkUURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/digital\.library\.yorku\.ca\/wayback\/(\d*)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "https://digital.library.yorku.ca/wayback/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "yorku";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Archive It URL
	 *
	 * @access public
	 *
	 * @param string $url An Archive It URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveArchiveItURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:wayback\.)?archive-it\.org\/(\d*|all)\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  =
				"https://wayback.archive-it.org/" . $match[1] . "/" . $match[2] . "/" .
				$match[3];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[3], true );
			$returnArray['archive_time'] = strtotime( $match[2] );
			$returnArray['archive_host'] = "archiveit";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given an Arquivo URL
	 *
	 * @access public
	 *
	 * @param string $url A Arquivo URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveArquivoURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/arquivo.pt\/wayback\/(?:wayback\/)?(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  =
				"http://arquivo.pt/wayback/" . $match[1] . "/" . $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "arquivo";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a LOC URL
	 *
	 * @access public
	 *
	 * @param string $url A LOC URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveLocURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/webarchive.loc.gov\/(?:all\/|lcwa\d{4}\/)(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "http://webarchive.loc.gov/all/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "loc";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Webharvest URL
	 *
	 * @access public
	 *
	 * @param string $url A Webharvest URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWebharvestURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:www.)?webharvest.gov\/(.*?)\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "https://www.webharvest.gov/" . $match[1] . "/" . $match[2] . "/" .
			                               $match[3];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[3], true );
			$returnArray['archive_time'] = strtotime( $match[2] );
			$returnArray['archive_host'] = "warbharvest";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Bibalex URL
	 *
	 * @access public
	 *
	 * @param string $url A Bibalex URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveBibalexURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:web\.)?(?:archive|petabox)\.bibalex\.org(?:\:80)?(?:\/web)?\/(\d*?)\/(\S*)/i', $url,
		                $match
		) ) {
			$returnArray['archive_url']  = "http://web.archive.bibalex.org/web/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "bibalex";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Collections Canada URL
	 *
	 * @access public
	 *
	 * @param string $url A Collections Canada URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveCollectionsCanadaURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:www\.)?collectionscanada(?:\.gc)?\.ca\/(?:archivesweb|webarchives)\/(\d*?)\/(\S*)/i',
		                $url, $match
		) ) {
			/*$returnArray['archive_url'] =
				"https://www.collectionscanada.gc.ca/webarchives/" . $match[1] . "/" .
				$match[2];*/
			$returnArray['archive_url']  =
				"http://webarchive.bac-lac.gc.ca:8080/wayback/" . $match[1] . "/" .
				$match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			//$returnArray['archive_host'] = "collectionscanada";
			$returnArray['archive_host'] = "lacarchive";
			$returnArray['force']        = true;
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Veebiarhiiv URL
	 *
	 * @access public
	 *
	 * @param string $url A Veebiarhiiv URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveVeebiarhiivURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/veebiarhiiv\.digar\.ee\/a\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "http://veebiarhiiv.digar.ee/a/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "veebiarhiiv";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Vefsafn URL
	 *
	 * @access public
	 *
	 * @param string $url A Vefsafn URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveVefsafnURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/wayback\.vefsafn\.is\/wayback\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "http://wayback.vefsafn.is/wayback/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "vefsafn";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Proni URL
	 *
	 * @access public
	 *
	 * @param string $url A Proni URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveProniURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/webarchive\.proni\.gov\.uk\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			/*$returnArray['archive_url'] = "http://webarchive.proni.gov.uk/" . $match[1] . "/" .
			                              $match[2];*/
			$returnArray['archive_url']  = "https://wayback.archive-it.org/11112/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			//$returnArray['archive_host'] = "proni";
			$returnArray['archive_host'] = "archiveit";
			$returnArray['force']        = true;
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Spletni URL
	 *
	 * @access public
	 *
	 * @param string $url A Spletni URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveSpletniURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/nukrobi2\.nuk\.uni-lj\.si:8080\/wayback\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "http://nukrobi2.nuk.uni-lj.si:8080/wayback/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "spletni";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Stanford URL
	 *
	 * @access public
	 *
	 * @param string $url A Stanford URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveStanfordURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:sul-)?swap(?:\-prod)?\.stanford\.edu\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  =
				"https://swap.stanford.edu/" . $match[1] . "/" . $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "stanford";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a National Archives URL
	 *
	 * @access public
	 *
	 * @param string $url A National Archives URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveNationalArchivesURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:yourarchives|webarchive)\.nationalarchives\.gov\.uk\/(\d*?)\/(\S*)/i', $url, $match
		) ) {
			$returnArray['archive_url']  = "http://webarchive.nationalarchives.gov.uk/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "nationalarchives";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Parliament UK URL
	 *
	 * @access public
	 *
	 * @param string $url A Parliament UK URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveParliamentUKURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/webarchive\.parliament\.uk\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "http://webarchive.parliament.uk/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "parliamentuk";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a WAS URL
	 *
	 * @access public
	 *
	 * @param string $url A WAS URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWASURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/eresources\.nlb\.gov\.sg\/webarchives\/wayback\/(\d*?)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  =
				"http://eresources.nlb.gov.sg/webarchives/wayback/" . $match[1] . "/" .
				$match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "was";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Perma CC URL
	 *
	 * @access public
	 *
	 * @param string $url A Perma CC URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolvePermaCCURL( $url )
	{
		$checkIfDead = new CheckIfDead();

		permaccurlbegin:
		$returnArray = [];
		if( preg_match( '/\/\/perma(?:-archives\.org|\.cc)(?:\/warc)?\/([^\s\/]*)(\/\S*)?/i', $url, $match ) ) {

			if( !is_numeric( $match[1] ) ) {
				if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
					$url = $newURL;
					goto permaccurlbegin;
				}
				$queryURL = "https://api.perma.cc/v1/public/archives/" . $match[1] . "/";
				if( IAVERBOSE ) echo "Making query: $queryURL\n";
				$data = self::makeHTTPRequest( $queryURL, [], false, false );
				$data = json_decode( $data, true );
				if( is_null( $data ) ) return $returnArray;
				if( ( $returnArray['archive_time'] =
						strtotime( $data['capture_time'] ) ) === false ) {
					$returnArray['archive_time'] =
						strtotime( $data['creation_timestamp'] );
				}

				$returnArray['url']          = $checkIfDead->sanitizeURL( $data['url'], true );
				$returnArray['archive_host'] = "permacc";
				$returnArray['archive_url']  =
					"https://perma-archives.org/warc/" . date( 'YmdHms', $returnArray['archive_time'] ) . "/" .
					$returnArray['url'];
				if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
				DB::accessArchiveCache( $url, $returnArray['archive_url'] );
			} else {
				$returnArray['archive_url']  = "https://perma-archives.org/warc/" . $match[1] . $match[2];
				$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
				$returnArray['archive_time'] = strtotime( $match[1] );
				$returnArray['archive_host'] = "permacc";
				if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
			}
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Library and Archives Canada (LAC) URL
	 *
	 * @access public
	 *
	 * @param string $url A Library and Archives Canada (LAC) URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveLACURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/webarchive\.bac\-lac\.gc\.ca\:8080\/wayback\/(\d*)\/(\S*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = "http://webarchive.bac-lac.gc.ca:8080/wayback/" . $match[1] . "/" .
			                               $match[2];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "lacarchive";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a google web cache URL
	 *
	 * @access public
	 *
	 * @param string $url A google web cache URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveGoogleURL( $url )
	{
		$returnArray = [];
		$checkIfDead = new CheckIfDead();
		if( preg_match( '/(?:https?\:)?\/\/(?:webcache\.)?google(?:usercontent)?\.com\/.*?\:(?:(?:.*?\:(.*?)\+.*?)|(.*))/i',
		                $url,
		                $match
		) ) {
			$returnArray['archive_url'] = $url;
			if( !empty( $match[1] ) ) {
				$returnArray['url'] = $checkIfDead->sanitizeURL( "http://" . $match[1], true );
			} elseif( !empty( $match[2] ) ) {
				$returnArray['url'] = $checkIfDead->sanitizeURL( $match[2], true );
			}
			$returnArray['archive_time'] = "x";
			$returnArray['archive_host'] = "google";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a NLA Australia URL
	 *
	 * @access public
	 *
	 * @param string $url A NLA Australia URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveNLAURL( $url )
	{
		$returnArray = [];
		$checkIfDead = new CheckIfDead();
		if( preg_match( '/\/\/((?:pandora|(?:content\.)?webarchive|trove)\.)?nla\.gov\.au\/(pan\/\d{4,7}\/|nph\-wb\/|nph-arch\/\d{4}\/|gov\/(?:wayback\/)?)([a-z])?(\d{4}\-(?:[a-z]{3,9}|\d{1,2})\-\d{1,2}|\d{8}\-\d{4}|\d{4,14})\/((?:(?:https?\:)?\/\/|www\.)\S*)/i',
		                $url,
		                $match
		) ) {
			$returnArray['archive_url'] =
				"http://" . $match[1] . "nla.gov.au/" . $match[2] . ( isset( $match[3] ) ? $match[3] : "" ) .
				$match[4] . "/" . $match[5];
			//Hack.  Strtotime fails with certain date stamps
			$match[4]                    = preg_replace( '/jan(uary)?/i', "01", $match[4] );
			$match[4]                    = preg_replace( '/feb(ruary)?/i', "02", $match[4] );
			$match[4]                    = preg_replace( '/mar(ch)?/i', "03", $match[4] );
			$match[4]                    = preg_replace( '/apr(il)?/i', "04", $match[4] );
			$match[4]                    = preg_replace( '/may/i', "05", $match[4] );
			$match[4]                    = preg_replace( '/jun(e)?/i', "06", $match[4] );
			$match[4]                    = preg_replace( '/jul(y)?/i', "07", $match[4] );
			$match[4]                    = preg_replace( '/aug(ust)?/i', "08", $match[4] );
			$match[4]                    = preg_replace( '/sep(tember)?/i', "09", $match[4] );
			$match[4]                    = preg_replace( '/oct(ober)?/i', "10", $match[4] );
			$match[4]                    = preg_replace( '/nov(ember)?/i', "11", $match[4] );
			$match[4]                    = preg_replace( '/dec(ember)?/i', "12", $match[4] );
			$match[4]                    = strtotime( $match[4] );
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[5], true );
			$returnArray['archive_time'] = $match[4];
			$returnArray['archive_host'] = "nla";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Wikiwix URL
	 *
	 * @access public
	 *
	 * @param string $url A Wikiwix URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWikiwixURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		wikiwixbegin:
		if( preg_match( '/archive\.wikiwix\.com\/cache\/(\d{14})\/(.*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = $url;
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2] );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "wikiwix";
		} elseif( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
			$url = $newURL;
			goto wikiwixbegin;
		} elseif( preg_match( '/\/\/(?:www\.|archive\.)?wikiwix\.com\/cache\/(?:(?:display|index)\.php(?:.*?)?)?\?url\=(.*)/i',
		                      $url, $match
		) ) {
			$returnArray['archive_url'] =
				"http://archive.wikiwix.com/cache/?url=" . urldecode( $match[1] ) . "&apiresponse=1";
			if( IAVERBOSE ) echo "Making query: {$returnArray['archive_url']}\n";
			$data = self::makeHTTPRequest( $returnArray['archive_url'], [], false, false );
			if( $data == "can't connect db" ) return [];
			$data = json_decode( $data, true );

			if( $data['status'] >= 400 ) return [];

			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[1], true );
			$returnArray['archive_time'] = $data['timestamp'];
			$returnArray['archive_url']  = $data['longformurl'];
			$returnArray['archive_host'] = "wikiwix";

			DB::accessArchiveCache( $url, $returnArray['archive_url'] );
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a freezepage URL
	 *
	 * @access public
	 *
	 * @param string $url A freezepage URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveFreezepageURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
			return unserialize( $newURL );
		}

		$returnArray = [];
		//Try and decode the information from the URL first
		if( preg_match( '/(?:www\.)?freezepage.com\/\S*/i', $url, $match ) ) {
			if( IAVERBOSE ) echo "Making query: $url\n";
			$data = self::makeHTTPRequest( $url, [], false, false );
			if( preg_match( '/\<a.*?\>((?:ftp|http).*?)\<\/a\> as of (.*?) \<a/i', $data, $match ) ) {
				$returnArray['archive_url']  = $url;
				$returnArray['url']          = $checkIfDead->sanitizeURL( htmlspecialchars_decode( $match[1] ), true );
				$returnArray['archive_time'] = strtotime( $match[2] );
				$returnArray['archive_host'] = "freezepage";
			}
			DB::accessArchiveCache( $url, serialize( $returnArray ) );
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Web Recorder URL
	 *
	 * @access public
	 *
	 * @param string $url A Web Recorder URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWebRecorderURL( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/webrecorder\.io\/(.*?)\/(.*?)\/(\d*).*?\/(\S*)/i',
		                $url, $match
		) ) {
			$returnArray['archive_url']  =
				"https://webrecorder.io/" . $match[1] . "/" . $match[2] . "/" . $match[3] . "/" . $match[4];
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[4], true );
			$returnArray['archive_time'] = strtotime( $match[3] );
			$returnArray['archive_host'] = "webrecorder";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves the times specific URLs were added to a wiki page
	 *
	 * @param array $urls A list of URLs to look up
	 *
	 * @access public
	 * @return array A list of timestamps of when the resective URLs were added.  Array keys are preserved.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getTimesAdded( $urls )
	{
		$processArray = [];
		$queryArray   = [];
		$returnArray  = [];

		//Use the database to execute the search if available
		if( USEWIKIDB !== false && !empty( REVISIONTABLE ) && !empty( TEXTTABLE ) &&
		    ( $db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT ) )
		) {
			foreach( $urls as $tid => $url ) {
				if( empty( $url ) ) {
					$returnArray[$tid] = time();
					continue;
				}
				$query = "SELECT " . REVISIONTABLE . ".rev_timestamp FROM " . REVISIONTABLE . " JOIN " .
				         TEXTTABLE . " ON " . REVISIONTABLE . ".rev_id = " . TEXTTABLE .
				         ".old_id WHERE CONTAINS(" . TEXTTABLE . ".old_id, '" .
				         mysqli_escape_string( $db, $url ) . "') ORDER BY " . REVISIONTABLE .
				         ".rev_timestamp ASC LIMIT 0,1;";

				if( IAVERBOSE ) echo "Making query: $query\n";

				$res = mysqli_query( $db, $query );
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
			$processArray[$tid]['upper']    = $range - 1;
			$processArray[$tid]['lower']    = 0;
			$processArray[$tid]['needle']   = round( $range / 2 ) - 1;
			$processArray[$tid]['time']     = time();
			$processArray[$tid]['useQuery'] = -1;
		}

		//Do a binary sweep of the page history with all the URLs at once.  This minimizes the bandwidth and time consumed.
		if( IAVERBOSE ) echo "Performing binary sweep of page history via API\n";
		if( $range >= 100 ) {
			for( $stage = 2; $stage <= 16; $stage++ ) {
				if( IAVERBOSE ) echo "On iterative stage $stage of 16\n";
				$revs = [];
				foreach( $urls as $tid => $url ) {
					if( empty( $url ) ) {
						$returnArray[$tid] = time();
						continue;
					}
					$revs[$processArray[$tid]['needle']] = $this->history[$processArray[$tid]['needle']]['revid'];
				}
				$params = [
					'action' => 'query',
					'prop' => 'revisions',
					'format' => 'json',
					'rvprop' => 'timestamp|content|ids',
					'rvslots' => '*',
					'revids' => implode( '|', $revs )
				];
				$get    = http_build_query( $params );

				if( IAVERBOSE ) echo "Making query $get\n";

				//Fetch revisions of needle location in page history.  Scan for the presence of URL.
				$data = self::makeHTTPRequest( API, $params );
				$data = json_decode( $data, true );

				//The scan of each URL happens here
				foreach( $urls as $tid => $url ) {
					if( empty( $url ) ) {
						$returnArray[$tid] = time();
						continue;
					}
					//Do an error check for the proper revisions.
					if( isset( $data['query']['pages'] ) ) {
						foreach( $data['query']['pages'] as $template ) {
							if( isset( $template['revisions'] ) ) {
								foreach( $template['revisions'] as $revision ) {
									if( $revision['revid'] ==
									    $this->history[$processArray[$tid]['needle']]['revid']
									) {
										break;
									} else $revision = false;
								}
							} else $revision = false;
						}
					} else $revision = false;
					if( $revision === false ) {
						continue;
					} else {
						//Look for the URL in the fetched revisions
						if( isset( $revision['slots']['main']['*'] ) ) {
							if( strpos( $revision['slots']['main']['*'], $url ) === false ) {
								//URL not found, move needle forward half the distance of the last jump
								$processArray[$tid]['lower']  = $processArray[$tid]['needle'] + 1;
								$processArray[$tid]['needle'] += round( $range / ( pow( 2, $stage ) ) );
							} else {
								//URL found, move needle back half the distance of the last jump
								$processArray[$tid]['upper']  = $processArray[$tid]['needle'];
								$processArray[$tid]['needle'] -= round( $range / ( pow( 2, $stage ) ) ) - 1;
							}
						} else continue;
					}
				}
				//If we narrowed it to a sufficiently low amount or if the needle isn't changing, why continue?
				if( $processArray[$tid]['upper'] - $processArray[$tid]['lower'] <= 20 ||
				    $processArray[$tid]['needle'] == $processArray[$tid]['upper'] ||
				    ( $processArray[$tid]['lower'] + 1 ) == $processArray[$tid]['lower']
				) {
					break;
				}
			}
		}

		//Group each URL into a revision group.  Some may share the same revision range group.  No need to pull from the API more than once.
		foreach( $processArray as $tid => $link ) {
			$tid2 = -1;
			foreach( $queryArray as $tid2 => $query ) {
				if( $query['lower'] == $link['lower'] && $query['upper'] == $link['upper'] ) {
					$processArray[$tid]['useQuery'] = $tid2;
					break;
				}
			}
			if( $processArray[$tid]['useQuery'] === -1 ) {
				$queryArray[$tid2 + 1]          = [ 'lower' => $link['lower'], 'upper' => $link['upper'] ];
				$processArray[$tid]['useQuery'] = $tid2 + 1;
			}
		}

		//Run each revision group range
		foreach( $queryArray as $tid => $bounds ) {
			$params = [
				'action' => 'query',
				'prop' => 'revisions',
				'format' => 'json',
				'rvdir' => 'newer',
				'rvprop' => 'timestamp|content',
				'rvslots' => '*',
				'rvlimit' => 'max',
				'rvstartid' => $this->history[$bounds['lower']]['revid'],
				'rvendid' => $this->history[$bounds['upper']]['revid'],
				'titles' => $this->page
			];
			$get    = http_build_query( $params );
			if( IAVERBOSE ) echo "Making query $get\n";
			$data = self::makeHTTPRequest( API, $params );
			$data = json_decode( $data, true );
			//Another error check
			if( isset( $data['query']['pages'] ) ) {
				foreach( $data['query']['pages'] as $template ) {
					if( isset( $template['revisions'] ) ) {
						$revisions = $template['revisions'];
					} else $revisions = null;
				}
			} else $revisions = null;
			//Run through each URL from within the range group.
			foreach( $processArray as $tid2 => $tmp ) {
				if( $tmp['useQuery'] !== $tid ) continue;
				if( is_null( $revisions ) ) {
					$returnArray[$tid2] = time();
					continue;
				}
				$time = time();
				foreach( $revisions as $revision ) {
					if( !isset( $revision['slots']['main']['*'] ) ) continue;
					if( strpos( $revision['slots']['main']['*'], $urls[$tid2] ) !== false ) {
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
	 * Creates a log entry at the central API as specified in the configuration file.
	 *
	 * @param array $magicwords A list of words to replace the API call with.
	 *
	 * @access public
	 * @return bool True on success, false on failure, null if disabled
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function logCentralAPI( $magicwords )
	{
		if( LOGAPI === true && self::isEnabled() && DISABLEEDITS === false ) {
			$url = $this->getConfigText( APICALL, $magicwords );
			if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
			curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 1 );
			if( IAVERBOSE ) echo "Making query: $url\n";
			$data     = curl_exec( self::$globalCurl_handle );
			$function = DECODEMETHOD;
			$data     = $function( $data, true );
			if( $data == EXPECTEDRETURN ) {
				return true;
			} else return false;
		} else return null;
	}

	/**
	 * Replaces magic word place holders with actual values.
	 * Uses a parameter string or returns the complete given string
	 * if the parameter doesn't match
	 *
	 * @param string $value A parameter or string to handle.
	 * @param array $magicwords A list of magic words and associative values to replace with.
	 *
	 * @access public
	 * @return string Completed string
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getConfigText( $value, $magicwords = [] )
	{
		if( isset( $this->config[$value] ) ) {
			$string = $this->config[$value];
		} else $string = $value;
		$string = str_replace( "\\n", "\n", $string );
		foreach( $magicwords as $magicword => $value ) {
			$string = str_ireplace( "{{$magicword}}", $value, $string );
		}
		$string = str_replace( "\:", "ESCAPEDCOLON", $string );

		while( preg_match( '/\{(.*?timestamp)\:(.*?)\}/i', $string, $match ) ) {
			if( isset( $magicwords[$match[1]] ) ) {
				if( !empty( $match[2] ) && $match[2] != "automatic" ) {
					$string =
						str_replace( $match[0], DataGenerator::strftime( $match[2], $magicwords[$match[1]] ), $string );
				} elseif( isset( $magicwords['timestampauto'] ) ) {
					$string =
						str_replace( $match[0],
						             DataGenerator::strftime( $magicwords['timestampauto'], $magicwords[$match[1]] ),
						             $string
						);
				} else $string = str_replace( $match[0], $magicwords[$match[1]], $string );
			} else {
				if( !empty( $match[2] ) && $match[2] != "automatic" ) {
					$string =
						str_replace( $match[0], DataGenerator::strftime( $match[2], time() ), $string );
				} elseif( isset( $magicwords['timestampauto'] ) ) {
					$string =
						str_replace( $match[0], DataGenerator::strftime( $magicwords['timestampauto'], time() ), $string
						);
				} else $string = str_replace( $match[0], time(), $string );
			}
		}
		while( preg_match( '/\{permadead\:(.*?)\:(.*?)\}/i', $string, $match ) ) {
			if( isset( $magicwords['permadead'] ) && $magicwords['permadead'] === true ) {
				$string =
					str_replace( $match[0], $match[1], $string );
			} else $string = str_replace( $match[0], $match[2], $string );
		}
		while( preg_match( '/\{deadvalues\:(.*?)\:(.*?)(?:\:(.*?))?(?:\:(.*?))?\}/i', $string, $match ) ) {
			if( isset( $magicwords['is_dead'] ) ) switch( $magicwords['is_dead'] ) {
				case "yes":
					$string = str_replace( $match[0], explode( ";;", $match[1] )[0], $string );
					break;
				case "no":
					$string = str_replace( $match[0], explode( ";;", $match[2] )[0], $string );
					break;
				case "usurp":
					if( isset( $match[3] ) ) {
						$string = str_replace( $match[0], explode( ";;", $match[3] )[0], $string );
						break;
					} else {
						$string = str_replace( $match[0], explode( ";;", $match[1] )[0], $string );
						break;
					}
			} else {
				$string = str_replace( $match[0], "", $string );
			}
		}
		while( preg_match( '/\{df\:(.*?)\}/i', $string, $match ) ) {
			if( isset( $magicwords['timestampauto'] ) ) {
				$rules = explode( ";;;", $match[1] );
				foreach( $rules as $rule ) {
					$rule = explode( ";;", $rule );
					if( $rule[0] == $magicwords['timestampauto'] ) {
						$string = str_replace( $match[0], $rule[1], $string );
						break;
					}
				}
				$string = str_replace( $match[0], "", $string );
			} else $string = str_replace( $match[0], "", $string );
		}

		while( preg_match( '/\$\$TIMESTAMP\$(.*?)\$\$/', $string, $timeFormat ) ) {
			if( !empty( $timeFormat[1] ) && $timeFormat[1] != "automatic" ) {
				$string =
					str_replace( $timeFormat[0], DataGenerator::strftime( $timeFormat[1], time() ), $string );
			} elseif( isset( $magicwords['timestampauto'] ) ) {
				$string =
					str_replace( $timeFormat[0], DataGenerator::strftime( $magicwords['timestampauto'], time() ),
					             $string
					);
			} else $string = str_replace( $timeFormat[0], time(), $string );
		}

		$string = str_replace( "ESCAPEDCOLON", ":", $string );

		return replaceMagicInitWords( $string );
	}

	/**
	 * Close the resource handles
	 *
	 * @access public
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function closeResources()
	{
		$this->db->closeResource();
		curl_close( self::$globalCurl_handle );
		self::$globalCurl_handle = null;
		$this->db                = null;
	}
}
