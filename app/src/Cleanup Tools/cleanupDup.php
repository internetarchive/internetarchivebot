<?php

use Wikimedia\DeadlinkChecker\CheckIfDead;

define( 'IAVERBOSE', false );

echo "----------STARTING UP SCRIPT----------\nStart Timestamp: " . date( 'r' ) . "\n\n";

if( !function_exists( 'pcntl_fork' ) ) die( "PCNTL extension missing.\n" );

echo "Initializing...\n";
define( 'USEWEBINTERFACE', 0 );
//Establish root path
@define( 'IABOTROOT', cleanupDup . phpdirname( __FILE__, 2 ) );
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
@define( 'IABOTDBSSL', $ssl );

require_once( IABOTROOT . '../../vendor/autoload.php' );

DB::createConfigurationTable();

$accessibleWikis = DB::getConfiguration( "global", "systemglobals-allwikis" );

ksort( $accessibleWikis );

$maxWikis = 3;

$wikiChildren = [];

$stats = [];

$childMax = 150;
$articleTimeout = 600;
$wikiTimeout = 1200;
$children = [];
$fileNames = [];

$externalIP = file_get_contents( "http://ipecho.net/plain" );
$hostName = gethostname();

$reattempts = 20;

$resumeWiki = 'ukwiki';
$resumeTarget = false;

pcntl_async_signals( true );

$checkIfDead = new CheckIfDead( 30, 60, false, true, true );

