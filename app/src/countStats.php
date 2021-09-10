<?php

use Wikimedia\DeadlinkChecker\CheckIfDead;

define( 'IAVERBOSE', false );

//$resumeOn = 'enwiki';
//$testDay = '8/1/21';

echo "----------STARTING UP SCRIPT----------\nStart Timestamp: " . date( 'r' ) . "\n\n";

if( !function_exists( 'pcntl_fork' ) ) die( "PCNTL extension missing.\n" );

echo "Initializing...\n";
define( 'USEWEBINTERFACE', 0 );
//Establish root path
@define( 'IABOTROOT', dirname( __FILE__, 1 ) . DIRECTORY_SEPARATOR );
date_default_timezone_set( "UTC" );
ini_set( 'memory_limit', '256M' );
require_once( IABOTROOT . 'deadlink.config.inc.php' );

if( file_exists( IABOTROOT . 'deadlink.config.local.inc.php' ) ) {
	require_once( IABOTROOT . 'deadlink.config.local.inc.php' );
}
require_once 'Core/DB.php';
@define( 'HOST', $host );
@define( 'PORT', $port );
@define( 'USER', $user );
@define( 'PASS', $pass );
@define( 'DB', $db );

DB::createConfigurationTable();

if( isset( $resumeOn ) ) {
	$fh = fopen( 'countStats.tsv', 'a+' );
} else {
	$fh = fopen( 'countStats.tsv', 'w+' );

	fputs( $fh,
	       "Wiki\tTimestamp\tNumber of pages edited\tProactive edits\tReactive Edits\tHelpless Edits\tUnknown Edits\tTotal links edited\tDead links rescued\tLive links enhanced\tDead links tagged\tUnknown links handled\n"
	);
}

$accessibleWikis = DB::getConfiguration( "global", "systemglobals-allwikis" );

ksort( $accessibleWikis );

$maxWikis = 1;

$wikiChildren = [];

$stats = [];

$queryMax = 5000;
$childMax = 720;
$children = [];
$fileNames = [];

