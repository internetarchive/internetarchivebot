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
 * Parser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */

/**
 * Parser class
 * Allows for the parsing on project specific wiki pages
 * @abstract
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */

use Wikimedia\DeadlinkChecker\CheckIfDead;

class Parser
{

	/**
	 * The API class
	 *
	 * @var API
	 * @access public
	 */
	public $commObject;

	/**
	 * The DB2 class
	 *
	 * @var DB2
	 * @access public
	 */
	public $dbObject;

	/**
	 * The CheckIfDead class
	 *
	 * @var CheckIfDead
	 * @access protected
	 */
	protected $deadCheck;

	/**
	 * The Generator class
	 *
	 * @var DataGenerator
	 * @access protected
	 */
	protected $generator;

	/**
	 * The regex for detecting proper RfC compliant URLs, with UTF-8 support.
	 * The scheme is not required to match.
	 *
	 * @var string
	 * @access protected
	 */
	protected $schemelessURLRegex = '(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#]*)?(?:\#([^\s\#\[\]]*))?';

	/**
	 * The regex for detecting proper RfC compliant URLs, with UTF-8 support.
	 * The scheme is required to match.
	 *
	 * @var string
	 * @access protected
	 */
	protected $schemedURLRegex = '(?:[a-z0-9\+\-\.]*:)\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#]*)?(?:\#([^\s\#\[\]]*))?';

	/**
	 * Caches template strings already parsed
	 *
	 * @var array
	 * @access protected
	 */
	protected $templateParamCache = [];

	/**
	 * Routines for detecting false positives
	 *
	 * @var FalsePositive
	 * @access protected
	 */
	protected $falsePositiveHandler;

	/**
	 * Parser class constructor
	 *
	 * @param API $commObject
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 */
	public function __construct( API $commObject )
	{
		$this->commObject = $commObject;
		$this->deadCheck  = new CheckIfDead( 20, 60, CIDUSERAGENT, true, true );
		$tmp              = GENERATORCLASS;
		$this->generator  = new $tmp( $commObject );
		CiteMap::loadGenerator( $this->generator );
		if( AUTOFPREPORT === true ) {
			$this->dbObject             = new DB2();
			$this->falsePositiveHandler = new FalsePositives( $this->commObject, $this->dbObject );
		}
	}

	/**
	 * Master page analyzer function.  Analyzes the entire page's content,
	 * retrieves specified URLs, and analyzes whether they are dead or not.
	 * If they are dead, the function acts based on onwiki specifications.
	 *
	 * @access public
	 *
	 * @param array $modifiedLinks Pass back a list of links modified
	 * @param bool $webRequest Prevents analysis of large pages that may cause the tool to timeout
	 *
	 * @return array containing analysis statistics of the page
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 *
	 */
	public function analyzePage( &$modifiedLinks = [], $webRequest = false, &$editError = false )
	{
		if( DEBUG === false || LIMITEDRUN === true ) {
			file_put_contents( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID, serialize( [
				                                                                               'title' => $this->commObject->page,
				                                                                               'id' => $this->commObject->pageid
			                                                                               ]
			                                                                  )
			);
		}
		$dumpcount = 0;
		unset( $tmp );
		echo "Analyzing {$this->commObject->page} ({$this->commObject->pageid})...\n";
		global $jobID;
		if( !empty( $jobID ) ) $watchDog['jobID'] = $jobID;
		$watchDog['page']   = $this->commObject->page;
		$watchDog['status'] = 'start';
		DB::pingWatchDog( $watchDog );
		//Tare statistics variables
		$modifiedLinks   = [];
		$archiveProblems = [];
		$archived        = 0;
		$rescued         = 0;
		$notrescued      = 0;
		$tagged          = 0;
		$waybackadded    = 0;
		$otheradded      = 0;
		$analyzed        = 0;
		$newlyArchived   = [];
		$timestamp       = date( "Y-m-d\TH:i:s\Z" );
		$history         = [];
		$toCheck         = [];
		$toCheckMeta     = [];
		if( AUTOFPREPORT === true ) {
			echo "Fetching previous bot revisions...\n";
			$watchDog['status'] = 'previousbotrevs';
			DB::pingWatchDog( $watchDog );
			$lastRevIDs   = $this->commObject->getBotRevisions();
			$lastRevTexts = [];
			$lastRevLinks = [];
			$oldLinks     = [];
			if( !empty( $lastRevIDs ) ) {
				$temp = API::getRevisionText( $lastRevIDs );
				foreach( $temp['query']['pages'][$this->commObject->pageid]['revisions'] as $lastRevText ) {
					$lastRevTexts[$lastRevText['revid']] = new Memory( $lastRevText['slots']['main']['*'] );
				}
				unset( $temp );
			}
		}

		if( $this->commObject->config['link_scan'] == 0 ) {
			echo "Fetching all external links...\n";
			$referencesOnly = false;
		} else {
			echo "Fetching all references...\n";
			$referencesOnly = true;
		}

		$watchDog['status'] = 'fetchlinks';
		DB::pingWatchDog( $watchDog );
		$links = $this->getExternalLinks( $referencesOnly, false, $webRequest );
		if( $links === false && $webRequest === true ) return false;
		if( isset( $lastRevTexts ) ) {
			foreach( $lastRevTexts as $id => $lastRevText ) {
				$lastRevLinks[$id] =
					new Memory( $this->getExternalLinks( $referencesOnly, false, $lastRevText->get( true ) ) );
			}
		}
		$analyzed = $links['count'];
		unset( $links['count'] );

		$newtext = $this->commObject->content;

		//Process the links
		$watchDog['status'] = 'processpage';
		DB::pingWatchDog( $watchDog );
		$checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = [];
		//Perform a 3 phase process.
		//Phases 1 and 2 collect archive information based on the configuration settings on wiki, needed for further analysis.
		//Phase 3 does the actual rescuing.
		for( $i = 0; $i < 3; $i++ ) {
			switch( $i ) {
				case 0:
					echo "Phase 1: Checking what's available and what needs archiving...\n";
					break;
				case 1:
					echo "Phase 2: Submitting requests for archives...\n";
					break;
				case 2:
					echo "Phase 3: Applying necessary changes to page...\n";
			}
			$namedReferences = [];
			foreach( $links as $tid => $link ) {
				if( $link['link_type'] == "reference" ) {
					$reference = true;
					if( preg_match( '/name\s*\=\s*(\"[^\"]*?\"|[^\s>]*)/i', $link['reference']['open'], $match ) ) {
						$referenceName = trim( $match[1], '"' );
						if( isset( $namedReferences[$referenceName] ) ) {
							$tmpOffset                          = $link['reference']['offset'];
							$links[$tid]                        = $namedReferences[$referenceName]['link'];
							$links[$tid]['reference']['offset'] = $tmpOffset;
							$rescued                            += $namedReferences[$referenceName]['stats']['rescued'];
							$archived                           += $namedReferences[$referenceName]['stats']['archived'];
							$tagged                             += $namedReferences[$referenceName]['stats']['tagged'];
							continue;
						}
					} else unset( $referenceName );
				} else $reference = false;
				$id        = 0;
				$linkStats = [ 'rescued' => 0, 'tagged' => 0, 'archived' => 0 ];
				do {
					if( $reference === true ) {
						$link                 = $links[$tid]['reference'][$id];
						$link['is_reference'] = true;
					} else {
						$link                 = $link[$link['link_type']];
						$link['is_reference'] = false;
					}
					if( isset( $link['ignore'] ) && $link['ignore'] === true ) continue;

					//Create a flag that marks the source as being improperly formatting and needing fixing
					$invalidEntry = ( ( $link['has_archive'] === true && ( ( $link['archive_type'] == "invalid" &&
					                                                         !isset( $link['ignore_iarchive_flag'] ) ) ||
					                                                       ( $this->commObject->config['convert_archives'] ==
					                                                         1 &&
					                                                         isset( $link['convert_archive_url'] ) &&
					                                                         ( !isset( $link['converted_encoding_only'] ) ||
					                                                           $this->commObject->config['convert_archives_encoding'] ==
					                                                           1 ) ) ) ) ||
					                  ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" ) ) &&
					                $link['link_type'] != "x";
					//Create a flag that determines basic clearance to edit a source.
					$linkRescueClearance =
						( ( ( $this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false ) &&
						    $link['permanent_dead'] === false ) || $invalidEntry === true ) &&
						$link['link_type'] != "x";
					//DEAD_ONLY = 0; Modify ALL links clearance flag
					$dead0 = $this->commObject->config['dead_only'] == 0 &&
					         !( $link['tagged_dead'] === true && $link['is_dead'] === false &&
					            $this->commObject->config['tag_override'] == 0 );
					//DEAD_ONLY = 1; Modify only tagged links clearance flag
					$dead1 = $this->commObject->config['dead_only'] == 1 && ( $link['tagged_dead'] === true &&
					                                                          ( $link['is_dead'] === true ||
					                                                            $this->commObject->config['tag_override'] ==
					                                                            1 ) );
					//DEAD_ONLY = 2; Modify all dead links clearance flag
					$dead2 = $this->commObject->config['dead_only'] == 2 &&
					         ( ( $link['tagged_dead'] === true && $this->commObject->config['tag_override'] == 1 ) ||
					           $link['is_dead'] === true );
					//Tag remove clearance flag
					$tagremoveClearance = $link['tagged_dead'] === true && $link['is_dead'] === false &&
					                      $this->commObject->config['tag_override'] == 0;
					//Forced update clearance
					$forceClearance = ( isset( $link['force'] ) ) ||
					                  ( $dead1 === true && isset( $link['force_when_dead'] ) &&
					                    $link['is_dead'] === true ) ||
					                  ( $this->commObject->config['tag_override'] == 0 &&
					                    isset( $link['force_when_alive'] ) && $link['is_dead'] === false );

					if( $i == 0 && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) &&
					    $this->commObject->config['archive_alive'] == 1
					) {
						//Populate a list of URLs to check, if an archive exists.
						if( $reference === false ) {
							$toArchive[$tid] = $link['url'];
						} else $toArchive["$tid:$id"] = $link['url'];
					} elseif( $i >= 1 && $reference === true &&
					          ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) &&
					          $this->commObject->config['archive_alive'] == 1 && $checkResponse["$tid:$id"] !== true
					) {
						//Populate URLs to submit for archiving.
						if( $i == 1 ) {
							$toArchive["$tid:$id"] = $link['url'];
						} else {
							//If it archived, then tally the success, otherwise, note it.
							if( $archiveResponse["$tid:$id"] === true ) {
								$archived++;
							} elseif( $archiveResponse["$tid:$id"] === false ) {
								$archiveProblems["$tid:$id"] = $link['url'];
							}
						}
					} elseif( $i >= 1 && $reference === false &&
					          ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) &&
					          $this->commObject->config['archive_alive'] == 1 && $checkResponse[$tid] !== true
					) {
						//Populate URLs to submit for archiving.
						if( $i == 1 ) {
							$toArchive[$tid] = $link['url'];
						} else {
							//If it archived, then tally the success, otherwise, note it.
							if( $archiveResponse[$tid] === true ) {
								$archived++;
								$linkStats['archived']++;
							} elseif( $archiveResponse[$tid] === false ) {
								$archiveProblems[$tid] = $link['url'];
							}
						}
					}

					if( $i >= 1 && ( ( $linkRescueClearance === true &&
					                   ( $dead0 === true || $dead1 === true || $dead2 === true ) ) ||
					                 $invalidEntry === true || $forceClearance === true )
					) {
						//Populate URLs that need we need to retrieve an archive for
						if( $i == 1 ) {
							if( $reference === false ) {
								$toFetch[$tid] = [
									$link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ?
										( $link['access_time'] != "x" ? $link['access_time'] : null ) : null )
								];
							} else {
								$toFetch["$tid:$id"] = [
									$link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ?
										( $link['access_time'] != "x" ? $link['access_time'] : null ) : null )
								];
							}
						} elseif( $i == 2 ) {
							//Do actual work
							if( ( ( $reference === false && ( $temp = $fetchResponse[$tid] ) !== false ) ||
							      ( $reference === true && ( $temp = $fetchResponse["$tid:$id"] ) !== false ) ) &&
							    !is_null( $temp )
							) {
								if( $reference !== false || $link['link_type'] != "stray" ||
								    $link['archive_type'] != "invalid" ||
								    ( $link['link_type'] == "stray" && $link['archive_type'] == "invalid" )
								) {
									if( $this->rescueLink( $link, $modifiedLinks, $temp, $tid, $id ) ===
									    true ) {
										$rescued++;
										$linkStats['rescued']++;
									}
								}
							} elseif( $temp === false && empty( $link['archive_url'] ) && $link['is_dead'] === true ) {
								$notrescued++;
								if( $link['tagged_dead'] !== true ) {
									if( $this->noRescueLink( $link, $modifiedLinks, $tid, $id ) ) {
										$link['newdata']['tagged_dead'] = true;
										$tagged++;
										$linkStats['tagged']++;
									} else {
										unset( $link['newdata'] );
									}
								} else continue;
							}
						}
					} elseif( $i == 2 && $tagremoveClearance ) {
						//This removes the tag.  When tag override is off.
						$rescued++;
						$linkStats['rescued']++;
						$modifiedLinks["$tid:$id"]['type'] = "tagremoved";
						$modifiedLinks["$tid:$id"]['link'] = $link['url'];
						$link['newdata']['tagged_dead']    = false;
					}