foreach( $accessibleWikis as $wikipedia => $data ) {
	if( in_array( $wikipedia, [ 'wikidatawiki', 'mediawikiwiki' ] ) ) continue;

	if( $wikipedia == $resumeWiki ) $resumeTarget = true;

	if( !$resumeTarget ) continue;

	sleep( 1 );

	while( count( $wikiChildren ) >= $maxWikis ) {
		echo "A max of $maxWikis have been spawned.  Waiting...  (" . implode( ', ', array_flip( $wikiChildren ) ) .
		     ")\n";
		while( true ) {
			$cid = pcntl_wait( $status );
			$normalExit = pcntl_wifexited( $status );
			$exitCode = pcntl_wexitstatus( $status );
			$sigTerm = pcntl_wifsignaled( $status );
			$termSig = pcntl_wtermsig( $status );

			if( $normalExit && !$sigTerm && $exitCode === 0 ) {
				wikiFinished();
				echo "A wiki ($cid) exited normally, " . count( $wikiChildren ) . " resuming...  (" .
				     implode( ', ', array_flip( $wikiChildren ) ) . ")\n";
				if( $cid !== -1 ) break;
			} else {
				wikiFinished();
				echo "ERROR: A wiki ($cid) exited abnormally.  Exit code: $exitCode; Termination signal: $termSig\n";
				if( $cid !== -1 ) break;
			}
		}
	}

	wikirefork:
	$pid = pcntl_fork();

	if( $pid == -1 || $pid == 0 ) {
		if( $pid == -1 ) {
			echo "Error: Can't fork process.  Waiting...\n";
			$cid = pcntl_wait( $status );
			$normalExit = pcntl_wifexited( $status );
			$exitCode = pcntl_wexitstatus( $status );
			$sigTerm = pcntl_wifsignaled( $status );
			$termSig = pcntl_wtermsig( $status );

			if( $normalExit && !$sigTerm && $exitCode === 0 ) {
				wikiFinished();
				echo "A wiki ($cid) exited normally, " . count( $wikiChildren ) . " resuming...  (" .
				     implode( ', ', array_flip( $wikiChildren ) ) . ")\n";
			} else {
				wikiFinished();
				echo "ERROR: A wiki ($cid) exited abnormally.  Exit code: $exitCode; Termination signal: $termSig\n";
			}
			goto wikirefork;
		}
		pcntl_alarm( $articleTimeout );

		pcntl_signal( SIGALRM, function( $signal ) use ( $wikipedia ) {
			echo "ERROR: Timeout occurred on this wiki worker ($wikipedia)...\n";
			exit( 1 );
		} );

		define( 'WIKIPEDIA', $wikipedia );

		require_once( 'html/loader.php' );

		ini_set( 'memory_limit', '8G' );

		$dbObject = new DB2();

		$config = API::fetchConfiguration();
		$config['link_scan'] = 0;
		$config['tag_override'] = 1;

		API::escapeTags( $config );

		$tmp = APIICLASS;
		$commObject = new $tmp( 'Main Page', 0, $config );
		$tmp = GENERATORCLASS;
		$generator = new $tmp( $commObject );
		$tmp = PARSERCLASS;
		$parser = new $tmp( $commObject );
		$dbObject2 = new DB( $commObject );

		$ch = curl_init();
		//curl_setopt( $ch, CURLOPT_COOKIEFILE, COOKIE );
		//curl_setopt( $ch, CURLOPT_COOKIEJAR, COOKIE );
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
			'action'       => 'query',
			'format'       => 'json',
			'list'         => 'allrevisions',
			'arvprop'      => 'ids|timestamp|flags|comment|user|userid',
			'arvslots'     => '*',
			'arvlimit'     => 'max',
			'arvuser'      => 'InternetArchiveBot',
			'arvdir'       => 'newer',
			'arvnamespace' => 0,
			'arvend'       => date( 'Y-m-d\T', strtotime( '-1 day' ) ) . "23:59:59Z"
		];

		do {
			$get = http_build_query( $query );
			$url = API . "?$get";

			$attempts = 1;

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
				if( $attempts <= $reattempts ) {
					$attempts++;
				} else {
					echo "ERROR: Aborting revision pull\n";
					break;
				}
			} while( !$data );

			if( !is_array( $data ) || isset( $data['error'] ) ) {
				echo "Data read failure.  Retrying...\nContent was:\n";
				var_dump( $data );
				usleep( 500000 );
				if( $attempts <= $reattempts ) {
					$attempts++;
					goto retryquery;
				} else {
					echo "ERROR: Aborting revision pull\n";
					break;
				}
			}

			$reactiveEdits = 0;
			$proactiveEdits = 0;
			$deadEdits = 0;
			$unknownEdits = 0;
			$reactiveLinks = 0;
			$proactiveLinks = 0;
			$deadLinks = 0;
			$unknownLinks = 0;

			if( !is_array( $data['query']['allrevisions'] ) ) {
				echo "Bad data detected.  Content received:\n";
				var_dump( $data );
				if( $attempts <= $reattempts ) {
					$attempts++;
					goto retryquery;
				} else {
					echo "ERROR: Aborting revision pull\n";
					break;
				}
			}

			foreach( $data['query']['allrevisions'] as $revisions ) {
				$title = $revisions['title'];
				$pageID = $revisions['pageid'];

				pcntl_alarm( $wikiTimeout );
				while( count( $children ) >= $childMax ) {
					$cid = pcntl_wait( $status );
					$normalExit = pcntl_wifexited( $status );
					$exitCode = pcntl_wexitstatus( $status );
					$sigTerm = pcntl_wifsignaled( $status );
					$termSig = pcntl_wtermsig( $status );

					if( $normalExit && !$sigTerm ) {
						childFinished();
						if( $cid !== -1 ) break;
					} else {
						childFinished();
						echo "ERROR: A child ($cid) exited abnormally.  Exit code: $exitCode; Termination signal: $termSig\n";
						if( $cid !== -1 ) break;
					}
				}

				refork:
				$pid = pcntl_fork();

				if( $pid == -1 || $pid == 0 ) {
					if( $pid == -1 ) {
						echo "ERROR: Can't fork process.  Waiting...\n";
						$cid = pcntl_wait( $status );
						$normalExit = pcntl_wifexited( $status );
						$exitCode = pcntl_wexitstatus( $status );
						$sigTerm = pcntl_wifsignaled( $status );
						$termSig = pcntl_wtermsig( $status );

						if( $normalExit && !$sigTerm ) {
							$returnedStats = childFinished();
							mergeStats( $stats, $returnedStats );
							echo "A child ($cid) exited normally, resuming...\n";
						} else {
							$returnedStats = childFinished();
							mergeStats( $stats, $returnedStats );
							echo "ERROR: A child ($cid) exited abnormally.  Exit code: $exitCode; Termination signal: $termSig\n";
						}
						goto refork;
					}
					//Necessary to change sockets.  Otherwise the DB will be confused.
					$dbObject->reconnect();
					$ch = curl_init();
					//curl_setopt( $ch, CURLOPT_COOKIEFILE, COOKIE );
					//curl_setopt( $ch, CURLOPT_COOKIEJAR, COOKIE );
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

					pcntl_alarm( $articleTimeout );

					pcntl_signal( SIGALRM, function( $signal ) use ( &$title, &$pageID ) {
						echo "ERROR: Timeout occurred while trying to process \"$title\" ($pageID) on " . WIKIPEDIA .
						     ".\n";
						exit( 1 );
					} );

					do {
						//$pageText = API::getPageText( $pageID, 'pageid' );
						$pageText = API::getPageText( $title );
					} while( empty( $pageText ) );

					$pageLinks = $parser->getExternallinks( false, $pageText );

					$makeEdit = false;

					foreach( $pageLinks as $tid => $linkData ) {
						$subID = 0;
						do {
							if( $linkData['link_type'] == 'reference' ) {
								$isReference = true;
								$subData = $linkData['reference'][$subID];
								$subID++;
							} else {
								$isReference = false;
								$subData = $linkData[$linkData['link_type']];
							}
							$editCite = false;

							if( $subData['link_type'] == 'template' ) {
								$templateParameters = $subData['link_template']['parameters'];
								$archiveURLAliases = [];
								$archiveDateAliases = [];
								$accessDateAliases = [];
								$deadLinkAliases = [];

								if( !isset( $subData['link_template']['template_map']['services']['@default']['archive_date'] ) &&
								    !isset( $subData['link_template']['template_map']['services']['@default']['archive_url'] ) ) continue;
								if( isset( $subData['link_template']['template_map']['services']['@default']['archive_url'] ) ) {
									foreach(
										$subData['link_template']['template_map']['services']['@default']['archive_url']
										as $dataPointer
									) {
										foreach(
											$subData['link_template']['template_map']['data'][$dataPointer]['mapto'] as
											$paramPointer
										) {
											$archiveURLAliases[] =
												$subData['link_template']['template_map']['params'][$paramPointer];
										}
									}
								}
								if( isset( $subData['link_template']['template_map']['services']['@default']['archive_date'] ) ) {
									foreach(
										$subData['link_template']['template_map']['services']['@default']['archive_date']
										as $dataPointer
									) {
										foreach(
											$subData['link_template']['template_map']['data'][$dataPointer['index']]['mapto']
											as $paramPointer
										) {
											$archiveDateAliases[] =
												$subData['link_template']['template_map']['params'][$paramPointer];
										}
									}
								}
								if( isset( $subData['link_template']['template_map']['services']['@default']['access_date'] ) ) {
									foreach(
										$subData['link_template']['template_map']['services']['@default']['access_date']
										as $dataPointer
									) {
										foreach(
											$subData['link_template']['template_map']['data'][$dataPointer['index']]['mapto']
											as $paramPointer
										) {
											$accessDateAliases[] =
												$subData['link_template']['template_map']['params'][$paramPointer];
										}
									}
								}
								if( isset( $subData['link_template']['template_map']['services']['@default']['deadvalues'] ) ) {
									foreach(
										$subData['link_template']['template_map']['services']['@default']['deadvalues']
										as $dataPointer
									) {
										foreach(
											$subData['link_template']['template_map']['data'][$dataPointer['index']]['mapto']
											as $paramPointer
										) {
											$deadLinkAliases[] =
												$subData['link_template']['template_map']['params'][$paramPointer];
										}
									}
								}

								$archiveURLAlias = false;
								$archiveDateAlias = false;
								$accessDateAlias = false;
								$deadLinkAlias = false;

								foreach( $archiveURLAliases as $alias ) {
									if( !empty( $templateParameters[$alias] ) ) {
										if( $archiveURLAlias === false ) $archiveURLAlias = $alias;
										else {
											if( $isReference ) unset( $linkData['reference'][$subID -
											                                                 1]['link_template']['parameters'][$alias]
											);
											else unset( $linkData[$linkData['link_type']]['link_template']['parameters'][$alias] );
											$makeEdit = true;
											$editCite = true;
										}
									}
								}

								foreach( $archiveDateAliases as $alias ) {
									if( !empty( $templateParameters[$alias] ) ) {
										if( $archiveDateAlias === false ) $archiveDateAlias = $alias;
										else {
											if( $isReference ) unset( $linkData['reference'][$subID -
											                                                 1]['link_template']['parameters'][$alias]
											);
											else unset( $linkData[$linkData['link_type']]['link_template']['parameters'][$alias] );
											$makeEdit = true;
											$editCite = true;
										}
									}
								}

								foreach( $accessDateAliases as $alias ) {
									if( !empty( $templateParameters[$alias] ) ) {
										if( $accessDateAlias === false ) $accessDateAlias = $alias;
										else {
											if( $isReference ) unset( $linkData['reference'][$subID -
											                                                 1]['link_template']['parameters'][$alias]
											);
											else unset( $linkData[$linkData['link_type']]['link_template']['parameters'][$alias] );
											$makeEdit = true;
											$editCite = true;
										}
									}
								}

								foreach( $deadLinkAliases as $alias ) {
									if( !empty( $templateParameters[$alias] ) ) {
										if( $deadLinkAlias === false ) $deadLinkAlias = $alias;
										else {
											if( $isReference ) unset( $linkData['reference'][$subID -
											                                                 1]['link_template']['parameters'][$alias]
											);
											else unset( $linkData[$linkData['link_type']]['link_template']['parameters'][$alias] );
											$makeEdit = true;
											$editCite = true;
										}
									}
								}
							}
						} while( $subID !== 0 && isset( $linkData['reference'][$subID] ) );

						if( $editCite ) {
							$newString = $generator->generateString( $linkData );

							$pageText = DataGenerator::str_replace( $linkData['string'], $newString,
							                                        $pageText, $count, 1
							);
						}
					}

					if( $makeEdit && API::edit( $title, $pageText,
					                            "Cleaning up redundant parameters added by prior faulty versions.", true
						) ) {
						echo WIKIPEDIA . ": Successfully cleaned up \"$title\" ($pageID)\n";
					} elseif( $makeEdit ) {
						echo "ERROR - " . WIKIPEDIA . ": Failed to clean up \"$title\" ($pageID)\n";
					}

					exit( 0 );
				} else {
					$children[] = $pid;
				}
			}
			if( isset( $data['continue'] ) ) $query = array_replace( $query, $data['continue'] );
		} while( isset( $data['continue'] ) );

		echo "Waiting for child workers...\n";

		while( !empty( $children ) ) {
			pcntl_alarm( $wikiTimeout );
			$cid = pcntl_wait( $status );
			$normalExit = pcntl_wifexited( $status );
			$exitCode = pcntl_wexitstatus( $status );
			$sigTerm = pcntl_wifsignaled( $status );
			$termSig = pcntl_wtermsig( $status );

			if( $normalExit && !$sigTerm && $exitCode === 0 ) {
				childFinished();
				echo "A child ($cid) exited normally, " . count( $children ) . " remaining...\n";
			} else {
				childFinished();
				echo "ERROR: A child ($cid) exited abnormally.  Exit code: $exitCode; Termination signal: $termSig\n";
			}
		}

		exit( 0 );
	} else {
		$wikiChildren[$wikipedia] = $pid;

		echo "Spawned $wikipedia; Number of children: " . count( $wikiChildren ) . "\n";
	}
}

while( !empty( $wikiChildren ) ) {
	$cid = pcntl_wait( $status );
	$normalExit = pcntl_wifexited( $status );
	$exitCode = pcntl_wexitstatus( $status );
	$sigTerm = pcntl_wifsignaled( $status );
	$termSig = pcntl_wtermsig( $status );

	if( $normalExit && !$sigTerm && $exitCode === 0 ) {
		wikiFinished();
		echo "A wiki ($cid) exited normally, " . count( $wikiChildren ) . " remaining...  (" .
		     implode( ', ', array_flip( $wikiChildren ) ) . ")\n";
	} else {
		wikiFinished();
		echo "ERROR: A wiki ($cid) exited abnormally.  Exit code: $exitCode; Termination signal: $termSig\n";
	}
}

function wikiFinished() {
	global $wikiChildren;

	foreach( $wikiChildren as $key => $child ) {
		if( file_exists( "/proc/$child" ) === false ) {
			unset( $wikiChildren[$key] );
			echo "$key finished processing\n";
		}
	}
}

function childFinished() {
	global $children;

	$data = [];

	foreach( $children as $key => $child ) {
		if( file_exists( "/proc/$child" ) === false ) {
			unset( $children[$key] );
		}
	}

	return $data;
}