foreach( $accessibleWikis as $wikipedia => $data ) {
	if( in_array( $wikipedia, [ 'allthetropeswiki', 'wikidatawiki', 'mediawikiwiki' ] ) ) continue;

	if( isset( $resumeOn ) && $wikipedia != $resumeOn ) continue;
	else unset( $resumeOn );

	$wikiMax = $queryMax;

	if( count( $wikiChildren ) >= $maxWikis ) {
		echo "A max of $maxWikis have been spawned.  Waiting...\n";
		pcntl_wait( $status );
		$tsv = wikiFinished();
		foreach( $tsv as $string ) {
			fputs( $fh, $string );
		}
		echo "A child exited with code " . pcntl_wexitstatus( $status ) . ", resuming...\n";
	}

	wikirefork:
	$pid = pcntl_fork();

	if( $pid == -1 || $pid == 0 ) {
		if( $pid == -1 ) {
			echo "Error: Can't fork process.  Waiting...\n";
			pcntl_wait( $status );
			echo "A child exited with code " . pcntl_wexitstatus( $status ) . ", resuming...\n";
			goto wikirefork;
		}

		define( 'WIKIPEDIA', $wikipedia );

		require_once( 'html/loader.php' );

		ini_set( 'memory_limit', '8G' );
		ini_set( 'max_execution_time', 10800 );

		$dbObject = new DB2();

		DB::checkDB();

		$config = API::fetchConfiguration();
		$config['link_scan'] = 0;
		$config['tag_override'] = 1;

		API::escapeTags( $config );

		$tmp = APIICLASS;
		$commObject = new $tmp( 'Main Page', 0, $config );
		$tmp = PARSERCLASS;
		$parser = new $tmp( $commObject );

		$checkIfDead = new CheckIfDead();

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_COOKIEFILE, COOKIE );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, COOKIE );
		curl_setopt( $ch, CURLOPT_USERAGENT, USERAGENT );
		curl_setopt( $ch, CURLOPT_MAXCONNECTS, 100 );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 20 );
		curl_setopt( $ch, CURLOPT_ENCODING, 'gzip' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 1 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, true );
		@curl_setopt( $ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true );
		curl_setopt( $ch, CURLOPT_DNS_CACHE_TIMEOUT, 60 );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		if( PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION >= 7.3 ) {
			curl_setopt( $ch,
			             CURLOPT_SSLVERSION,
			             CURL_SSLVERSION_TLSv1_3
			);
		}

		$query = [
			'action'  => 'query',
			'format'  => 'json',
			'list'    => 'users',
			'usprop'  => 'editcount',
			'ususers' => 'InternetArchiveBot'
		];

		$get = http_build_query( $query );
		$url = API . "?$get";

		curl_setopt( $ch, CURLOPT_TIMEOUT, 100 );
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER,
		             [ API::generateOAuthHeader( 'GET', $url, $keys ) ]
		);

		do {
			$data = curl_exec( $ch );
			$data = json_decode( $data, true );
		} while( !$data );

		if( isset( $data['error']['code'] ) &&
		    $data['error']['code'] = 'mwoauth-invalid-authorization-invalid-user' ) exit( 0 );
		if( isset( $data['query']['users'][0]['missing'] ) ) exit( 0 );

		if( !isset( $data['query']['users'][0]['editcount'] ) ) {
			echo "Data read failure.  Using max...\nContent was:\n";
			var_dump( $data );
		} else $wikiMax = min( ceil( $data['query']['users'][0]['editcount'] / $childMax ), $queryMax );

		if( $wikiMax == 0 ) $wikiMax = 'max';

		echo "Using a query limit of $wikiMax for " . WIKIPEDIA . "\n";

		$query = [
			'action'       => 'query',
			'format'       => 'json',
			'list'         => 'allrevisions',
			'arvprop'      => 'ids|timestamp|flags|comment|user|userid',
			'arvslots'     => '*',
			'arvlimit'     => $wikiMax,
			'arvuser'      => 'InternetArchiveBot',
			'arvdir'       => 'newer',
			'arvnamespace' => 0
		];

		if( isset( $testDay ) ) {
			$query['arvstart'] = date( 'Y-m-d\TH:i:s\Z', strtotime( $testDay ) );
			$query['arvend'] = date( 'Y-m-d\TH:i:s\Z', strtotime( $testDay . '+ 1 day' ) );
		}

		do {
			$get = http_build_query( $query );
			$url = API . "?$get";

			retryquery:
			curl_setopt( $ch, CURLOPT_TIMEOUT, 100 );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
			curl_setopt( $ch, CURLOPT_HTTPHEADER,
			             [ API::generateOAuthHeader( 'GET', $url ) ]
			);

			do {
				$data = curl_exec( $ch );
				$data = json_decode( $data, true );
			} while( !$data );

			if( !is_array( $data ) || isset( $data['error'] ) ) {
				echo "Data read failure.  Retrying...\nContent was:\n";
				var_dump( $data );
				usleep( 500000 );
				goto retryquery;
			}

			$filename = "$wikipedia-" . md5( microtime( true ) ) . '.serialized';

			if( count( $children ) >= $childMax ) {
				echo "A max of $childMax have been spawned.  Waiting...\n";
				pcntl_wait( $status );
				echo "A child exited with code " . pcntl_wexitstatus( $status ) . ", resuming...\n";
				$returnedStats = childFinished();
				mergeStats( $stats, $returnedStats );
			}

			refork:
			$pid = pcntl_fork();

			if( $pid == -1 || $pid == 0 ) {
				if( $pid == -1 ) {
					echo "Error: Can't fork process.  Waiting...\n";
					pcntl_wait( $status );
					echo "A child exited with code " . pcntl_wexitstatus( $status ) . ", resuming...\n";
					$returnedStats = childFinished();
					mergeStats( $stats, $returnedStats );
					goto refork;
				}
				//Necessary to change sockets.  Otherwise the DB will be confused.
				$dbObject->reconnect();
				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_COOKIEFILE, COOKIE );
				curl_setopt( $ch, CURLOPT_COOKIEJAR, COOKIE );
				curl_setopt( $ch, CURLOPT_USERAGENT, USERAGENT );
				curl_setopt( $ch, CURLOPT_MAXCONNECTS, 100 );
				curl_setopt( $ch, CURLOPT_MAXREDIRS, 20 );
				curl_setopt( $ch, CURLOPT_ENCODING, 'gzip' );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 1 );
				curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 1 );
				curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, true );
				@curl_setopt( $ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true );
				curl_setopt( $ch, CURLOPT_DNS_CACHE_TIMEOUT, 60 );
				curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
				if( PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION >= 7.3 ) {
					curl_setopt( $ch,
					             CURLOPT_SSLVERSION,
					             CURL_SSLVERSION_TLSv1_3
					);
				}
				if( !is_array( $data['query']['allrevisions'] ) ) {
					echo "Bad data detected.  Content received:\n";
					var_dump( $data );
				}
				$stats = [];

				$reactiveEdits = 0;
				$proactiveEdits = 0;
				$deadEdits = 0;
				$unknownEdits = 0;
				$reactiveLinks = 0;
				$proactiveLinks = 0;
				$deadLinks = 0;
				$unknownLinks = 0;

				foreach( $data['query']['allrevisions'] as $revisions )
					foreach( $revisions['revisions'] as $revision ) {
						$parentID = $revision['parentid'];
						$revID = $revision['revid'];
						$timestamp = $revision['timestamp'];

						$toOut = "$wikipedia - $timestamp";

						$timestamp = strtotime( $timestamp );

						$sectionJunk = false;
						do {
							$parentRevision = API::getPageText( $parentID, 'revid' );
						} while( empty( $parentRevision ) );
						do {
							$botRevision = API::getPageText( $revID, 'revid' );
						} while( empty( $botRevision ) );

						$parentLinks = $parser->getExternallinks( false, $parentRevision );
						$revisedLinks = $parser->getExternallinks( false, $botRevision );

						$reactiveLinksEdit = 0;
						$proactiveLinksEdit = 0;
						$deadLinksEdit = 0;
						$unknownLinksEdit = 0;

						foreach( $parentLinks as $tid => $linkData ) {
							$revisionLink = $revisedLinks[$tid];

							if( $linkData == $revisionLink ) continue;

							$subID = 0;
							do {
								if( $linkData['link_type'] == 'reference' ) {
									$subData = $linkData['reference'][$subID];
									$revisionData = $revisionLink['reference'][$subID];
									$subID++;
								} else {
									$subData = $linkData[$linkData['link_type']];
									$revisionData = $revisionLink[$revisionLink['link_type']];
								}

								if( $subData['tagged_dead'] === true && $subData['has_archive'] === false &&
								    $revisionData['has_archive'] === true ) {
									$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m',
									                                                                       $timestamp
									)][(int) strftime( '%d', $timestamp )]['deadlinks']++;
									$reactiveLinks++;
									$reactiveLinksEdit++;

									continue;
								}
								if( $subData['tagged_dead'] === false && $revisionData['tagged_dead'] === true &&
								    $subData['has_archive'] === false && $revisionData['has_archive'] === false ) {
									$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m',
									                                                                       $timestamp
									)][(int) strftime( '%d', $timestamp )]['404links']++;
									$deadLinks++;
									$deadLinksEdit++;

									continue;
								}
								if( $subData['has_archive'] === false && $revisionData['has_archive'] === true ) {
									$sqlURL =
										"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url` = '" .
										$dbObject->sanitize( $revisionData['url'] ) . "';";
									if( ( $res = $dbObject->queryDB( $sqlURL ) ) &&
									    ( $result = mysqli_fetch_assoc( $res ) ) ) {
										mysqli_free_result( $res );
										if( in_array( $result['live_state'], [ 0, 1, 2, 4, 6 ] ) ||
										    $result['paywall_status'] == 2 ) {
											$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m',
											                                                                       $timestamp
											)][(int) strftime( '%d', $timestamp )]['deadlinks']++;
											$reactiveLinks++;
											$reactiveLinksEdit++;

											continue;
										} else {
											$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m',
											                                                                       $timestamp
											)][(int) strftime( '%d', $timestamp )]['livelinks']++;
											$proactiveLinks++;
											$proactiveLinksEdit++;

											continue;
										}
									} else {
										$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m',
										                                                                       $timestamp
										)][(int) strftime( '%d', $timestamp )]['unknownlinks']++;
										$unknownLinks++;
										$unknownLinksEdit++;

										continue;
									}
								}/*
							if( $subData['has_archive'] === true && $revisionData['tagged_dead'] === true ) {
								if( $revisionData['tag_type'] != 'template' ) {
									if( $subData['tagged_dead'] && $subData['tag_type'] == 'implied' && $revisionData['tag_type'] == 'parameter' ) {
										$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m', $timestamp
										)][(int) strftime( '%d', $timestamp )]['deadlinks']++;
										$reactiveLinks++;
										$reactiveLinksEdit++;
										continue;
									}
									if( !$subData['tagged_dead'] && $revisionData['tag_type'] == 'parameter' ) {
										$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m', $timestamp
										)][(int) strftime( '%d', $timestamp )]['deadlinks']++;
										$reactiveLinks++;
										$reactiveLinksEdit++;
										continue;
									}
								}
							}*/
							} while( $subID !== 0 && isset( $linkData['reference'][$subID] ) );
						}

						if( $reactiveLinksEdit > 0 ) {
							$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m', $timestamp
							)][(int) strftime( '%d', $timestamp )]['reactiveedits']++;
							$toOut .= " - REACTIVE";
							$reactiveEdits++;
						} elseif( $proactiveLinksEdit > 0 ) {
							$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m', $timestamp
							)][(int) strftime( '%d', $timestamp )]['proactiveedits']++;
							$toOut .= " - PROACTIVE";
							$proactiveEdits++;
						} elseif( $deadLinksEdit > 0 ) {
							$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m', $timestamp
							)][(int) strftime( '%d', $timestamp )]['404edits']++;
							$toOut .= " - DEAD";
							$deadEdits++;
						} else {
							$stats[$wikipedia][(int) strftime( '%Y', $timestamp )][(int) strftime( '%m', $timestamp
							)][(int) strftime( '%d', $timestamp )]['unknownedits']++;
							$toOut .= " - UNKNOWN";
							$unknownEdits++;
						}

						$toOut .= " - D:$reactiveLinks A:$proactiveLinks 404:$deadLinks U:$unknownLinks\n";

						echo $toOut;
					}

				file_put_contents( $filename, serialize( $stats ) );

				$dbObject = null;
				curl_close( $ch );
				exit( 0 );
			} else {
				$children[] = $pid;
				$fileNames[$pid] = $filename;

				echo "\t(" . WIKIPEDIA . ") Spawned child with ID: $pid; Number of children: " . count( $children ) .
				     "\n";
			}

			if( isset( $data['continue'] ) ) $query = array_replace( $query, $data['continue'] );
			usleep( 50000 );
		} while( isset( $data['continue'] ) );

		while( !empty( $children ) ) {
			pcntl_wait( $status );
			$returnedStats = childFinished();
			mergeStats( $stats, $returnedStats );
			echo "A child exited with code " . pcntl_wexitstatus( $status ) . ", " . count( $children ) .
			     " remaining...\n";
		}

		ksort( $stats[$wikipedia] );

		foreach( $stats[$wikipedia] as $year => $yearEntries ) {
			ksort( $yearEntries );
			foreach( $yearEntries as $month => $monthEntries ) {
				ksort( $monthEntries );
				foreach( $monthEntries as $day => $data ) {
					$totalEdits = 0;
					if( !empty( $data['reactiveedits'] ) ) {
						$totalEdits += $data['reactiveedits'];
						$reactiveEdits = $data['reactiveedits'];
					} else $reactiveEdits = 0;
					if( !empty( $data['proactiveedits'] ) ) {
						$totalEdits += $data['proactiveedits'];
						$proactiveEdits = $data['proactiveedits'];
					} else $proactiveEdits = 0;
					if( !empty( $data['unknownedits'] ) ) {
						$totalEdits += $data['unknownedits'];
						$unknownEdits = $data['unknownedits'];
					} else $unknownEdits = 0;
					if( !empty( $data['404edits'] ) ) {
						$totalEdits += $data['404edits'];
						$deadEdits = $data['404edits'];
					} else $deadEdits = 0;

					$totalLinks = 0;
					if( !empty( $data['deadlinks'] ) ) {
						$totalLinks += $data['deadlinks'];
						$deadLinks = $data['deadlinks'];
					} else $deadLinks = 0;
					if( !empty( $data['livelinks'] ) ) {
						$totalLinks += $data['livelinks'];
						$liveLinks = $data['livelinks'];
					} else $liveLinks = 0;
					if( !empty( $data['404links'] ) ) {
						$totalLinks += $data['404links'];
						$tagLinks = $data['404links'];
					} else $tagLinks = 0;
					if( !empty( $data['unknownlinks'] ) ) {
						$totalLinks += $data['unknownlinks'];
						$unknownLinks = $data['unknownlinks'];
					} else $unknownLinks = 0;

					$wfh = fopen( "$wikipedia.tsv", 'a+' );

					fputs( $wfh,
					       "$wikipedia\t$year-$month-$day\t$totalEdits\t$proactiveEdits\t$reactiveEdits\t$deadEdits\t$unknownEdits\t$totalLinks\t$deadLinks\t$liveLinks\t$tagLinks\t$unknownLinks\n"
					);

					$sql =
						"INSERT INTO externallinks_statistics (`stat_wiki`, `stat_timestamp`, `stat_year`, `stat_month`, `stat_day`, `stat_key`, `stat_value`) VALUES ";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'TotalEdits',$totalEdits),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'TotalLinks',$totalLinks),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'ReactiveEdits',$reactiveEdits),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'ProactiveEdits',$proactiveEdits),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'DeadEdits',$deadEdits),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'UnknownEdits',$unknownEdits),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'LiveLinks',$liveLinks),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'DeadLinks',$deadLinks),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'TagLinks',$tagLinks),";
					$sql .= "('$wikipedia','$year-$month-$day',$year,$month,$day,'UnknownLinks',$unknownLinks);";

					$dbObject->queryDB( $sql );
				}
			}
		}

		unset( $stats[$wikipedia] );
		exit( 0 );
	} else {
		$wikiChildren[$wikipedia] = $pid;

		echo "Spawned $wikipedia; Number of children: " . count( $wikiChildren ) . "\n";
	}

}

