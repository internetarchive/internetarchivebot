<?php

if( !isset( $argv[5] ) || !isset( $argv[1] ) || !isset( $argv[2] ) || !isset( $argv[3] ) || !isset( $argv[4] ) ) die( "FAIL" );
define( 'CONSUMERKEY', $argv[1] );
define( 'CONSUMERSECRET', $argv[2] );
define( 'ACCESSTOKEN', $argv[3] );
define( 'ACCESSSECRET', $argv[4] );

echo generateOAuthHeader( 'GET', $argv[5] );

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