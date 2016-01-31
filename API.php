<?php

/**
 * @file
 * API object
 */

/**
 * API class
 * Manages the core functions of IABot including communication to external APIs
 * The API class initialized per page, and destoryed at the end of it's use.
 * It also manages the page data for every thread, and handles DB and parser calls.
 */
   
class API {
	
	/**
	 * Stores the global curl handle for the bot.
	 *
	 * @var resource
	 * @access protected
	 */	
	protected static $globalCurl_handle = null;
	
	/**
	* Configuration variables as set on Wikipedia, as well as page and page id variables.
	* This serves as an access point across multiple functions without having to pass a
	* ridiculous amount of variables everytime.
	* 
	* @var mixed
	* @access public
	*/
	public $page, $pageid, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN;
	
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
	
	public $db;
	
	public function __construct( $page, $pageid, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN ) {
		$this->page = $page;
        $this->pageid = $pageid;
        $this->ARCHIVE_ALIVE = $ARCHIVE_ALIVE;
        $this->TAG_OVERRIDE = $TAG_OVERRIDE;
        $this->ARCHIVE_BY_ACCESSDATE = $ARCHIVE_BY_ACCESSDATE;
        $this->TOUCH_ARCHIVE = $TOUCH_ARCHIVE;
        $this->DEAD_ONLY = $DEAD_ONLY;
        $this->NOTIFY_ERROR_ON_TALK = $NOTIFY_ERROR_ON_TALK;
        $this->NOTIFY_ON_TALK = $NOTIFY_ON_TALK;
        $this->TALK_MESSAGE_HEADER = $TALK_MESSAGE_HEADER;
        $this->TALK_MESSAGE = $TALK_MESSAGE;
        $this->TALK_ERROR_MESSAGE_HEADER = $TALK_ERROR_MESSAGE_HEADER;
        $this->TALK_ERROR_MESSAGE = $TALK_ERROR_MESSAGE;
        $this->DEADLINK_TAGS = $DEADLINK_TAGS;
        $this->CITATION_TAGS = $CITATION_TAGS;
        $this->IGNORE_TAGS = $IGNORE_TAGS;
        $this->ARCHIVE_TAGS = $ARCHIVE_TAGS;
        $this->VERIFY_DEAD = $VERIFY_DEAD;
        $this->LINK_SCAN = $LINK_SCAN;	
        $this->content = self::getPageText( $page );
        
        $this->db = new DB( $this );
        
	}
	
