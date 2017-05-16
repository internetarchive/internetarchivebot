<?php
/**
 * Created by PhpStorm.
 * User: maxdoerr
 * Date: 5/11/17
 * Time: 15:15
 */
set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
date_default_timezone_set( "UTC" );
ini_set( 'memory_limit', '128M' );

require_once( 'deadlink.config.inc.php' );
if( isset( $accessibleWikis[WIKIPEDIA]['language'] ) &&
    isset( $locales[$accessibleWikis[WIKIPEDIA]['language']] )
) setlocale( LC_ALL, $locales[$accessibleWikis[WIKIPEDIA]['language']] );

$url = "https://tools.wmflabs.org/iabot/api.php?wiki=enwiki";
$url2 = OAUTH . '/identify';
$header = generateOAuthHeader( 'GET', $url2 );
$header = "Authorization: OAuth oauth_consumer_key=\"ad8e33572688dd300d2b726bee409f5d\", oauth_token=\"147e94d316131e029a70db90bda94940\", oauth_version=\"1.0\", oauth_nonce=\"35e1d21a02daab6f820f827be0bc3f61\", oauth_timestamp=\"1494709330\", oauth_signature_method=\"HMAC-SHA1\", oauth_signature=\"EtbuLeZDj8qpqCnRbNOUAdvZ6Gg%3D\"";

$ch = curl_init();
curl_setopt( $ch, CURLOPT_COOKIEFILE, COOKIE );
curl_setopt( $ch, CURLOPT_COOKIEJAR, COOKIE );
curl_setopt( $ch, CURLOPT_USERAGENT, USERAGENT );
curl_setopt( $ch, CURLOPT_MAXCONNECTS, 100 );
curl_setopt( $ch, CURLOPT_MAXREDIRS, 20 );
curl_setopt( $ch, CURLOPT_ENCODING, 'gzip' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt( $ch, CURLOPT_TIMEOUT, 100 );
curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, true );
curl_setopt( $ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true );
curl_setopt( $ch, CURLOPT_DNS_CACHE_TIMEOUT, 60 );
curl_setopt( $ch, CURLOPT_URL, $url );
curl_setopt( $ch, CURLOPT_HTTPHEADER, [ $header ] );
curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
curl_setopt( $ch, CURLOPT_POST, 0 );
$data = curl_exec( $ch );
die (var_dump( json_decode( $data, true ) ));

if( !$data ) {
	$error = 'Curl error: ' . htmlspecialchars_decode( curl_error( self::$globalCurl_handle ) );
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
	$error = 'Invalid identify response: ' . htmlspecialchars_decode( $data );
	goto loginerror;
}

// Validate the header. MWOAuth always returns alg "HS256".
$header = base64_decode( strtr( $fields[0], '-_', '+/' ), true );
if( $header !== false ) {
	$header = json_decode( $header );
}
if( !is_object( $header ) || $header->typ !== 'JWT' || $header->alg !== 'HS256' ) {
	$error = 'Invalid header in identify response: ' . htmlspecialchars_decode( $data );
	goto loginerror;
}

// Verify the signature
$sig = base64_decode( strtr( $fields[2], '-_', '+/' ), true );
$check = hash_hmac( 'sha256', $fields[0] . '.' . $fields[1], CONSUMERSECRET, true );
if( $sig !== $check ) {
	$error = 'JWT signature validation failed: ' . htmlspecialchars_decode( $data );
	goto loginerror;
}

// Decode the payload
$payload = base64_decode( strtr( $fields[1], '-_', '+/' ), true );
if( $payload !== false ) {
	$payload = json_decode( $payload );
}
if( !is_object( $payload ) ) {
	$error = 'Invalid payload in identify response: ' . htmlspecialchars_decode( $data );
	goto loginerror;
}

if( USERNAME == $payload->username ) {
	echo "Success!!\n\n";

	return true;
} else {
	loginerror:
	echo "Failed!!\n";
	if( !empty( $error ) ) echo "ERROR: $error\n";
	else echo "ERROR: The bot logged into the wrong username.\n";

	return false;
}


function generateOAuthHeader( $method = 'GET', $url ) {
	$headerArr = [
		// OAuth information
		'oauth_consumer_key'     => CONSUMERKEY,
		'oauth_token'            => ACCESSTOKEN,
		'oauth_version'          => '1.0',
		'oauth_nonce'            => md5( microtime() . mt_rand() ),
		'oauth_timestamp'        => time(),

		// We're using secret key signatures here.
		'oauth_signature_method' => 'HMAC-SHA1',
	];
	$signature = generateSignature( $method, $url, $headerArr );
	$headerArr['oauth_signature'] = $signature;

	$header = [];
	foreach( $headerArr as $k => $v ) {
		$header[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
	}
	$header = 'Authorization: OAuth ' . join( ', ', $header );
	unset( $headerArr );

	return $header;
}

function generateSignature( $method, $url, $params = [] ) {
	$parts = parse_url( $url );

	// We need to normalize the endpoint URL
	$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
	$host = isset( $parts['host'] ) ? $parts['host'] : '';
	$port = isset( $parts['port'] ) ? $parts['port'] : ( $scheme == 'https' ? '443' : '80' );
	$path = isset( $parts['path'] ) ? $parts['path'] : '';
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
	$key = rawurlencode( CONSUMERSECRET ) . '&' . rawurlencode( ACCESSSECRET );

	return base64_encode( hash_hmac( 'sha1', $toSign, $key, true ) );
}