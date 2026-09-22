<?php
/*
	Copyright (c) 2015-2026, Maximilian Doerr, James Hare, Internet Archive
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

echo "----------STARTING UP SCRIPT----------\nStart Timestamp: " . date( 'r' ) . "\n\n";
echo "Initializing...\n";
require_once( 'Core/init.php' );

if( THROTTLECDXREQUESTS === false ) exit( 0 );

echo "Cleaning up temporary files...\n";
Memory::clean();

DB::checkDB();

$previousRequests = [];
$activePayloads = [];

$retryLimit = 50;
$period = 60;
$batchLimit = 50;

while( true ) {
	do {
		$microEpoch = (int ) ( ( microtime( true ) - $period ) * 1000000 );
	} while( isset( $previousRequests[$microEpoch] ) );
	$numberLastEdits = count( $previousRequests );
	if( $numberLastEdits > THROTTLECDXREQUESTS ) {
		foreach( $previousRequests as $execTime => $junk ) {
			if( $execTime < $microEpoch ) unset( $previousRequests[$execTime] );
		}

		echo "Hit rate limit of " . THROTTLECDXREQUESTS . "!  Sleeping for 1 second.\n";
		sleep( 1 );
		continue;
	}
	if( $numberLastEdits >= THROTTLECDXREQUESTS ) {
		foreach( $previousRequests as $oldRequestTime => $junk ) {
			$expired = (int) ( ( microtime( true ) - $period ) * 1000000 );

			if( $oldRequestTime - $expired <= 0 ) break;

			echo "Sleeping for " . round( ( $oldRequestTime - $expired ) / 1000000, 2 ) . " second(s).\n";
			usleep( $oldRequestTime - $expired );

			break;
		}
	}

	$pendingRequests = DB::getPendingAvailabilityRequests();

	if( !isset( $getURLs ) ) $getURLs = [];
	foreach( $pendingRequests as $pendingRequest ) {
		$payload = $pendingRequest['payload'];
		$requestID = (int) $pendingRequest['request_id'];
		parse_str( $payload, $payloadBreakdown );
		$originalTag = isset( $payloadBreakdown['tag'] ) ? $payloadBreakdown['tag'] : (string) $requestID;
		if( isset( $getURLs[$requestID] ) ) continue;
		if( empty( $payloadBreakdown['url'] ) ) {
			DB::updateAvailabilityRequest( $requestID, false );
			continue;
		}

		if( count( $getURLs ) >= $batchLimit ) break;

		$payloadParts = explode( "&", $payload );
		$tagReplaced = false;
		foreach( $payloadParts as $key => $value ) {
			if( strpos( $value, "tag=" ) === 0 ) {
				$payloadParts[$key] = "tag=$requestID";
				$tagReplaced = true;
				break;
			}
		}
		if( !$tagReplaced ) $payloadParts[] = "tag=$requestID";
		$getURLs[$requestID] = implode( "&", $payloadParts );

		$activePayloads[$requestID]['count'] = 0;
		$activePayloads[$requestID]['request_id'] = $requestID;
		$activePayloads[$requestID]['tag'] = $originalTag;
	}

	if( empty( $getURLs ) ) {
		sleep( 1 );
		continue;
	}

	$previousRequests[microtime( true ) * 1000000] = 0;
	$results = API::CDXQuery( $getURLs, true, true );

	if( !empty( $results['results'] ) ) {
		foreach( $results['results'] as $tid => $result ) {
			if( is_numeric( $tid ) ) $tid = (int) $tid;
			if( !isset( $activePayloads[$tid] ) ) continue;

			if( $result === null ) {
				$activePayloads[$tid]['count']++;
				if( $activePayloads[$tid]['count'] >= $retryLimit ) {
					DB::updateAvailabilityRequest( $activePayloads[$tid]['request_id'], false );
					unset( $activePayloads[$tid], $getURLs[$tid] );
				}
			} else {
				if( is_array( $result ) ) $result['tag'] = $activePayloads[$tid]['tag'];
				DB::updateAvailabilityRequest( $activePayloads[$tid]['request_id'], true, $result );
				unset( $activePayloads[$tid], $getURLs[$tid] );
			}
		}
	} else {
		foreach( $activePayloads as $tid => $payloadData ) {
			$activePayloads[$tid]['count']++;
			if( $activePayloads[$tid]['count'] >= $retryLimit ) {
				DB::updateAvailabilityRequest( $payloadData['request_id'], false );
				unset( $activePayloads[$tid], $getURLs[$tid] );
			}
		}

		if( $results['code'] == 429 ) sleep( 5 );
	}

}