while( !empty( $wikiChildren ) ) {
	pcntl_wait( $status );
	$tsv = wikiFinished();
	foreach( $tsv as $string ) {
		fputs( $fh, $string );
	}
	echo "A wiki exited with code " . pcntl_wexitstatus( $status ) . ", " . count( $wikiChildren ) .
	     " remaining...\n";
}

function wikiFinished() {
	global $wikiChildren;

	foreach( $wikiChildren as $key => $child ) {
		if( file_exists( "/proc/$child" ) === false ) {
			unset( $wikiChildren[$key] );
			if( file_exists( "$key.tsv" ) ) {
				$tsv[$key] = file_get_contents( "$key.tsv" );
				unlink( "$key.tsv" );
			}
			echo "$key finished processing\n";
		}
	}

	return $tsv;
}

function childFinished() {
	global $children, $fileNames;

	$data = [];

	foreach( $children as $key => $child ) {
		if( file_exists( "/proc/$child" ) === false ) {
			$data[$child] = unserialize( file_get_contents( $fileNames[$child] ) );
			unlink( $fileNames[$child] );
			unset( $children[$key], $fileNames[$child] );
			echo "$child died\n";
		}
	}

	return $data;
}

function mergeStats( &$stats, $toMerge ) {
	foreach( $toMerge as $childID => $childData )
		foreach( $childData as $wikipedia => $wikipediaEntries ) foreach( $wikipediaEntries as $year => $yearEntries )
			foreach( $yearEntries as $month => $monthEntries )
				foreach( $monthEntries as $day => $data )
					foreach( $data as $tid => $value ) @$stats[$wikipedia][$year][$month][$day][$tid] += $value;
}