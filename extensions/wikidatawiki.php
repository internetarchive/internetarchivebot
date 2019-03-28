<?php
/*
	Copyright (c) 2015-2018, Maximilian Doerr

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
 * wikidatawikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

use Wikimedia\DeadlinkChecker\CheckIfDead;

/**
 * wikidatawikiParser class
 * Extension of the master parser class specifically for www.wikidata.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class wikidatawikiParser extends Parser {

	protected $qid;

	/**
	 * Parser class constructor
	 *
	 * @param API $commObject
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 */
	public function __construct( API $commObject ) {
		$this->commObject = $commObject;
		$this->deadCheck = new CheckIfDead( 30, 60, CIDUSERAGENT, true, true );
		if( AUTOFPREPORT === true ) $this->dbObject = new DB2();

		$this->qid = $this->commObject->qid;
	}

	/**
	 * Master page analyzer function.  Analyzes the entire page's content,
	 * retrieves specified URLs, and analyzes whether they are dead or not.
	 * If they are dead, the function acts based on onwiki specifications.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 *
	 * @param array $modifiedLinks Pass back a list of links modified
	 * @param bool $webRequest Prevents analysis of large pages that may cause the tool to timeout
	 *
	 * @return array containing analysis statistics of the page
	 */
	public function analyzePage( &$modifiedLinks = [], $webRequest = false ) {
		if( DEBUG === false || LIMITEDRUN === true ) {
			file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID, serialize( [
				                                                                 'title' => $this->commObject->page,
				                                                                 'id'    => $this->commObject->pageid
			                                                                 ]
			                                                    )
			);
		}
		$dumpcount = 0;
		unset( $tmp );
		echo "Analyzing {$this->commObject->qid} ({$this->commObject->pageid})...\n";
		//Tare statistics variables
		$modifiedLinks = [];
		$archiveProblems = [];
		$archived = 0;
		$rescued = 0;
		$notrescued = 0;
		$tagged = 0;
		$waybackadded = 0;
		$otheradded = 0;
		$timestamp = date( "Y-m-d\TH:i:s\Z" );
		$performUpdate = false;

		echo "Fetching all external links...\n";
		$links = $this->getExternalLinks( false, false, $webRequest );
		if( $links === false && $webRequest === true ) return false;

		$analyzed = $links['count'];
		unset( $links['count'] );

		//Process the links
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
			foreach( $links as $tid => $link ) {
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) continue;
				//Create a flag that marks the source as being improperly formatting and needing fixing
				$invalidEntry = ( $link['has_archive'] === true && ( isset( $link['invalid_archive'] ) ||
				                                                     ( $this->commObject->config['convert_archives'] ==
				                                                       1 &&
				                                                       isset( $link['convert_archive_url'] ) &&
				                                                       ( !isset( $link['converted_encoding_only'] ) ||
				                                                         $this->commObject->config['convert_archives_encoding'] ==
				                                                         1 ) ) ) );
				//Create a flag that determines basic clearance to edit a source.
				$linkRescueClearance =
					( ( $this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false ) ||
					  $invalidEntry === true );
				//DEAD_ONLY = 0; Modify ALL links clearance flag
				$dead0 = $this->commObject->config['dead_only'] == 0;
				//DEAD_ONLY = 1 || 2; Modify all dead links clearance flag
				$dead1 = $dead2 =
					( $this->commObject->config['dead_only'] == 1 || $this->commObject->config['dead_only'] == 2 ) &&
					$link['is_dead'] === true;

				//Forced update clearance
				$forceClearance = ( isset( $link['force'] ) ) ||
				                  ( isset( $link['force_when_dead'] ) && $link['is_dead'] === true ) ||
				                  ( isset( $link['force_when_alive'] ) && $link['is_dead'] === false );

				if( $i == 0 && $link['is_dead'] !== true && $this->commObject->config['archive_alive'] == 1 ) {
					//Populate a list of URLs to check, if an archive exists.
					$toArchive[$tid] = $link['url'];
				} elseif( $i >= 1 && $link['is_dead'] !== true && $this->commObject->config['archive_alive'] == 1 &&
				          $checkResponse[$tid] !== true ) {
					//Populate URLs to submit for archiving.
					if( $i == 1 ) {
						$toArchive[$tid] = $link['url'];
					} else {
						//If it archived, then tally the success, otherwise, note it.
						if( $archiveResponse[$tid] === true ) {
							$archived++;
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
						$toFetch[$tid] = [
							$link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ?
								( $link['access_time'] != "x" ? $link['access_time'] : null ) : null )
						];
					} elseif( $i == 2 ) {
						//Do actual work
						if( ( $temp = $fetchResponse[$tid] ) !== false && !is_null( $temp ) ) {
							if( $this->rescueLink( $link, $modifiedLinks, $temp, $tid, 0 ) ===
							    true ) $rescued++;
						}
					}
				}

				if( $i == 2 && isset( $modifiedLinks[$tid] ) ) {
					if( $this->commObject->config['notify_on_talk_only'] == 2 ) {
						switch( $modifiedLinks["$tid"]['type'] ) {
							case "addarchive":
							case "modifyarchive":
							case "fix":
								$modifiedLinks["$tid"]['talkonly'] = true;
								unset( $link['newdata'] );
						}
					}
				}
				$links[$tid] = $link;

				//Check if the newdata index actually contains newdata and if the link should be touched.  Avoid redundant work and edits this way.
				if( $i == 2 && self::newIsNew( $links[$tid] ) ) {
					$performUpdate = true;
				}
			}

			//Check if archives exist for the provided URLs
			if( $i == 0 && !empty( $toArchive ) ) {
				$checkResponse = $this->commObject->isArchived( $toArchive );
				$checkResponse = $checkResponse['result'];
				$toArchive = [];
			}
			$errors = [];
			//Submit provided URLs for archiving
			if( $i == 1 && !empty( $toArchive ) ) {
				$archiveResponse = $this->commObject->requestArchive( $toArchive );
				$errors = $archiveResponse['errors'];
				$archiveResponse = $archiveResponse['result'];
			}
			//Retrieve snapshots of provided URLs
			if( $i == 1 && !empty( $toFetch ) ) {
				$fetchResponse = $this->commObject->retrieveArchive( $toFetch );
				$fetchResponse = $fetchResponse['result'];
			}
		}

		$archiveResponse = $checkResponse = $fetchResponse = null;
		unset( $archiveResponse, $checkResponse, $fetchResponse );
		echo "Rescued: $rescued; Archived: $archived; Memory Used: " .
		     ( memory_get_usage( true ) / 1048576 ) . " MB; Max System Memory Used: " .
		     ( memory_get_peak_usage( true ) / 1048576 ) . " MB\n";
		//Talk page stuff.  This part leaves a message on archives that failed to save on the wayback machine.
		if( !empty( $archiveProblems ) && $this->commObject->config['notify_error_on_talk'] == 1 ) {
			$out = "";
			foreach( $archiveProblems as $id => $problem ) {
				$magicwords = [];
				$magicwords['problem'] = $problem;
				$magicwords['error'] = $errors[$id];
				$out .= "* " . $this->commObject->getConfigText( "plerror", $magicwords ) . "\n";
			}
			$body = $this->commObject->getConfigText( "talk_error_message", [ 'problematiclinks' => $out ] ) . "~~~~";
			API::edit( "Talk:{$this->commObject->qid}", $body,
			           $this->commObject->getConfigText( "errortalkeditsummary", [] ), false, true, "new",
			           $this->commObject->getConfigText( "talk_error_message_header", [] )
			);
		}
		foreach( $modifiedLinks as $link ) {
			if( $link['type'] == "addarchive" ) {
				if( self::getArchiveHost( $link['newarchive'], $data ) == "wayback" ) {
					$waybackadded++;
				} else $otheradded++;
			}
		}
		$pageModified = false;
		//This is the courtesy message left behind when it edits the main article.
		if( $performUpdate === true ||
		    ( $this->commObject->config['notify_on_talk_only'] == 2 && !empty( $modifiedLinks ) ) ) {
			$pageModified = $performUpdate;
			$magicwords = [];
			$magicwords['namespacepage'] = $this->commObject->page;
			$magicwords['linksmodified'] = $tagged + $rescued;
			$magicwords['linksrescued'] = $rescued;
			$magicwords['linksnotrescued'] = $notrescued;
			$magicwords['linkstagged'] = $tagged;
			$magicwords['linksarchived'] = $archived;
			$magicwords['linksanalyzed'] = $analyzed;
			$magicwords['pageid'] = $this->commObject->pageid;
			$magicwords['title'] = urlencode( $this->commObject->page );
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
					wikidatawikiAPI::edit( $this->commObject->qid, $links,
					                       $this->commObject->getConfigText( "maineditsummary", $magicwords ), false,
					                       $timestamp
					);
			} else $magicwords['logstatus'] = "posted";
			if( isset( $revid ) ) {
				$magicwords['diff'] = str_replace( "api.php", "index.php", API ) . "?diff=prev&oldid=$revid";
				$magicwords['revid'] = $revid;
			} else {
				$magicwords['diff'] = "";
				$magicwords['revid'] = "";
			}
			if( ( ( $this->commObject->config['notify_on_talk'] == 1 && isset( $revid ) && $revid !== false ) ||
			      $this->commObject->config['notify_on_talk_only'] == 1 ||
			      $this->commObject->config['notify_on_talk_only'] == 2 || $this->leaveTalkOnly() == true ) &&
			    $this->leaveTalkMessage() == true
			) {
				for( $talkOnlyFlag = 0; $talkOnlyFlag <= (int) $addTalkOnly; $talkOnlyFlag++ ) {
					$out = "";
					$editTalk = false;
					$talkOnly = $this->commObject->config['notify_on_talk_only'] == 1 || $this->leaveTalkOnly() ||
					            (bool) $talkOnlyFlag == true ||
					            ( $this->commObject->config['notify_on_talk_only'] == 2 && !$pageModified );
					if( (bool) $talkOnlyFlag === true ) {
						//Reverse the numbers
						$magicwords['linksmodified'] = $rescued + $tagged - $magicwords['linksmodified'];
						$magicwords['linksrescued'] = $rescued - $magicwords['linksrescued'];
						$magicwords['linkstagged'] = $tagged - $magicwords['linkstagged'];
					}
					foreach( $modifiedLinks as $tid => $link ) {
						if( isset( $link['talkonly'] ) && $talkOnly === false ) continue;
						if( (bool) $talkOnlyFlag === true && !isset( $link['talkonly'] ) ) continue;
						$magicwords2 = [];
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
								} else $tout .= $this->commObject->getConfigText( "mladdarchivetalkonly", $magicwords2
								);
								$editTalk = true;
								break;
							case "modifyarchive":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mlmodifyarchive", $magicwords2 );
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
									$tout .= $this->commObject->getConfigText( "mltaggedtalkonly", $magicwords2 );
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
									$tout .= $this->commObject->getConfigText( "mltagremovedtalkonly", $magicwords2 );
									$editTalk = true;
								}
								break;
							default:
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mldefault", $magicwords2 );
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
						wikidataAPI::edit( "Talk:{$this->commObject->page}", $body,
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

		unset( $this->commObject, $newtext, $history, $res, $db );
		$returnArray = [
			'linksanalyzed' => $analyzed, 'linksarchived' => $archived, 'linksrescued' => $rescued,
			'linkstagged'   => $tagged, 'pagemodified' => $pageModified, 'waybacksadded' => $waybackadded,
			'othersadded'   => $otheradded, 'revid' => ( isset( $revid ) ? $revid : false )
		];

		return $returnArray;
	}

	/**
	 * Fetch all links in an article
	 *
	 * @param bool $referenceOnly Fetch references only
	 * @param string $text Page text to analyze
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Details about every link on the page
	 */
	public function getExternalLinks( $referenceOnly = false, $json = false, $webRequest = false ) {
		$linksAnalyzed = 0;
		$returnArray = [];
		$toCheck = [];
		$parseData = $this->commObject->getEntities( $this->qid );
		if( $parseData === false ) return false;

		foreach( $parseData['claims'] as $property => $data ) {
			//P813 is an access date property
			//P854 is a reference URL property
			//P973 is a primary reference URL
			//P1065 is an archive URL for the reference
			//P2960 is an archive date for P1065

			$returnArray[$property]['has_archive'] = false;
			$returnArray[$property]['is_archive'] = false;
			if( $property == "P973" ) {
				$returnArray['P973']['url'] = $data[0]['mainsnak']['datavalue']['value'];
				if( !empty( $data[0]['references'] ) ) {
					if( isset( $data[0]['references'][0]['snaks']['P1065'] ) ) {
						$returnArray['P973']['archive_url'] =
							$data[0]['references'][0]['snaks']['P1065'][0]['datavalue']['value'];
						if( !API::isArchive( $returnArray['P973']['archive_url'], $returnArray['P973'] ) ) {
							$returnArray['invalid_archive'] = true;
						}
						$returnArray['P973']['has_archive'] = true;
					}
					if( isset( $data[0]['references'][0]['snaks']['P813'] ) ) {
						$returnArray['P973']['access_time'] =
							strtotime( $data[0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['time'] );
					} else {
						$returnArray['P973']['access_time'] = "x";
					}
				}
			} elseif( $property == "P1065" ) {
				$returnArray['P1065']['archive_url'] = $data[0]['mainsnak']['datavalue']['value'];
				$returnArray['P1065']['has_archive'] = true;
				$returnArray['P1065']['is_archive'] = true;
				if( !API::isArchive( $returnArray['P1065']['archive_url'], $returnArray['P1065'] ) ) {
					$returnArray['invalid_archive'] = true;
				}
				if( !empty( $data[0]['references'] ) ) {
					if( isset( $data[0]['references'][0]['snaks']['P854'] ) ) {
						$returnArray['P1065']['url'] =
							$data[0]['references'][0]['snaks']['P854'][0]['datavalue']['value'];
					} else {
						$returnArray['P1065']['stray'] = true;
					}
				}
				if( isset( $data[0]['references'][0]['snaks']['P813'] ) ) {
					$returnArray['P1065']['access_time'] =
						strtotime( $data[0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['time'] );
				} else {
					$returnArray['P1065']['access_time'] = "x";
				}
			} else {
				$returnArray[$property]['has_archive'] = false;
				$returnArray[$property]['is_archive'] = false;
				if( isset( $data[0]['references'][0]['snaks']['P854'] ) ) {
					$returnArray[$property]['url'] =
						$data[0]['references'][0]['snaks']['P854'][0]['datavalue']['value'];
				}
				if( isset( $data[0]['references'][0]['snaks']['P1065'] ) ) {
					$returnArray[$property]['archive_url'] =
						$data[0]['references'][0]['snaks']['P1065'][0]['datavalue']['value'];
					if( !API::isArchive( $returnArray[$property]['archive_url'], $returnArray[$property] ) ) {
						$returnArray['invalid_archive'] = true;
					}
					$returnArray[$property]['has_archive'] = true;
				}
				if( isset( $data[0]['references'][0]['snaks']['P813'] ) ) {
					$returnArray[$property]['access_time'] =
						strtotime( $data[0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['time'] );
				} else {
					$returnArray[$property]['access_time'] = "x";
				}
			}

			if( !isset( $returnArray[$property]['access_time'] ) ) $returnArray[$property]['access_time'] = "x";
			$returnArray[$property]['tagged_paywall'] = false;
			$returnArray[$property]['tagged_dead'] = false;

			if( !isset( $returnArray[$property]['url'] ) ) {
				$returnArray[$property] = [ 'ignore' => true ];
				continue;
			}

			if( preg_match( '/' . $this->schemelessURLRegex . '/i', $returnArray[$property]['url'], $match ) ) {
				//Sanitize the URL to keep it consistent in the DB.
				$returnArray[$property]['url'] =
					$this->deadCheck->sanitizeURL( $match[0], true );
				//If the sanitizer can't handle the URL, ignore the reference to prevent a garbage edit.
				if( $returnArray[$property]['url'] == "https:///" ) {
					$returnArray[$property] = [ 'ignore' => true ];
					continue;
				}
				if( $returnArray[$property]['url'] == "https://''/" ) {
					$returnArray[$property] = [ 'ignore' => true ];
					continue;
				}
				if( $returnArray[$property]['url'] == "http://''/" ) {
					$returnArray[$property] = [ 'ignore' => true ];
					continue;
				}
				if( isset( $match[1] ) ) {
					$returnArray[$property]['fragment'] = $match[1];
				} else $returnArray[$property]['fragment'] = null;
				if( isset( $returnArray[$property]['archive_url'] ) ) {
					$parts = $this->deadCheck->parseURL( $returnArray[$property]['archive_url'] );
					if( isset( $parts['fragment'] ) ) {
						$returnArray[$property]['archive_fragment'] = $parts['fragment'];
					} else $returnArray[$property]['archive_fragment'] = null;
					$returnArray[$property]['archive_url'] = preg_replace( '/#.*/', '', $returnArray[$property]['archive_url'] );
				}
			}

			$linksAnalyzed++;

			if( isset( $returnArray[$property]['ignore'] ) ) {
				unset( $returnArray[$property] );
				continue;
			}

			if( $json === false ) $this->commObject->db->retrieveDBValues( $returnArray[$property], $property );
			$toCheck[$property] = $returnArray[$property];

		}

		//Retrieve missing access times that couldn't be extrapolated from the parser.
		if( $json === false ) $toCheck = $this->updateAccessTimes( $toCheck, true );
		//Set the live states of all the URL, and run a dead check if enabled.
		if( $json === false ) $toCheck = $this->updateLinkInfo( $toCheck );
		//Transfer data back to the return array.
		foreach( $toCheck as $tid => $link ) {
			$returnArray[$tid] = $link;
		}
		$returnArray['count'] = $linksAnalyzed;

		return $returnArray;
	}

	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id = 0 ) {
		//The initial assumption is that we are adding an archive to a URL.
		$modifiedLinks["$tid:$id"]['type'] = "addarchive";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

		//The newdata index is all the data being injected into the link array.  This allows for the preservation of the old data for easier manipulation and maintenance.
		$link['newdata']['has_archive'] = true;
		$link['newdata']['archive_url'] = $temp['archive_url'];
		if( !empty( $link['archive_fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['archive_fragment'];
		elseif( !empty( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['fragment'];
		$link['newdata']['archive_time'] = $temp['archive_time'];

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
			$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
			$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
		}
		unset( $temp );

		return true;
	}

	/**
	 * Verify that newdata is actually different from old data
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param mixed $link
	 *
	 * @return bool Whether the data in the link array contains new data from the old data.
	 */
	public static function newIsNew( $link ) {
		$t = false;
		if( isset( $link['newdata'] ) ) {
			foreach(
				$link['newdata'] as $parameter => $value
			) {
				if( !isset( $link[$parameter] ) ||
				    $value != $link[$parameter]
				) {
					$t = true;
				}
			}
		}

		return $t;
	}
}

class wikidatawikiAPI extends API {

	protected static $lastEntity;

	public $qid;

	public function __construct( $qid, $pageid, $config ) {
		$this->qid = $qid;
		$this->pageid = $pageid;
		$this->config = $config;

		$tmp = DBCLASS;
		$this->db = new $tmp( $this );
	}

	public static function getEntities( $qid ) {
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$get = http_build_query( [
			                         'action' => 'wbgetentities',
			                         'ids'    => $qid,
			                         'format' => 'json'
		                         ]
		);

		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url = ( API . "?$get" ) );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER,
		             [ self::generateOAuthHeader( 'GET', API . "?$get" ) ]
		);
		$data = curl_exec( self::$globalCurl_handle );

		$headers = curl_getinfo( self::$globalCurl_handle );

		if( $headers['http_code'] == 404 ) return false;

		self::$lastEntity = json_decode( $data, true );

		return self::$lastEntity['entities'][$qid];
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
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return mixed Revid if successful, else false
	 */
	public static function edit( $qid, $links, $summary, $minor = false, $timestamp = false, $bot = true,
	                             $section = false, $title = "", &
	                             $error = null
	) {
		$entity = self::$lastEntity['entities'][$qid]['claims'];

		//P813 is an access date property
		//P854 is a reference URL property
		//P973 is a primary reference URL
		//P1065 is an archive URL for the reference
		//P2960 is an archive date for P1065
		foreach( $links as $property => $data ) {
			if( !empty( $data['access_time'] ) && $data['access_time'] != "x" ) {
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['snaktype'] = "value";
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['property'] = "P813";
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datatype'] = "time";
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['type'] = "time";
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['time'] =
					date( '+Y\-m\-d\T00\:00\:00\Z', $data['access_time'] );
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['timezone'] = 0;
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['before'] = 0;
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['after'] = 0;
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['precision'] = 11;
				$entity[$property][0]['references'][0]['snaks']['P813'][0]['datavalue']['value']['calendarmodel'] =
					"http://www.wikidata.org/entity/Q1985727";
			}
			if( $property != "P973" && isset( $data['newdata']['url'] ) ) {
				$entity[$property][0]['references'][0]['snaks']['P854'][0]['snaktype'] = "value";
				$entity[$property][0]['references'][0]['snaks']['P854'][0]['property'] = "P854";
				$entity[$property][0]['references'][0]['snaks']['P854'][0]['datatype'] = "url";
				$entity[$property][0]['references'][0]['snaks']['P854'][0]['datavalue']['type'] = "string";
				$entity[$property][0]['references'][0]['snaks']['P854'][0]['datavalue']['value'] =
					$data['newdata']['url'];
			}
			if( $property != "P1065" && isset( $data['newdata']['archive_url'] ) ) {
				$entity[$property][0]['references'][0]['snaks']['P1065'][0]['snaktype'] = "value";
				$entity[$property][0]['references'][0]['snaks']['P1065'][0]['property'] = "P1065";
				$entity[$property][0]['references'][0]['snaks']['P1065'][0]['datatype'] = "url";
				$entity[$property][0]['references'][0]['snaks']['P1065'][0]['datavalue']['type'] = "string";
				$entity[$property][0]['references'][0]['snaks']['P1065'][0]['datavalue']['value'] =
					$data['newdata']['archive_url'];
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['snaktype'] = "value";
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['property'] = "P2960";
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datatype'] = "time";
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['type'] = "time";
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['value']['time'] =
					date( '+Y\-m\-d\T00\:00\:00\Z', $data['newdata']['archive_time'] );
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['value']['timezone'] = 0;
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['value']['before'] = 0;
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['value']['after'] = 0;
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['value']['precision'] = 11;
				$entity[$property][0]['references'][0]['snaks']['P2960'][0]['datavalue']['value']['calendarmodel'] =
					"http://www.wikidata.org/entity/Q1985727";
			}
		}

		$entity = [ 'claims' => $entity ];

		$text = json_encode( $entity );

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
			DB::logEditFailure( $qid, $text, $error );

			return false;
		}
		$summary .= " #IABot (v" . VERSION . ")";
		if( defined( "REQUESTEDBY" ) ) $summary .= " ([[User:" . REQUESTEDBY . "|" . REQUESTEDBY . "]])";
		if( is_null( self::$globalCurl_handle ) ) self::initGlobalCurlHandle();
		$post = [
			'action' => 'wbeditentity', 'id' => $qid, 'data' => $text, 'format' => 'json', 'summary' => $summary
		];

		if( $bot ) {
			$post['bot'] = 'yes';
		}

		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		$get = http_build_query( [
			                         'action' => 'query',
			                         'meta'   => 'tokens',
			                         'format' => 'json'
		                         ]
		);
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API . "?$get" );
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, [ self::generateOAuthHeader( 'GET', API . "?$get" ) ]
		);
		$data = curl_exec( self::$globalCurl_handle );
		$data = json_decode( $data, true );
		$post['token'] = $data['query']['tokens']['csrftoken'];
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POSTFIELDS, $post );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, API );
		repeatEditRequest:
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPHEADER, [ self::generateOAuthHeader( 'POST', API ) ] );
		$data2 = curl_exec( self::$globalCurl_handle );
		$data = json_decode( $data2, true );
		if( isset( $data['success'] ) && $data['success'] == 1 && !isset( $data['entity']['nochange'] ) ) {
			return $data['entity']['lastrevid'];
		} elseif( isset( $data['error'] ) ) {
			$error = "{$data['error']['code']}: {$data['error']['info']}";
			echo "EDIT ERROR: $error\n";
			DB::logEditFailure( $qid, $text, $error );
			if( $data['error']['code'] == "maxlag" ) {
				sleep( 5 );
				goto repeatEditRequest;
			}

			return false;
		} else {
			$error = "bad response";
			echo "EDIT ERROR: Received a bad response from the API.\nResponse: $data2\n";
			DB::logEditFailure( $qid, $text, $error );

			return false;
		}
	}

}