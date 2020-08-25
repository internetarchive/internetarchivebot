<?php

if( !isset( $argv[2] ) || !isset( $argv[1] ) ) die( "FAIL" );

define( 'CONSUMERSECRET', $argv[1] );

// There are three fields in the response
$fields = explode( '.', $argv[2] );
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
$sig = base64_decode( strtr( $fields[2], '-_', '+/' ), true );
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

die( json_encode( $payload ) );

loginerror:
die( $error );