					//If the original URL was generated from a template, put it back in the URL field.
					if( $i == 2 && isset( $link['template_url'] ) ) {
						$link['url'] = $link['template_url'];
						unset( $link['template_url'] );
					}
					if( $i == 2 && isset( $modifiedLinks["$tid:$id"] ) ) {
						if( $reference === false ) {
							if( $this->commObject->config['notify_on_talk_only'] == 2 ) {
								switch( $modifiedLinks["$tid:$id"]['type'] ) {
									case "addarchive":
									case "modifyarchive":
									case "fix":
										$modifiedLinks["$tid:$id"]['talkonly'] = true;
										unset( $link['newdata'] );
								}
							}
						} elseif( in_array( parse_url( $link['url'], PHP_URL_HOST ),
						                    $this->commObject->config['notify_domains']
						) ) {
							$modifiedLinks["$tid:$id"]['talkonly'] = true;
							unset( $link['newdata'] );
						}
					}
					if( $i == 2 && $reference === true ) {
						$links[$tid]['reference'][$id] = $link;
					} elseif( $i == 2 ) {
						$links[$tid][$links[$tid]['link_type']] = $link;
					}
				} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );

				//Check if the newdata index actually contains newdata and if the link should be touched.  Avoid redundant work and edits this way.
				if( $i == 2 && DataGenerator::newIsNew( $links[$tid] ) ) {
					//If it is new, generate a new string.
					$links[$tid]['newstring'] = $this->generator->generateString( $links[$tid] );
					if( AUTOFPREPORT === true && !empty( $lastRevTexts ) &&
					    $botID = self::isEditReversed( $links[$tid], $lastRevLinks ) ) {
						echo "A revert has been detected.  Analyzing previous " .
						     count( $this->commObject->getRevTextHistory( $botID ) ) . " revisions...\n";
						foreach( $this->commObject->getRevTextHistory( $botID ) as $revID => $text ) {
							echo "\tAnalyzing revision $revID...\n";
							if( !isset( $oldLinks[$revID] ) ) {
								$oldLinks[$revID] =
									new Memory( $this->getExternalLinks( $referencesOnly, false, $text['*'] ) );
							}
						}

						echo "Attempting to identify reverting user...";
						$reverter = $this->commObject->getRevertingUser( $links[$tid], $oldLinks, $botID );
						if( $reverter !== false ) {
							$userDataAPI = API::getUser( $reverter['userid'] );
							$userData    =
								$this->dbObject->getUser( $userDataAPI['centralids']['CentralAuth'], WIKIPEDIA );
							if( empty( $userData ) ) {
								$wikiLanguage = str_replace( "wiki", "", WIKIPEDIA );
								$this->dbObject->createUser( $userDataAPI['centralids']['CentralAuth'], WIKIPEDIA,
								                             $userDataAPI['name'], 0, $wikiLanguage, serialize( [
									                                                                                'registration_epoch' => strtotime( $userDataAPI['registration']
									                                                                                ),
									                                                                                'editcount' => $userDataAPI['editcount'],
									                                                                                'wikirights' => $userDataAPI['rights'],
									                                                                                'wikigroups' => $userDataAPI['groups'],
									                                                                                'blockwiki' => isset( $userDataAPI['blockid'] )
								                                                                                ]
								                             )
								);
								$userData =
									$this->dbObject->getUser( $userDataAPI['centralids']['CentralAuth'], WIKIPEDIA );
								echo $userData['user_name'] . "\n";
							}
						} else echo "Failed!\n";
						echo "Attempting to ascertain reason for revert...\n";
						if( $links[$tid]['link_type'] == "reference" ) {
							$makeModification = true;
							foreach( $links[$tid]['reference'] as $id => $link ) {
								if( !is_numeric( $id ) ) continue;
								if( $this->isLikelyFalsePositive( "$tid:$id", $link, $modifyLink ) ) {
									if( $reverter !== false ) {
										$toCheck["$tid:$id"]     = $link['url'];
										$toCheckMeta["$tid:$id"] = $userData;
									}
								}
								$makeModification = $modifyLink && $makeModification;
								if( $modifyLink === false ) {
									switch( $modifiedLinks["$tid:$id"]['type'] ) {
										case "fix":
										case "modifyarchive":
										case "tagremoved":
										case "addarchive":
											$rescued--;
											break;
										case "tagged":
											$tagged--;
											$notrescued--;
											break;
									}
									unset( $modifiedLinks["$tid:$id"] );
								}
							}
							if( $makeModification === true ) {
								$newtext =
									DataGenerator::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
									                            $this->commObject->content, $count, 1,
									                            $links[$tid][$links[$tid]['link_type']]['offset'],
									                            $newtext
									);
							}
						} else {
							if( $this->isLikelyFalsePositive( $tid, $links[$tid][$links[$tid]['link_type']],
							                                  $makeModification
							) ) {
								if( $reverter !== false ) {
									$toCheck[$tid]     = $link['url'];
									$toCheckMeta[$tid] = $userData;
								}
							} elseif( $makeModification === true ) {
								$newtext =
									DataGenerator::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
									                            $this->commObject->content, $count, 1,
									                            $links[$tid][$links[$tid]['link_type']]['offset'],
									                            $newtext
									);
							}

							if( $makeModification === false ) {
								switch( $modifiedLinks["$tid:0"]['type'] ) {
									case "fix":
									case "modifyarchive":
									case "tagremoved":
									case "addarchive":
										$rescued--;
										break;
									case "tagged":
										$tagged--;
										$notrescued--;
										break;
								}
								unset( $modifiedLinks["$tid:0"] );
							}
						}
					} else {
						//Yes, this is ridiculously convoluted but this is the only makeshift str_replace expression I could come up with the offset start and limit support.
						$newtext = DataGenerator::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
						                                       $this->commObject->content, $count, 1,
						                                       $links[$tid][$links[$tid]['link_type']]['offset'],
						                                       $newtext
						);
					}
				}

				if( $reference && isset( $referenceName ) ) {
					$namedReferences[$referenceName]['link']  = $links[$tid];
					$namedReferences[$referenceName]['stats'] = $linkStats;
				}
			}

			//Check if archives exist for the provided URLs
			if( $i == 0 && !empty( $toArchive ) ) {
				$checkResponse = $this->commObject->isArchived( $toArchive );
				$checkResponse = $checkResponse['result'];
				$toArchive     = [];
			}
			$errors = [];
			//Submit provided URLs for archiving
			if( $i == 1 && !empty( $toArchive ) ) {
				$archiveResponse = $this->commObject->requestArchive( $toArchive );
				$errors          = $archiveResponse['errors'];
				$archiveResponse = $archiveResponse['result'];
			}
			//Retrieve snapshots of provided URLs
			if( $i == 1 && !empty( $toFetch ) ) {
				$fetchResponse = $this->commObject->retrieveArchive( $toFetch );
				$fetchResponse = $fetchResponse['result'];
			}
		}

		if( !empty( $toCheck ) ) {
			$escapedURLs = [];
			foreach( $toCheck as $url ) {
				$escapedURLs[] = $this->dbObject->sanitize( $url );
			}
			$sql             =
				"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id WHERE `url` IN ( '" .
				implode( "', '", $escapedURLs ) . "' ) AND `report_status` = 0;";
			$res             = $this->dbObject->queryDB( $sql );
			$alreadyReported = [];
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$alreadyReported[] = $result['url'];
			}

			$toCheck = array_diff( $toCheck, $alreadyReported );
		}

		if( !empty( $toCheck ) ) {
			$results     = $this->deadCheck->areLinksDead( $toCheck );
			$errors      = $this->deadCheck->getErrors();
			$whitelisted = [];
			if( USEADDITIONALSERVERS === true ) {
				$toValidate = [];
				foreach( $toCheck as $tid => $url ) {
					if( $results[$url] === true ) {
						$toValidate[] = $url;
					}
				}
				if( !empty( $toValidate ) ) {
					foreach( explode( "\n", CIDSERVERS ) as $server ) {
						$serverResults = API::runCIDServer( $server, $toValidate );
						$toValidate    = array_flip( $toValidate );
						foreach( $serverResults['results'] as $surl => $sResult ) {
							if( $surl == "errors" ) continue;
							if( $sResult === false ) {
								$whitelisted[] = $surl;
								unset( $toValidate[$surl] );
							} else {
								$errors[$surl] = $serverResults['results']['errors'][$surl];
							}
						}
						$toValidate = array_flip( $toValidate );
					}
				}
			}

			$toReset     = [];
			$toWhitelist = [];
			$toReport    = [];
			foreach( $toCheck as $id => $url ) {
				if( $results[$url] !== true ) {
					$toReset[] = $url;
				} else {
					if( !in_array( $url, $whitelisted ) ) {
						$toReport[] = $url;
					} else $toWhitelist[] = $url;
				}
			}
			foreach( $toReport as $report ) {
				$tid = array_search( $report, $toCheck );
				if( $this->dbObject->insertFPReport( WIKIPEDIA, $toCheckMeta[$tid]['user_link_id'],
				                                     $this->commObject->db->dbValues[$tid]['url_id'],
				                                     CHECKIFDEADVERSION, $errors[$report]
				) ) {
					$this->dbObject->insertLogEntry( "global", WIKIPEDIA, "fpreport", "report",
					                                 $this->commObject->db->dbValues[$tid]['url_id'], $report,
					                                 $toCheckMeta[$tid]['user_link_id']
					);
				}
			}

			$escapedURLs = [];
			$domains     = [];
			$tids        = [];
			foreach( $toReset as $report ) {
				$tid = array_search( $report, $toCheck );
				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 ) {
					continue;
				} elseif( $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) {
					continue;
				} elseif( in_array( $this->commObject->db->dbValues[$tid]['paywall_id'], $escapedURLs ) ) {
					continue;
				} else {
					$escapedURLs[] = $this->commObject->db->dbValues[$tid]['paywall_id'];
					$domains[]     = $this->deadCheck->parseURL( $report )['host'];
					$tids[]        = $tid;
				}
			}
			if( !empty( $escapedURLs ) ) {
				$sql = "UPDATE externallinks_global SET `live_state` = 3 WHERE `paywall_id` IN ( " .
				       implode( ", ", $escapedURLs ) . " );";
				if( $this->dbObject->queryDB( $sql ) ) {
					foreach( $escapedURLs as $id => $paywallID ) {
						$this->dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changestate", $paywallID,
						                                 $domains[$id],
						                                 $toCheckMeta[$tids[$id]]['user_link_id'], -1, 3
						);
					}
				}
			}
			$escapedURLs     = [];
			$domains         = [];
			$paywallStatuses = [];
			$tids            = [];
			foreach( $toWhitelist as $report ) {
				$tid = array_search( $report, $toCheck );
				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 ) {
					continue;
				} elseif( in_array( $this->commObject->db->dbValues[$tid]['paywall_id'], $escapedURLs ) ) {
					continue;
				} else {
					$escapedURLs[]     = $this->commObject->db->dbValues[$tid]['paywall_id'];
					$domains[]         = $this->deadCheck->parseURL( $report )['host'];
					$paywallStatuses[] = $this->commObject->db->dbValues[$tid]['paywall_status'];
					$tids[]            = $tid;
				}
			}
			if( !empty( $escapedURLs ) ) {
				$sql = "UPDATE externallinks_paywall SET `paywall_status` = 3 WHERE `paywall_id` IN ( " .
				       implode( ", ", $escapedURLs ) . " );";
				if( $this->dbObject->queryDB( $sql ) ) {
					foreach( $escapedURLs as $id => $paywallID ) {
						$this->dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changeglobalstate",
						                                 $paywallID,
						                                 $domains[$id], $toCheckMeta[$tids[$id]]['user_link_id'],
						                                 $paywallStatuses[$id], 3
						);
					}
				}
			}
			if( !empty( $toReport ) ) {
				$sql =
					"SELECT * FROM externallinks_user LEFT JOIN externallinks_userpreferences ON externallinks_userpreferences.user_link_id= externallinks_user.user_link_id WHERE `user_email_confirmed` = 1 AND `user_email_fpreport` = 1 AND `wiki` = '" .
					WIKIPEDIA . "';";
				$res = $this->dbObject->queryDB( $sql );
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$mailObject = new HTMLLoader( "emailmain", $result['language'], PUBLICHTML . "Templates/",
					                              PUBLICHTML . "i18n/"
					);
					$body       = "{{{fpreportedstartermultiple}}}:<br>\n";
					$body       .= "<ul>\n";
					foreach( $toReport as $report ) {
						$body .= "<li><a href=\"$report\">" . htmlspecialchars( $report ) . "</a></li>\n";
					}
					$body .= "</ul>";
					$mailObject->assignElement( "body", $body );
					$mailObject->assignAfterElement( "rooturl", ROOTURL );
					$mailObject->finalize();
					$subjectObject =
						new HTMLLoader( "{{{fpreportedsubject}}}", $result['language'], false, PUBLICHTML . "i18n/" );
					$subjectObject->finalize();
					mailHTML( $result['user_email'], $subjectObject->getLoadedTemplate(),
					          $mailObject->getLoadedTemplate(), true
					);
				}
			}
		}

		$watchDog['status'] = 'makingedits';
		DB::pingWatchDog( $watchDog );
		$archiveResponse = $checkResponse = $fetchResponse = null;
		unset( $archiveResponse, $checkResponse, $fetchResponse );
		echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: " .
		     ( memory_get_usage( true ) / 1048576 ) . " MB; Max System Memory Used: " .
		     ( memory_get_peak_usage( true ) / 1048576 ) . " MB\n";
		//Talk page stuff.  This part leaves a message on archives that failed to save on the wayback machine.
		if( !empty( $archiveProblems ) && $this->commObject->config['notify_error_on_talk'] == 1 ) {
			$out = "";
			foreach( $archiveProblems as $id => $problem ) {
				$magicwords            = [];
				$magicwords['problem'] = $problem;
				$magicwords['error']   = $errors[$id];
				$out                   .= "* " . $this->commObject->getConfigText( "plerror", $magicwords ) . "\n";
			}
			$body = $this->commObject->getConfigText( "talk_error_message", [ 'problematiclinks' => $out ] ) . "~~~~";
			API::edit( "Talk:{$this->commObject->page}", $body,
			           $this->commObject->getConfigText( "errortalkeditsummary", [] ), false, false, true, "new",
			           $this->commObject->getConfigText( "talk_error_message_header", [] )
			);
		}
		foreach( $modifiedLinks as $link ) {
			if( $link['type'] == "addarchive" ) {
				if( DataGenerator::getArchiveHost( $link['newarchive'], $data ) == "wayback" ) {
					$waybackadded++;
				} else $otheradded++;
			}
		}
		$pageModified = false;
		//This is the courtesy message left behind when it edits the main article.
		if( $this->commObject->content != $newtext ||
		    ( $this->commObject->config['notify_on_talk_only'] == 2 && !empty( $modifiedLinks ) ) ) {
			$pageModified                  = $this->commObject->content != $newtext && $rescued + $tagged !== 0;
			$magicwords                    = [];
			$magicwords['namespacepage']   = $this->commObject->page;
			$magicwords['linksmodified']   = $tagged + $rescued;
			$magicwords['linksrescued']    = $rescued;
			$magicwords['linksnotrescued'] = $notrescued;
			$magicwords['linkstagged']     = $tagged;
			$magicwords['linksarchived']   = $archived;
			$magicwords['linksanalyzed']   = $analyzed;
			if( defined( 'BOTLANGUAGE' ) ) {
				if( !isset( $locales[BOTLANGUAGE] ) &&
				    method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE ) ) {
					$tmp                           = "localize_" . BOTLANGUAGE;
					$magicwords['linksmodified']   =
						IABotLocalization::$tmp( (string) $magicwords['linksmodified'], false );
					$magicwords['linksrescued']    =
						IABotLocalization::$tmp( (string) $magicwords['linksrescued'], false );
					$magicwords['linksnotrescued'] =
						IABotLocalization::$tmp( (string) $magicwords['linksnotrescued'], false );
					$magicwords['linkstagged']     =
						IABotLocalization::$tmp( (string) $magicwords['linkstagged'], false );
					$magicwords['linksarchived']   =
						IABotLocalization::$tmp( (string) $magicwords['linksarchived'], false );
					$magicwords['linksanalyzed']   =
						IABotLocalization::$tmp( (string) $magicwords['linksanalyzed'], false );
				}
				if( method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE . "_extend" ) ) {
					$tmp                           = "localize_" . BOTLANGUAGE . "_extend";
					$magicwords['linksmodified']   = IABotLocalization::$tmp( $magicwords['linksmodified'], false );
					$magicwords['linksrescued']    = IABotLocalization::$tmp( $magicwords['linksrescued'], false );
					$magicwords['linksnotrescued'] = IABotLocalization::$tmp( $magicwords['linksnotrescued'], false );
					$magicwords['linkstagged']     = IABotLocalization::$tmp( $magicwords['linkstagged'], false );
					$magicwords['linksarchived']   = IABotLocalization::$tmp( $magicwords['linksarchived'], false );
					$magicwords['linksanalyzed']   = IABotLocalization::$tmp( $magicwords['linksanalyzed'], false );
				}
			}
			$magicwords['pageid']    = $this->commObject->pageid;
			$magicwords['title']     = urlencode( $this->commObject->page );
			$magicwords['logstatus'] = "fixed";
			// Make some adjustments for the message describing the changes.
			$addTalkOnly = false;
			if( $this->commObject->config['notify_on_talk_only'] == 2 && $this->leaveTalkOnly() == false &&
			    $pageModified ) {
				foreach( $modifiedLinks as $link ) {
					if( isset( $link['talkonly'] ) ) {
						$addTalkOnly = true;
						switch( $link['type'] ) {
							case "fix":
							case "modifyarchive":
								$rescued--;
							case "tagremoved":
							case "addarchive":
								$magicwords['linksmodified']--;
								$magicwords['linksrescued']--;
								break;
							case "tagged":
								$magicwords['linkstagged']--;
								break;
						}
					}
				}
			}
			if( ( $this->commObject->config['notify_on_talk_only'] == 0 ||
			      $this->commObject->config['notify_on_talk_only'] == 2 ) && $this->leaveTalkOnly() == false &&
			    $pageModified ) {
				$revid =
					API::edit( $this->commObject->page, $newtext,
					           $this->commObject->getConfigText( "maineditsummary", $magicwords ), false, $timestamp,
					           true, false, "", $editError
					);
			} else $magicwords['logstatus'] = "posted";
			if( isset( $revid ) ) {
				$magicwords['diff']  = str_replace( "api.php", "index.php", API ) . "?diff=prev&oldid=$revid";
				$magicwords['revid'] = $revid;
			} else {
				$magicwords['diff']  = "";
				$magicwords['revid'] = "";
			}
			if( ( ( $this->commObject->config['notify_on_talk'] == 1 && isset( $revid ) && $revid !== false ) ||
			      $this->commObject->config['notify_on_talk_only'] == 1 ||
			      $this->commObject->config['notify_on_talk_only'] == 2 || $this->leaveTalkOnly() == true ) &&
			    $this->leaveTalkMessage() == true
			) {
				for( $talkOnlyFlag = 0; $talkOnlyFlag <= (int) $addTalkOnly; $talkOnlyFlag++ ) {
					$out      = "";
					$editTalk = false;
					$talkOnly = $this->commObject->config['notify_on_talk_only'] == 1 || $this->leaveTalkOnly() ||
					            (bool) $talkOnlyFlag == true ||
					            ( $this->commObject->config['notify_on_talk_only'] == 2 && !$pageModified );
					if( (bool) $talkOnlyFlag === true ) {
						//Reverse the numbers
						$magicwords['linksmodified'] = $rescued + $tagged - $magicwords['linksmodified'];
						$magicwords['linksrescued']  = $rescued - $magicwords['linksrescued'];
						$magicwords['linkstagged']   = $tagged - $magicwords['linkstagged'];
					}
					foreach( $modifiedLinks as $tid => $link ) {
						if( isset( $link['talkonly'] ) && $talkOnly === false ) continue;
						if( (bool) $talkOnlyFlag === true && !isset( $link['talkonly'] ) ) continue;
						$magicwords2         = [];
						$magicwords2['link'] = $link['link'];
						if( isset( $link['oldarchive'] ) ) $magicwords2['oldarchive'] = $link['oldarchive'];
						if( isset( $link['newarchive'] ) ) $magicwords2['newarchive'] = $link['newarchive'];
						$tout = "*";
						switch( $link['type'] ) {
							case "addarchive":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mladdarchive",
									                                           $magicwords2
									);
								} else {
									$tout .= $this->commObject->getConfigText( "mladdarchivetalkonly", $magicwords2
									);
								}
								$editTalk = true;
								break;
							case "modifyarchive":
								if( $talkOnly === false ) {
									$tout     .= $this->commObject->getConfigText( "mlmodifyarchive", $magicwords2 );
									$editTalk = true;
								}
								break;
							case "fix":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mlfix", $magicwords2
									);
									if( $this->commObject->config['talk_message_verbose'] == 1 ) $editTalk = true;
								}
								break;
							case "tagged":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mltagged",
									                                           $magicwords2
									);
									if( $this->commObject->config['talk_message_verbose'] == 1 ) $editTalk = true;
								} else {
									$tout     .= $this->commObject->getConfigText( "mltaggedtalkonly", $magicwords2 );
									$editTalk = true;
								}
								break;
							case "tagremoved":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mltagremoved",
									                                           $magicwords2
									);
									if( $this->commObject->config['talk_message_verbose'] == 1 ) $editTalk = true;
								} else {
									$tout     .= $this->commObject->getConfigText( "mltagremovedtalkonly", $magicwords2
									);
									$editTalk = true;
								}
								break;
							default:
								if( $talkOnly === false ) {
									$tout     .= $this->commObject->getConfigText( "mldefault", $magicwords2 );
									$editTalk = true;
								}
								break;
						}
						$tout .= "\n";
						if( $talkOnly === true &&
						    !$this->commObject->db->setNotified( $tid )
						) {
							continue;
						} else {
							if( $tout != "*\n" ) $out .= $tout;
						}
					}
					$magicwords['modifiedlinks'] = $out;
					if( empty( $out ) ) $editTalk = false;
					if( $talkOnly === false && $this->commObject->config['notify_on_talk'] == 0 ) $editTalk = false;
					if( $talkOnly === false ) {
						$header =
							$this->commObject->getConfigText( "talk_message_header", $magicwords );
					} else $header = $this->commObject->getConfigText( "talk_message_header_talk_only", $magicwords );
					if( $talkOnly === false ) {
						$body =
							$this->commObject->getConfigText( "talk_message", $magicwords ) . "~~~~";
					} else $body = $this->commObject->getConfigText( "talk_message_talk_only", $magicwords ) . "~~~~";
					if( $editTalk === true ) {
						API::edit( "Talk:{$this->commObject->page}", $body,
						           $this->commObject->getConfigText( "talkeditsummary", $magicwords ),
						           false, false, true, "new", $header
						);
					}
				}
			}
			$this->commObject->logCentralAPI( $magicwords );
		}
		$this->commObject->db->updateDBValues();

		echo "\n";

		$newtext = $history = null;

		unset( $lastRevLinks, $lastRevTexts, $oldLinks );

		unset( $this->commObject, $newtext, $history, $res, $db );
		$returnArray = [
			'linksanalyzed' => $analyzed, 'linksarchived' => $archived, 'linksrescued' => $rescued,
			'linkstagged' => $tagged, 'pagemodified' => $pageModified, 'waybacksadded' => $waybackadded,
			'othersadded' => $otheradded, 'revid' => ( isset( $revid ) ? $revid : false )
		];

		$watchDog['status'] = 'done';
		DB::pingWatchDog( $watchDog );

		return $returnArray;
	}

	/**
	 * Fetch all links in an article
	 *
	 * @param bool $referenceOnly Fetch references only
	 * @param string $text Page text to analyze
	 *
	 * @access public
	 * @return array Details about every link on the page
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getExternalLinks( $referenceOnly = false, $text = false, $webRequest = false )
	{
		$linksAnalyzed = 0;
		$returnArray   = [];
		$toCheck       = [];
		if( IAVERBOSE ) echo "Parsing page text...\n";
		$parseData = $this->parseLinks( $referenceOnly, $text, $webRequest );
		if( IAVERBOSE ) echo "Finished parsing\n";
		if( $parseData === false ) return false;
		$lastLink    = [ 'tid' => null, 'id' => null ];
		$currentLink = [ 'tid' => null, 'id' => null ];
		//Run through each captured source from the parser
		if( IAVERBOSE ) echo "Processing " . count( $parseData ) . " objects...\n";
		foreach( $parseData as $tid => $parsed ) {
			if( IAVERBOSE ) echo "Processing parse object $tid\n";
			//If there's nothing to work with, move on.
			if( empty( $parsed['link_string'] ) && empty( $parsed['remainder'] ) ) continue;
			if( $parsed['type'] == "reference" && empty( $parsed['contains'] ) ) continue;

			$returnArray[$tid]['link_type'] = $parsed['type'];
			$returnArray[$tid]['string']    = $parsed['string'];
			if( $parsed['type'] == "reference" ) {
				$returnArray[$tid]['reference']['offset'] = $parsed['offset'];
				$returnArray[$tid]['reference']['open']   = $parsed['open'];
				$returnArray[$tid]['reference']['close']  = $parsed['close'];
				foreach( $parsed['contains'] as $parsedlink ) {
					$returnArray[$tid]['reference'][] = array_merge( $tmp =
						                                                 $this->getLinkDetails( $parsedlink['link_string'],
						                                                                        $parsedlink['remainder'] .
						                                                                        $parsed['remainder']
						                                                 ), [ 'string' => isset( $tmp['ignore'] ) ?
						                                                                                                                           $parsedlink['string'] :
						                                                                                                                           $tmp['link_string'] .
						                                                                                                                           $tmp['remainder'],
					                                                          'offset' => $parsedlink['offset']
					                                                                                                                           ]
					);
				}
				$tArray = array_merge( $this->commObject->config['deadlink_tags'],
				                       $this->commObject->config['ignore_tags'],
				                       $this->commObject->config['paywall_tags'],
				                       $this->commObject->config['archive_tags']
				);
				$regex  = DataGenerator::fetchTemplateRegex( $tArray, true );
				if( count( $parsed['contains'] ) == 1 && !isset( $returnArray[$tid]['reference'][0]['ignore'] ) &&
				    empty( trim( preg_replace( $regex, "",
				                               str_replace( $parsed['contains'][0]['link_string'], "",
				                                            $parsed['link_string']
				                               )
				                 )
				    )
				    ) ) {
					$returnArray[$tid]['reference'][0]['converttocite'] = true;
				}
			} else {
				$returnArray[$tid][$parsed['type']] =
					array_merge( $tmp = $this->getLinkDetails( $parsed['link_string'], $parsed['remainder'] ),
					             [ 'string' => isset( $tmp['ignore'] ) ? $parsed['string'] :
						             $tmp['link_string'] . $tmp['remainder'], 'offset' => $parsed['offset']
					             ]
					);
			}
			if( IAVERBOSE ) echo "Extracted details for object $tid\n";
			if( $parsed['type'] == "reference" ) {
				$returnArray[$tid]['reference']['link_string'] = $parsed['link_string'];
			}
			if( $parsed['type'] == "template" ) {
				$returnArray[$tid]['template']['name'] = $parsed['name'];
			}
			if( !isset( $returnArray[$tid][$parsed['type']]['ignore'] ) ||
			    $returnArray[$tid][$parsed['type']]['ignore'] === false
			) {
				if( $parsed['type'] == "reference" ) {
					//In instances where the main function runs through references, it uses a while loop incrementing the id by 1.
					//Gaps in the indexes, ie a missing index 2 for example, will cause the while loop to prematurely stop.
					//We fix this by not allowing gaps like this to happen.
					if( IAVERBOSE ) echo "Object $tid is a reference\n";
					$indexOffset = 0;
					foreach( $returnArray[$tid]['reference'] as $id => $link ) {
						if( IAVERBOSE ) echo "\t$tid: Processing reference object $id\n";
						if( !is_int( $id ) || isset( $link['ignore'] ) ) {
							//This will create a gap, so increment the offset.
							if( is_int( $id ) && $id !== 0 ) unset( $returnArray[$tid]['reference'][$id] );
							if( is_int( $id ) ) $indexOffset++;
							continue;
						}
						$currentLink['tid'] = $tid;
						//Compensate for skipped indexes.
						$currentLink['id'] = $id;
						//Check if the neighboring source has some kind of connection to each other.
						if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
							if( IAVERBOSE ) echo "\t$tid: Current and previous objects are connected\n";
							unset( $returnArray[$tid]['reference'][$id] );
							//If so, update $toCheck at the respective index, with the new information.
							$toCheck["{$lastLink['tid']}:{$lastLink['id']}"] =
								$returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
							$indexOffset++;
							if( $text ===
							    false ) {
								if( IAVERBOSE ) echo "\t$tid: Reloading DB values for ref object {$lastLink['id']}\n";
								$this->commObject->db->retrieveDBValues( $returnArray[$lastLink['tid']]['reference'][$lastLink['id']],
								                                         "{$lastLink['tid']}:{$lastLink['id']}"
								);
							}
							continue;
						}
						$linksAnalyzed++;
						//Load respective DB values into the active cache.
						if( $text ===
						    false ) {
							if( IAVERBOSE ) echo "\t$tid: Loading DB values for ref object $id\n";
							$this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'][$id],
							                                         "$tid:" . ( $id - $indexOffset )
							);
						}
						$toCheck["$tid:" . ( $id - $indexOffset )] = $returnArray[$tid]['reference'][$id];
						$lastLink['tid']                           = $tid;
						$lastLink['id']                            = $id - $indexOffset;
						if( $indexOffset !== 0 ) {
							$returnArray[$tid]['reference'][$id - $indexOffset] = $returnArray[$tid]['reference'][$id];
							unset( $returnArray[$tid]['reference'][$id] );
						}
					}
				} else {
					$currentLink['tid'] = $tid;
					$currentLink['id']  = null;
					//Check if the neighboring source has some kind of connection to each other.
					if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
						if( IAVERBOSE ) echo "Current and previous objects are connected\n";
						$returnArray[$lastLink['tid']]['string'] =
							$returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']]['string'];
						$toCheck[$lastLink['tid']]               =
							$returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']];
						if( $text ===
						    false ) {
							if( IAVERBOSE ) echo "Reloading DB values for object {$lastLink['tid']}\n";
							$this->commObject->db->retrieveDBValues( $returnArray[$lastLink['tid']][$parsed['type']],
							                                         $lastLink['tid']
							);
						}
						continue;
					}
					$linksAnalyzed++;
					//Load respective DB values into the active cache.
					if( $text === false ) {
						if( IAVERBOSE ) echo "Loading DB values for object $tid\n";
						$this->commObject->db->retrieveDBValues( $returnArray[$tid][$parsed['type']],
						                                         $tid
						);
					}
					$toCheck[$tid]   = $returnArray[$tid][$parsed['type']];
					$lastLink['tid'] = $tid;
					$lastLink['id']  = null;
				}
			}
		}
		//Retrieve missing access times that couldn't be extrapolated from the parser.
		if( IAVERBOSE ) echo "Checking link access times...\n";
		if( $text === false ) $toCheck = $this->updateAccessTimes( $toCheck );
		//Set the live states of all the URL, and run a dead check if enabled.
		if( IAVERBOSE ) echo "Updating link states...\n";
		if( $text === false ) $toCheck = $this->updateLinkInfo( $toCheck );
		//Transfer data back to the return array.
		if( IAVERBOSE ) echo "Cleaning up objects...\n";
		foreach( $toCheck as $tid => $link ) {
			if( is_int( $tid ) ) {
				$returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
			} else {
				$tid                                                               = explode( ":", $tid );
				$returnArray[$tid[0]][$returnArray[$tid[0]]['link_type']][$tid[1]] = $link;
			}
		}
		$returnArray['count'] = $linksAnalyzed;

		if( IAVERBOSE ) echo "Analyzed $linksAnalyzed links\n";

		return $returnArray;
	}

	/**
	 * Parses the pages for references, citation templates, and bare links.
	 *
	 * @param bool $referenceOnly
	 * @param string $text Page text to analyze
	 * @param bool $webRequest Return false is analysis exceeds 300 parsed elements
	 *
	 * @access public
	 * @return array All parsed links
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function parseLinks( $referenceOnly = false, $text = false, $webRequest = false )
	{
		$returnArray = [];

		if( $text === false ) {
			$pageText = $this->commObject->content;
		} else $pageText = $text;

		if( IAVERBOSE ) {
			if( $text ) {
				echo "Processing custom input:\n\t" .
				     preg_replace( '/(?:(\<\!\s*)--|--(\s*\>))/', '$1- -$2', $text ) . "\n";
			} else echo "Processing page text\n";

			echo "Text size: " . strlen( $pageText ) . " Bytes\n";
		}

		//Set scan needle to the beginning of the string
		$pos            = 0;
		$offsets        = [];
		$startingOffset = false;

		while( ( $startingOffset =
				$this->parseUpdateOffsets( $pageText, $pos, $offsets, $startingOffset, $referenceOnly ) ) &&
		       ( $webRequest === false || ( $webRequest === true && count( $returnArray ) < 301 ) ) ) {
			unset( $start, $startOffset, $end );
			$subArray = [];

			if( IAVERBOSE ) {
				echo "Processing offset $pos\n";
			}

			switch( $startingOffset ) {
				case "{{":
					if( isset( $offsets['__CITE__'] ) ) {
						if( $offsets['__CITE__'][1] == $offsets['{{'] ) {
							$subArray['type'] = "template";
							$subArray['name'] = trim( substr( $pageText, $offsets['__CITE__'][1] + 2,
							                                  strpos( $pageText, "|", $offsets['__CITE__'][1] ) -
							                                  $offsets['__CITE__'][1] - 2
							                          )
							);
							$startOffset      = $start = $offsets['__CITE__'][1];
							$end              = $offsets['/__CITE__'][1];
							$pos              = $offsets['}}'] + 2;
							break;
						} else {
							$tmp = $this->parseLinks( false, substr( $pageText, $offsets['{{'] + 2,
							                                         $offsets['}}'] - 2 - $offsets['{{']
							                               )
							);
							foreach( $tmp as $ptmp ) {
								if( $ptmp['type'] == "template" || $ptmp['type'] == "reference" ) {
									$ptmp['offset'] = $ptmp['offset'] + $offsets['{{'] + 2;
									$returnArray[]  = $ptmp;
								}
							}
						}
					}
					$pos = $offsets['}}'] + 2;
					continue 2;
				case "[[":
					$pos = $offsets[']]'] + 2;
					continue 2;
				case "[":
					$pos = $end = $offsets[']'] + 1;
					if( isset( $offsets['__SCHEMELESSURL__'] ) ) {
						if( $offsets['__SCHEMELESSURL__'][1] == $offsets['['] + 1 ) {
							$startOffset      = $start = $offsets['['];
							$subArray['type'] = "externallink";
							break;
						}
					}
					if( isset( $offsets['{{'] ) ) {
						if( $offsets['{{'] == $offsets['['] + 1 && $offsets['}}'] <= $end ) {
							if( isset( $offsets['__REMAINDERA__'] ) &&
							    $offsets['__REMAINDERA__'][1] == $offsets['{{'] ) {
								$subArray['remainder'] = substr( $pageText, $offsets['__REMAINDERA__'][1],
								                                 $offsets['/__REMAINDERA__'][1] -
								                                 $offsets['__REMAINDERA__'][1]
								);
							}
							$startOffset      = $start = $offsets['['];
							$subArray['type'] = "externallink";
							break;
						}
					}
					$pos = $offsets['['] + 1;
					continue 2;
				case "__CITE__":
					$subArray['type'] = "template";
					$subArray['name'] = trim( substr( $pageText, $offsets['__CITE__'][1] + 2,
					                                  strpos( $pageText, "|", $offsets['__CITE__'][1] ) -
					                                  $offsets['__CITE__'][1] - 2
					                          )
					);
					$startOffset      = $start = $offsets['__CITE__'][1];
					if( $startOffset == $offsets['{{'] ) {
						$pos = $end = $offsets['}}'] + 2;
					} else $pos = $end = $offsets['/__CITE__'][1];
					break;
				case "__URL__":
					$startOffset = $start = $offsets['__URL__'][1];
					$pos         = $end = $offsets['/__URL__'][1];

					$junk = [];
					if( preg_match( '/\<.*$/', substr( $pageText, $start, $end - $start ), $junk[0], PREG_OFFSET_CAPTURE
					    ) &&
					    preg_match( '/\<.*?>/', substr( $pageText, $start ), $junk[1], PREG_OFFSET_CAPTURE,
					                $junk[0][0][1]
					    ) ) {
						if( $junk[0][0][1] === $junk[1][0][1] ) $end = $pos = $junk[0][0][1] + $start;
					}
					while( preg_match( '/[\.\,\:\;\?\!\)\"\>\<\[\]\\\\]/i',
					                   $char = substr( $pageText, $end - 1, 1 )
					) ) {
						if( $char == ")" ) {
							if( strpos( substr( $pageText, $start, $end - $start ), "(" ) !== false ) {
								break;
							}
						}
						$end--;
						$pos--;
					}
					if( isset( $offsets['{{'] ) && $offsets['{{'] <= $end ) {
						if( isset( $offsets['__REMAINDERA__'] ) && $offsets['__REMAINDERA__'][1] == $offsets['{{'] ) {
							$pos = $end = $offsets['{{'];
						} else {
							$pos = $end = $offsets['}}'] + 2;
						}
					}
					$subArray['type'] = "externallink";
					break;
				case "__REF__":
					$startOffset          = $offsets['__REF__'][1];
					$start                = $offsets['__REF__'][1] + $offsets['__REF__'][2];
					$end                  = $offsets['/__REF__'][1];
					$pos                  = $offsets['/__REF__'][1] + $offsets['/__REF__'][2];
					$subArray['type']     = "reference";
					$subArray['contains'] =
						$this->parseLinks( false, substr( $pageText, $start, $end - $start ) );
					$subArray['open']     = substr( $pageText, $offsets['__REF__'][1], $offsets['__REF__'][2] );
					$subArray['close']    = substr( $pageText, $offsets['/__REF__'][1], $offsets['/__REF__'][2] );

					/*
					if( $text === false && $subArray['open'] !== "<ref>" && substr( $subArray['open'], 0, 1 ) == "<" ) {
						$this->commObject->content = $pageText =
							DataGenerator::str_replace( substr( $pageText, $startOffset, $pos - $startOffset ),
							                            str_replace( ">", "/>", $subArray['open'] ), $pageText,
							                            $replacements, -1, $pos
							);

						if( $replacements ) {
							if( IAVERBOSE ) echo "Transformed $replacements identical named references into self closing references\n";

							//We need to recalculate offsets
							$offsets = [];
						}
					}*/
					break;
				case "__REMAINDERS__":
				case "__REMAINDERA__":
					$startOffset      = $start = $offsets[$startingOffset][1];
					$end              = $pos = $offsets["/$startingOffset"][1];
					$subArray['type'] = "stray";
					break;
				default:
					if( !is_string( $offsets[$startingOffset][0] ) && $offsets[$startingOffset][0][0] == "html" ) {
						$pos =
							$offsets[$offsets[$startingOffset][0][2]][1] + $offsets[$offsets[$startingOffset][0][2]][2];
					} else {
						$pos = $offsets["/$startingOffset"][1] + $offsets["/$startingOffset"][2];
					}
					continue 2;
			}

			if( !in_array( $startingOffset, [ '__REMAINDERA__', '__REMAINDERS__' ] ) ) {
				$subArray['link_string'] =
					substr( $pageText, $start, $end - $start );
			} else $subArray['remainder'] = substr( $pageText, $start, $end - $start );
			$subArray['offset'] = $startOffset;
			$subArray['string'] = substr( $pageText, $startOffset, $pos - $startOffset );

			if( !in_array( $startingOffset, [ '__REMAINDERA__', '__REMAINDERS__' ] ) &&
			    $this->parseGetNextOffset( $pos, $offsets, $pageText, $referenceOnly ) == "__REMAINDERA__" ) {
				$inBetween = substr( $pageText, $pos, $offsets['__REMAINDERA__'][1] - $pos );

				if( $startingOffset == "__REF__" && preg_match( '/^\s*?$/', $inBetween ) ) {
					$start                 = $pos;
					$end                   = $pos = $offsets['/__REMAINDERA__'][1];
					$subArray['remainder'] = substr( $pageText, $start, $end - $start );
				} elseif( strpos( $inBetween, "\n\n" ) === false && strlen( $inBetween ) < 100 &&
				          ( ( strpos( $inBetween, "\n" ) === false &&
				              strpos( strtolower( $inBetween ), "<br" ) === false ) ||
				            !preg_match( '/\S/i', $inBetween ) ) ) {
					$start                 = $pos;
					$end                   = $pos = $offsets['/__REMAINDERA__'][1];
					$subArray['remainder'] = substr( $pageText, $start, $end - $start );
				} else $subArray['remainder'] = "";

				$subArray['string'] .= $subArray['remainder'];

			} elseif( !isset( $subArray['remainder'] ) ) {
				$subArray['remainder'] = "";
			}

			if( !isset( $subArray['link_string'] ) ) $subArray['link_string'] = "";

			$returnArray[] = $subArray;
		}

		if( $webRequest === true && count( $returnArray ) > 300 ) return false;

		return $returnArray;
	}

	private function parseUpdateOffsets( $pageText, $pos = 0, &$offsets = [], $lastOne = false, $referenceOnly = false,
	                                     $addedItems = []
	) {
		if( count( debug_backtrace() ) > 100 ) {
			echo "WOAH!!! Catastrophic recursion detected.  Exiting out and leaving a backtrace!!\n";
			debug_print_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
			print_r( get_defined_vars() );
			//return false;
		}

		$additionalItems = $addedItems;

		//Set exclusion items
		$exclude = [
			[ 'html', '<!--', '-->' ], [ 'element', 'nowiki' ], [ 'element', 'pre' ], [ 'element', 'source' ],
			[ 'element', 'syntaxhighlight' ], [ 'element', 'code' ], [ 'element', 'math' ]
		];
		//Set inclusion items
		$include = array_merge( [ [ 'element', 'ref' ] ], $this->commObject->config['ref_bounds'] );
		//Set bracket items
		$doubleOpen  = array_search( '[[', $additionalItems );
		$doubleClose = array_search( ']]', $additionalItems );
		if( $doubleOpen !== false || $doubleClose !== false ) {
			$brackets = [ [ '{{', '}}' ], [ '[[', ']]' ], [ '[', ']', ] ];
			if( $doubleOpen !== false ) unset( $additionalItems[$doubleOpen] );
			if( $doubleClose !== false ) unset( $additionalItems[$doubleClose] );
		} else {
			$brackets = [ [ '{{', '}}' ], [ '[', ']', ] ];
		}
		//Set conflicting brackets
		$conflictingBrackets = [ [ '[', '[[' ], [ ']', ']]' ] ];

		//Set nested brackets array
		$inside = [];

		$skipAhead = [];

		if( empty( $offsets ) ) {

			$numericalOffsets = [];

			$tArray        =
				array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
				             $this->commObject->config['ignore_tags'],
				             $this->commObject->config['paywall_tags']
				);
			$tArrayAppend  =
				array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['aarchive_tags'],
				             $this->commObject->config['ignore_tags'],
				             $this->commObject->config['paywall_tags']
				);
			$tArraySwallow =
				array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['sarchive_tags'],
				             $this->commObject->config['ignore_tags'],
				             $this->commObject->config['paywall_tags']
				);
			//This is a giant regex to capture citation tags and the other tags that follow it.
			$regex           = DataGenerator::fetchTemplateRegex( $this->commObject->config['citation_tags'], false );
			$remainderRegex  =
				substr_replace( substr_replace( DataGenerator::fetchTemplateRegex( $tArray, true ), '/(?:', 0, 1 ),
				                ')+/i', -2, 2
				);
			$remainderRegexS =
				substr_replace( substr_replace( DataGenerator::fetchTemplateRegex( $tArraySwallow, true ), '/(?:', 0, 1
				                ),
				                ')+/i', -2, 2
				);
			$remainderRegexA =
				substr_replace( substr_replace( DataGenerator::fetchTemplateRegex( $tArrayAppend, true ), '/(?:', 0, 1
				                ),
				                ')+/i', -2, 2
				);

			$elementRegexComponent       = "";
			$templateStartRegexComponent = "";
			$templateEndRegexComponent   = "";
			foreach( $include as $includeItem ) {
				if( $includeItem[0] == "element" ) {
					if( !empty( $elementRegexComponent ) ) $elementRegexComponent .= "|";
					$elementRegexComponent .= $includeItem[1];
				} elseif( $includeItem[0] == "template" ) {
					if( !empty( $templateStartRegexComponent ) ) $templateStartRegexComponent .= "|";
					if( !empty( $templateEndRegexComponent ) ) $templateEndRegexComponent .= "|";

					$templateStartRegexComponent .= '(\{\{[\s\n]*(' . implode( '|', $includeItem[1] ) .
					                                ')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)?\}\})';
					$templateEndRegexComponent   .= '(\{\{[\s\n]*(' .
					                                str_replace( "\{\{", "\{\{\s*",
					                                             str_replace( "\}\}", "", implode( '|',
					                                                                               $includeItem[2]
					                                                                )
					                                             )
					                                ) . ')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)?\}\})';
				}
			}
			if( !empty( $elementRegexComponent ) ) {
				$elementOpenRegex  = '<(?:' . $elementRegexComponent . ')(\s+.*?)?(?<selfclosing>\/)?\s*>';
				$elementCloseRegex = '<\/' . $elementRegexComponent . '\s*?>';
			}
			if( !empty( $elementOpenRegex ) &&
			    ( !empty( $templateStartRegexComponent ) && !empty( $templateEndRegexComponent ) ) ) {
				$refStartRegex = '(?:' . $elementOpenRegex . '|' . $templateStartRegexComponent . ')';
				$refEndRegex   = '(?:' . $elementCloseRegex . '|' . $templateEndRegexComponent . ')';
			} elseif( !empty( $templateStartRegexComponent ) && !empty( $templateEndRegexComponent ) ) {
				$refStartRegex = $templateStartRegexComponent;
				$refEndRegex   = $templateEndRegexComponent;
			} elseif( !empty( $elementOpenRegex ) ) {
				$refStartRegex = $elementOpenRegex;
				$refEndRegex   = $elementCloseRegex;
			}

			//Let's start collecting offsets.

			//Let's collect all of the elements we are excluding from processing
			foreach( $exclude as $excludedItem ) {
				unset( $tOffset2, $tOffset, $tLngth );
				//do {
				if( isset( $tOffset ) && isset( $tOffset2 ) ) {
					unset( $inside[$tOffset], $inside[$tOffset2] );
					$tOffset = $tOffset2 + 1;

				}
				if( $excludedItem[0] == "html" ) {
					if( !isset( $tOffset ) ) $tOffset = $pos;
					do {
						$tOffset = strpos( $pageText, $excludedItem[1], $tOffset );
					} while( $tOffset !== false && isset( $inside[$tOffset] ) );

					$tOffset2 = $tOffset;
					if( $tOffset !== false ) {
						do {
							$tOffset2 = strpos( $pageText, $excludedItem[2], $tOffset2 );
						} while( $tOffset2 !== false && isset( $inside[$tOffset2] ) );
					}

					if( $tOffset2 !== false ) {
						$offsets[$excludedItem[1]] = [ $excludedItem, $tOffset, strlen( $excludedItem[1] ) ];
						$offsets[$excludedItem[2]] = [ $excludedItem, $tOffset2, strlen( $excludedItem[2] ) ];
						$inside[$tOffset]          = $excludedItem[1];
						$inside[$tOffset2]         = $excludedItem[2];
						$skipAhead[$tOffset]       = $tOffset2 + strlen( $excludedItem[2] );
					}

					while( $tOffset2 !== false ) {
						$tOffset = $tOffset2 + strlen( $excludedItem[2] );
						do {
							$tOffset = strpos( $pageText, $excludedItem[1], $tOffset );
						} while( $tOffset !== false && isset( $inside[$tOffset] ) );

						$tOffset2 = $tOffset;
						if( $tOffset !== false ) {
							do {
								$tOffset2 = strpos( $pageText, $excludedItem[2], $tOffset2 );
							} while( $tOffset2 !== false && isset( $inside[$tOffset2] ) );
						}

						if( $tOffset2 !== false ) {
							$skipAhead[$tOffset] = $tOffset2 + strlen( $excludedItem[2] );
						}
					}
				} elseif( $excludedItem[0] == "element" ) {
					$elementOpenRegex  = '<(?:' . $excludedItem[1] . ')(\s+.*?)?(?<selfclosing>\/)?\s*>';
					$elementCloseRegex = '<\/' . $excludedItem[1] . '\s*?>';
					$tOffset           = $pos;
					while( preg_match( '/' . $elementOpenRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE, $tOffset
					) ) {
						$tOffset = $junk[0][1];
						$tLngth  = strlen( $junk[0][0] );
						if( !empty( $junk['selfclosing'] ) ) {
							$skipAhead[$tOffset] = $tOffset + $tLngth;
							$tOffset             += $tLngth;
							continue;
						}
						if( preg_match( '/' . $elementCloseRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE, $tOffset
						) ) {
							$tOffset2                        = $junk[0][1];
							$tLngth2                         = strlen( $junk[0][0] );
							$offsets[$excludedItem[1]]       = [ $excludedItem, $tOffset, $tLngth ];
							$offsets['/' . $excludedItem[1]] = [ $excludedItem, $tOffset2, $tLngth2 ];
							$inside[$tOffset]                = $excludedItem[1];
							$inside[$tOffset2]               = '/' . $excludedItem[1];
							$skipAhead[$tOffset]             = $tOffset2 + $tLngth2;
						}
						break;
					}

					while( isset( $tOffset2 ) && $tOffset2 !== false ) {
						$tOffset = $tOffset2 + $tLngth2;
						unset( $tOffset2, $tLngth2 );
						while( preg_match( '/' . $elementOpenRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE,
						                   $tOffset
						) ) {
							$tOffset = $junk[0][1];
							$tLngth  = strlen( $junk[0][0] );
							if( !empty( $junk['selfclosing'] ) ) {
								$skipAhead[$tOffset] = $tOffset + $tLngth;
								$tOffset             += $tLngth;
								continue;
							}
							if( preg_match( '/' . $elementCloseRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE,
							                $tOffset
							) ) {
								$tOffset2            = $junk[0][1];
								$tLngth2             = strlen( $junk[0][0] );
								$skipAhead[$tOffset] = $tOffset2 + $tLngth2;
							}
							$tOffset = max( $tOffset, $tOffset2 ) + 1;
						}
					}
					unset( $tOffset2, $tLngth2 );
				}
				//} while( !$this->parseValidateOffsets( $inside, $brackets, $exclude ) );
			}

			$tOffset = $pos;
			//Collect the offsets of the next reference
			while( preg_match( '/' . $refStartRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE, $tOffset ) ) {
				$tOffset = $junk[0][1];
				$tLngth  = strlen( $junk[0][0] );
				if( !empty( $junk['selfclosing'] ) ) {
					$skipAhead[$tOffset] = $tOffset + $tLngth;
					$tOffset             += $tLngth;
					continue;
				}
				if( preg_match( '/' . $refEndRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE, $tOffset ) ) {
					$tOffset2            = $junk[0][1];
					$tLngth2             = strlen( $junk[0][0] );
					$offsets['__REF__']  = [ $refStartRegex, $tOffset, $tLngth ];
					$offsets['/__REF__'] = [ $refEndRegex, $tOffset2, $tLngth2 ];
					$inside[$tOffset]    = '__REF__';
					$inside[$junk[0][1]] = '/__REF__';
				}
				break;
			}

			while( isset( $tOffset2 ) && $tOffset2 !== false ) {
				$tOffset = $tOffset2 + $tLngth2;
				unset( $tOffset2, $tLngth2 );
				while( preg_match( '/' . $refStartRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE,
				                   $tOffset
				) ) {
					$tOffset = $junk[0][1];
					$tLngth  = strlen( $junk[0][0] );
					if( !empty( $junk['selfclosing'][0] ) ) {
						$skipAhead[$tOffset] = $tOffset + $tLngth;
						$tOffset             += $tLngth;
						continue;
					}

					$tOffset++;
				}
			}

			unset( $tOffset2, $tLngth2 );

			ksort( $skipAhead );

			if( !empty( $skipAhead ) ) $offsets['__SKIP__'] = $skipAhead;

			//Collect offsets of wiki brackets "[] [[]] {{}}"
			$offsets = array_merge( $offsets,
			                        $this->parseGetBrackets( $pageText, $brackets, $conflictingBrackets, $exclude, $pos,
			                                                 $inside, false, $skipAhead
			                        )
			);

			$regexes = [
				'__CITE__' => $regex,             //Match giant regex for the presence of a citation template.
				//'__REMAINDER__'  => $remainderRegex,    //Match for the presence of an archive template
				'__REMAINDERS__' => $remainderRegexS,   //Match for only templates that swallow
				'__REMAINDERA__' => $remainderRegexA,   //Match for only templates that append
				'__URL__' => '/' . $this->schemedURLRegex . '/i',   //Match for the presence of a bare URL
				'__SCHEMELESSURL__' => '/' . $this->schemelessURLRegex . '/i' //This is for bracketed URLs
			];

			//Collect cite template, remainder body, and URL offsets
			if( empty( $additionalItems ) ) {
				foreach( $regexes as $index => $iteratedRegex ) {
					reset( $skipAhead );
					if( !empty( $skipAhead ) ) {
						foreach( $skipAhead as $skipStart => $skipEnd ) {
							if( $pos < $skipEnd ) break;
						}
					} else $skipStart = $skipEnd = false;
					$tOffset = $pos;
					while( preg_match( $iteratedRegex, $pageText, $junk, PREG_OFFSET_CAPTURE, $tOffset ) ) {
						$tOffset  = $junk[0][1];
						$tOffset2 = $junk[0][1] + strlen( $junk[0][0] );
						while( $skipEnd !== false && $tOffset >= $skipEnd ) {
							$skipEnd = next( $skipAhead );
							if( $skipEnd === false ) {
								$skipStart = false;
								break;
							}
							if( $skipEnd < $tOffset ) {
								continue;
							}
							$skipStart = key( $skipAhead );
						}
						if( $skipStart !== false && $tOffset >= $skipStart ) {
							$tOffset = $skipEnd;
							continue;
						}
						while( $skipEnd !== false && $tOffset2 >= $skipEnd ) {
							$skipEnd = next( $skipAhead );
							if( $skipEnd === false ) {
								$skipStart = false;
								break;
							}
							if( $skipEnd < $tOffset2 ) continue;
							$skipStart = key( $skipAhead );
						}

						if( $skipStart !== false && $tOffset2 > $skipStart ) {
							$tOffset = $skipEnd;
							continue;
						}

						$offsets[$index]    = [ $iteratedRegex, $tOffset ];
						$offsets["/$index"] = [ $iteratedRegex, $tOffset2 ];
						$inside[$tOffset]   = $index;
						$inside[$tOffset2]  = "/$index";

						break;
					}
				}
			}

			foreach( $additionalItems as $item ) {
				$offsets[$item] = strpos( $pageText, $item, $pos );
				if( $offsets[$item] === false ) unset( $offsets[$item] );
			}
		} else {
			if( $lastOne !== false ) {
				$offsetIndex = $lastOne;
			} else {
				$offsetIndex = $this->parseGetNextOffset( 0, $offsets, $pageText, $referenceOnly );
			}

			if( isset( $offsets['__SKIP__'] ) ) {
				$skipAhead = $offsets['__SKIP__'];
			} else $skipAhead = [];

			if( isset( $offsets[$offsetIndex] ) ) switch( $offsetIndex ) {
				case "[":
				case "[[":
				case "{{":
					foreach( $brackets as $subBracket ) {
						if( $offsetIndex ==
						    $subBracket[0] ) {
							unset( $offsets[$subBracket[0]], $offsets[$subBracket[1]] );
						}
					}
					$offsets = array_replace( $offsets,
					                          $this->parseGetBrackets( $pageText, $brackets, $conflictingBrackets,
					                                                   $exclude, $pos, $inside, $offsetIndex, $skipAhead
					                          )
					);
					break;
				case "__CITE__":
				case "__URL__":
				case "__SCHEMELESSURL__":
				case "__REMAINDERA__":
				case "__REMAINDERS__":
					if( !empty( $skipAhead ) ) {
						foreach( $skipAhead as $skipStart => $skipEnd ) {
							if( $pos >= $skipStart || $pos <= $skipEnd ) break;
						}
					} else $skipStart = $skipEnd = false;
					$tOffset = $pos;
					while( $matched =
						preg_match( $offsets[$offsetIndex][0], $pageText, $junk, PREG_OFFSET_CAPTURE, $tOffset ) ) {
						$tOffset  = $junk[0][1];
						$tOffset2 = $junk[0][1] + strlen( $junk[0][0] );
						while( $skipEnd !== false && $tOffset >= $skipEnd ) {
							$skipEnd = next( $skipAhead );
							if( $skipEnd === false ) {
								$skipStart = false;
								break;
							}
							if( $skipEnd < $tOffset ) {
								continue;
							}
							$skipStart = key( $skipAhead );
						}
						if( $skipStart !== false && $tOffset >= $skipStart ) {
							$tOffset = $skipEnd;
							continue;
						}
						while( $skipEnd !== false && $tOffset2 >= $skipEnd ) {
							$skipEnd = next( $skipAhead );
							if( $skipEnd === false ) {
								$skipStart = false;
								break;
							}
							if( $skipEnd < $tOffset2 ) continue;
							$skipStart = key( $skipAhead );
						}

						if( $skipStart !== false && $tOffset2 >= $skipStart ) {
							$tOffset = $skipEnd;
							continue;
						}

						$offsets[$offsetIndex][1]    = $tOffset;
						$offsets["/$offsetIndex"][1] = $tOffset2;
						$inside[$tOffset]            = $offsetIndex;
						$inside[$tOffset2]           = "/$offsetIndex";
						break;
					}
					if( !$matched ) {
						unset( $offsets[$offsetIndex], $offsets["/$offsetIndex"] );
					}
					break;
				default:
					if( !in_array( $offsetIndex, $additionalItems ) ) {
						if( is_string( $offsets[$offsetIndex][0] ) ) {
							$tOffset = $pos;
							while( $matched = preg_match( '/' . $offsets[$offsetIndex][0] . '/i', $pageText, $junk,
							                              PREG_OFFSET_CAPTURE,
							                              $tOffset
							) ) {
								$tOffset = $junk[0][1];
								$tLngth  = strlen( $junk[0][0] );
								if( !empty( $junk['selfclosing'][0] ) ) {
									$tOffset += $tLngth;
									continue;
								}
								if( preg_match( '/' . $offsets["/$offsetIndex"][0] . '/i', $pageText, $junk,
								                PREG_OFFSET_CAPTURE, $tOffset + $tLngth
								) ) {
									$offsets[$offsetIndex][1]    = $tOffset;
									$offsets[$offsetIndex][2]    = $tLngth;
									$offsets["/$offsetIndex"][1] = $junk[0][1];
									$offsets["/$offsetIndex"][2] = strlen( $junk[0][0] );
									$inside[$tOffset]            = $offsetIndex;
									$inside[$junk[0][1]]         = "/$offsetIndex";
								} else {
									unset( $offsets[$offsetIndex], $offsets["/$offsetIndex"] );
								}
								break;
							}

							if( !$matched ) {
								unset( $offsets[$offsetIndex], $offsets["/$offsetIndex"] );
							}
						} else {
							if( $offsets[$offsetIndex][0][0] == "html" ) {
								$tOffset = $pos;
								do {
									$tOffset = strpos( $pageText, $offsets[$offsetIndex][0][1], $tOffset );
								} while( $tOffset !== false && isset( $inside[$tOffset] ) );

								$tOffset2 = $tOffset;
								if( $tOffset !== false ) {
									do {
										$tOffset2 = strpos( $pageText, $offsets[$offsetIndex][0][2], $tOffset2 );
									} while( $tOffset2 !== false && isset( $inside[$tOffset2] ) );
								}

								if( $tOffset2 !== false ) {
									$offsets[$offsets[$offsetIndex][0][1]][1] = $tOffset;
									$offsets[$offsets[$offsetIndex][0][1]][2] = strlen( $offsets[$offsetIndex][0][1] );
									$offsets[$offsets[$offsetIndex][0][2]][1] = $tOffset2;
									$offsets[$offsets[$offsetIndex][0][2]][2] = strlen( $offsets[$offsetIndex][0][2] );
									$inside[$tOffset]                         = $offsets[$offsetIndex][0][1];
									$inside[$tOffset2]                        = $offsets[$offsetIndex][0][2];
								} else {
									unset( $offsets[$offsets[$offsetIndex][0][2]], $offsets[$offsets[$offsetIndex][0][1]] );
								}
							} elseif( $offsets[$offsetIndex][0][0] == "element" ) {
								$elementOpenRegex  = '<(?:' . $offsets[$offsetIndex][0][1] . ')(\s+.*?)?(\/)?\s*>';
								$elementCloseRegex = '<\/' . $offsets[$offsetIndex][0][1] . '\s*?>';
								if( preg_match( '/' . $elementOpenRegex . '/i', $pageText, $junk, PREG_OFFSET_CAPTURE,
								                $pos
								) ) {
									$tOffset = $junk[0][1];
									$tLngth  = strlen( $junk[0][0] );
									if( preg_match( '/' . $elementCloseRegex . '/i', $pageText, $junk,
									                PREG_OFFSET_CAPTURE, $tOffset
									) ) {
										$offsets[$offsets[$offsetIndex][0][1]][1]       = $tOffset;
										$offsets[$offsets[$offsetIndex][0][1]][2]       = $tLngth;
										$offsets['/' . $offsets[$offsetIndex][0][1]][1] = $junk[0][1];
										$offsets['/' . $offsets[$offsetIndex][0][1]][2] = strlen( $junk[0][0] );
										$inside[$tOffset]                               = $offsets[$offsetIndex][0][1];
										$inside[$junk[0][1]]                            =
											'/' . $offsets[$offsetIndex][0][1];
									} else {
										unset( $offsets['/' .
										                $offsets[$offsetIndex][0][1]], $offsets[$offsets[$offsetIndex][0][1]]
										);
									}
								} else {
									unset( $offsets['/' .
									                $offsets[$offsetIndex][0][1]], $offsets[$offsets[$offsetIndex][0][1]]
									);
								}
							}
						}
						break;
					} else {
						$offsets[$offsetIndex] = strpos( $pageText, $offsetIndex, $pos );
						if( $offsets[$offsetIndex] === false ) unset( $offsets[$offsetIndex] );
					}
			}
		}

		return $this->parseGetNextOffset( $pos, $offsets, $pageText, $referenceOnly, $addedItems );
	}

	protected function parseGetBrackets( $pageText, $brackets, $conflictingBrackets, $exclude, &$pos = 0, &$inside = [],
	                                     $toUpdate = false, $skipAhead = []
	) {
		$bracketOffsets = [];

		if( $toUpdate !== false ) {
			$toChange = [];
			foreach( $brackets as $bracketItem ) {
				if( $bracketItem[0] == $toUpdate ) $toChange[] = $bracketItem;
			}
			$brackets = $toChange;
		}

		//Collect all of the bracket offsets
		foreach( $brackets as $bracketItem ) {
			if( !empty( $skipAhead ) ) {
				foreach( $skipAhead as $skipStart => $skipEnd ) {
					if( $pos <= $skipStart || $pos <= $skipEnd ) break;
				}
			} else $skipStart = $skipEnd = false;
			unset( $tOffset, $tOffset2, $conflictingBracket, $lastEnd );
			$tOffset    = $pos;
			$conflict   = [];
			$skipString = "";
			foreach( $conflictingBrackets as $bracketItemSub ) {
				if( $bracketItem[0] == $bracketItemSub[0] ) {
					$conflict[0] = $bracketItemSub;
				} elseif( $bracketItem[1] == $bracketItemSub[0] ) {
					$conflict[1] = $bracketItemSub;
				}
			}
			do {
				if( isset( $tOffset ) && isset( $tOffset2 ) ) {
					unset( $inside[$tOffset], $inside[$tOffset2] );
					$tOffset = $tOffset2 + 1;

				}
				do {
					$reset = false;
					if( isset( $conflictingBracket ) ) {
						if( $conflictingBracket[0] == $bracketItem[0] ) {
							$tOffset  += strlen( $conflictingBracket[1] );
							$tOffset2 = $tOffset;
						} elseif( isset( $tOffset2 ) &&
						          $conflictingBracket[0] ==
						          $bracketItem[1] ) {
							$tOffset2 += strlen( $conflictingBracket[1]
							);
						}
						unset( $conflictingBracket );
					}

					$tOffset = strpos( $pageText, $bracketItem[0], $tOffset );

					while( $skipEnd !== false && $tOffset >= $skipEnd ) {
						$skipEnd = next( $skipAhead );
						if( $skipEnd === false ) {
							$skipStart = false;
							break;
						}
						if( $skipEnd < $tOffset ) {
							continue;
						}
						$skipStart = key( $skipAhead );
					}
					if( $skipStart !== false && $tOffset !== false && $tOffset >= $skipStart ) {
						$tOffset = $skipEnd;
						$reset   = true;
						continue;
					}

					$moveStart = false;
					if( $tOffset !== false ) {
						do {
							$reset = false;
							if( !isset( $tOffset2 ) ) {
								$lastEnd = $tOffset2 = strpos( $pageText, $bracketItem[1], $tOffset );
							} else {
								if( !$moveStart ) {
									$tOffset2 = strpos( $pageText, $bracketItem[1],
									                    max( $tOffset, $tOffset2 ) + strlen( $bracketItem[1] )
									);
								}
								if( !isset( $lastEnd ) ) $lastEnd = $tOffset2;
								if( $tOffset2 === false ) {
									$moveStart = true;
								} else $lastEnd = $tOffset2;
								if( $moveStart ) {
									$tOffset =
										strpos( $pageText, $bracketItem[0], $tOffset + strlen( $bracketItem[0] ) );
									if( $tOffset !== false ) {
										$tOffset2 = strpos( $pageText, $bracketItem[1], $tOffset );
									} else $tOffset2 = false;
								}
							}

							while( $skipEnd !== false && $tOffset2 >= $skipEnd ) {
								$skipEnd = next( $skipAhead );
								if( $skipEnd === false ) {
									$skipStart = false;
									break;
								}
								if( $skipEnd < $tOffset2 ) continue;
								$skipStart = key( $skipAhead );
							}

							if( $skipStart !== false && $tOffset2 !== false && $tOffset2 >= $skipStart ) {
								$tOffset2   = $skipEnd;
								$skipString .= substr( $pageText, $skipStart, $skipEnd - $skipStart );
								$reset      = true;
								continue;
							}

							if( $tOffset2 === false ) break;

							$nestedOpened =
								substr_count( $pageText, $bracketItem[0], $tOffset + strlen( $bracketItem[0] ),
								              $tOffset2 - $tOffset - strlen( $bracketItem[0] )
								) - substr_count( $skipString, $bracketItem[0], 0 );
							$nestedClosed =
								substr_count( $pageText, $bracketItem[1], $tOffset + strlen( $bracketItem[0] ),
								              $tOffset2 - $tOffset - strlen( $bracketItem[0] )
								) - substr_count( $skipString, $bracketItem[1], 0 );
							if( !empty( $conflict ) ) {
								if( $bracketItem[0] == $conflict[0][0] ) {
									$nestedOpenedConflicted =
										substr_count( $pageText, $conflict[0][1], $tOffset + strlen( $bracketItem[0] ),
										              $tOffset2 - $tOffset - strlen( $bracketItem[0] )
										) - substr_count( $skipString, $conflict[0][1], 0 );
								}
								if( $bracketItem[1] == $conflict[1][0] ) {
									$nestedClosedConflicted =
										substr_count( $pageText, $conflict[1][1], $tOffset + strlen( $bracketItem[0] ),
										              $tOffset2 - $tOffset - strlen( $bracketItem[0] )
										) - substr_count( $skipString, $conflict[1][1], 0 );
								}
							}

							if( isset( $nestedOpenedConflicted ) ) {
								$nestedOpened = ( $nestedOpened * strlen( $conflict[0][0] ) ) -
								                ( $nestedOpenedConflicted * strlen( $conflict[0][1] ) );
							}
							if( isset( $nestedClosedConflicted ) ) {
								$nestedClosed = ( $nestedClosed * strlen( $conflict[1][0] ) ) -
								                ( $nestedClosedConflicted * strlen( $conflict[1][1] ) );
							}

						} while( $reset || $nestedOpened != $nestedClosed );
					}

					if( $tOffset !== false && $tOffset2 !== false && !empty( $conflict ) ) {
						if( $bracketItem[0] == $conflict[0][0] &&
						    substr( $pageText, $tOffset, strlen( $conflict[0][1] ) ) == $conflict[0][1] ) {
							$conflictingBracket = $conflict[0];
							continue;
						} elseif( $bracketItem[1] == $conflict[1][0] &&
						          substr( $pageText, $tOffset2, strlen( $conflict[1][1] ) ) == $conflict[1][1] ) {
							$conflictingBracket = $conflict[1];
							continue;
						} else unset( $conflictingBracket );
					} elseif( $tOffset && $tOffset2 === false ) {
						$tOffset += strlen( $bracketItem[0] );
						$reset   = true;
					}

				} while( $reset || isset( $conflictingBracket ) );

				if( $tOffset !== false && $tOffset2 !== false ) {
					$bracketOffsets[$bracketItem[0]] = $tOffset;
					$bracketOffsets[$bracketItem[1]] = $tOffset2;
					$inside[$tOffset]                = $bracketItem[0];
					$inside[$tOffset2]               = $bracketItem[1];
				}

			} while( !$this->parseValidateOffsets( $inside, $brackets, $exclude ) );
		}

		return $bracketOffsets;
	}

	private function parseValidateOffsets( $offsets, $brackets, $exclude )
	{
		$next          = [];
		$openBrackets  = [];
		$closeBrackets = [];
		foreach( $brackets as $pair ) {
			$openBrackets[]  = $pair[0];
			$closeBrackets[] = $pair[1];
		}
		foreach( $exclude as $pair ) {
			if( $pair[0] == "html" ) {
				$openBrackets[]  = $pair[1];
				$closeBrackets[] = $pair[2];
			}
		}
		foreach( $offsets as $offset => $item ) {
			$expected = end( $next );
			if( $expected !== false && $item == $expected ) {
				end( $next );
				unset( $next[key( $next )] );
			} else {
				$index = array_search( $item, $openBrackets );
				if( $index !== false ) {
					$next[] = $closeBrackets[$index];
				} else {
					$next[] = "/$item";
				}
			}
		}

		return empty( $next );
	}

	//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.

	private function parseGetNextOffset( $pos, &$offsets, $pageText, $referenceOnly = false, $additionalItems = [] )
	{
		$minimum = false;
		$index   = false;
		if( $referenceOnly === false ) {
			foreach( $offsets as $item => $data ) {
				if( $item == "__SKIP__" ) continue;
				if( substr( $item, 0, 1 ) == '/' ) continue;
				if( !is_array( $data ) ) {
					$offset = $data;
				} else $offset = $data[1];

				if( $minimum === false && $offset >= $pos && !in_array( $item, [ '__SCHEMELESSURL__' ] ) ) {
					$minimum = $offset;
					$index   = $item;
				} elseif( ( $offset < $minimum || ( $offset == $minimum && is_array( $data ) ) ) && $offset >= $pos &&
				          !in_array( $item, [ '__SCHEMELESSURL__' ] ) ) {
					$minimum = $offset;
					$index   = $item;
				} elseif( $offset < $pos ) {
					return $this->parseUpdateOffsets( $pageText, $pos, $offsets, $item, $referenceOnly, $additionalItems
					);
				}
			}
		} else {
			if( !empty( $offsets['__SKIP__'] ) ) {
				$skipAhead = $offsets['__SKIP__'];
			} else $skipAhead = [];

			if( isset( $offsets['__REF__'] ) ) {
				reset( $skipAhead );
				if( !empty( $skipAhead ) ) {
					foreach( $skipAhead as $skipStart => $skipEnd ) {
						if( $offsets['__REF__'][1] < $skipEnd ) break;
					}
				} else $skipStart = $skipEnd = false;

				if( $offsets['__REF__'][1] < $pos ) {
					return $this->parseUpdateOffsets( $pageText, $pos, $offsets, "__REF__", $referenceOnly,
					                                  $additionalItems
					);
				} elseif( $skipStart !== false && $offsets['__REF__'][1] >= $skipStart &&
				          $offsets['__REF__'][1] < $skipEnd ) {
					return $this->parseUpdateOffsets( $pageText, $skipEnd, $offsets, "__REF__", $referenceOnly,
					                                  $additionalItems
					);
				} else {
					return '__REF__';
				}
			} else return false;
		}

		return $index;
	}

	/**
	 * Parses a given reference/external link string and returns details about it.
	 *
	 * @param string $linkString Primary reference string
	 * @param string $remainder Left over stuff that may apply
	 *
	 * @access public
	 * @return array    Details about the link
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getLinkDetails( $linkString, $remainder )
	{
		$returnArray                   = [];
		$returnArray['link_string']    = $linkString;
		$returnArray['remainder']      = $remainder;
		$returnArray['has_archive']    = false;
		$returnArray['link_type']      = "x";
		$returnArray['tagged_dead']    = false;
		$returnArray['is_archive']     = false;
		$returnArray['access_time']    = false;
		$returnArray['tagged_paywall'] = false;
		$returnArray['is_paywall']     = false;
		$returnArray['permanent_dead'] = false;

		//Check if there are tags flagging the bot to ignore the source
		if( preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['ignore_tags'] ),
		                $remainder, $params
		    ) ||
		    preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['ignore_tags'] ),
		                $linkString, $params
		    )
		) {
			return [ 'ignore' => true ];
		}
		if( !preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['citation_tags'], false ),
		                 $linkString,
		                 $params
			) && preg_match( '/' . $this->schemelessURLRegex . '/i',
		                     $this->filterText( html_entity_decode( trim( $linkString, "[] \t\n\r" ),
		                                                            ENT_QUOTES | ENT_HTML5, "UTF-8"
		                                        )
		                     ),
		                     $params
		    )
		) {
			$this->analyzeBareURL( $returnArray, $params );
		} elseif( preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['citation_tags'], false ),
		                      $linkString, $params
		) ) {
			if( $this->analyzeCitation( $returnArray, $params ) ) return [ 'ignore' => true ];
		}
		//Check the source remainder
		$this->analyzeRemainder( $returnArray, $remainder );

		//Check for the presence of a paywall tag
		if( preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['paywall_tags'] ),
		                $remainder, $params
		    ) ||
		    preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['paywall_tags'] ),
		                $linkString, $params
		    )
		) {
			$returnArray['tagged_paywall'] = true;
		}

		//If there is no url after this then this source is useless.
		if( !isset( $returnArray['url'] ) ) return [ 'ignore' => true ];

		//Remove HTML entities from the URL and archive URL
		$returnArray['url'] = html_entity_decode( $returnArray['url'], ENT_QUOTES | ENT_HTML5, "UTF-8" );
		if( !empty( $returnArray['archive_url'] ) ) {
			$returnArray['archive_url'] =
				html_entity_decode( $returnArray['archive_url'], ENT_QUOTES | ENT_HTML5, "UTF-8" );
		}

		//Resolve templates, into URLs
		//If we can't resolve them, then ignore this link, as it will be fruitless to handle them.
		if( strpos( $returnArray['url'], "{{" ) !== false ) {
			preg_match( '/\{\{[\s\S\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i', $returnArray['url'],
			            $params
			);
			$returnArray['template_url'] = $returnArray['url'];
			$returnArray['url']          = API::resolveExternalLink( $returnArray['template_url'] );
			if( $returnArray['url'] === false ) {
				$returnArray['url'] =
					API::resolveExternalLink( "https:" . $returnArray['template_url'] );
			}
			if( $returnArray['url'] === false ) return [ 'ignore' => true ];

			if( strpos( $returnArray['template_url'], $returnArray['url'] ) !== false ) {
				//Whoops, we absorbed an irrelevant template
				unset( $returnArray['template_url'] );
				$returnArray['original_url'] = $returnArray['url'];
				if( !empty( $remainder ) ) {
					$returnArray['remainder'] = str_replace( $returnArray['url'], '', $linkString ) .
					                            $remainder;
				}
				$returnArray['link_string'] = $returnArray['url'];
			}
		}

		if( $returnArray['has_archive'] === true && strpos( $returnArray['archive_url'], "{{" ) !== false ) {
			preg_match( '/\{\{[\s\S\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i',
			            $returnArray['archive_url'],
			            $params
			);
			$returnArray['archive_url'] = API::resolveExternalLink( $returnArray['archive_url'] );
			if( $returnArray['archive_url'] === false ) {
				$returnArray['archive_url'] =
					API::resolveExternalLink( "https:" . $returnArray['archive_url'] );
			}
			if( $returnArray['archive_url'] === false ) {
				$returnArray['archive_type'] = "invalid";
			}
		}

		if( empty( $returnArray['original_url'] ) ) $returnArray['original_url'] = $returnArray['url'];

		if( $returnArray['is_archive'] === false ) {
			$tmp = $returnArray['original_url'];
		} else $tmp = $returnArray['url'];
		//Extract nonsense stuff from the URL, probably due to a misuse of wiki syntax
		//If a url isn't found, it means it's too badly formatted to be of use, so ignore
		if( ( ( $returnArray['link_type'] === "template" || ( strpos( $tmp, "[" ) &&
		                                                      strpos( $tmp, "]" ) ) ) &&
		      preg_match( '/' . $this->schemelessURLRegex . '/i', $tmp, $match ) ) ||
		    preg_match( '/' . $this->schemedURLRegex . '/i', $tmp, $match )
		) {
			//Sanitize the URL to keep it consistent in the DB.
			$returnArray['url'] =
				$this->deadCheck->sanitizeURL( $match[0], true );
			//If the sanitizer can't handle the URL, ignore the reference to prevent a garbage edit.
			if( $returnArray['url'] == "https:///" ) return [ 'ignore' => true ];
			if( $returnArray['url'] == "https://''/" ) return [ 'ignore' => true ];
			if( $returnArray['url'] == "http://''/" ) return [ 'ignore' => true ];
			if( isset( $match[1] ) ) {
				$returnArray['fragment'] = $match[1];
			} else $returnArray['fragment'] = null;
			if( isset( $returnArray['archive_url'] ) ) {
				$parts = $this->deadCheck->parseURL( $returnArray['archive_url'] );
				if( isset( $parts['fragment'] ) ) {
					$returnArray['archive_fragment'] = $parts['fragment'];
				} else $returnArray['archive_fragment'] = null;
				$returnArray['archive_url'] = preg_replace( '/#.*/', '', $returnArray['archive_url'] );
			}
		} else {
			return [ 'ignore' => true ];
		}

		if( $returnArray['access_time'] === false ) {
			$returnArray['access_time'] = "x";
		}

		if( isset( $returnArray['original_url'] ) &&
		    $this->deadCheck->sanitizeURL( $returnArray['original_url'], true ) !=
		    $this->deadCheck->sanitizeURL( $returnArray['url'], true ) &&
		    $returnArray['is_archive'] === false && $returnArray['has_archive'] === true &&
		    !isset( $returnArray['template_url'] )
		) {
			$returnArray['archive_mismatch'] = true;
			$returnArray['url']              = $this->deadCheck->sanitizeURL( $returnArray['original_url'], true );
			unset( $returnArray['original_url'] );
		}

		if( isset( $returnArray['archive_template'] ) ) {
			if( isset( $returnArray['archive_template']['parameters']['__FORMAT__'] ) ) {
				$returnArray['archive_template']['format'] =
					$returnArray['archive_template']['parameters']['__FORMAT__'];
				unset( $returnArray['archive_template']['parameters']['__FORMAT__'] );
			}
		}

		if( isset( $returnArray['tag_template'] ) ) {
			if( isset( $returnArray['tag_template']['parameters']['__FORMAT__'] ) ) {
				$returnArray['tag_template']['format'] =
					$returnArray['tag_template']['parameters']['__FORMAT__'];
				unset( $returnArray['tag_template']['parameters']['__FORMAT__'] );
			}
		}

		return $returnArray;
	}

	/**
	 * Filters out the text that does not get rendered normally.
	 * This includes comments, and plaintext formatting.
	 *
	 * @param string $text String to filter
	 * @param bool $trim Trim the output
	 *
	 * @return string Filtered text.
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 */
	protected function filterText( $text, $trim = false )
	{
		$text = preg_replace( '/\<\!\-\-(?:.|\n)*?\-\-\>/i', "", $text );
		if( preg_match( '/\<\s*source[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/source\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*source[^\/]*?\>(?:.|\n)*?\<\/source\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*syntaxhighlight[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/syntaxhighlight\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] )
		) {
			$text = preg_replace( '/\<\s*syntaxhighlight[^\/]*?\>(?:.|\n)*?\<\/syntaxhighlight\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*code[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/code\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*code[^\/]*?\>(?:.|\n)*?\<\/code\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*nowiki[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/nowiki\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*nowiki[^\/]*?\>(?:.|\n)*?\<\/nowiki\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*pre[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/pre\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*pre[^\/]*?\>(?:.|\n)*?\<\/pre\s*\>/i', "", $text );
		}

		if( $trim ) {
			return trim( $text );
		} else return $text;
	}

	/**
	 * Analyzes the bare link
	 *
	 * @param array $returnArray Array being generated
	 * @param string $linkString Link string being parsed
	 * @param array $params Extracted URL from link string
	 *
	 * @access protected
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function analyzeBareURL( &$returnArray, &$params )
	{

		if( strpos( $params[0], "''" ) !== false ) $params[0] = substr( $params[0], 0, strpos( $params[0], "''" ) );
		if( stripos( $params[0], "%c2" ) === false && stripos( urlencode( $params[0] ), "%c2" ) !== false ) {
			$params[0] = urldecode( substr( urlencode( $params[0] ), 0, stripos( urlencode( $params[0] ), "%c2" ) ) );
		}
		if( stripos( $params[0], "%e3" ) === false && stripos( urlencode( $params[0] ), "%e3" ) !== false ) {
			$params[0] = urldecode( substr( urlencode( $params[0] ), 0, stripos( urlencode( $params[0] ), "%e3" ) ) );
		}
		if( strpos( $params[0], "\"" ) !== false ) $params[0] = substr( $params[0], 0, strpos( $params[0], "\"" ) );

		$returnArray['original_url'] =
		$returnArray['url'] = $params[0];
		$returnArray['link_type']    = "link";
		$returnArray['access_time']  = "x";
		$returnArray['is_archive']   = false;
		$returnArray['tagged_dead']  = false;
		$returnArray['has_archive']  = false;

		if( preg_match( '/\[(?:\{\{[\s\S\n]*\}\}|\S*\s+)(.*)\]/', $returnArray['link_string'], $match ) &&
		    !empty( $match[1] ) ) {
			$returnArray['title'] = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, "UTF-8" );
		}
		if( preg_match( DataGenerator::fetchTemplateRegex( [ '.*?' ] ), $returnArray['link_string'], $junk ) ) {
			$returnArray['template_url'] = $returnArray['link_string'];
			$returnArray['original_url'] = $returnArray['link_string'];
			$returnArray['url']          = $returnArray['link_string'];
		}

		//If this is a bare archive url
		if( API::isArchive( $returnArray['url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive']  = true;
			if( !isset( $returnArray['archive_type'] ) || $returnArray['archive_type'] != "invalid" ) {
				$returnArray['archive_type'] = "link";
			}
			//$returnArray['link_type'] = "x";
			$returnArray['access_time'] = $returnArray['archive_time'];
		}
	}

	/**
	 * Analyze the citation template
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $params Citation template regex match breakdown
	 *
	 * @access protected
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function analyzeCitation( &$returnArray, &$params )
	{
		$returnArray['tagged_dead']                 = false;
		$returnArray['url_usurp']                   = false;
		$returnArray['link_type']                   = "template";
		$returnArray['link_template']               = [];
		$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
		$returnArray['link_template']['format']     = $returnArray['link_template']['parameters']['__FORMAT__'];
		unset( $returnArray['link_template']['parameters']['__FORMAT__'] );
		$returnArray['link_template']['name']   = trim( $params[1] );
		$returnArray['link_template']['string'] = $params[0];

		$returnArray['link_template']['template_object'] =
			CiteMap::findMapObject( $returnArray['link_template']['name'] );
		$returnArray['link_template']['template_map']    =
		$returnArray['link_template']['template_map'] = $returnArray['link_template']['template_object']->getMap();

		if( isset( $returnArray['link_template']['template_map']['services'] ) ) {
			$mappedObjects =
				$returnArray['link_template']['template_map']['services']['@default'];
		} else return false;
		$toLookFor = [
			'url' => true, 'access_date' => false, 'archive_url' => false, 'deadvalues' => false, 'paywall' => false,
			'title' => false, 'linkstring' => false, 'remainder' => false
		];

		foreach( $toLookFor as $mappedObject => $required ) {
			if( $required && !isset( $mappedObjects[$mappedObject] ) ) return false;

			$mapFound = false;
			if( isset( $mappedObjects[$mappedObject] ) ) {
				foreach( $mappedObjects[$mappedObject] as $sID => $dataObject ) {
					if( is_array( $dataObject ) ) {
						$dataIndex = $dataObject['index'];
					} else $dataIndex = $dataObject;

					foreach( $returnArray['link_template']['template_map']['data'][$dataIndex]['mapto'] as $paramIndex )
					{
						if( !empty( $returnArray['link_template']['parameters'][$returnArray['link_template']['template_map']['params'][$paramIndex]] ) ) {
							$mapFound = true;
							$value    =
								html_entity_decode( $this->filterText( str_replace( "{{!}}", "|",
								                                                    str_replace( "{{=}}", "=",
								                                                                 $returnArray['link_template']['parameters'][$returnArray['link_template']['template_map']['params'][$paramIndex]]
								                                                    )
								                                       ), true
								), ENT_QUOTES | ENT_HTML5, "UTF-8"
								);

							switch( $mappedObject ) {
								case "title":
									if( empty( $returnArray['title'] ) ) $returnArray['title'] = $value;
									break;
								case "url":
									if( preg_match( '/\[(.*?)\s+(.*)\]/', $value, $match ) && !empty( $match[1] ) ) {
										$returnArray['title']        = $match[2];
										$returnArray['original_url'] = $returnArray['url'] = $match[1];
									} else $returnArray['original_url'] = $returnArray['url'] = $value;
									break;
								case "access_date":
									$time =
										DataGenerator::strptime( $value,
										                         $this->generator->retrieveDateFormat( $value, $this
										                         )
										);
									if( is_null( $time ) || $time === false ) {
										$timestamp =
											$this->filterText( API::resolveWikitext( $value ) );
										$time      = DataGenerator::strptime( $timestamp,
										                                      $this->generator->retrieveDateFormat( $timestamp,
										                                                                            $this
										                                      )
										);
									}
									if( $time === false || is_null( $time ) ) {
										$time = "x";
									} else {
										$time = DataGenerator::strptimetoepoch( $time );
									}
									$returnArray['access_time'] = $time;
									break;
								case "archive_url":
									$returnArray['archive_url'] = $value;
									if( API::isArchive( $returnArray['archive_url'], $returnArray ) ) {
										$returnArray['archive_type'] = "parameter";
										$returnArray['has_archive']  = true;
										$returnArray['is_archive']   = false;
									}
									break;
								case "deadvalues":
									$valuesYes = explode( ";;", $dataObject['valueyes'] );
									if( strpos( $dataObject['valueyes'], '$$TIMESTAMP' ) !== false ) {
										$timestampYes = true;
									} else $timestampYes = false;
									$valuesNo     = explode( ";;", $dataObject['valueno'] );
									$valuesUsurp  = explode( ";;", $dataObject['valueusurp'] );
									$defaultValue = $dataObject['defaultvalue'];
									if( $timestampYes || in_array( $value, $valuesYes ) ) {
										$returnArray['tagged_dead'] = true;
										$returnArray['tag_type']    = "parameter";
									} elseif( in_array( $value, $valuesNo ) ) {
										$returnArray['force_when_dead'] = true;
									} elseif( in_array( $value, $valuesUsurp ) ) {
										$returnArray['tagged_dead'] = true;
										$returnArray['tag_type']    = "parameter";
										$returnArray['url_usurp']   = true;
									} elseif( $defaultValue == "yes" && $returnArray['has_archive'] === true ) {
										$returnArray['tagged_dead'] = true;
										$returnArray['tag_type']    = "implied";
									}
									break;
								case "paywall":
									$valuesYes = explode( ";;", $dataObject['valueyes'] );
									$valuesNo  = explode( ";;", $dataObject['valueno'] );
									if( in_array( $value, $valuesYes ) ) {
										$returnArray['tagged_paywall'] = true;
									} elseif( in_array( $value, $valuesNo ) ) {
										$returnArray['tagged_paywall'] = false;
									} else continue 2;
									break;
								case "nestedstring":
									$returnArray2 = $this->getLinkDetails( $value, "" );

									if( !isset( $returnArray2['ignore'] ) ) {
										$returnArray['sub_string'] = $returnArray2['link_string'];
										if( $returnArray2['has_archive'] === true &&
										    $returnArray['has_archive'] === false ) {
											$returnArray['has_archive']  = $returnArray2['has_archive'];
											$returnArray['is_archive']   = $returnArray2['is_archive'];
											$returnArray['archive_type'] = $returnArray2['archive_type'];
											$returnArray['archive_url']  = $returnArray2['archive_url'];
											if( isset( $returnArray2['archive_template'] ) ) {
												$returnArray['archive_template'] = $returnArray2['archive_template'];
											}
											$returnArray['archive_time'] = $returnArray2['archive_time'];
										}

										$returnArray['sub_type'] = $returnArray2['link_type'];
										if( isset( $returnArray2['link_template'] ) ) {
											$returnArray['sub_template'] =
												$returnArray2['link_template'];
										}
										if( $returnArray['access_time'] == "x" &&
										    $returnArray2['access_time'] != "x" ) {
											$returnArray['access_time'] = $returnArray2['access_time'];
										}

										if( $returnArray2['tagged_paywall'] === true ) {
											$returnArray['tagged_paywall'] =
												true;
										}
										if( $returnArray2['is_paywall'] === true ) $returnArray['is_paywall'] = true;
										if( $returnArray2['url_usurp'] === true ) $returnArray['url_usurp'] = true;
										$returnArray['url']          = $returnArray2['url'];
										$returnArray['original_url'] = $returnArray2['original_url'];

										if( !empty( $returnArray2['title'] ) ) {
											$returnArray['title'] =
												$returnArray2['title'];
										}
									}

									unset( $returnArray2 );
									break;
								case "remainder":
									$returnArray2 = $this->getLinkDetails( "", $value );

									if( !isset( $returnArray2['ignore'] ) ) {
										$returnArray['remainder']   = $returnArray2['remainder'];
										$returnArray['has_archive'] = $returnArray2['has_archive'];
										$returnArray['is_archive']  = $returnArray2['is_archive'];
										if( isset( $returnArray2['archive_type'] ) ) {
											$returnArray['archive_type'] =
												$returnArray2['archive_type'];
										}
										if( isset( $returnArray2['archive_url'] ) ) {
											$returnArray['archive_url'] =
												$returnArray2['archive_url'];
										}
										if( isset( $returnArray2['archive_template'] ) ) {
											$returnArray['archive_template'] =
												$returnArray2['archive_template'];
										}
										if( isset( $returnArray2['archive_time'] ) ) {
											$returnArray['archive_time'] =
												$returnArray2['archive_time'];
										}

										$returnArray['tagged_dead'] = $returnArray2['tagged_dead'];
										if( isset( $returnArray2['tag_type'] ) ) {
											$returnArray['tag_type'] =
												$returnArray2['tag_type'];
										}
										if( isset( $returnArray2['tag_template'] ) ) {
											$returnArray['tag_template'] =
												$returnArray2['tag_template'];
										}

										$returnArray['link_type'] = $returnArray2['link_type'];
										if( $returnArray['access_time'] == "x" &&
										    $returnArray2['access_time'] != "x" ) {
											$returnArray['access_time'] = $returnArray2['access_time'];
										}

										if( $returnArray2['tagged_paywall'] === true ) {
											$returnArray['tagged_paywall'] =
												true;
										}
										if( $returnArray2['is_paywall'] === true ) $returnArray['is_paywall'] = true;
										if( $returnArray2['url_usurp'] === true ) $returnArray['url_usurp'] = true;
										$returnArray['url'] = $returnArray2['url'];

										if( empty( $returnArray['title'] ) ) {
											$returnArray['title'] =
												$returnArray2['title'];
										}
									}

									unset( $returnArray2 );
									break;

							}
							break;
						}
					}
					if( $mapFound ) continue 2;
					if( $required && !$mapFound ) return false;
				}
			}
		}

		if( !isset( $mappedObjects['archive_url'] ) ) $returnArray['cite_noarchive'] = true;
	}

	/**
	 * Fetch the parameters of the template
	 *
	 * @param string $templateString String of the template without the {{example bit
	 *
	 * @access public
	 * @return array Template parameters with respective values
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function getTemplateParameters( $templateString )
	{
		if( isset( $this->templateParamCache[$templateString] ) ) {
			return $this->templateParamCache[$templateString];
		}

		$returnArray = [];
		$formatting  = [];
		if( empty( $templateString ) ) return $returnArray;

		$returnArray = [];

		//Set scan needle to the beginning of the string
		$pos            = 0;
		$offsets        = [];
		$startingOffset = false;
		$counter        = 1;
		$parameter      = "";
		$index          = $counter;

		while( $startingOffset =
			$this->parseUpdateOffsets( $templateString, $pos, $offsets, $startingOffset, false, [ '|', '=', '[[', ']]' ]
			) ) {
			switch( $startingOffset ) {
				case "{{":
					$pos = $offsets['}}'] + 2;
					break;
				case "[[":
					$pos = $offsets[']]'] + 2;
					break;
				case "[":
					$pos = $offsets[']'] + 1;
					break;
				case "|":
					$start = $pos;
					$end   = $offsets['|'];
					$pos   = $end + 1;
					if( isset( $realStart ) ) $start = $realStart;
					$value               = substr( $templateString, $start, $end - $start );
					$returnArray[$index] = trim( $value );
					if( !empty( trim( $parameter ) ) && !empty( trim( $value ) ) ) {
						if( preg_match( '/^(\s*).+?(\s*)$/iu', $parameter, $fstring1 ) &&
						    preg_match( '/^(\s*).+?(\s*)$/iu', $value, $fstring2 ) ) {
							if( isset( $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] .
							                       '{value}' .
							                       $fstring2[2]]
							) ) {
								$formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' .
								            $fstring2[2]]++;
							} else {
								$formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' .
								            $fstring2[2]] = 1;
							}
						}
					}
					$parameter = "";
					$counter++;
					$index = $counter;
					unset( $realStart );
					break;
				case "=":
					$start = $pos;
					$end   = $offsets['='];
					$pos   = $end + 1;
					if( empty( $parameter ) ) {
						$parameter = substr( $templateString, $start, $end - $start );
						$index     = $this->filterText( $parameter, true );
						$realStart = $pos;
					}
					break;
				default:
					if( !is_string( $offsets[$startingOffset][0] ) && $offsets[$startingOffset][0][0] == "html" ) {
						$pos =
							$offsets[$offsets[$startingOffset][0][2]][1] + $offsets[$offsets[$startingOffset][0][2]][2];
					} else {
						$pos = $offsets["/$startingOffset"][1] + $offsets["/$startingOffset"][2];
					}
					break;
			}
		}

		$start = $pos;
		$end   = strlen( $templateString );
		if( isset( $realStart ) ) $start = $realStart;
		$value               = substr( $templateString, $start, $end - $start );
		$returnArray[$index] = trim( $value );
		if( !empty( trim( $parameter ) ) && !empty( trim( $value ) ) ) {
			if( preg_match( '/^(\s*).+?(\s*)$/iu', $parameter, $fstring1 ) &&
			    preg_match( '/^(\s*).+?(\s*)$/iu', $value, $fstring2 ) ) {
				if( isset( $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] .
				                       '{value}' .
				                       $fstring2[2]]
				) ) {
					$formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' .
					            $fstring2[2]]++;
				} else {
					$formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' .
					            $fstring2[2]] = 1;
				}
			}
		}

		if( !empty( $formatting ) ) {
			$returnArray['__FORMAT__'] = array_search( max( $formatting ), $formatting );
			if( count( $formatting ) > 4 && strpos( $returnArray['__FORMAT__'], "\n" ) !== false ) {
				$returnArray['__FORMAT__'] = "multiline-pretty";
			}
		} else $returnArray['__FORMAT__'] = " {key} = {value} ";

		$this->templateParamCache[$templateString] = $returnArray;

		return $returnArray;
	}

	/**
	 * Analyze the remainder string
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $remainder Remainder string
	 *
	 * @access protected
	 * @return string The language code of the template.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function analyzeRemainder( &$returnArray, &$remainder )
	{
		//If there's an archive tag, then...
		if( preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['archive_tags'] ),
		                $remainder, $params2
		) ) {
			if( $returnArray['has_archive'] === false ) {
				$returnArray['archive_type']                   = "template";
				$returnArray['archive_template']               = [];
				$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
				$returnArray['archive_template']['name']       = str_replace( "{{", "", $params2[1] );
				$returnArray['archive_template']['string']     = $params2[0];
			}

			//If there already is an archive in this source, it's means there's an archive template attached to a citation template.  That's needless confusion when sourcing.
			if( $returnArray['link_type'] == "template" && $returnArray['has_archive'] === false &&
			    !isset( $returnArray['cite_noarchive'] ) ) {
				$returnArray['archive_type'] = "invalid";
				$returnArray['tagged_dead']  = true;
				$returnArray['tag_type']     = "implied";
			} elseif( $returnArray['has_archive'] === true ) {
				$returnArray['redundant_archives'] = true;

				return;
			}

			$returnArray['has_archive'] = true;

			//Process all the defined tags
			foreach( $this->commObject->config['all_archives'] as $archiveName => $archiveData ) {
				$archiveName2 = str_replace( " ", "_", $archiveName );
				if( isset( $this->commObject->config["darchive_$archiveName2"] ) ) {
					if( preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config["darchive_$archiveName2"],
					                                                   $this
					),
					                $remainder
					) ) {
						$tmpAnalysis = [];
						$archiveMap  = $archiveData['archivetemplatedefinitions']->getMap();
						foreach( $archiveMap['services'] as $service => $mappedObjects ) {
							$tmpAnalysis[$service] = [];
							if( !isset( $mappedObjects['archive_url'] ) ) {
								foreach( $mappedObjects['archive_date'] as $id => $mappedArchiveDate ) {
									foreach(
										$archiveMap['data'][$mappedArchiveDate['index']]['mapto']
										as $paramIndex
									) {
										if( isset( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]] ) ) {
											switch( $mappedArchiveDate['type'] ) {
												case "microepochbase62":
													$webciteTimestamp =
														$returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]];
													$decodedTimestamp =
														API::to10( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]],
														           62
														);
												case "microepoch":
													if( !isset( $decodedTimestamp ) ) {
														$decodedTimestamp =
															floor( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]]
															);
													} else $decodedTimestamp = floor( $decodedTimestamp / 1000000 );
													goto epochCheck;
												case "epochbase62":
													$decodedTimestamp =
														API::to10( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]],
														           62
														);
												case "epoch":
													epochCheck:
													if( !isset( $decodedTimestamp ) ) {
														$decodedTimestamp =
															$returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]];
													}
													if( !is_numeric( $decodedTimestamp ) ) {
														unset( $decodedTimestamp );
														break 2;
													}
													if( $decodedTimestamp > time() ||
													    $decodedTimestamp < 831859200 ) {
														unset( $decodedTimestamp );
														break 2;
													}
													$tmpAnalysis[$service]['timestamp'] = $decodedTimestamp;
													unset( $decodedTimestamp );
													break;
												case "timestamp":
													$decodedTimestamp =
														DataGenerator::strptime( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]],
														                         $mappedArchiveDate['format']
														);
													if( $decodedTimestamp === false || is_null( $decodedTimestamp ) ) {
														$decodedTimestamp =
															strtotime( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]]
															);
														if( $decodedTimestamp === false ) break 2;
														$returnArray['archive_type'] = 'invalid';
													} else {
														$decodedTimestamp =
															DataGenerator::strptimetoepoch( $decodedTimestamp );
													}
													break;
											}
											if( $decodedTimestamp ) {
												$tmpAnalysis[$service]['timestamp'] =
													$decodedTimestamp;
												$archiveURLTimestamp                =
													DataGenerator::strftime( "%Y%m%d%H%M%S", $decodedTimestamp );
											}
											break;
										}
									}
								}
								foreach(
									$archiveMap['data'][$mappedObjects['url'][0]]['mapto']
									as $paramIndex
								) {
									if( isset( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]] ) ) {
										$tmpAnalysis[$service]['url'] =
											$returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]];
										break;
									}
								}
								if( isset( $tmpAnalysis[$service]['timestamp'] ) &&
								    isset( $tmpAnalysis[$service]['url'] ) ) {
									$tmpAnalysis[$service]['complete'] = true;
								} else {
									$tmpAnalysis[$service]['complete'] = false;
								}
							} else {
								foreach(
									$archiveMap['data'][$mappedObjects['archive_url'][0]]['mapto']
									as $paramIndex
								) {
									if( isset( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]] ) ) {
										$tmpAnalysis[$service]['archive_url'] =
											$returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]];
										break;
									}
								}
								if( isset( $tmpAnalysis[$service]['archive_url'] ) ) {
									$tmpAnalysis[$service]['complete'] = true;
								} else {
									$tmpAnalysis[$service]['complete'] = false;
								}
							}

							if( isset( $mappedObjects['title'] ) ) {
								foreach(
									$archiveMap['data'][$mappedObjects['title'][0]]['mapto']
									as $paramIndex
								) {
									if( isset( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]] ) ) {
										$tmpAnalysis[$service]['title'] =
											$returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]];
										break;
									}
								}
							}
						}
						foreach( $tmpAnalysis as $service => $templateData ) {
							if( $templateData['complete'] === true ) {
								if( isset( $templateData['title'] ) && empty( $returnArray['title'] ) ) {
									$returnArray['title'] = $templateData['title'];
								}
								if( !isset( $templateData['archive_url'] ) ) {
									$originalURL = htmlspecialchars_decode( $templateData['url'] );
									switch( $service ) {
										case "@wayback":
											$archiveURL =
												"https://web.archive.org/web/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@europarchive":
											$archiveURL =
												"http://collection.europarchive.org/nli/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@archiveis":
											$archiveURL = "https://archive.is/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@memento":
											$archiveURL =
												"https://timetravel.mementoweb.org/memento/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@webcite":
											$archiveURL =
												"https://www.webcitation.org/{$webciteTimestamp}?url={$originalURL}";
											break;
										case "@archiveit":
											$archiveURL =
												"https://wayback.archive-it.org/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@arquivo":
											$archiveURL =
												"http://arquivo.pt/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@loc":
											$archiveURL =
												"http://webarchive.loc.gov/all/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@warbharvest":
											$archiveURL =
												"https://www.webharvest.gov/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@bibalex":
											$archiveURL =
												"http://web.archive.bibalex.org/web/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@collectionscanada":
											$archiveURL =
												"https://www.collectionscanada.gc.ca/webarchives/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@veebiarhiiv":
											$archiveURL =
												"http://veebiarhiiv.digar.ee/a/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@vefsafn":
											$archiveURL =
												"http://wayback.vefsafn.is/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@proni":
											$archiveURL =
												"http://webarchive.proni.gov.uk/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@spletni":
											$archiveURL =
												"http://nukrobi2.nuk.uni-lj.si:8080/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@stanford":
											$archiveURL =
												"https://swap.stanford.edu/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@nationalarchives":
											$archiveURL =
												"http://webarchive.nationalarchives.gov.uk/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@parliamentuk":
											$archiveURL =
												"http://webarchive.parliament.uk/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@was":
											$archiveURL =
												"http://eresources.nlb.gov.sg/webarchives/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@permacc":
											$archiveURL =
												"https://perma-archives.org/warc/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@ukwebarchive":
											$archiveURL =
												"https://www.webarchive.org.uk/wayback/archive/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@wikiwix":
											$archiveURL =
												"http://archive.wikiwix.com/cache/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@catalonianarchive":
											$archiveURL =
												"http://padi.cat:8080/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										default:
											$archiveURL = false;
											break;
									}
								} else {
									$archiveURL = htmlspecialchars_decode( $templateData['archive_url'] );
								}
								break;
							}
						}

						$tmp = [];
						if( isset( $archiveURL ) ) {
							$validArchive = API::isArchive( $archiveURL, $tmp );

							//If the original URL isn't present, then we are dealing with a stray archive template.
							if( !isset( $returnArray['url'] ) ) {
								if( $validArchive === true && $archiveData['templatebehavior'] == "swallow" ) {
									$returnArray['archive_type'] = "template-swallow";
									$returnArray['link_type']    = "stray";
									$returnArray['is_archive']   = true;
									if( isset( $archiveMap['services'][$service]['linkstring'] ) ) {
										foreach(
											$archiveMap['data'][$archiveMap['services'][$service]['linkstring'][0]]['mapto']
											as $paramIndex
										) {
											if( isset( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]] ) ) {
												$returnArray['archive_type'] = "template-swallow";
												$returnArray2                =
													$this->getLinkDetails( $returnArray['archive_template']['parameters'][$archiveMap['params'][$paramIndex]],
													                       ""
													);

												unset( $returnArray2['tagged_dead'], $returnArray2['permanent_dead'], $returnArray2['remainder'] );

												$returnArray = array_replace( $returnArray, $returnArray2 );
												unset( $returnArray2 );
												break;
											} else {
												$returnArray['archive_type'] = "invalid";
											}
										}
									}
								} else {
									$returnArray['archive_type'] = "invalid";
									$returnArray['link_type']    = "stray";
									$returnArray['is_archive']   = true;
								}
							}

							$returnArray = array_replace( $returnArray, $tmp );
						}

						unset( $tmp );

						if( isset( $originalURL ) && API::isArchive( $originalURL, $junk ) &&
						    $junk['archive_host'] == $service ) {
							//We detected an improper use of the template.  Let's fix it.
							$returnArray['archive_type'] = "invalid";
							$returnArray                 = array_replace( $returnArray, $junk );
						} elseif( !isset( $archiveURL ) || $archiveURL === false ) {
							//Whoops, this template isn't filled out correctly.  Let's fix it.
							$returnArray['archive_url']  = "x";
							$returnArray['archive_time'] = "x";
							$returnArray['archive_type'] = "invalid";
						} elseif( $validArchive === false ) {
							//Whoops, this template is pointing to an invalid archive.  Let's make it valid.
							$returnArray['archive_type'] = "invalid";
						}

						//Check if the archive template is deprecated.
						if( isset( $this->commObject->config['deprecated_archives'] ) &&
						    in_array( $archiveName2, $this->commObject->config['deprecated_archives'] ) ) {
							$returnArray['archive_type'] = "invalid";
						}
					}
				}
			}

			//If we have multiple archives, we can't handle these correctly, so remove any force markers that may force the editing of the citations.
			if( $returnArray['link_type'] == "template" && $returnArray['has_archive'] === true &&
			    $returnArray['archive_type'] == "template"
			) {
				unset( $returnArray['convert_archive_url'] );
				unset( $returnArray['force_when_dead'] );
				unset( $returnArray['force'] );
				unset( $returnArray['force_when_alive'] );
			}
		}

		if( preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['deadlink_tags'] ),
		                $remainder, $params2
		) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type']    = "template";
			if( isset( $params2[2] ) ) {
				$returnArray['tag_template']['parameters'] =
					$this->getTemplateParameters( $params2[2] );
			} else $returnArray['tag_template']['parameters'] = [];

			if( !empty( $this->commObject->config['deadlink_tags_data'] ) ) {
				$templateData = $this->commObject->config['deadlink_tags_data']->getMap();
				//Flag those that can't be fixed.
				if( isset( $templateData['services']['@default']['permadead'] ) ) {
					foreach( $templateData['services']['@default']['permadead'] as $valueData ) {
						foreach( $templateData['data'][$valueData['index']]['mapto'] as $mapAddress ) {
							if( isset( $returnArray['tag_template']['parameters'][$templateData['params'][$mapAddress]] ) &&
							    ( !isset( $returnArray['tag_template']['paramaters']['bot'] ) ||
							      $returnArray['tag_template']['paramaters']['bot'] != USERNAME ) ) {
								$returnArray['permanent_dead'] = true;
							}
						}
					}
				}
			}

			if( $this->commObject->config['templatebehavior'] == "swallow" ) {
				$returnArray['tag_type'] = "template-swallow";
				if( isset( $templateData['services']['@default']['linkstring'] ) ) {
					foreach(
						$templateData['data'][$templateData['services']['@default']['linkstring'][0]]['mapto']
						as $paramIndex
					) {
						if( isset( $returnArray['tag_template']['parameters'][$templateData['params'][$paramIndex]] ) ) {
							$returnArray['tag_type'] = "template-swallow";
							$returnArray2            =
								$this->getLinkDetails( $returnArray['tag_template']['parameters'][$templateData['params'][$paramIndex]],
								                       ""
								);

							unset( $returnArray2['tagged_dead'], $returnArray2['permanent_dead'], $returnArray2['remainder'] );

							$returnArray = array_replace( $returnArray, $returnArray2 );
							unset( $returnArray2 );
							break;
						} else {
							$returnArray['tag_type'] = "invalid";
						}
					}
				}
			}

			$returnArray['tag_template']['name']   = str_replace( "{{", "", $params2[1] );
			$returnArray['tag_template']['string'] = $params2[0];
		}
	}

	/**
	 * Determines if 2 separate but close together links have a connection to each other.
	 * If so, the link contained in $currentLink will be merged to the previous one.
	 *
	 * @param array $lastLink Index information of last link looked at
	 * @param array $currentLink index of the current link looked at
	 * @param array $returnArray The array of links to look at and modify
	 *
	 * @return bool True if the 2 links are related.
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 */
	public function isConnected( $lastLink, $currentLink, &$returnArray )
	{
		//If one is in a reference and the other is not, there can't be a connection.
		if( ( !is_null( $lastLink['id'] ) xor !is_null( $currentLink['id'] ) ) === true ) return false;
		//If the reference IDs are different, also no connection.
		if( ( !is_null( $lastLink['id'] ) && !is_null( $currentLink['id'] ) ) &&
		    $lastLink['tid'] !== $currentLink['tid']
		) {
			return false;
		}
		//If this is the first link being analyzed, wait for it to be the second run.
		if( is_null( $lastLink['tid'] ) ) return false;
		//Recall the previous link that was analyzed.
		if( !is_null( $lastLink['id'] ) ) {
			$link = $returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
		} else {
			$link = $returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']];
		}
		//Recall the current link being analyzed
		if( !is_null( $currentLink['id'] ) ) {
			$temp = $returnArray[$currentLink['tid']]['reference'][$currentLink['id']];
		} else {
			$temp = $returnArray[$currentLink['tid']][$returnArray[$currentLink['tid']]['link_type']];
		}

		$lastCleanURL    = urldecode( $this->deadCheck->cleanURL( $link['url'] ) );
		$currentCleanURL = urldecode( $this->deadCheck->cleanURL( $temp['url'] ) );

		$urlMatch = ( strpos( $lastCleanURL, $currentCleanURL ) !== false ||
		              strpos( $currentCleanURL, $lastCleanURL ) !== false );

		//If the original URLs of both links match, and the archive is located in the current link, then merge into previous link
		if( $urlMatch && $temp['is_archive'] === true
		) {
			//An archive template initially detected on it's own, is flagged as a stray.  Attached to the original URL, it's flagged as a template.
			//A stray is usually in the remainder only.
			//Define the archive_string to help the string generator find the original archive.
			if( $temp['link_type'] != "stray" ) {
				$link['archive_string'] = $temp['link_string'];
			} else $link['archive_string'] = $temp['remainder'];
			//Expand original string and remainder indexes of previous link to contain the body of the current link.
			if( ( $tstart = strpos( $this->commObject->content, $link['archive_string'] ) ) !== false &&
			    ( $lstart = strpos( $this->commObject->content, $link['link_string'] ) ) !== false ) {
				if( preg_match( '/\=\=.*?\=\=/', substr( $this->commObject->content, $lstart,
				                                         $tstart - $lstart +
				                                         strlen( $temp['remainder'] . $temp['link_string'] )
				                               )
				) ) {
					return false;
				}
				//if( $tstart - strlen( $link['link_string'] ) - $lstart > 200 ) return false;
				$link['string']    = substr( $this->commObject->content, $lstart,
				                             $tstart - $lstart + strlen( $temp['remainder'] . $temp['link_string'] )
				);
				$link['remainder'] = str_replace( $link['link_string'], "", $link['string'] );
			}

			//Merge the archive information.
			$link['has_archive'] = true;
			//Transfer the archive type.  If it was a stray, redefine it as a template.
			if( $temp['link_type'] != "stray" ) {
				$link['archive_type'] = $temp['archive_type'];
			} else $link['archive_type'] = "template";
			//Transfer template information from current link to previous link.
			if( $link['archive_type'] == "template" ) {
				$link['archive_template'] = $temp['archive_template'];
				$link['tagged_dead']      = true;
				$link['tag_type']         = "implied";
			}
			$link['archive_url']  = $temp['archive_url'];
			$link['archive_time'] = $temp['archive_time'];
			if( !isset( $temp['archive_host'] ) ) $link['archive_host'] = $temp['archive_host'];
			//If the previous link is a citation template, but the archive isn't, then flag as invalid, for later merging.
			if( $link['link_type'] == "template" && $link['archive_type'] != "parameter" ) {
				$link['archive_type'] =
					"invalid";
			}

			//Transfer the remaining tags.
			if( $temp['tagged_paywall'] === true ) {
				$link['tagged_paywall'] = true;
			}
			if( $temp['is_paywall'] === true ) {
				$link['is_paywall'] = true;
			}
			if( $temp['permanent_dead'] === true ) {
				$link['permanent_dead'] = true;
			}
			if( $temp['tagged_dead'] === true ) {
				$link['tag_type'] = $temp['tag_type'];
				if( $link['tag_type'] == "template" ) {
					$link['tag_template'] = $temp['tag_template'];
				}
			}
			//Save previous link back into the passed array.
			if( !is_null( $lastLink['id'] ) ) {
				$returnArray[$lastLink['tid']]['reference'][$lastLink['id']] = $link;
			} else {
				$returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']] = $link;
			}
			//Unset the current link.  It's been merged into the previous link.
			if( !is_null( $currentLink['id'] ) ) {
				unset( $returnArray[$currentLink['tid']]['reference'][$currentLink['id']] );
			} else {
				unset( $returnArray[$currentLink['tid']] );
			}

			return true;
		} //Else if the original URLs in both links match and the archive is in the previous link, then merge into previous link
		elseif( $urlMatch && $link['is_archive'] === true
		) {
			//Raise the reversed flag for the string generator.  Archive URLs are usually in the remainder.
			$link['reversed'] = true;
			//Define the archive_string to help the string generator find the original archive.
			if( $link['link_type'] != "stray" ) {
				$link['archive_string'] = $link['link_string'];
			} else $link['archive_string'] = $link['remainder'];
			//Expand original string and remainder indexes of previous link to contain the body of the current link.
			if( ( $tstart = strpos( $this->commObject->content, $temp['string'] ) ) !== false &&
			    ( $lstart = strpos( $this->commObject->content, $link['archive_string'] ) ) !== false ) {
				if( preg_match( '/\=\=.*?\=\=/',
				                substr( $this->commObject->content, $lstart,
				                        $tstart - $lstart + strlen( $temp['string'] )
				                )
				) ) {
					return false;
				}
				//if( $tstart - $lstart - strlen( $link['archive_string'] ) > 200 ) return false;
				$link['string']      =
					substr( $this->commObject->content, $lstart, $tstart - $lstart + strlen( $temp['string'] ) );
				$link['link_string'] = $link['archive_string'];
				$link['remainder']   = str_replace( $link['archive_string'], "", $link['string'] );
			}
			//We now know that the previous link is only an attachment to the original URL.
			$link['is_archive'] = false;

			//If the previous link was thought to be a stray archive template, redefine it to the type "template"
			if( $link['link_type'] == "stray" ) $link['archive_type'] = "template";

			//Transfer the link type to the previous link
			$link['link_type'] = $temp['link_type'];
			//If it's a cite template, copy the template data over, and check for an invalid combination of archive and link usage.
			if( $link['link_type'] == "template" ) {
				if( $link['archive_type'] != "parameter" ) $link['archive_type'] = "invalid";
				$link['link_template'] = $temp['link_template'];
			}

			//Transfer access time
			$link['access_time'] = $temp['access_time'];

			//Transfer the miscellaneous tags
			if( $temp['tagged_paywall'] === true ) {
				$link['tagged_paywall'] = true;
			}
			if( $temp['is_paywall'] === true ) {
				$link['is_paywall'] = true;
			}
			if( $temp['permanent_dead'] === true ) {
				$link['permanent_dead'] = true;
			}
			if( $temp['tagged_dead'] === true ) {
				$link['tag_type'] = $temp['tag_type'];
				if( $link['tag_type'] == "template" ) {
					$link['tag_template'] = $temp['tag_template'];
				}
			}
			//Save new previous link data back into it's original location
			if( !is_null( $lastLink['id'] ) ) {
				$returnArray[$lastLink['tid']]['reference'][$lastLink['id']] = $link;
			} else {
				$returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']] = $link;
			}
			//Delete the index of the current link.
			if( !is_null( $currentLink['id'] ) ) {
				unset( $returnArray[$currentLink['tid']]['reference'][$currentLink['id']] );
			} else {
				unset( $returnArray[$currentLink['tid']] );
			}

			return true;
		}

		//No connection
		return false;
	}

	/**
	 * Look for stored access times in the DB, or update the DB with a new access time
	 * Adds access time to the link details.
	 *
	 * @param array $links A collection of links with respective details
	 *
	 * @access public
	 * @return array Returns the same array with the access_time parameters updated
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function updateAccessTimes( $links, $skipSearch = false )
	{
		$toGet = [];
		if( IAVERBOSE ) echo "Scanning " . count( $links ) . " links for access times...\n";
		foreach( $links as $tid => $link ) {
			if( !isset( $this->commObject->db->dbValues[$tid]['createglobal'] ) && $link['access_time'] == "x" ) {
				if( strtotime( $this->commObject->db->dbValues[$tid]['access_time'] ) > time() ||
				    strtotime( $this->commObject->db->dbValues[$tid]['access_time'] ) < 978307200 ) {
					$toGet[$tid] = $link['url'];
				} else {
					$links[$tid]['access_time'] = $this->commObject->db->dbValues[$tid]['access_time'];
				}
			} elseif( $link['access_time'] == "x" ) {
				$toGet[$tid] = $link['url'];
			} else {
				if( $link['access_time'] > time() || $link['access_time'] < 978307200 ) {
					$toGet[$tid] = $link['url'];
				} else {
					$this->commObject->db->dbValues[$tid]['access_time'] = $link['access_time'];
				}
			}
		}
		if( IAVERBOSE ) echo "Links with missing access times: " . count( $toGet ) . "\n";
		if( !empty( $toGet ) && $skipSearch === false ) {
			$toGet = $this->commObject->getTimesAdded( $toGet );
			foreach( $toGet as $tid => $time ) {
				$this->commObject->db->dbValues[$tid]['access_time'] = $links[$tid]['access_time'] = $time;
			}
		}

		return $links;
	}

	/**
	 * Update the link details array with values stored in the DB, and vice versa
	 * Updates the dead status of the given link
	 *
	 * @param array $link Array of link with details
	 * @param int $tid Array key to preserve index keys
	 *
	 * @access public
	 * @return array Returns the same array with updated values, if any
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function updateLinkInfo( $links )
	{
		$toCheck = [];
		foreach( $links as $tid => $link ) {
			if( $this->commObject->config['verify_dead'] == 1 &&
			    $this->commObject->db->dbValues[$tid]['live_state'] < 5 &&
			    ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 0 ||
			      $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 ) &&
			    ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200 ) &&
			    ( $this->commObject->db->dbValues[$tid]['live_state'] != 3 ||
			      ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 604800 ) )
			) {
				$toCheck[$tid] = $link['url'];
			}
		}
		$results = $this->deadCheck->areLinksDead( $toCheck );
		$errors  = $this->deadCheck->getErrors();
		$details = $this->deadCheck->getRequestDetails();
		//$snapshots  = $this->deadCheck->getRequestSnapshots();
		$externalIP = file_get_contents( "http://ipecho.net/plain" );
		$hostName   = gethostname();

		$whitelisted = [];
		if( USEADDITIONALSERVERS === true ) {
			$toValidate = [];
			foreach( $toCheck as $tid => $url ) {
				if( $results[$url] === true && $this->commObject->db->dbValues[$tid]['live_state'] == 1 ) {
					$toValidate[] = $url;
				}
			}
			if( !empty( $toValidate ) ) {
				foreach( explode( "\n", CIDSERVERS ) as $server ) {
					if( empty( $toValidate ) ) break;
					$serverResults = API::runCIDServer( $server, $toValidate );
					$toValidate    = array_flip( $toValidate );
					if( !is_null( $serverResults ) ) {
						foreach( $serverResults['results'] as $surl => $sResult ) {
							if( $surl == "errors" ) continue;
							if( $sResult === false ) {
								$whitelisted[] = $surl;
								unset( $toValidate[$surl] );
							} else {
								$errors[$surl] = $serverResults['results']['errors'][$surl];
							}
						}
					} elseif( is_null( $serverResults ) ) {
						echo "ERROR: $server did not respond!\n";
					}
					$toValidate = array_flip( $toValidate );
				}
			}
		}

		$logged = [];
		foreach( $this->commObject->db->dbValues as $dbValue ) {
			if( isset( $results[$dbValue['url']] ) ) {
				if( !empty( $errors[$dbValue['url']] ) ) {
					$error = $errors[$dbValue['url']];
				} else $error = null;

				if( isset( $dbValue['url_id'] ) && !in_array( $dbValue['url_id'], $logged ) ) {
					$logged[] = $dbValue['url_id'];
					$this->commObject->db->logScanResults( $dbValue['url_id'],
					                                       $results[$dbValue['url']], $externalIP, $hostName,
					                                       $details[$dbValue['url']]['http_code'],
					                                       $details[$dbValue['url']], $error
					);
				}
			}
		}

		foreach( $links as $tid => $link ) {
			if( array_search( $link['url'], $whitelisted ) !== false ) {
				$this->commObject->db->dbValues[$tid]['paywall_status'] = 3;
				$link['is_dead']                                        = false;
				$links[$tid]                                            = $link;
				continue;
			}

			$link['is_dead'] = null;
			if( $this->commObject->config['verify_dead'] == 1 ) {
				if( $this->commObject->db->dbValues[$tid]['live_state'] < 5 &&
				    ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 0 ||
				      $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 ) &&
				    ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200 ) &&
				    ( $this->commObject->db->dbValues[$tid]['live_state'] != 3 ||
				      ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 604800 ) )
				) {
					$link['is_dead']                                        = $results[$link['url']];
					$this->commObject->db->dbValues[$tid]['last_deadCheck'] = time();
					if( $link['tagged_dead'] === false && $link['is_dead'] === true ) {
						if( $this->commObject->db->dbValues[$tid]['live_state'] ==
						    4 ) {
							$this->commObject->db->dbValues[$tid]['live_state'] = 2;
						} else $this->commObject->db->dbValues[$tid]['live_state']--;
					} elseif( $link['tagged_dead'] === false && $link['is_dead'] === false &&
					          $this->commObject->db->dbValues[$tid]['live_state'] != 3
					) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 3;
					} elseif( $link['tagged_dead'] === true && $link['is_dead'] === true ) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 0;
					} else {
						$this->commObject->db->dbValues[$tid]['live_state'] = 3;
					}

					if( $link['is_dead'] === true && $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 &&
					    preg_match( '/4\d\d/i', $errors[$link['url']], $code ) &&
					    array_search( $code[0], [ 401, 402, 403, 412, 428, 440, 449 ] ) ) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 5;
					}
				}
				if( isset( $this->commObject->db->dbValues[$tid]['live_state'] ) ) {
					if( in_array( $this->commObject->db->dbValues[$tid]['live_state'], [ 3, 7 ] ) ) {
						$link['is_dead'] = false;
					}
					if( in_array( $this->commObject->db->dbValues[$tid]['live_state'], [ 1, 2, 4, 5 ] ) ) {
						$link['is_dead'] = null;
					}
					if( in_array( $this->commObject->db->dbValues[$tid]['live_state'], [ 0, 6 ] ) ) {
						$link['is_dead'] = true;
					}
				} else {
					$link['is_dead'] = null;
				}

				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 &&
				    $this->commObject->db->dbValues[$tid]['live_state'] !== 6 ) {
					$link['is_dead'] = false;
				}

				if( ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 2 ||
				      ( isset( $link['invalid_archive'] ) && !isset( $link['ignore_iarchive_flag'] ) ) ) ||
				    ( $this->commObject->config['tag_override'] == 1 && $link['tagged_dead'] === true )
				) {
					$link['is_dead'] = true;
				}
			}
			$links[$tid] = $link;
		}

		return $links;
	}

	/**
	 * Rescue a link
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links that were modified
	 * @param array $temp Cached result value from archive retrieval function
	 *
	 * @access protected
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id )
	{
		//The initial assumption is that we are adding an archive to a URL.
		$modifiedLinks["$tid:$id"]['type']       = "addarchive";
		$modifiedLinks["$tid:$id"]['link']       = $link['url'];
		$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

		//The newdata index is all the data being injected into the link array.  This allows for the preservation of the old data for easier manipulation and maintenance.
		$link['newdata']['has_archive'] = true;
		$link['newdata']['archive_url'] = $temp['archive_url'];
		if( !empty( $link['archive_fragment'] ) ) {
			$link['newdata']['archive_url'] .= "#" . $link['archive_fragment'];
		} elseif( !empty( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['fragment'];
		$link['newdata']['archive_time'] = $temp['archive_time'];

		//Set the conversion to cite templates bit
		$convertToCite = $this->commObject->config['convert_to_cites'] == 1 &&
		                 ( isset( $link['converttocite'] ) || $link['link_type'] == "stray" );

		//Set the cite template bit
		$useCiteGenerator = ( ( $link['link_type'] == "link" || $link['link_type'] == "stray" ) && $convertToCite &&
		                      $link['is_reference'] ) ||
		                    $link['link_type'] == "template";

		//Set the archive template bit
		$useArchiveGenerator = $link['is_archive'] === false && $link['link_type'] != "stray";

		//Set the plain link bit
		$usePlainLink = $link['link_type'] == "link";

		if( !$useCiteGenerator || !$this->generator->generateNewCitationTemplate( $link ) ) {
			if( !$useArchiveGenerator || !$this->generator->generateNewArchiveTemplate( $link, $temp ) ) {
				if( !$usePlainLink ) {
					unset( $link['newdata']['archive_url'], $link['newdata']['archive_time'], $link['newdata']['has_archive'] );
					unset( $modifiedLinks["$tid:$id"], $link['newdata'] );

					return false;
				} else {
					$link['newdata']['archive_type'] = "link";
					$link['newdata']['is_archive']   = true;
					$link['newdata']['tagged_dead']  = false;
				}
			} else {
				if( empty( $link['newdata']['archive_type'] ) || $useCiteGenerator ) {
					$link['newdata']['archive_type'] =
						"template";
				}
				$link['newdata']['tagged_dead'] = false;
				$link['newdata']['is_archive']  = false;
			}
		} else {
			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
				if( !empty( $link['newdata']['link_template']['template_map'] ) ) {
					$map =
						$link['newdata']['link_template']['template_map'];
				} elseif( !empty( $link['link_template']['template_map'] ) ) {
					$map =
						$link['link_template']['template_map'];
				}


				if( !empty( $map['services']['@default']['url'] ) ) {
					foreach( $map['services']['@default']['url'] as $dataIndex ) {
						foreach( $map['data'][$dataIndex]['mapto'] as $paramIndex ) {
							if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ||
							    isset( $link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] ) ) {
								break 2;
							}
						}
					}
				}

				if( !isset( $link['template_url'] ) ) {
					$link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] = $link['url'];
				} else {
					$link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] =
						$link['template_url'];
				}

				$modifiedLinks["$tid:$id"]['type'] = "fix";
			}

			//Force change the link type to a template.  This part is not within the scope of the array merger, as it's too high level.
			if( $convertToCite ) $link['link_type'] = "template";
		}

		//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
		if( isset( $link['convert_archive_url'] ) ||
		    ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
		    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
		) {
			$modifiedLinks["$tid:$id"]['type'] = "fix";
			if( isset( $link['convert_archive_url'] ) ) $link['newdata']['converted_archive'] = true;
		}
		//If we ended up changing the archive URL despite invalid flags, we should mention that change instead.
		if( $link['has_archive'] === true && $link['archive_url'] != $temp['archive_url'] &&
		    !isset( $link['convert_archive_url'] )
		) {
			$modifiedLinks["$tid:$id"]['type']       = "modifyarchive";
			$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
		}
		unset( $temp );

		return true;
	}

	/**
	 * Modify link that can't be rescued
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links modified array
	 *
	 * @access protected
	 * @abstract
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function noRescueLink( &$link, &$modifiedLinks, $tid, $id )
	{
		$modifiedLinks["$tid:$id"]['type'] = "tagged";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		if( $link['link_type'] == "template" &&
		    ( $this->commObject->config['tag_cites'] == 1 || $link['has_archive'] === true ) ) {

			$magicwords['is_dead'] = "yes";
			$map                   = $link['link_template']['template_map'];

			if( isset( $map['services']['@default']['deadvalues'] ) ) {
				$link['newdata']['tag_type'] = "parameter";
				$parameter                   = null;

				$dataIndex = $map['services']['@default']['deadvalues'][0];

				foreach( $map['data'][$dataIndex['index']]['mapto'] as $paramIndex ) {
					if( is_null( $parameter ) ) $parameter = $map['params'][$paramIndex];

					if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ) {
						$parameter = $map['params'][$paramIndex];
						break;
					}
				}

				$link['newdata']['link_template']['parameters'][$parameter] =
					$map['data'][$dataIndex['index']]['valueString'];

				if( !empty( $map['services']['@default']['others'] ) ) {
					foreach(
						$map['services']['@default']['others'] as $dataIndex
					) {
						$parameter = null;
						foreach( $map['data'][$dataIndex]['mapto'] as $paramIndex ) {
							if( is_null( $parameter ) ) $parameter = $map['params'][$paramIndex];

							if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ) {
								$parameter = $map['params'][$paramIndex];
								break;
							}
						}

						$link['newdata']['link_template']['parameters'][$parameter] =
							$map['data'][$dataIndex]['valueString'];
					}
				}

				if( isset( $link['newdata']['link_template']['parameters'] ) ) {
					foreach( $link['newdata']['link_template']['parameters'] as $param => $value ) {
						$link['newdata']['link_template']['parameters'][$param] =
							$this->commObject->getConfigText( $value, $magicwords );
					}
				}
			} else {
				return false;
			}
		} else {
			$deadlinkTags = DB::getConfiguration( WIKIPEDIA, "wikiconfig", "deadlink_tags" );

			if( empty( $deadlinkTags ) ) return false;

			if( $this->commObject->config['templatebehavior'] == "append" ) {
				$link['newdata']['tag_type'] = "template";
			} elseif( $this->commObject->config['templatebehavior'] == "swallow" ) {
				$link['newdata']['tag_type'] =
					"template-swallow";
			}

			$link['newdata']['tag_template']['name'] = trim( $deadlinkTags[0], "{}" );

			if( !empty( $this->commObject->config['deadlink_tags_data'] ) ) {
				$deadMap = $this->commObject->config['deadlink_tags_data']->getMap();
				foreach(
					$deadMap['services']['@default'] as $category => $categorySet
				) {
					foreach( $categorySet as $dataIndex ) {
						if( $category == "permadead" ) {
							$dataIndex = $dataIndex['index'];
						}
						if( is_array( $dataIndex ) ) continue;

						$paramIndex =
							$deadMap['data'][$dataIndex]['mapto'][0];

						$link['newdata']['tag_template']['parameters'][$deadMap['params'][$paramIndex]] =
							$deadMap['data'][$dataIndex]['valueString'];
					}
				}

				$magicwords = [];
				if( isset( $link['url'] ) ) $magicwords['url'] = $link['url'];
				$magicwords['timestampauto'] = $this->generator->retrieveDateFormat( $link['string'], $this );
				$magicwords['linkstring']    = $link['link_string'];
				$magicwords['remainder']     = $link['remainder'];
				$magicwords['string']        = $link['string'];
				$magicwords['permadead']     = true;
				$magicwords['url']           = $link['url'];

				if( isset( $link['title'] ) ) {
					$magicwords['title'] = $link['title'];
				} else $magicwords['title'] = "";

				if( isset( $link['newdata']['tag_template']['parameters'] ) ) {
					foreach( $link['newdata']['tag_template']['parameters'] as $param => $value ) {
						$link['newdata']['tag_template']['parameters'][$param] =
							$this->commObject->getConfigText( $value, $magicwords );
					}
				}
			} else {
				$link['newdata']['tag_template']['parameters'] = [];
			}
		}

		return true;
	}

	/**
	 * Determine if the bot was likely reverted
	 *
	 * @param array $newlink The new link to look at
	 * @param array $lastRevLinks The collection of link data from the previous revision to compare with.
	 *
	 * @access public
	 * @return array Details about every link on the page
	 * @return bool|int If the edit was likely the bot being reverted, it will return the first bot revid it occurred
	 *     on.
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public function isEditReversed( $newlink, $lastRevLinkss )
	{
		foreach( $lastRevLinkss as $revisionID => $lastRevLinks ) {
			$lastRevLinks = $lastRevLinks->get( true );
			if( $newlink['link_type'] == "reference" ) {
				foreach( $newlink['reference'] as $tid => $link ) {
					if( !is_numeric( $tid ) ) continue;
					if( !isset( $link['newdata'] ) ) continue;

					$breakout = false;
					foreach( $lastRevLinks as $revLink ) {
						if( !is_array( $revLink ) ) continue;
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

					if( isset( $oldLink ) && is_array( $oldLink ) ) {
						if( API::isReverted( $oldLink, $link ) ) {
							return $revisionID;
						} else continue;
					} else continue;
				}
			} else {
				$link = $newlink[$newlink['link_type']];

				$breakout = false;
				foreach( $lastRevLinks as $revLink ) {
					if( !is_array( $revLink ) ) continue;
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

				if( is_array( $oldLink ) ) {
					if( API::isReverted( $oldLink, $link ) ) {
						return $revisionID;
					} else continue;
				} else continue;
			}
		}

		return false;
	}

	/**
	 * Determine if the given link is likely a false positive
	 *
	 * @param string|int $id array index ID
	 * @param array $link Array of link information with details
	 *
	 * @access public
	 * @return array Details about every link on the page
	 * @return bool If the link is likely a false positive
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public function isLikelyFalsePositive( $id, $link, &$makeModification = true )
	{
		if( is_null( $makeModification ) ) $makeModification = true;
		if( $this->commObject->db->dbValues[$id]['live_state'] == 0 &&
		    $this->commObject->db->dbValues[$id]['paywall_status'] !== 2
		) {
			if( $link['has_archive'] === true ) return false;
			if( $link['tagged_dead'] === true ) {
				if( $link['tag_type'] == "parameter" ) {
					$makeModification = false;

					return true;
				}

				return false;
			}

			$sql =
				"SELECT * FROM externallinks_fpreports WHERE `report_status` = 2 AND `report_url_id` = {$this->commObject->db->dbValues[$id]['url_id']};";
			if( $res = $this->dbObject->queryDB( $sql ) ) {
				if( mysqli_num_rows( $res ) > 0 ) {
					mysqli_free_result( $res );

					return false;
				}
			}

			$makeModification = false;

			return true;
		} else {
			if( $link['tagged_dead'] === true ) {
				if( $link['tag_type'] == "parameter" ) $makeModification = false;

				return false;
			}
		}

		return false;
	}

	/**
	 * Return whether or not to skip editing the main article.
	 *
	 * @access public
	 * @return bool True to skip
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function leaveTalkOnly()
	{
		return preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['talk_only_tags'] ),
		                   $this->commObject->content,
		                   $garbage
		);
	}

	/**
	 * Return whether or not to leave a talk page message.
	 *
	 * @access protected
	 * @return bool
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function leaveTalkMessage()
	{
		return !preg_match( DataGenerator::fetchTemplateRegex( $this->commObject->config['no_talk_tags'] ),
		                    $this->commObject->content,
		                    $garbage
		);
	}

	/**
	 * Destroys the class
	 *
	 * @access public
	 * @return void
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public function __destruct()
	{
		$this->deadCheck  = null;
		$this->commObject = null;
	}
}