	protected static function initGlobalCurlHandle() {
		self::$globalCurl_handle = curl_init();
	    curl_setopt( self::$globalCurl_handle,CURLOPT_COOKIEFILE, COOKIE );
	    curl_setopt( self::$globalCurl_handle,CURLOPT_COOKIEJAR, COOKIE );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_USERAGENT, USERAGENT );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_MAXCONNECTS, 100 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_MAXREDIRS, 10 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_ENCODING, 'gzip' );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_RETURNTRANSFER, 1 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_HEADER, 1 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_TIMEOUT, 100 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_CONNECTTIMEOUT, 10 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 0 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_SSL_VERIFYPEER, false );
	}
	
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
	    $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	    $data = trim( substr( $data, $header_size ) );;
	    if ( !$data ) {
	        $error = 'Curl error: ' . htmlspecialchars( curl_error( $ch ) );
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
	
	//Submit archive requests
	public function requestArchive( $urls ) {
	    $getURLs = array();
	    $returnArray = array( 'result'=>array(), 'errors'=>array() );
	    foreach( $urls as $id=>$url ) {
	        if( $this->db->dbValues[$id]['archived'] == 1 || ( isset( $this->db->dbValues[$id]['archivable'] ) && $this->db->dbValues[$id]['archivable'] == 0 ) ) {
	            $returnArray['result'][$id] = null;
	            continue;
	        }
	        $getURLs[$id] = array( 'url' => "http://web.archive.org/save/$url", 'type' => "get" ); 
	    }
	    while( !empty( $getURLs ) ) {
	        $res = $this->multiquery( $getURLs );
	        foreach( $res['headers'] as $id=>$item ) {
	            if( $res['code'][$id] != 503 ) unset( $getURLs[$id] );
	            else continue;
	            if( isset( $item['X-Archive-Wayback-Liveweb-Error'] ) ) {
	                $this->db->dbValues[$id]['archive_failure'] = $returnArray['errors'][$id] = $item['X-Archive-Wayback-Liveweb-Error'];
	                $returnArray['result'][$id] = false;
	                $this->db->dbValues[$id]['archivable'] = 0;
	                if( !isset( $this->db->dbValues[$id]['create'] ) ) $this->db->dbValues[$id]['update'] = true;
	            } else $returnArray['result'][$id] = true;
	        }
	    }
	    $res = null;
	    unset( $res );
	    return $returnArray;
	}
	
	//Checks availability of archives
	public function isArchived( $urls ) {
	    $getURLs = array();
	    $returnArray = array( 'result'=>array(), 'errors'=>array() );
	    foreach( $urls as $id=>$url ) {
	        if( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 1 ) {
	            $returnArray['result'][$id] = true;
	            continue;
	        } elseif( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 0 ) {
	            $returnArray['result'][$id] = false;
	            continue;
	        }
	        $url = urlencode( $url );
	        $getURLs[$id] = array( 'url'=>"http://web.archive.org/cdx/search/cdx?url=$url&output=json&limit=-2&matchType=exact&filter=statuscode:(200|203|206)", 'type'=>"get" );
	    }
	    while( !empty( $getURLs ) ) {
	        $res = $this->multiquery( $getURLs );
	        foreach( $res['results'] as $id=>$data ) {
	            if( $res['code'][$id] != 503 ) unset( $getURLs[$id] );
	            else continue;
	            $data = json_decode( $data, true );
	            $returnArray['result'][$id] = !empty( $data );
	            $this->db->dbValues[$id]['archived'] = $returnArray['result'][$id] ? 1 : 0;
	            if( !isset( $this->db->dbValues[$id]['create'] ) ) $this->db->dbValues[$id]['update'] = true;
	            if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
	        }
	    }
	    $res = null;
	    unset( $res );
	    return $returnArray;
	}

	//Fetches archives
	public function retrieveArchive( $data ) {
	    $returnArray = array( 'result'=>array(), 'errors'=>array() );
	    foreach( $data as $id=>$item ) {
	        if( isset( $this->db->dbValues[$id]['has_archive'] ) && $this->db->dbValues[$id]['has_archive'] == 1 ) {
	            $returnArray['result'][$id]['archive_url'] = $this->db->dbValues[$id]['archive_url'];
	            $returnArray['result'][$id]['archive_time'] = $this->db->dbValues[$id]['archive_time'];
	            continue;
	        } elseif( isset( $this->db->dbValues[$id]['archived'] ) && $this->db->dbValues[$id]['archived'] == 0 ) {
	            $returnArray['result'][$id] = false;
	            $this->db->dbValues[$id]['has_archive'] = 0;
	            if( !isset( $this->db->dbValues[$id]['create'] ) ) $this->db->dbValues[$id]['update'] = true;
	            continue;
	        }
	        $url = $item[0];
	        $time = $item[1];
	        $url = urlencode( $url ); 
	        $getURLs[$id] = array( 'url'=>"http://web.archive.org/cdx/search/cdx?url=$url".( !is_null( $time ) ? "&to=".date( 'YmdHis', $time ) : "" )."&output=json&limit=-2&matchType=exact&filter=statuscode:(200|203|206)", 'type'=>"get" );
	    }
	    while( !empty( $getURLs ) ) {
	        $res = $this->multiquery( $getURLs );
	        $getURLs = array();
	        foreach( $res['results'] as $id=>$data2 ) {
	            if( $res['code'][$id] != 503 ) unset( $getURLs[$id] );
	            else continue;
	            $data2 = json_decode( $data2, true );
	            if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error']; 
	            if( !empty($data2) ) {
	                $this->db->dbValues[$id]['archive_url'] = $returnArray['result'][$id]['archive_url'] = "https://web.archive.org/".$data2[count($data2)-1][1]."/".$data2[count($data2)-1][2];
	                $this->db->dbValues[$id]['archive_time'] = $returnArray['result'][$id]['archive_time'] = strtotime( $data2[count($data2)-1][1] );    
	                $this->db->dbValues[$id]['has_archive'] = 1;
	                $this->db->dbValues[$id]['archived'] = 1;
	            } else {
	                $url = $data[$id][0];
	                $time = $data[$id][1];
	                $getURLs[$id] = array( 'url'=>"http://web.archive.org/cdx/search/cdx?url=$url".( !is_null( $time ) ? "&from=".date( 'YmdHis', $time ) : "" )."&output=json&limit=2&matchType=exact&filter=statuscode:(200|203|206)", 'type'=>"get" );  
	                $this->db->dbValues[$id]['has_archive'] = 0;
	            }
	            if( !isset( $this->db->dbValues[$id]['create'] ) ) $this->db->dbValues[$id]['update'] = true;
	        }
	        $res = null;
	        unset( $res );
	        while( !empty( $getURLs ) ) {
	            $res = $this->multiquery( $getURLs );
	            foreach( $res['results'] as $id=>$data ) {
	                if( $res['code'][$id] != 503 ) unset( $getURLs[$id] );
	                else continue;
	                $data = json_decode( $data, true );
	                if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
	                if( !empty($data) ) {
	                    $this->db->dbValues[$id]['archive_url'] = $returnArray['result'][$id]['archive_url'] = "https://web.archive.org/".$data[1][1]."/".$data[1][2];
	                    $this->db->dbValues[$id]['archive_time'] = $returnArray['result'][$id]['archive_time'] = strtotime( $data[1][1] );
	                    $this->db->dbValues[$id]['has_archive'] = 1; 
	                    $this->db->dbValues[$id]['archived'] = 1;   
	                } else {
	                    $returnArray['result'][$id] = false;
	                    $this->db->dbValues[$id]['has_archive'] = 0;
	                    $this->db->dbValues[$id]['archived'] = 0;
	                }
	                if( !isset( $this->db->dbValues[$id]['create'] ) ) $this->db->dbValues[$id]['update'] = true;
	            }
	            $res = null;
	            unset( $res );
	        }
	    } 
	    return $returnArray;
	}

	//Perform multiple queries simultaneously
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
	                $url .= '?' . http_build_query( $item['data'] );
	            }
	            curl_setopt( $curl_instances[$id], CURLOPT_URL, $item['url'] );    
	        } else {
	            return false;
	        }
	        curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
	    }
	    $active = null;
	    do {
	        $mrc = curl_multi_exec($multicurl_resource, $active);
	    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

	    while ($active && $mrc == CURLM_OK) {
	        if (curl_multi_select($multicurl_resource) == -1) {
	            usleep(100);
	        }
	        do {
	            $mrc = curl_multi_exec($multicurl_resource, $active);
	        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
	        
	    }
	    
	    foreach( $data as $id=>$item ) {
	        $returnArray['errors'][$id] = curl_error( $curl_instances[$id] );
	        if( ($returnArray['results'][$id] = curl_multi_getcontent( $curl_instances[$id] ) ) !== false ) {
	            $header_size = curl_getinfo( $curl_instances[$id], CURLINFO_HEADER_SIZE );
	            $returnArray['code'][$id] = curl_getinfo( $curl_instances[$id], CURLINFO_HTTP_CODE );
	            $returnArray['headers'][$id] = self::http_parse_headers( substr( $returnArray['results'][$id], 0, $header_size ) );
	            $returnArray['results'][$id] = trim( substr( $returnArray['results'][$id], $header_size ) );
	        }
	        curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
	    }
	    curl_multi_close( $multicurl_resource );
	    return $returnArray;
	}

	protected function http_parse_headers( $header ) {
	    $header = preg_replace( '/http\/\d\.\d\s\d{3}.*?\n/i', "", $header );
	    $header = explode( "\n", $header );
	    $returnArray = array();
	    foreach( $header as $id=>$item) $header[$id] = explode( ":", $item, 2 );
	    foreach( $header as $id=>$item) if( count( $item ) == 2 ) $returnArray[trim($item[0])] = trim($item[1]);
	    return $returnArray;
	}
	
	public static function getAllArticles( $limit, $resume ) {
	    $returnArray = array();
	    if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();    
	    while( true ) {
	        $get = "action=query&list=allpages&format=php&apnamespace=0&apfilterredir=nonredirects&aplimit=".($limit-count($returnArray))."&rawcontinue=&apcontinue=$resume";
	        curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
	        curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	        curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
	        curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
	        $data = curl_exec( self::$globalCurl_handle ); 
	        $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	        $data = trim( substr( $data, $header_size ) );
	        $data = unserialize( $data ); 
	        $returnArray = array_merge( $returnArray, $data['query']['allpages'] );
	        if( isset( $data['query-continue']['allpages']['apcontinue'] ) ) $resume = $data['query-continue']['allpages']['apcontinue'];
	        else {
	            $resume = "";
	            break;
	        } 
	        if( $limit <= count( $returnArray ) ) break; 
	    }
	    return array( $returnArray, $resume );
	}
	
	public static function edit( $page, $text, $summary, $minor = false, $timestamp = false, $bot = true, $section = false, $title = "" ) {
	    if( !self::isEnabled() ) {
	        echo "ERROR: BOT IS DISABLED!!\n\n";
	        return false; 
	    }
	    if( NOBOTS === true && self::nobots( $text ) ) {
	        echo "ERROR: RESTRICTED BY NOBOTS!!\n\n";
	        return false;
	    }
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
	    $get = "action=query&meta=tokens&format=php";    
	    curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	    $data = curl_exec( self::$globalCurl_handle ); 
	    $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	    $data = trim( substr( $data, $header_size ) );
	    $data = unserialize( $data );
	    $post['token'] = $data['query']['tokens']['csrftoken'];
	    curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $post );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API ); 
	    curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'POST', API  ) ) );
	    $data = curl_exec( self::$globalCurl_handle ); 
	    $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	    $data = trim( substr( $data, $header_size ) );
	    $data = unserialize( $data );
	    if( isset( $data['edit'] ) && $data['edit']['result'] == "Success" && !isset( $data['edit']['nochange']) ) {
	        return $data['edit']['newrevid'];
	    } else {
	        return false;
	    }
	}
	
	protected static function isEnabled() {
	    $text = self::getPageText( RUNPAGE );
	    if( $text == "enable" ) return true;
	    else return false;
	}

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
	        if( ( !is_null( USERNAME ) && in_array( trim( USERNAME ), $allow ) ) || ( !is_null( TASKNAME ) && in_array( trim( $taskname ), $allow ) ) ) {
	            return true;
	        }
	        return false;
	    }
	    return false;   
	}
	
	public static function getPageHistory( $page ) {
	    $returnArray = array();
	    $resume = "";
	    if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
	    while( true ) {
	        $get = "action=query&prop=revisions&format=php&rvdir=newer&rvprop=ids&rvlimit=max".( empty($resume) ? "" : "&rvcontinue=$resume" )."&rawcontinue=&titles=".urlencode($page);
	        curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		    curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		    curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	        $data = curl_exec( self::$globalCurl_handle ); 
	        $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	        $data2 = trim( substr( $data, $header_size ) );
	        $data = null;
	        $data = unserialize( $data2 );
	        $data2 = null; 
	        if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
	            if( isset( $template['revisions'] ) ) $returnArray = array_merge( $returnArray, $template['revisions'] );
	        } 
	        if( isset( $data['query-continue']['revisions']['rvcontinue'] ) ) $resume = $data['query-continue']['revisions']['rvcontinue'];
	        else {
	            $resume = "";
	            break;
	        } 
	        $data = null;
	        unset($data);
	    }
	    return $returnArray;    
	}
	
	public static function getTaggedArticles( $titles, $limit, $resume ) {
	    $returnArray = array();
	    if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();    
	    while( true ) {
	        $get = "action=query&prop=transcludedin&format=php&tinamespace=0&tilimit=".($limit-count($returnArray)).( empty($resume) ? "" : "&ticontinue=$resume" )."&rawcontinue=&titles=".urlencode($titles);
	        curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		    curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		    curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	        $data = curl_exec( self::$globalCurl_handle ); 
	        $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	        $data = trim( substr( $data, $header_size ) );
	        $data = unserialize( $data ); 
	         foreach( $data['query']['pages'] as $template ) {
	            if( isset( $template['transcludedin'] ) ) $returnArray = array_merge( $returnArray, $template['transcludedin'] );
	        } 
	        if( isset( $data['query-continue']['transcludedin']['ticontinue'] ) ) $resume = $data['query-continue']['transcludedin']['ticontinue'];
	        else {
	            $resume = "";
	            break;
	        } 
	        if( $limit <= count( $returnArray ) ) break; 
	    }
	    return array( $returnArray, $resume);
	}
	
	public static function getPageText( $page ) {
	    if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
	    $get = "action=raw&title=".urlencode($page);
	    $api = str_replace( "api.php", "index.php", API );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $api."?$get" );
 		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', $api."?$get" ) ) );
	    $data = curl_exec( self::$globalCurl_handle ); 
	    $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	    $data = trim( substr( $data, $header_size ) );
	    return $data;   
	}
	
	public static function isLoggedOn( $user ) {
	    if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
	    $get = "action=query&meta=userinfo&format=php";
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	    $data = curl_exec( self::$globalCurl_handle ); 
	    $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	    $data = trim( substr( $data, $header_size ) );
	    $data = unserialize( $data );
	    if( $data['query']['userinfo']['name'] == $user ) return true;
	    else return false;
	}
	
	public function getTimeAdded( $url ) {
    
	    //Return current time for an empty input.
	    if( empty( $url ) ) return time();
	    
	    //Use the database to execute the search if available
	    if( USEWIKIDB === true && ($db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT )) ) {
	        $res = mysqli_query( $db, "SELECT ".REVISIONTABLE.".rev_timestamp FROM ".REVISIONTABLE." JOIN ".TEXTTABLE." ON ".REVISIONTABLE.".rev_id = ".TEXTTABLE.".old_id WHERE CONTAINS(".TEXTTABLE.".old_id, '".mysqli_escape_string( $db, $url )."') ORDER BY ".REVISIONTABLE.".rev_timestamp ASC LIMIT 0,1;" );       
	        //$res = mysqli_query( $db, "SELECT ".REVISIONTABLE.".rev_timestamp FROM ".REVISIONTABLE." JOIN ".TEXTTABLE." ON ".REVISIONTABLE.".rev_id = ".TEXTTABLE.".old_id WHERE ".TEXTTABLE.".old_id LIKE '%".mysqli_escape_string( $db, $url )."%') ORDER BY ".REVISIONTABLE.".rev_timestamp ASC LIMIT 0,1;" );
	        $tmp = mysqli_fetch_assoc( $res );
	        mysqli_free_result( $res );
	        unset( $res );
	        if( $tmp !== false ) {
	            mysqli_close( $db );
	            unset( $db );
	            return strtotime( $tmp['rev_timestamp'] );
	        }
	    }
	    if( isset( $db ) ) {
	        mysqli_close( $db );
	        unset( $db );
	        echo "ERROR: Wiki database usage failed.  Defaulting to API Binary search...\n";
	    }
	    
	    //Do a binary search with predictions.  (Predicting future revisions may speed up performance)
	    if( empty( $this->history ) ) $this->history = self::getPageHistory( $this->page );
	    
	    $range = count( $this->history );
	    $upper = $range - 1;
	    $lower = 0;
	    $needle = round( $range/2 ) - 1;
	    $time = time();
	    
	    if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
	    
	    for( $stage = 2; $stage <= 16; $stage++ ) {
	        /*if( $stage % 4 == 0) {
	            $get = "action=query&prop=revisions&format=json&rvprop=ids%7Ctimestamp%7Ccontent&revids=";
	            $trange = $upper - $lower;
	        }      */
	        $get = "action=query&prop=revisions&format=php&rvdir=newer&rvprop=timestamp%7Ccontent&rvlimit=1&rawcontinue=&rvstartid={$this->history[$needle]['revid']}&rvendid={$this->history[$needle]['revid']}&titles=".urlencode( $this->page );
	        curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	        $data = curl_exec( self::$globalCurl_handle ); 
	        $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	        $data2 = trim( substr( $data, $header_size ) );
	        $data = null;
	        $data = unserialize( $data2 );
	        $data2 = null; 
	        if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
	            if( isset( $template['revisions'] ) ) $revision = $template['revisions'][0];
	            else $revision = false;
	        } else $revision = false;
	        if( $revision === false ) break;
	        else {
	            if( isset( $revision['*'] ) ) {
	                if( strpos( $revision['*'], $url ) === false ) {
	                    $lower = $needle + 1;
	                    $needle += round( $range/(pow( 2, $stage )) );
	                } else {
	                    $upper = $needle;
	                    $needle -= round( $range/(pow( 2, $stage )) );
	                }   
	            } else break;
	        }
	        //If we narrowed it to a sufficiently low amount or if the needle isn't changing, why continue?
	        if( $upper - $lower <= 5 || $needle == $upper || ($needle + 1) == $lower ) break;
	    }
	    
	    $get = "action=query&prop=revisions&format=php&rvdir=newer&rvprop=timestamp%7Ccontent&rvlimit=max&rawcontinue=&rvstartid={$this->history[$lower]['revid']}&rvendid={$this->history[$upper]['revid']}&titles=".urlencode( $this->page );
	    curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API."?$get" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, array( self::generateOAuthHeader( 'GET', API."?$get" ) ) );
	    $data = curl_exec( self::$globalCurl_handle ); 
	    $header_size = curl_getinfo( self::$globalCurl_handle, CURLINFO_HEADER_SIZE );
	    $data2 = trim( substr( $data, $header_size ) );
	    $data = null;
	    $data = unserialize( $data2 );
	    $data2 = null; 
	    
	    if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
	        if( isset( $template['revisions'] ) ) $revisions = $template['revisions'];
	        else {
	            $revisions = null;
	            unset( $revisions );
	            return $time;   
	        }
	    } else {
	        $revisions = null;
	        unset( $revisions );
	        return $time;   
	    }
	    
	    foreach( $revisions as $revision ) {
	        $time = strtotime( $revision['timestamp'] ); 
	        if( !isset( $revision['*'] ) ) continue;
	        if( strpos( $revision['*'], $url ) !== false ) break;  
	    }
	    $revision = $revisions = null;
	    unset( $revisions, $revision );
	    return $time;
	}
	
	public function __destruct() {
		$this->db->__destruct();
		curl_close( self::$globalCurl_handle );
		self::$globalCurl_handle = null;
	}
}
