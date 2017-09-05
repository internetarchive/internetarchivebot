<?php

/*
	Copyright (c) 2015-2017, Maximilian Doerr

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
 * Parser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * Parser class
 * Allows for the parsing on project specific wiki pages
 * @abstract
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

use Wikimedia\DeadlinkChecker\CheckIfDead;

abstract class Parser {

	/**
	 * The API class
	 *
	 * @var API
	 * @access public
	 */
	public $commObject;

	/**
	 * The CheckIfDead class
	 *
	 * @var CheckIfDead
	 * @access protected
	 */
	protected $deadCheck;

	/**
	 * The Regex for fetching templates with parameters being optional
	 *
	 * @var string
	 * @access protected
	 */
	protected $templateRegexOptional = '/({{{{templates}}}})[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\}/i';

	/**
	 * The Regex for fetching templates with parameters being mandatory
	 *
	 * @var string
	 * @access protected
	 */
	protected $templateRegexMandatory = '/({{{{templates}}}})[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i';

	/**
	 * The regex for detecting proper RfC compliant URLs, with UTF-8 support.
	 * The scheme is not required to match.
	 *
	 * @var string
	 * @access protected
	 */
	protected $schemelessURLRegex = '(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';

	/**
	 * The regex for detecting proper RfC compliant URLs, with UTF-8 support.
	 * The scheme is required to match.
	 *
	 * @var string
	 * @access protected
	 */
	protected $schemedURLRegex = '(?:[a-z0-9\+\-\.]*:)\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';

	/**
	 * Citation template parameters sorted by language.
	 *
	 * @var array
	 * @access protected
	 */
	protected $parameters = PARAMETERS;

	/**
	 * Parser class constructor
	 *
	 * @param API $commObject
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 */
	public function __construct( API $commObject ) {
		$this->commObject = $commObject;
		$this->deadCheck = new CheckIfDead();
		$this->parameters = json_decode( PARAMETERS, true );
	}

	/**
	 * Merge the new data in a custom array_merge function
	 *
	 * @param array $link An array containing details and newdata about a specific reference.
	 * @param bool $recurse Is this function call a recursive call?
	 *
	 * @static
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Merged data
	 */
	public static function mergeNewData( $link, $recurse = false ) {
		$returnArray = [];
		if( $recurse !== false ) {
			foreach( $link as $parameter => $value ) {
				if( isset( $recurse[$parameter] ) && !is_array( $recurse[$parameter] ) && !is_array( $value ) ) {
					$returnArray[$parameter] = $recurse[$parameter];
				} elseif( isset( $recurse[$parameter] ) && is_array( $recurse[$parameter] ) && is_array( $value ) ) {
					$returnArray[$parameter] = self::mergeNewData( $value, $recurse[$parameter] );
				} elseif( isset( $recurse[$parameter] ) ) $returnArray[$parameter] = $recurse[$parameter];
				else $returnArray[$parameter] = $value;
			}
			foreach( $recurse as $parameter => $value ) {
				if( !isset( $returnArray[$parameter] ) ) $returnArray[$parameter] = $value;
			}

			return $returnArray;
		}
		if( isset( $link['newdata'] ) ) {
			$newdata = $link['newdata'];
			unset( $link['newdata'] );
		} else $newdata = [];
		foreach( $link as $parameter => $value ) {
			if( isset( $newdata[$parameter] ) && !is_array( $newdata[$parameter] ) && !is_array( $value ) ) {
				$returnArray[$parameter] = $newdata[$parameter];
			} elseif( isset( $newdata[$parameter] ) && is_array( $newdata[$parameter] ) && is_array( $value ) ) {
				$returnArray[$parameter] = self::mergeNewData( $value, $newdata[$parameter] );
			} elseif( isset( $newdata[$parameter] ) ) $returnArray[$parameter] = $newdata[$parameter];
			else $returnArray[$parameter] = $value;
		}
		foreach( $newdata as $parameter => $value ) {
			if( !isset( $returnArray[$parameter] ) ) $returnArray[$parameter] = $value;
		}

		return $returnArray;
	}

	/**
	 * Master page analyzer function.  Analyzes the entire page's content,
	 * retrieves specified URLs, and analyzes whether they are dead or not.
	 * If they are dead, the function acts based on onwiki specifications.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param array $modifiedLinks Pass back a list of links modified
	 *
	 * @return array containing analysis statistics of the page
	 */
	public function analyzePage( &$modifiedLinks = [] ) {
		if( DEBUG === false || LIMITEDRUN === true ) {
			file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID, serialize( [
				                                                                 'title' => $this->commObject->page,
				                                                                 'id'    => $this->commObject->pageid
			                                                                 ]
			                                                    )
			);
		}
		unset( $tmp );
		echo "Analyzing {$this->commObject->page} ({$this->commObject->pageid})...\n";
		//Tare statistics variables
		$modifiedLinks = [];
		$archiveProblems = [];
		$archived = 0;
		$rescued = 0;
		$notrescued = 0;
		$tagged = 0;
		$waybackadded = 0;
		$otheradded = 0;
		$analyzed = 0;
		$newlyArchived = [];
		$timestamp = date( "Y-m-d\TH:i:s\Z" );
		$history = [];
		$newtext = $this->commObject->content;

		if( $this->commObject->config['link_scan'] == 0 ) {
			$links = $this->getExternalLinks();
		} else $links = $this->getReferences();
		$analyzed = $links['count'];
		unset( $links['count'] );

		//Process the links
		$checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = [];
		//Perform a 3 phase process.
		//Phases 1 and 2 collect archive information based on the configuration settings on wiki, needed for further analysis.
		//Phase 3 does the actual rescuing.
		for( $i = 0; $i < 3; $i++ ) {
			foreach( $links as $tid => $link ) {
				if( $link['link_type'] == "reference" ) {
					$reference = true;
				} else $reference = false;
				$id = 0;
				do {
					if( $reference === true ) {
						$link = $links[$tid]['reference'][$id];
					} else $link = $link[$link['link_type']];
					if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;

					//Create a flag that marks the source as being improperly formatting and needing fixing
					$invalidEntry = ( ( $link['has_archive'] === true && ( $link['archive_type'] == "invalid" ||
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
					                  ( isset( $link['force_when_dead'] ) && $link['is_dead'] === true ) ||
					                  ( isset( $link['force_when_alive'] ) && $link['is_dead'] === false );

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
								    $link['archive_type'] != "invalid"
								) {
									if( $this->rescueLink( $link, $modifiedLinks, $temp, $tid, $id ) ===
									    true ) $rescued++;
								}
							} elseif( $temp === false && empty( $link['archive_url'] ) && $link['is_dead'] === true ) {
								$notrescued++;
								if( $link['tagged_dead'] !== true ) {
									$link['newdata']['tagged_dead'] = true;
								} else continue;
								$tagged++;
								$this->noRescueLink( $link, $modifiedLinks, $tid, $id );
							}
						}
					} elseif( $i == 2 && $tagremoveClearance ) {
						//This removes the tag.  When tag override is off.
						$rescued++;
						$modifiedLinks["$tid:$id"]['type'] = "tagremoved";
						$modifiedLinks["$tid:$id"]['link'] = $link['url'];
						$link['newdata']['tagged_dead'] = false;
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
				if( $i == 2 && Parser::newIsNew( $links[$tid] ) ) {
					//If it is new, generate a new string.
					$links[$tid]['newstring'] = $this->generateString( $links[$tid] );
					//Yes, this is ridiculously convoluted but this is the only makeshift str_replace expression I could come up with the offset start and limit support.
					$newtext = self::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
					                              $this->commObject->content, $count, 1,
					                              $links[$tid][$links[$tid]['link_type']]['offset'], $newtext
					);
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
		echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: " .
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
			API::edit( "Talk:{$this->commObject->page}", $body,
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
		if( $this->commObject->content != $newtext ||
		    ( $this->commObject->config['notify_on_talk_only'] == 2 && !empty( $modifiedLinks ) ) ) {
			$pageModified = $this->commObject->content != $newtext;
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
					API::edit( $this->commObject->page, $newtext,
					           $this->commObject->getConfigText( "maineditsummary", $magicwords ), false, $timestamp
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
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Details about every link on the page
	 */
	public function getExternalLinks( $referenceOnly = false ) {
		$linksAnalyzed = 0;
		$returnArray = [];
		$toCheck = [];
		//Parse all the links
		$parseData = $this->parseLinks( $referenceOnly );
		$lastLink = [ 'tid' => null, 'id' => null ];
		$currentLink = [ 'tid' => null, 'id' => null ];
		//Run through each captured source from the parser
		foreach( $parseData as $tid => $parsed ) {
			//If there's nothing to work with, move on.
			if( empty( $parsed['link_string'] ) && empty( $parsed['remainder'] ) ) continue;
			if( $parsed['type'] == "reference" && empty( $parsed['contains'] ) ) continue;
			$returnArray[$tid]['link_type'] = $parsed['type'];
			$returnArray[$tid]['string'] = $parsed['string'];
			if( $parsed['type'] == "reference" ) {
				$returnArray[$tid]['reference']['offset'] = $parsed['offset'];
				foreach( $parsed['contains'] as $parsedlink ) {
					$returnArray[$tid]['reference'][] =
						array_merge( $this->getLinkDetails( $parsedlink['link_string'],
						                                    $parsedlink['remainder'] . $parsed['remainder']
						), [ 'string' => $parsedlink['string'], 'offset' => $parsedlink['offset'] ]
						);
				}
			} else {
				$returnArray[$tid][$parsed['type']] =
					array_merge( $this->getLinkDetails( $parsed['link_string'], $parsed['remainder'] ),
					             [ 'string' => $parsed['string'], 'offset' => $parsed['offset'] ]
					);
			}
			if( $parsed['type'] == "reference" ) {
				if( !empty( $parsed['parameters'] ) ) {
					$returnArray[$tid]['reference']['parameters'] =
						$parsed['parameters'];
				}
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
					$indexOffset = 0;
					foreach( $returnArray[$tid]['reference'] as $id => $link ) {
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
							unset( $returnArray[$tid]['reference'][$id] );
							//If so, update $toCheck at the respective index, with the new information.
							$toCheck["{$lastLink['tid']}:{$lastLink['id']}"] =
								$returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
							$indexOffset++;
							$this->commObject->db->retrieveDBValues( $returnArray[$lastLink['tid']]['reference'][$lastLink['id']],
							                                         "{$lastLink['tid']}:{$lastLink['id']}"
							);
							continue;
						}
						$linksAnalyzed++;
						//Load respective DB values into the active cache.
						$this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'][$id],
						                                         "$tid:" . ( $id - $indexOffset )
						);
						$toCheck["$tid:" . ( $id - $indexOffset )] = $returnArray[$tid]['reference'][$id];
						$lastLink['tid'] = $tid;
						$lastLink['id'] = $id - $indexOffset;
						if( $indexOffset !== 0 ) {
							$returnArray[$tid]['reference'][$id - $indexOffset] = $returnArray[$tid]['reference'][$id];
							unset( $returnArray[$tid]['reference'][$id] );
						}
					}
				} else {
					$currentLink['tid'] = $tid;
					$currentLink['id'] = null;
					//Check if the neighboring source has some kind of connection to each other.
					if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
						$returnArray[$lastLink['tid']]['string'] =
							$returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']]['string'];
						$toCheck[$lastLink['tid']] =
							$returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']];
						$this->commObject->db->retrieveDBValues( $returnArray[$lastLink['tid']][$parsed['type']],
						                                         $lastLink['tid']
						);
						continue;
					}
					$linksAnalyzed++;
					//Load respective DB values into the active cache.
					$this->commObject->db->retrieveDBValues( $returnArray[$tid][$parsed['type']], $tid );
					$toCheck[$tid] = $returnArray[$tid][$parsed['type']];
					$lastLink['tid'] = $tid;
					$lastLink['id'] = null;
				}
			}
		}
		//Retrieve missing access times that couldn't be extrapolated from the parser.
		$toCheck = $this->updateAccessTimes( $toCheck );
		//Set the live states of all the URL, and run a dead check if enabled.
		$toCheck = $this->updateLinkInfo( $toCheck );
		//Transfer data back to the return array.
		foreach( $toCheck as $tid => $link ) {
			if( is_int( $tid ) ) {
				$returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
			} else {
				$tid = explode( ":", $tid );
				$returnArray[$tid[0]][$returnArray[$tid[0]]['link_type']][$tid[1]] = $link;
			}
		}
		$returnArray['count'] = $linksAnalyzed;

		return $returnArray;
	}

	/**
	 * Parses the pages for refences, citation templates, and bare links.
	 *
	 * @param bool $referenceOnly
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array All parsed links
	 */
	protected function parseLinks( $referenceOnly = false ) {
		$returnArray = [];
		$tArray = array_merge( $this->commObject->config['deadlink_tags'],
		                       $this->commObject->config['ignore_tags'],
		                       $this->commObject->config['paywall_tags']
		);
		$scrapText = $this->commObject->content;
		//Filter out the comments and plaintext rendered markup.
		$filteredText = $this->filterText( $this->commObject->content );
		//Detect tags lying outside of the closing reference tag.
		$regex = '/<\/ref\s*?>\s*?((\s*(' .
		         str_replace( "\{\{", "\{\{\s*", str_replace( "\}\}", "", implode( '|', $tArray ) ) ) .
		         ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})*)/i';
		$tid = 0;
		//Look for all opening reference tags
		$refCharRemoved = 0;
		$pageStartLength = strlen( $scrapText );
		while( preg_match( '/<ref(\s+.*?)?(\/)?\s*>/i', $scrapText, $match, PREG_OFFSET_CAPTURE ) ) {
			//Note starting positing of opening reference tag
			$offset = $match[0][1];
			//If there is no closing tag after the opening tag, abort.  Malformatting detected.
			//Otherwise, record location
			if( isset( $match[2] ) && $match[2][0] == "/" ) {
				$scrapText = preg_replace( '/' . preg_quote( $match[0][0], '/' ) . '/', "", $scrapText, 1 );
				$refCharRemoved += $pageStartLength - strlen( $scrapText );
				$pageStartLength = strlen( $scrapText );
				continue;
			}
			if( ( $endoffset = stripos( $scrapText, "</ref", $offset ) ) === false ) break;
			//Use the detection regex on this closing reference tag.
			if( preg_match( $regex, $scrapText, $match1, PREG_OFFSET_CAPTURE, $endoffset ) ) {
				//Redundancy, not location of closing tag.
				$endoffset = $match1[0][1];
				//Grab string from opening tag, up to closing tag.
				$scrappy = substr( $scrapText, $offset, $endoffset - $offset );
				//Merge the string from opening tag, and attach closing tag, with additional tags that were detected.
				$fullmatch = $scrappy . $match1[0][0];
				//string is the full match
				$returnArray[$tid]['string'] = $fullmatch;
				//Remainder is the group of inline tags detected in the capture group.
				$returnArray[$tid]['remainder'] = $match1[1][0];
				//Mark as reference.
				$returnArray[$tid]['type'] = "reference";
				$returnArray[$tid]['offset'] = $offset;
			} else break;

			//Some reference opening tags have parameters embedded in there.
			if( isset( $match[1] ) ) {
				$refValues = $match[1][0];
			} else $refValues = "";
			$returnArray[$tid]['parameters'] = $this->getReferenceParameters( $refValues );
			//Trim tag from start.  Link_string contains the body of reference.
			$returnArray[$tid]['link_string'] = str_replace( $match[0][0], "", $scrappy );
			//Save it back into $scrappy
			$scrappy = $returnArray[$tid]['link_string'];
			$returnArray[$tid]['contains'] = [];
			//References can sometimes have more than one source inside.  Fetch all of them.
			$charRemoved = 0;
			$startLength = strlen( $scrappy );
			while( ( $temp = $this->getNonReference( $scrappy ) ) !== false ) {
				//Store each source in here.
				$temp['offset'] += $charRemoved;
				$charRemoved += $startLength - strlen( $scrappy );
				$startLength = strlen( $scrappy );
				$returnArray[$tid]['contains'][] = $temp;
			}
			//If the filtered match is no where to be found, then it's being rendered in plaintext or is a comment
			//We want to leave those alone.
			if( strpos( $filteredText, $this->filterText( $fullmatch ) ) !== false ) {
				$returnArray[$tid]['offset'] += $refCharRemoved;
				$tid++;
				//Large regexes break things, so if we exceed 30000 characters, use a simple str_replace as large
				//strings like that are most likely unique.
				if( strlen( $this->filterText( $fullmatch ) ) > 30000 ) {
					$filteredText = str_replace( $this->filterText( $fullmatch ), "", $filteredText );
				} else {
					$filteredText =
						preg_replace( '/' . preg_quote( $this->filterText( $fullmatch ), '/' ) . '/', "", $filteredText,
						              1
						);
				}
			} else {
				unset( $returnArray[$tid] );
			}
			//Large regexes break things, so if we exceed 30000 characters, use a simple str_replace as large
			//strings like that are most likely unique.
			if( strlen( $fullmatch ) > 30000 ) {
				$scrapText = str_replace( $fullmatch, "", $scrapText );
			} else {
				$scrapText = preg_replace( '/' . preg_quote( $fullmatch, '/' ) . '/', "", $scrapText, 1 );
			}
			$refCharRemoved += $pageStartLength - strlen( $scrapText );
			$pageStartLength = strlen( $scrapText );
		}
		//If we are looking for everything, then...
		if( $referenceOnly === false ) {
			//scan the rest of the page text for non-reference sources.
			$lastOffset = 0;
			while( ( $temp = $this->getNonReference( $scrapText ) ) !== false ) {
				if( strpos( $filteredText, $this->filterText( $temp['string'] ) ) !== false ) {

					if( substr( $scrapText, $temp['offset'], 10 ) !== false && strpos( $this->commObject->content,
					                                                                   substr( $scrapText,
					                                                                           $temp['offset'], 10
					                                                                   )
					                                                           ) !== false
					) {
						$lastOffset = $temp['offset'] = strpos( $this->commObject->content, $temp['string'],
						                                        max( strpos( $this->commObject->content,
						                                                     substr( $scrapText, $temp['offset'], 10 ),
						                                                     $temp['offset']
						                                             ) - strlen( $temp['string'] ), $lastOffset
						                                        )
						);
					} elseif( strlen( $this->commObject->content ) - 5 - strlen( $temp['string'] ) > 0 &&
					          strpos( $this->commObject->content, $temp['string'],
					                  strlen( $this->commObject->content ) - 5 - strlen( $temp['string'] )
					          ) !== false
					) {
						$lastOffset = $temp['offset'] = strpos( $this->commObject->content, $temp['string'],
						                                        strlen( $this->commObject->content ) - 5 -
						                                        strlen( $temp['string'] )
						);
					} else {
						$lastOffset =
						$temp['offset'] = strpos( $this->commObject->content, $temp['string'], $lastOffset );
					}
					$lastOffset += strlen( $temp['string'] );
					$returnArray[] = $temp;
					//We need preg_replace since it has a limiter whereas str_replace does not.
					$filteredText =
						preg_replace( '/' . preg_quote( $this->filterText( $temp['string'] ), '/' ) . '/', "",
						              $filteredText, 1
						);
				}
				$refCharRemoved += $pageStartLength - strlen( $scrapText );
				$pageStartLength = strlen( $scrapText );
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
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 */
	protected function filterText( $text, $trim = false ) {
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

		if( $trim ) return trim( $text );
		else return $text;
	}

	/**
	 * Read and parse the reference string.
	 * Extract the reference parameters
	 *
	 * @param string $refparamstring reference string
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Contains the parameters as an associative array
	 */
	public function getReferenceParameters( $refparamstring ) {
		$returnArray = [];
		preg_match_all( '/(\S*)\s*=\s*(".*?"|\'.*?\'|\S*)/i', $refparamstring, $params );
		foreach( $params[0] as $tid => $tvalue ) {
			$returnArray[$params[1][$tid]] = $params[2][$tid];
		}

		return $returnArray;
	}

	/**
	 * Fetches the first non-reference it finds in the supplied text and returns it.
	 * This function will remove the text it found in the passed parameter.
	 *
	 * @param string $scrapText Text to look at.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Details of the first non-reference found.  False on failure.
	 */
	protected function getNonReference( &$scrapText = "" ) {
		$returnArray = [];
		$tArray =
			array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
			             $this->commObject->config['ignore_tags'],
			             $this->commObject->config['paywall_tags']
			);
		//This is a giant regex to capture citation tags and the other tags that follow it.
		$regex = '/((' . str_replace( "\{\{", "\{\{\s*", str_replace( "\}\}", "", implode( '|',
		                                                                                   $this->commObject->config['citation_tags']
		                                                                    )
		                                    )
			) . ')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\})/i';
		$remainderRegex = '/((' . str_replace( "\{\{", "\{\{\s*",
		                                       str_replace( "\}\}", "", implode( '|', $tArray ) )
			) . ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i';
		//Match giant regex for the presence of a citation template.
		$citeTemplate = preg_match( $regex, $scrapText, $citeMatch, PREG_OFFSET_CAPTURE );
		//Match for the presence of an archive template
		$remainder = preg_match( $remainderRegex,
		                         $scrapText, $remainderMatch, PREG_OFFSET_CAPTURE
		);
		//Match for the presence of a bare URL
		$bareLink =
			preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch, PREG_OFFSET_CAPTURE );
		beginparsing:
		$offsets = [];
		//Collect all the offsets of all matches regex patterns
		if( $citeTemplate ) $offsets[] = $citeMatch[0][1];
		if( $remainder ) $offsets[] = $remainderMatch[0][1];
		if( $bareLink ) $offsets[] = $bareMatch[0][1];
		//We want to handle the match that comes first in an article.  This is necessary for the isConnected function to work right.
		if( !empty( $offsets ) ) {
			$firstOffset = min( $offsets );
		} else $firstOffset = 0;
		$characterChopped = false;

		//If a complete citation template with remainder was matched first, then...
		if( $citeTemplate && $citeMatch[0][1] == $firstOffset ) {
			//string is the full match, citation template and respective inline templates
			$returnArray['string'] = $citeMatch[0][0];
			//link_string is the citation template
			$returnArray['link_string'] = $citeMatch[1][0];
			$returnArray['type'] = "template";
			//Name of the citation template
			$returnArray['name'] = trim( str_replace( "{{", "", $citeMatch[2][0] ) );
			$returnArray['offset'] = $citeMatch[0][1];
			$start = $citeMatch[0][1];
			$end = strlen( $citeMatch[0][0] ) + $start;
		} //If we matched a bare link first, then...
		elseif( ( $remainder && $bareLink && $remainderMatch[0][1] > $bareMatch[0][1] ) ||
		        ( $bareLink && !$remainderMatch )
		) {
			$returnArray['type'] = "externallink";
			//Record starting string offset of URL
			$start = $bareMatch[0][1];
			//Detect if this is a bracketed external link
			if( substr( $bareMatch[0][0], 0, 1 ) == "[" && strpos( $scrapText, "]", $start ) !== false &&
			    strpos( $scrapText, "]", $start ) > $start
			) {
				//Record offset of the end of string.  That is one character past the closing bracket location.
				$end = strpos( $scrapText, "]", $start ) + 1;
				//Make sure we're not disrupting an embedded wikilink.
				while( substr( $scrapText, $end - 1, 2 ) == "]]" ) {
					//If so, move past double closing bracket
					$end++;
					//Record new offset of closing bracket.
					$end = strpos( $scrapText, "]", $end ) + 1;
				}
				$recheck = true;
				while( $recheck ) {
					$recheck = false;
					//Let's make sure the closing bracket isn't inside a nowiki tag.
					do {
						$beforeOpen = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "<nowiki" );
						$beforeClose = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "</nowiki" );
						if( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
						    $end !== false
						) {
							$end = strpos( $scrapText, "]", $end ) + 1;
						}
					} while( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
					         $end !== false );
					//Let's make sure the closing bracket isn't inside a comment tag.
					do {
						$beforeOpen = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "<!--" );
						$beforeClose = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "-->" );
						if( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
						    $end !== false
						) {
							$end = strpos( $scrapText, "]", $end ) + 1;
							$recheck = true;
						}
					} while( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
					         $end !== false );
				}
				//A sanity check to make sure we are capturing a bracketed URL
				//In the event we have an end offset that suggests no closing bracket, default to bracketless parsing.
				//The goto in this statement sends execution to the else block of this if statement, which handles
				//bracketless URL parsing.
				if( $end === false || $end <= $start ) goto processPlainURL;
			} else {
				processPlainURL:
				//Record starting point of plain URL
				$start = strpos( $scrapText, $bareMatch[1][0], ( isset( $start ) ? $start : 0 ) );
				//The end is easily calculated by simply taking the string length of the url and adding it to the starting offset.
				$end = $start + strlen( $bareMatch[1][0] );
				//Make sure we're not absorbing a template into the URL.  Curly braces are valid characters.
				if( ( $toffset = strpos( $bareMatch[1][0], "{{" ) ) !== false ) {
					$toffset += $start;
					if( preg_match( '/((\{\{.*?)[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i',
					                $scrapText, $garbage, PREG_OFFSET_CAPTURE, $start
					) ) {
						if( $toffset == $garbage[0][1] ) $end = $toffset;
					}
				}
				//Make sure we don't absorb an HTML tag into the URL, as they are valid URL characters too.
				if( ( $toffset = strpos( $bareMatch[1][0], "<" ) ) !== false ) {
					$toffset += $start;
					if( $end > $toffset ) $end = $toffset;
				}
				//Since this is an unbracketed link, if the URL ends with one of .,:;?!)”<>[]\, then chop off that character.
				while( preg_match( '/[\.\,\:\;\?\!\)\"\>\<\[\]\\\\]/i',
				                   substr( substr( $scrapText, $start, $end - $start ),
				                           strlen( substr( $scrapText, $start, $end - $start ) ) - 1, 1
				                   )
				) ) {
					$end--;
					$characterChopped = (int) $characterChopped + 1;
				}
			}
			//Let's make sure we're not inside an unknown template or comments, that could break when modified.
			$toTest = [ [ [ "{{", "}}" ], [ "", "}}" ] ], [ [ "<!--", "-->" ], [ "--", "-->" ] ] ];
			foreach( $toTest as $test ) {
				$beforeOpen = strrpos( substr( $scrapText, 0, $start + 1 ), $test[0][0] );
				$beforeClose = strrpos( substr( $scrapText, 0, $start + 1 ), $test[0][1] );
				$afterOpen = strpos( substr( $scrapText, $end ), $test[0][0] );
				$afterClose = strpos( substr( $scrapText, $end ), $test[0][1] );
				if( ( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
				      $afterClose !== false && ( $afterOpen === false || $afterOpen > $afterClose ) )
				) {
					//We're inside something we shouldn't touch, let's move on.
					//Look for the next instance of a plain link, starting from the end of the restricted area.
					$afterClose += $end;
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $afterClose + strlen( $test[0][1] )
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
				            substr( $scrapText, $end - strlen( $test[1][1] ) + (int) $characterChopped,
				                    strlen( $test[0][1] )
				            ) == $test[0][1] )
				) {
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $end
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $afterClose !== false && ( $afterOpen === false || $afterOpen > $afterClose ) &&
				            substr( $scrapText, $start - strlen( $test[0][0] ) + strlen( $test[1][0] ),
				                    strlen( $test[0][0] )
				            ) == $test[0][0] )
				) {
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $end
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $afterClose === false && $afterOpen === false ) &&
				          substr( $scrapText, $start - strlen( $test[0][0] ) + strlen( $test[1][0] ),
				                  strlen( $test[0][0] )
				          ) == $test[0][0] &&
				          substr( $scrapText, $end - strlen( $test[1][1] ) + (int) $characterChopped,
				                  strlen( $test[0][1] )
				          ) == $test[0][1]
				) {
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $end
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				}
			}
			//Grab the URL with or without brackets, and save it to link_string
			$returnArray['link_string'] = substr( $scrapText, $start, $end - $start );
			$returnArray['offset'] = $start;
			//Transfer entire string to the string index
			$returnArray['string'] = trim( substr( $scrapText, $start, $end - $start ) );
		} //If we detected an inline tag on it's own, then...
		elseif( ( $remainder && $bareLink && $remainderMatch[0][1] < $bareMatch[0][1] ) ||
		        ( !$bareLink && $remainder )
		) {
			$returnArray['remainder'] = $remainderMatch[0][0];
			$returnArray['link_string'] = "";
			$returnArray['string'] = $remainderMatch[0][0];
			$returnArray['type'] = "stray";
			$returnArray['name'] = str_replace( "{{", "", $remainderMatch[2][0] );
			$returnArray['offset'] = $remainderMatch[0][1];
			$start = $remainderMatch[0][1];
			$end = strlen( $remainderMatch[0][0] ) + $start;
		}

		if( isset( $returnArray['remainder'] ) && preg_match( '/((' . str_replace( "\{\{", "\{\{\s*",
		                                                                           str_replace( "\}\}", "",
		                                                                                        implode( '|',
		                                                                                                 $this->commObject->config['archive_tags']
		                                                                                        )
		                                                                           )
		                                                      ) .
		                                                      ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i',
		                                                      $returnArray['remainder'], $garbage
			)
		) {
			//Remove archive tags from the search array.
			$tArray = array_merge( $this->commObject->config['deadlink_tags'],
			                       $this->commObject->config['ignore_tags'],
			                       $this->commObject->config['paywall_tags']
			);
			$remainderRegex = '/((' . str_replace( "\{\{", "\{\{\s*",
			                                       str_replace( "\}\}", "", implode( '|', $tArray ) )
				) . ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i';
		}

		//Look for more remainder stuff.
		while( !empty( $offsets ) && ( $remainder = preg_match( $remainderRegex,
		                                                        $scrapText, $remainderMatch, PREG_OFFSET_CAPTURE, $end
			) ) ) {
			//Match giant regex for the presence of a citation template.
			$citeTemplate = preg_match( $regex, $scrapText, $citeMatch, PREG_OFFSET_CAPTURE, $end );
			//Match for the presence of a bare URL
			$bareLink =
				preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch, PREG_OFFSET_CAPTURE,
				            $end
				);
			$offsets = [];
			//Collect all the offsets of all matches regex patterns
			if( $citeTemplate ) $offsets[] = $citeMatch[0][1];
			if( $remainder ) $offsets[] = $remainderMatch[0][1];
			if( $bareLink ) $offsets[] = $bareMatch[0][1];
			//We want to handle the match that comes first in an article.  This is necessary for the isConnected function to work right.
			if( !empty( $offsets ) ) {
				$firstOffset = min( $offsets );
			} else $firstOffset = 0;

			if( $firstOffset !== 0 && $firstOffset == $remainderMatch[0][1] ) {
				$rStart = $remainderMatch[0][1];
				$rEnd = $rStart + strlen( $remainderMatch[0][0] );
				$inBetween = substr( $scrapText, $end, $rStart - $end );
				if( !isset( $returnArray['remainder'] ) ) $returnArray['remainder'] = "";
				if( strpos( $inBetween, "\n\n" ) === false && strlen( $inBetween ) < 50 &&
				    ( strpos( $inBetween, "\n" ) === false || !preg_match( '/\S/i', $inBetween ) ) &&
				    !preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $inBetween, $garbage ) &&
				    ( isset( $garbage[0] ) ? API::resolveExternalLink( $garbage[0] ) : false ) === false
				) {
					$returnArray['remainder'] .= $inBetween . $remainderMatch[0][0];
					$end = $rEnd;
				} else break;
			} else break;

			if( isset( $returnArray['remainder'] ) && preg_match( '/((' . str_replace( "\{\{", "\{\{\s*",
			                                                                           str_replace( "\}\}", "",
			                                                                                        implode( '|',
			                                                                                                 $this->commObject->config['archive_tags']
			                                                                                        )
			                                                                           )
			                                                      ) .
			                                                      ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i',
			                                                      $returnArray['remainder'], $garbage
				)
			) {
				//Remove archive tags from the search array.
				$tArray = array_merge( $this->commObject->config['deadlink_tags'],
				                       $this->commObject->config['ignore_tags'],
				                       $this->commObject->config['paywall_tags']
				);
				$remainderRegex = '/((' . str_replace( "\{\{", "\{\{\s*",
				                                       str_replace( "\}\}", "", implode( '|', $tArray ) )
					) . ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i';
			}
		}

		if( !empty( $returnArray ) ) {
			if( !isset( $returnArray['remainder'] ) ) $returnArray['remainder'] = "";
			$returnArray['string'] = substr( $scrapText, $start, $end - $start );
			//We need preg_replace since it has a limiter whereas str_replace does not.
			$scrapText = preg_replace( '/' . preg_quote( $returnArray['string'], '/' ) . '/', "", $scrapText, 1 );

			return $returnArray;
		}

		return false;
	}

	/**
	 * Parses a given refernce/external link string and returns details about it.
	 *
	 * @param string $linkString Primary reference string
	 * @param string $remainder Left over stuff that may apply
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array    Details about the link
	 */
	public function getLinkDetails( $linkString, $remainder ) {
		$returnArray = [];
		$returnArray['link_string'] = $linkString;
		$returnArray['remainder'] = $remainder;
		$returnArray['has_archive'] = false;
		$returnArray['link_type'] = "x";
		$returnArray['tagged_dead'] = false;
		$returnArray['is_archive'] = false;
		$returnArray['access_time'] = false;
		$returnArray['tagged_paywall'] = false;
		$returnArray['is_paywall'] = false;
		$returnArray['permanent_dead'] = false;

		//Check if there are tags flagging the bot to ignore the source
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['ignore_tags'] ), $remainder, $params ) ||
		    preg_match( $this->fetchTemplateRegex( $this->commObject->config['ignore_tags'] ), $linkString, $params )
		) {
			return [ 'ignore' => true ];
		}
		if( !preg_match( $this->fetchTemplateRegex( $this->commObject->config['citation_tags'], false ), $linkString,
		                 $params
			) && preg_match( '/' . $this->schemelessURLRegex . '/i', $this->filterText( $linkString ), $params )
		) {
			$this->analyzeBareURL( $returnArray, $params );
		} elseif( preg_match( $this->fetchTemplateRegex( $this->commObject->config['citation_tags'], false ),
		                      $linkString, $params
		) ) {
			if( $this->analyzeCitation( $returnArray, $params ) ) return [ 'ignore' => true ];
		}
		//Check the source remainder
		$this->analyzeRemainder( $returnArray, $remainder );

		//Check for the presence of a paywall tag
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['paywall_tags'] ), $remainder, $params ) ||
		    preg_match( $this->fetchTemplateRegex( $this->commObject->config['paywall_tags'] ), $linkString, $params )
		) {
			$returnArray['tagged_paywall'] = true;
		}

		//If there is no url after this then this source is useless.
		if( !isset( $returnArray['url'] ) ) return [ 'ignore' => true ];

		//A hacky checky for HTML encoded pipes
		$returnArray['url'] = str_replace( "&#124;", "|", $returnArray['url'] );
		if( isset( $returnArray['archive_url'] ) ) $returnArray['archive_url'] =
			str_replace( "&#124;", "|", $returnArray['archive_url'] );

		//Resolve templates, into URLs
		//If we can't resolve them, then ignore this link, as it will be fruitless to handle them.
		if( strpos( $returnArray['url'], "{{" ) !== false ) {
			preg_match( '/\{\{[\s\S\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i', $returnArray['url'],
			            $params
			);
			$returnArray['template_url'] = $returnArray['url'];
			$returnArray['url'] = API::resolveExternalLink( $returnArray['template_url'] );
			if( $returnArray['url'] === false ) {
				$returnArray['url'] =
					API::resolveExternalLink( "https:" . $returnArray['template_url'] );
			}
			if( $returnArray['url'] === false ) return [ 'ignore' => true ];
		}

		if( empty( $returnArray['original_url'] ) ) $returnArray['original_url'] = $returnArray['url'];

		$tmp = str_replace( "&#124;", "|", $returnArray['original_url'] );
		//Extract nonsense stuff from the URL, probably due to a misuse of wiki syntax
		//If a url isn't found, it means it's too badly formatted to be of use, so ignore
		if( ( ( $returnArray['link_type'] === "template" || ( strpos( $tmp, "[" ) &&
		                                                      strpos( $tmp, "]" ) ) ) &&
		      preg_match( '/' . $this->schemelessURLRegex . '/i', $tmp, $match ) ) ||
		    preg_match( '/' . $this->schemedURLRegex . '/i', $tmp, $match )
		) {
			//Sanitize the URL to keep it consistent in the DB.
			$returnArray['url'] =
				$this->deadCheck->sanitizeURL( $returnArray['url'], true );
			//If the sanitizer can't handle the URL, ignore the reference to prevent a garbage edit.
			if( $returnArray['url'] == "https:///" ) return [ 'ignore' => true ];
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
		    $returnArray['is_archive'] === false && !isset( $returnArray['template_url'] )
		) {
			$returnArray['archive_mismatch'] = true;
			$returnArray['url'] = $this->deadCheck->sanitizeURL( $returnArray['original_url'], true );
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

	//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.

	/**
	 * Generates a regex that detects the given list of escaped templates.
	 *
	 * @param array $escapedTemplateArray A list of bracketed templates that have been escaped to search for.
	 * @param bool $optional Make the reqex not require additional template parameters.
	 *
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string Generated regex
	 */
	protected function fetchTemplateRegex( $escapedTemplateArray, $optional = true ) {
		$escapedTemplateArray = implode( '|', $escapedTemplateArray );
		$escapedTemplateArray = str_replace( "\{\{", "\{\{\s*", str_replace( "\}\}", "", $escapedTemplateArray ) );
		if( $optional === true ) {
			$returnRegex = $this->templateRegexOptional;
		} else $returnRegex = $this->templateRegexMandatory;
		$returnRegex = str_replace( "{{{{templates}}}}", $escapedTemplateArray, $returnRegex );

		return $returnRegex;
	}

	/**
	 * Analyzes the bare link
	 *
	 * @param array $returnArray Array being generated
	 * @param string $linkString Link string being parsed
	 * @param array $params Extracted URL from link string
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeBareURL( &$returnArray, &$params ) {

		$returnArray['original_url'] =
		$returnArray['url'] = htmlspecialchars_decode( $params[0], true );
		$returnArray['link_type'] = "link";
		$returnArray['access_time'] = "x";
		$returnArray['is_archive'] = false;
		$returnArray['tagged_dead'] = false;
		$returnArray['has_archive'] = false;

		//If this is a bare archive url
		if( API::isArchive( $returnArray['url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeCitation( &$returnArray, &$params ) {
		$returnArray['tagged_dead'] = false;
		$returnArray['link_type'] = "template";
		$returnArray['link_template'] = [];
		$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
		$returnArray['link_template']['format'] = $returnArray['link_template']['parameters']['__FORMAT__'];
		unset( $returnArray['link_template']['parameters']['__FORMAT__'] );
		$returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
		$returnArray['link_template']['string'] = $params[0];
		$returnArray['link_template']['language'] =
			$this->getCiteLanguage( $returnArray['link_template'], substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4 ) );

		$parser = PARSERCLASS;

		//If we can't get a URL, then this is useless.  Discontinue analysis and move on.
		$urlParam =
			$this->getCiteActiveKey( "url", $returnArray['link_template']['language'], $returnArray['link_template'] );
		$accessParam = $this->getCiteActiveKey( "accessdate", $returnArray['link_template']['language'],
		                                        $returnArray['link_template']
		);
		$archiveParam = $this->getCiteActiveKey( "archiveurl", $returnArray['link_template']['language'],
		                                         $returnArray['link_template']
		);
		$deadParam =
			$this->getCiteActiveKey( "deadurl", $returnArray['link_template']['language'], $returnArray['link_template']
			);
		$paywallParam = $this->getCiteActiveKey( "closedaccess", $returnArray['link_template']['language'],
		                                         $returnArray['link_template']
		);
		if( $urlParam !== false && !empty( $returnArray['link_template']['parameters'][$urlParam] ) )
			$returnArray['original_url'] = $returnArray['url'] =
				htmlspecialchars_decode( $this->filterText( $returnArray['link_template']['parameters'][$urlParam], true
				)
				);
		else return false;
		//Fetch the access date.  Use the wikitext resolver in case a date template is being used.
		if( $accessParam !== false && !empty( $returnArray['link_template']['parameters'][$accessParam] ) ) {
			$time =
				$parser::strtotime( $this->filterText( $returnArray['link_template']['parameters'][$accessParam], true )
				);
			if( $time === false ) $time =
				$parser::strtotime( $this->filterText( API::resolveWikitext( $returnArray['link_template']['parameters'][$accessParam]
				), true
				)
				);
			if( $time === false ) $time = "x";
			$returnArray['access_time'] = $time;
		} else $returnArray['access_time'] = "x";
		//Check for the presence of an archive URL
		if( $archiveParam !== false && !empty( $returnArray['link_template']['parameters'][$archiveParam] ) )
			$returnArray['archive_url'] =
				htmlspecialchars_decode( $this->filterText( $returnArray['link_template']['parameters'][$archiveParam],
				                                            true
				)
				);
		if( !empty( $returnArray['link_template']['parameters'][$archiveParam] ) &&
		    API::isArchive( $returnArray['archive_url'], $returnArray )
		) {
			$returnArray['archive_type'] = "parameter";
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = false;
		}
		//Check for the presence of the deadurl parameter.
		if( $this->getCiteDefaultKey( "deadurl", $returnArray['link_template']['language'] ) !== false &&
		    $deadParam !== false
		) {
			if( isset( $returnArray['link_template']['parameters'][$deadParam] ) &&
			    $this->filterText( $returnArray['link_template']['parameters'][$deadParam], true ) ==
			    $this->getCiteDefaultKey( "deadurlyes", $returnArray['link_template']['language'] )
			) {
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "parameter";
			} elseif( isset( $returnArray['link_template']['parameters'][$deadParam] ) &&
			          $this->filterText( $returnArray['link_template']['parameters'][$deadParam], true ) ==
			          $this->getCiteDefaultKey( "deadurlno", $returnArray['link_template']['language'] )
			) {
				$returnArray['force_when_dead'] = true;
			} elseif( $returnArray['has_archive'] === true ) {
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "implied";
			}
		} elseif( $returnArray['has_archive'] === true ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "implied";
		}
		//Using an archive URL in the url field is not correct.  Flag as invalid usage if the URL is an archive.
		if( API::isArchive( $returnArray['original_url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "invalid";

			if( ( $accessParam === false || empty( $returnArray['link_template']['parameters'][$accessParam] ) ) &&
			    $returnArray['access_time'] == "x"
			) $returnArray['access_time'] = $returnArray['archive_time'];
		}

		//Check if this URL is lingering behind a paywall.
		if( $paywallParam !== false && isset( $returnArray['link_template']['parameters'][$paywallParam] ) ) {
			$returnArray['tagged_paywall'] = true;
		}
	}

	/**
	 * Analyze the remainder string
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $remainder Remainder string
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected abstract function analyzeRemainder( &$returnArray, &$remainder );

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
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 */
	public function isConnected( $lastLink, $currentLink, &$returnArray ) {
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

		//If the original URLs of both links match, and the archive is located in the current link, then merge into previous link
		if( $this->deadCheck->cleanURL( $link['url'] ) ==
		    $this->deadCheck->cleanURL( $temp['url'] ) && $temp['is_archive'] === true
		) {
			//An archive template initially detected on it's own, is flagged as a stray.  Attached to the original URL, it's flagged as a template.
			//A stray is usually in the remainder only.
			//Define the archive_string to help the string generator find the original archive.
			if( $temp['link_type'] != "stray" ) {
				$link['archive_string'] = $temp['link_string'];
			} else $link['archive_string'] = $temp['remainder'];
			//Expand original string and remainder indexes of previous link to contain the body of the current link.
			if( ( $tstart = strpos( $this->commObject->content, $link['archive_string'] ) ) !== false &&
			    ( $lstart = strpos( $this->commObject->content, $link['link_string'] ) ) !== false
			) {
				if( $tstart - strlen( $link['link_string'] ) - $lstart > 200 ) return false;
				$link['string'] = substr( $this->commObject->content, $lstart,
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
				$link['tagged_dead'] = true;
				$link['tag_type'] = "implied";
			}
			$link['archive_url'] = $temp['archive_url'];
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
		elseif( $this->deadCheck->cleanURL( $link['url'] ) ==
		        $this->deadCheck->cleanURL( $temp['url'] ) && $link['is_archive'] === true
		) {
			//Raise the reversed flag for the string generator.  Archive URLs are usually in the remainder.
			$link['reversed'] = true;
			//Define the archive_string to help the string generator find the original archive.
			if( $link['link_type'] != "stray" ) {
				$link['archive_string'] = $link['link_string'];
			} else $link['archive_string'] = $link['remainder'];
			//Expand original string and remainder indexes of previous link to contain the body of the current link.
			if( ( $tstart = strpos( $this->commObject->content, $temp['string'] ) ) !== false &&
			    ( $lstart = strpos( $this->commObject->content, $link['archive_string'] ) ) !== false
			) {
				if( $tstart - $lstart - strlen( $link['archive_string'] ) > 200 ) return false;
				$link['string'] =
					substr( $this->commObject->content, $lstart, $tstart - $lstart + strlen( $temp['string'] ) );
				$link['link_string'] = $link['archive_string'];
				$link['remainder'] = str_replace( $link['archive_string'], "", $link['string'] );
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Returns the same array with the access_time parameters updated
	 */
	public function updateAccessTimes( $links ) {
		$toGet = [];
		foreach( $links as $tid => $link ) {
			if( !isset( $this->commObject->db->dbValues[$tid]['createglobal'] ) && $link['access_time'] == "x" ) {
				$links[$tid]['access_time'] = $this->commObject->db->dbValues[$tid]['access_time'];
			} elseif( $link['access_time'] == "x" ) {
				$toGet[$tid] = $link['url'];
			} else {
				$this->commObject->db->dbValues[$tid]['access_time'] = $link['access_time'];
			}
		}
		if( !empty( $toGet ) ) $toGet = $this->commObject->getTimesAdded( $toGet );
		foreach( $toGet as $tid => $time ) {
			$this->commObject->db->dbValues[$tid]['access_time'] = $links[$tid]['access_time'] = $time;
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Returns the same array with updated values, if any
	 */
	public function updateLinkInfo( $links ) {
		$toCheck = [];
		foreach( $links as $tid => $link ) {
			if( $this->commObject->config['verify_dead'] == 1 &&
			    $this->commObject->db->dbValues[$tid]['live_state'] != 0 &&
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
		$errors = $this->deadCheck->getErrors();

		$whitelisted = [];
		if( USEADDITIONALSERVERS === true ) {
			$toValidate = [];
			foreach( $toCheck as $tid => $url ) {
				if( $results[$url] === true && $this->commObject->db->dbValues[$tid]['live_state'] == 1 ) {
					$toValidate[] = $url;
				}
			}
			if( !empty( $toValidate ) ) foreach( explode( "\n", CIDSERVERS ) as $server ) {
				$serverResults = API::runCIDServer( $server, $toValidate );
				$toValidate = array_flip( $toValidate );
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
		foreach( $links as $tid => $link ) {
			if( array_search( $link['url'], $whitelisted ) !== false ) {
				$this->commObject->db->dbValues[$tid]['paywall_status'] = 3;
				$link['is_dead'] = false;
				$links[$tid] = $link;
				continue;
			}

			$link['is_dead'] = null;
			if( $this->commObject->config['verify_dead'] == 1 ) {
				if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 &&
				    $this->commObject->db->dbValues[$tid]['live_state'] < 5 &&
				    ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 0 ||
				      $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 ) &&
				    ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200 ) &&
				    ( $this->commObject->db->dbValues[$tid]['live_state'] != 3 ||
				      ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 604800 ) )
				) {
					$link['is_dead'] = $results[$link['url']];
					$this->commObject->db->dbValues[$tid]['last_deadCheck'] = time();
					if( $link['tagged_dead'] === false && $link['is_dead'] === true ) {
						if( $this->commObject->db->dbValues[$tid]['live_state'] ==
						    4 ) $this->commObject->db->dbValues[$tid]['live_state'] = 2;
						else $this->commObject->db->dbValues[$tid]['live_state']--;
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
				if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) $link['is_dead'] = false;
				if( !isset( $this->commObject->db->dbValues[$tid]['live_state'] ) ||
				    $this->commObject->db->dbValues[$tid]['live_state'] == 4 ||
				    $this->commObject->db->dbValues[$tid]['live_state'] == 5
				) {
					$link['is_dead'] = null;
				}
				if( $this->commObject->db->dbValues[$tid]['live_state'] == 7 ) {
					$link['is_dead'] = false;
				}
				if( $this->commObject->db->dbValues[$tid]['live_state'] == 0 ||
				    $this->commObject->db->dbValues[$tid]['live_state'] == 6
				) {
					$link['is_dead'] = true;
				}

				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 ) {
					$link['is_dead'] = false;
				}
				if( ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 2 ||
				      isset( $link['invalid_archive'] ) ) ||
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
	 * Fetches all references only
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Details about every reference found
	 */
	public function getReferences() {
		return $this->getExternallinks( true );
	}

	/**
	 * Rescue a link
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links that were modified
	 * @param array $temp Cached result value from archive retrieval function
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
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
		//If we are dealing with an external link, or a stray archive template, then...
		if( $link['link_type'] == "link" || $link['link_type'] == "stray" ) {
			//If it is plain URL with no embedded text if it's in brackets, or is a stray archive template, then convert it to a citation template.
			//Else attach an archive template to it.
			if( $this->commObject->config['convert_to_cites'] == 1 &&
			    ( trim( $link['link_string'], " []" ) == $link['url'] || $link['link_type'] == "stray" )
			) {
				$link['newdata']['archive_type'] = "parameter";
				$templateLanguage = substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4 );
				$link['newdata']['link_template']['name'] =
					$this->getCiteDefaultKey( "templatename", $templateLanguage );
				$link['newdata']['link_template']['format'] = "{key}={value} ";
				$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "url", $templateLanguage )] =
					$link['url'];
				//If we are dealing with a stray archive template, try and copy the contents of its title parameter to the new citation template.
				if( $link['link_type'] == "stray" && ( !empty( $link['archive_template']['parameters']['title'] ) ||
				                                       !empty( $link['archive_template']['parameters'][$this->getCiteActiveKey( "title",
				                                                                                                                $templateLanguage,
				                                                                                                                $link['archive_template'],
				                                                                                                                true
				                                       )]
				                                       ) )
				) {
					if( isset( $link['archive_template']['parameters']['title'] ) ) {
						$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "title",
						                                                                          $templateLanguage
						)] =
							$link['archive_template']['parameters']['title'];
					} else {
						$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "title",
						                                                                          $templateLanguage
						)] =
							$link['archive_template']['parameters'][$this->getCiteActiveKey( "title", $templateLanguage,
							                                                                 $link['archive_template'],
							                                                                 true
							)];
					}
				} else $link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "title",
				                                                                                 $templateLanguage
				)] =
					$this->getCiteDefaultKey( "titleplaceholder", $templateLanguage );
				//We need to define the access date.
				$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "accessdate",
				                                                                          $templateLanguage
				)] =
					self::strftime( $this->retrieveDateFormat( true ), $link['access_time'] );
				//Let this function handle the rest.
				$this->generateNewCitationTemplate( $link, $templateLanguage );

				//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
				if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
					if( !isset( $link['template_url'] ) )
						$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "url",
						                                                                          $templateLanguage
						)] =
							$link['url'];
					else $link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "url",
					                                                                               $templateLanguage
					)] = $link['template_url'];
					$modifiedLinks["$tid:$id"]['type'] = "fix";
				}
				//Force change the link type to a template.  This part is not within the scope of the array merger, as it's too high level.
				$link['link_type'] = "template";
			} elseif( $link['link_type'] != "stray" ) {
				if( $link['is_archive'] === false && $this->generateNewArchiveTemplate( $link, $temp ) ) {
					$link['newdata']['archive_type'] = "template";
					$link['newdata']['tagged_dead'] = false;
					$link['newdata']['is_archive'] = false;
				} else {
					$link['newdata']['archive_type'] = "link";
					$link['newdata']['is_archive'] = true;
					$link['newdata']['tagged_dead'] = false;
				}
			} else {
				unset( $modifiedLinks["$tid:$id"], $link['newdata'] );

				return false;
			}
		} elseif( $link['link_type'] == "template" ) {
			//Since we already have a template, let this function make the needed modifications.
			$this->generateNewCitationTemplate( $link, $link['link_template']['language'] );

			$temporaryTemplateData = array_merge( $link['link_template'], $link['newdata']['link_template'] );

			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
			) {
				if( !isset( $link['template_url'] ) )
					$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "url",
					                                                                         $link['link_template']['language'],
					                                                                         $temporaryTemplateData,
					                                                                         true
					)] =
						$link['url'];
				else $link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "url",
				                                                                              $link['link_template']['language'],
				                                                                              $temporaryTemplateData,
				                                                                              true
				)] = $link['template_url'];
				$modifiedLinks["$tid:$id"]['type'] = "fix";
			}
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
			$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected abstract function noRescueLink( &$link, &$modifiedLinks, $tid, $id );

	/**
	 * A custom str_replace function with more dynamic abilities such as a limiter, and offset support, and alternate
	 * replacement strings This function is more expensive so use sparingly.
	 *
	 * @param $search String to search for
	 * @param $replace String to replace with
	 * @param $subject Subject to search
	 * @param int|null $count Number of replacements made
	 * @param int $limit Number of replacements to limit to
	 * @param int $offset Where to begin string searching in the subject
	 * @param string $replaceOn Try to make the replacement on this string with the string obtained at the offset of
	 *     subject
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return Replacement string
	 */
	public static function str_replace( $search, $replace, $subject, &$count = null, $limit = -1, $offset = 0,
	                                    $replaceOn = null
	) {
		if( !is_null( $replaceOn ) ) {
			$searchCounter = 0;
			$t1Offset = -1;
			if( ( $tenAfter = substr( $subject, $offset + strlen( $search ), 10 ) ) !== false ) {
				$t1Offset = strpos( $replaceOn, $search . $tenAfter );
			} elseif( $offset - 10 > -1 && ( $tenBefore = substr( $subject, $offset - 10, 10 ) ) !== false ) {
				$t1Offset = strpos( $replaceOn, $tenBefore . $search ) + 10;
			}

			$t2Offset = -1;
			while( ( $t2Offset = strpos( $subject, $search, $t2Offset + 1 ) ) !== false && $offset >= $t2Offset ) {
				$searchCounter++;
			}
			$t2Offset = -1;
			for( $i = 0; $i < $searchCounter; $i++ ) {
				$t2Offset = strpos( $replaceOn, $search, $t2Offset + 1 );
				if( $t2Offset === false ) break;
			}
			if( $t1Offset !== false && $t2Offset !== false ) $offset = max( $t1Offset, $t2Offset );
			elseif( $t1Offset === false ) $offset = $t2Offset;
			elseif( $t2Offset === false ) $offset = $t1Offset;
			else return $replaceOn;

			$subjectBefore = substr( $replaceOn, 0, $offset );
			$subjectAfter = substr( $replaceOn, $offset );
		} else {
			$subjectBefore = substr( $subject, 0, $offset );
			$subjectAfter = substr( $subject, $offset );
		}

		if( strlen( $search ) > 30000 ) {
			return $subjectBefore . str_replace( $search, $replace, $subjectAfter, $count );
		} else {
			return $subjectBefore . str_replace( $subjectAfter, preg_replace( '/' . preg_quote( $search, '/' ) . '/',
			                                                                  str_replace( '$', '\$', $replace ),
			                                                                  $subjectAfter, $limit, $count
				), $subjectAfter
				);
		}
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
		if( $link['link_type'] == "reference" ) {
			foreach( $link['reference'] as $tid => $tlink ) {
				if( isset( $tlink['newdata'] ) ) {
					foreach( $tlink['newdata'] as $parameter => $value ) {
						if( !isset( $tlink[$parameter] ) || $value != $tlink[$parameter] ) $t = true;
					}
				}
			}
		} elseif( isset( $link[$link['link_type']]['newdata'] ) ) {
			foreach(
				$link[$link['link_type']]['newdata'] as $parameter => $value
			) {
				if( !isset( $link[$link['link_type']][$parameter] ) ||
				    $value != $link[$link['link_type']][$parameter]
				) {
					$t = true;
				}
			}
		}

		return $t;
	}

	/**
	 * Generate a string to replace the old string
	 *
	 * @param array $link Details about the new link including newdata being injected.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string New source string
	 */
	public function generateString( $link ) {
		$out = "";
		if( $link['link_type'] != "reference" ) {
			if( strpos( $link[$link['link_type']]['link_string'], "\n" ) !== false ) $multiline = true;
			$mArray = Parser::mergeNewData( $link[$link['link_type']] );
			$tArray =
				array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
				             $this->commObject->config['ignore_tags']
				);
			$regex = $this->fetchTemplateRegex( $tArray );
			//Clear the existing archive, dead, and ignore tags from the remainder.
			//Why ignore?  It gives a visible indication that there's a bug in IABot.
			$remainder = preg_replace( $regex, "", $mArray['remainder'] );
			if( isset( $mArray['archive_string'] ) ) {
				$remainder =
					str_replace( $mArray['archive_string'], "", $remainder );
			}
		}
		//Beginning of the string
		//For references...
		if( $link['link_type'] == "reference" ) {
			//Build the opening reference tag with parameters, when dealing with references.
			$out .= "<ref";
			if( isset( $link['reference']['parameters'] ) ) {
				foreach( $link['reference']['parameters'] as $parameter => $value ) {
					$out .= " $parameter=$value";
				}
				unset( $link['reference']['parameters'] );
			}
			$out .= ">";
			//Store the original link string in sub output buffer.
			$tout = $link['reference']['link_string'];
			//Delete it, to avoid confusion when processing the array.
			unset( $link['reference']['link_string'] );
			//Process each individual source in the reference
			$offsetAdd = 0;
			foreach( $link['reference'] as $tid => $tlink ) {
				if( strpos( $tlink['link_string'], "\n" ) !== false ) $multiline = true;
				//Create an sub-sub-output buffer.
				$ttout = "";
				//If the ignore tag is set on this specific source, move on to the next.
				if( isset( $tlink['ignore'] ) && $tlink['ignore'] === true ) continue;
				if( !is_int( $tid ) ) continue;
				//Merge the newdata index with the link array.
				$mArray = Parser::mergeNewData( $tlink );
				$tArray =
					array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
					             $this->commObject->config['ignore_tags']
					);
				$regex = $this->fetchTemplateRegex( $tArray );
				//Clear the existing archive, dead, and ignore tags from the remainder.
				//Why ignore?  It gives a visible indication that there's a bug in IABot.
				$remainder = preg_replace( $regex, "", $mArray['remainder'] );
				//If handling a plain link, or a plain archive link...
				if( $mArray['link_type'] == "link" ||
				    ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" )
				) {
					//Store source link string into sub-sub-output buffer.
					$ttout .= $mArray['link_string'];
					//For other archives that don't have archive templates or there is no suitable template, replace directly.
					if( $tlink['is_archive'] === false && $mArray['is_archive'] === true ) {
						$ttout = str_replace( $mArray['original_url'], $mArray['archive_url'], $ttout );
					} elseif( $tlink['is_archive'] === true && $mArray['is_archive'] === true ) {
						$ttout = str_replace( $mArray['old_archive'], $mArray['archive_url'], $ttout );
					} elseif( $tlink['is_archive'] === true && $mArray['is_archive'] === false ) {
						$ttout = str_replace( $mArray['old_archive'], $mArray['url'], $ttout );
					}
				} //If handling a cite template...
				elseif( $mArray['link_type'] == "template" ) {
					//Build a clean cite template with the set parameters.
					$ttout .= "{{" . $mArray['link_template']['name'];
					if($mArray['link_template']['format'] == "multiline-pretty" ) $ttout .= "\n";
					else $ttout .= substr( $mArray['link_template']['format'],
					                  strpos( $mArray['link_template']['format'], "{value}" ) + 7
					);
					if($mArray['link_template']['format'] == "multiline-pretty" ) {
						$strlen = 0;
						foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
							$strlen = max( $strlen, strlen( $parameter ) );
						}
						foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
							$ttout .= " |" . str_pad( $parameter, $strlen, " " )." = $value\n";
						}
					} else foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
						$ttout .= "|" . str_replace( "{key}", $parameter,
						                             str_replace( "{value}", $value, $mArray['link_template']['format']
						                             )
							);
					}
					$ttout .= "}}";
				}
				//If the detected archive is invalid, replace with the original URL.
				if( $mArray['is_archive'] === true && isset( $mArray['invalid_archive'] ) ) {
					$ttout = str_replace( $mArray['iarchive_url'], $mArray['url'], $ttout );
				}
				//If tagged dead, and set as a template, add tag.
				if( $mArray['tagged_dead'] === true ) {
					if( $mArray['tag_type'] == "template" ) {
						$ttout .= "{{" . $mArray['tag_template']['name'];
						foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
							$ttout .= "|$parameter=$value ";
						}
						$ttout .= "}}";
					} elseif( $mArray['tag_type'] == "template-swallow" ) {
						$tttout = "{{" . $mArray['tag_template']['name'];
						foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
							$tttout .= "|$parameter=$value ";
						}
						$tttout .= "}}";
						$ttout = str_replace( $mArray['link_string'], $tttout, $ttout );
					}
				}
				//Attach the cleaned remainder.
				$ttout .= $remainder;
				//Attach archives as needed
				if( $mArray['has_archive'] === true ) {
					//For archive templates.
					if( $mArray['archive_type'] == "template" ) {
						if( $tlink['has_archive'] === true && $tlink['archive_type'] == "link" ) {
							$ttout = str_replace( $mArray['old_archive'], $mArray['archive_url'], $ttout );
						} else {
							$tttout = " {{" . $mArray['archive_template']['name'];
							foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
								$tttout .= "|$parameter=$value ";
							}
							$tttout .= "}}";
							if( isset( $mArray['archive_string'] ) ) {
								$ttout = str_replace( $mArray['archive_string'], trim( $tttout ), $ttout );
							} else {
								$ttout .= $tttout;
							}
						}
					}
					if( isset( $mArray['archive_string'] ) && $mArray['archive_type'] != "link" ) {
						$ttout =
							str_replace( $mArray['archive_string'], "", $ttout );
					}
				}
				//Search for source's entire string content, and replace it with the new string from the sub-sub-output buffer, and save it into the sub-output buffer.
				$tout =
					self::str_replace( $tlink['string'], $ttout, $tout, $count, 1, $tlink['offset'] + $offsetAdd );
				$offsetAdd += strlen( $ttout ) - strlen( $tlink['string'] );
			}

			//Attach contents of sub-output buffer, to main output buffer.
			$out .= $tout;
			//Close reference.
			$out .= "</ref>";

			return $out;

		} elseif( $link['link_type'] == "externallink" ) {
			//Attach the external link string to the output buffer.
			$out .= $link['externallink']['link_string'];
		} elseif( $link['link_type'] == "template" || $link['link_type'] == "stray" ) {
			//Create a clean cite template
			if( $link['link_type'] == "template" ) {
				$out .= "{{" . $link['template']['name'];
			} elseif( $link['link_type'] == "stray" ) $out .= "{{" . $mArray['link_template']['name'];
			if($mArray['link_template']['format'] == "multiline-pretty" ) $out .= "\n";
			else $out .= substr( $mArray['link_template']['format'],
			                strpos( $mArray['link_template']['format'], "{value}" ) + 7
			);
			if($mArray['link_template']['format'] == "multiline-pretty" ) {
				$strlen = 0;
				foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
					$strlen = max( $strlen, strlen( $parameter ) );
				}
				foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
					$out .= " |" . str_pad( $parameter, $strlen, " " )." = $value\n";
				}
			} else foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
				$out .= "|" . str_replace( "{key}", $parameter,
				                             str_replace( "{value}", $value, $mArray['link_template']['format']
				                             )
					);
			}
			$out .= "}}";
		}
		//Add dead link tag if needed.
		if( $mArray['tagged_dead'] === true ) {
			if( $mArray['tag_type'] == "template" ) {
				$out .= "{{" . $mArray['tag_template']['name'];
				foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
				$out .= "}}";
			} elseif( $mArray['tag_type'] == "template-swallow" ) {
				$tout = "{{" . $mArray['tag_template']['name'];
				foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
					$tout .= "|$parameter=$value ";
				}
				$tout .= "}}";
				$out = str_replace( $mArray['link_string'], $tout, $out );
			}
		}
		//Add remainder
		$out .= $remainder;
		//Add the archive if needed.
		if( $mArray['has_archive'] === true ) {
			if( $link['link_type'] == "externallink" ) {
				if( isset( $mArray['old_archive'] ) ) {
					$out =
						str_replace( $mArray['old_archive'], $mArray['archive_url'], $out );
				} else $out = str_replace( $mArray['original_url'], $mArray['archive_url'], $out );
			} elseif( $mArray['archive_type'] == "template" ) {
				$out .= " {{" . $mArray['archive_template']['name'];
				foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
					$out .= "|$parameter=$value ";
				}
				$out .= "}}";
			}
		}

		return $out;
	}

	/**
	 * Fetch the parameters of the template
	 *
	 * @param string $templateString String of the template without the {{example bit
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Template parameters with respective values
	 */
	public function getTemplateParameters( $templateString ) {
		$errorSetting = error_reporting();
		$returnArray = [];
		$formatting = [];
		$tArray = [];
		if( empty( $templateString ) ) return $returnArray;
		//Suppress errors for this functions.  While it almost never throws an error,
		//some mis-formatted templates cause the template parser to throw up.
		//In all cases however, a failure to properly parse the template will always
		//result in false being returned, error or not.  No sense in cluttering the output.
		error_reporting( 0 );
		while( true ) {
			$offset = 0;
			$loopcount = 0;
			$pipepos = strpos( $templateString, "|", $offset );
			$tstart = strpos( $templateString, "{{", $offset );
			$tend = strpos( $templateString, "}}", $offset );
			$lstart = strpos( $templateString, "[[", $offset );
			$lend = strpos( $templateString, "]]", $offset );
			$nstart = strpos( strtolower( $templateString ), "<nowiki", $offset );
			$nend = strpos( strtolower( $templateString ), "</nowiki", $offset );
			$cstart = strpos( $templateString, "<!--", $offset );
			$cend = strpos( $templateString, "-->", $offset );
			while( true ) {
				$loopcount++;
				$offsets = [];
				if( $lend !== false ) $offsets[] = $lend;
				if( $tend !== false ) $offsets[] = $tend;
				if( $cend !== false ) $offsets[] = $cend;
				if( $nend !== false ) $offsets[] = $nend;
				if( !empty( $offsets ) ) $offset = min( $offsets ) + 1;
				//Make sure we're not inside an embedded wikilink or template, or nowiki and comment tags.
				while( ( $tstart < $pipepos && $tend > $pipepos ) || ( $lstart < $pipepos && $lend > $pipepos ) ||
				       ( $cstart < $pipepos && $cend > $pipepos ) || ( $nstart < $pipepos && $nend > $pipepos ) ) {
					$pipepos = strpos( $templateString, "|", $pipepos + 1 );
				}
				$tstart = strpos( $templateString, "{{", $offset );
				$tend = strpos( $templateString, "}}", $offset );
				$lstart = strpos( $templateString, "[[", $offset );
				$lend = strpos( $templateString, "]]", $offset );
				$nstart = strpos( strtolower( $templateString ), "<nowiki", $offset );
				$nend = strpos( strtolower( $templateString ), "</nowiki", $offset );
				$cstart = strpos( $templateString, "<!--", $offset );
				$cend = strpos( $templateString, "-->", $offset );
				if( ( $pipepos < $tstart || $tstart === false ) && ( $pipepos < $lstart || $lstart === false ) &&
				    ( $pipepos < $nstart || $nstart === false ) && ( $pipepos < $cstart || $cstart === false )
				) break;
				if( $loopcount >= 500 ) {
					//re-enable error reporting
					error_reporting( $errorSetting );

					//We've looped more than 500 times, and haven't been able to parse the template.  Likely won't be able to.  Return false.
					return false;
				}
			}
			if( $pipepos !== false ) {
				$tArray[] = substr( $templateString, 0, $pipepos );
				$templateString = substr_replace( $templateString, "", 0, $pipepos + 1 );
			} else {
				$tArray[] = $templateString;
				break;
			}
		}
		$count = 0;
		foreach( $tArray as $tid => $tstring ) $tArray[$tid] = self::parameterExplode( '=', $tstring, $formatting );
		foreach( $tArray as $array ) {
			$count++;
			if( count( $array ) == 2 ) {
				$returnArray[$this->filterText( $array[0], true )] = trim( $array[1] );
			} else $returnArray[$count] = trim( $array[0] );
		}
		//re-enable error reporting
		error_reporting( $errorSetting );

		if( !empty( $formatting ) ) {
			$returnArray['__FORMAT__'] = array_search( max( $formatting ), $formatting );
			if( count( $formatting > 4 ) && strpos( $returnArray['__FORMAT__'], "\n" ) !== false )
				$returnArray['__FORMAT__'] = "multiline-pretty";
		} else $returnArray['__FORMAT__'] = "{key}={value} ";

		return $returnArray;
	}

	/**
	 * Break the parameters and values apart respecting HTML comments and nowiki tags
	 *
	 * @param string $delimiter The value to explode
	 * @param string $string String to explode
	 * @param array $formatting An array of formatting styles the template is formatted in.
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Exploded string
	 */
	public static function parameterExplode( $delimeter, $string, &$formatting = [] ) {
		$errorSetting = error_reporting();
		//Suppress errors for this functions.  While it almost never throws an error,
		//some mis-formatted templates cause the template parser to throw up.
		//In all cases however, a failure to properly parse the template will always
		//result in false being returned, error or not.  No sense in cluttering the output.
		error_reporting( 0 );
		$returnArray = [];
		$offset = 0;
		$delimPos = strpos( $string, $delimeter, $offset );
		$nstart = strpos( strtolower( $string ), "<nowiki", $offset );
		$nend = strpos( strtolower( $string ), "</nowiki", $offset );
		$tstart = strpos( $string, "{{", $offset );
		$tend = strpos( $string, "}}", $offset );
		$lstart = strpos( $string, "[[", $offset );
		$lend = strpos( $string, "]]", $offset );
		$cstart = strpos( $string, "<!--", $offset );
		$cend = strpos( $string, "-->", $offset );

		while( true ) {
			if( $lend !== false ) $offsets[] = $lend;
			if( $tend !== false ) $offsets[] = $tend;
			if( $cend !== false ) $offsets[] = $cend;
			if( $nend !== false ) $offsets[] = $nend;
			if( !empty( $offsets ) ) $offset = min( $offsets ) + 1;
			//Make sure we're not inside an embedded wikilink or template, or nowiki and comment tags.
			while( ( $tstart < $delimPos && $tend > $delimPos ) || ( $lstart < $delimPos && $lend > $delimPos ) ||
			       ( $cstart < $delimPos && $cend > $delimPos ) || ( $nstart < $delimPos && $nend > $delimPos ) ) {
				$delimPos = strpos( $string, $delimeter, $delimPos + 1 );
			}
			$nstart = strpos( strtolower( $string ), "<nowiki", $offset );
			$nend = strpos( strtolower( $string ), "</nowiki", $offset );
			$cstart = strpos( $string, "<!--", $offset );
			$cend = strpos( $string, "-->", $offset );
			$tstart = strpos( $string, "{{", $offset );
			$tend = strpos( $string, "}}", $offset );
			$lstart = strpos( $string, "[[", $offset );
			$lend = strpos( $string, "]]", $offset );
			if( $delimPos === false ||
			    ( ( $delimPos < $tstart || $tstart === false ) && ( $delimPos < $lstart || $lstart === false ) &&
			      ( $delimPos < $nstart || $nstart === false ) && ( $delimPos < $cstart || $cstart === false ) )
			) break;
		}

		if( $delimPos !== false ) {
			preg_match( '/(\s*).*\b[^\s]*(\s*)/i', substr( $string, 0, $delimPos ), $fstring1 );
			$returnArray[] = substr( $string, 0, $delimPos );
			preg_match( '/(\s*).*\b[^\s]*(\s*)/i', substr( $string, $delimPos + 1 ), $fstring2 );
			$returnArray[] = substr( $string, $delimPos + 1 );
			if( isset( $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' .
			                       $fstring2[2]]
			) ) $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' . $fstring2[2]]++;
			else $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' . $fstring2[2]] = 1;
		} else {
			$returnArray[] = $string;
		}

		//re-enable error reporting
		error_reporting( $errorSetting );

		return $returnArray;
	}

	/**
	 * Return whether or not to leave a talk page message.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return bool
	 */
	protected function leaveTalkMessage() {
		return !preg_match( $this->fetchTemplateRegex( $this->commObject->config['no_talk_tags'] ),
		                    $this->commObject->content,
		                    $garbage
		);
	}

	/**
	 * Return whether or not to skip editing the main article.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return bool True to skip
	 */
	protected function leaveTalkOnly() {
		return preg_match( $this->fetchTemplateRegex( $this->commObject->config['talk_only_tags'] ),
		                   $this->commObject->content,
		                   $garbage
		);
	}

	/**
	 * Destroys the class
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	public function __destruct() {
		$this->deadCheck = null;
		$this->commObject = null;
	}

	/**
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected abstract function generateNewArchiveTemplate( &$link, &$temp );

	/**
	 * Generates an appropriate citation template without altering existing parameters.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewCitationTemplate( &$link, $lang = "en" ) {
		$link['newdata']['archive_type'] = "parameter";
		//We need to flag it as dead so the string generator knows how to behave, when assigning the deadurl parameter.
		if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
		else $link['newdata']['tagged_dead'] = false;
		$link['newdata']['tag_type'] = "parameter";

		//If there was no link template array, then create an empty one.
		if( !isset( $link['link_template'] ) ) $link['link_template'] = [];
		//When we know we are adding an archive to a dead url, or merging an archive template to a citation template, we can set the deadurl flag to yes.
		//In cases where the original URL was no longer visible, like a template being used directly, are the archive URL being used in place of the original, we set the deadurl flag to "bot: unknown" which keeps the URL hidden, if supported.
		//The remaining cases will receive a deadurl=no.  These are the cases where dead_only is set to 0.
		if( $this->getCiteDefaultKey( "deadurl", $lang ) !== false ) {
			if( ( $link['tagged_dead'] === true || $link['is_dead'] === true ) ||
			    ( $this->getCiteDefaultKey( "deadurlusurp", $lang ) === false &&
			      ( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			        $link['link_type'] == "stray" ) )
			) {
				if( $this->getCiteDefaultKey( "deadurlyes", $lang ) !== false ) {
					$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "deadurl", $lang,
					                                                                         $link['link_template'],
					                                                                         true
					)] = $this->getCiteDefaultKey( "deadurlyes", $lang );
				}
			} elseif( $this->getCiteDefaultKey( "deadurlusurp", $lang ) !== false &&
			          ( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			            $link['link_type'] == "stray" )
			) {
				$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "deadurl", $lang,
				                                                                         $link['link_template'], true
				)] = $this->getCiteDefaultKey( "deadurlusurp", $lang );
			} else {
				if( $this->getCiteDefaultKey( "deadurlno", $lang ) !== false ) {
					$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "deadurl", $lang,
					                                                                         $link['link_template'],
					                                                                         true
					)] = $this->getCiteDefaultKey( "deadurlno", $lang );
				}
			}
		}
		//Set the archive URL
		$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "archiveurl", $lang,
		                                                                         $link['link_template'], true
		)] = $link['newdata']['archive_url'];

		//Set the archive date
		if( isset( $link['link_template']['parameters'][$this->getCiteActiveKey( "archivedate", $lang,
		                                                                         $link['link_template'], true
			)]
		) ) $link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "archivedate", $lang,
		                                                                             $link['link_template'], true
		)] =
			self::strftime( $this->retrieveDateFormat( $link['link_template']['parameters'][$this->getCiteActiveKey( "archivedate",
			                                                                                                         $lang,
			                                                                                                         $link['link_template'],
			                                                                                                         true
			)]
			), $link['newdata']['archive_time']
			);
		else $link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "archivedate", $lang,
		                                                                              $link['link_template'], true
		)] = self::strftime( $this->retrieveDateFormat( $link['string'] ), $link['newdata']['archive_time'] );

		//Set the time formatting variable.  ISO (default) is left blank.
		if( $this->getCiteDefaultKey( "df", $lang ) !== false ) {
			if( $this->getCiteActiveKey( "df", $lang, $link['link_template'] ) === false ) {
				switch( $this->retrieveDateFormat() ) {
					case '%-e %B %Y':
						$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "df", $lang )] =
							$this->getCiteDefaultKey( "dfeby", $lang );
						break;
					case '%B %-e, %Y':
						$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "df", $lang )] =
							$this->getCiteDefaultKey( "dfbey", $lang );
						break;
					default:
						$link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "df", $lang )] =
							false;
						break;
				}

				if( $link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "df", $lang )] === false
				) $link['newdata']['link_template']['parameters'][$this->getCiteDefaultKey( "df", $lang )] = "";
			}
		}

		if( empty( $link['link_template'] ) ) unset( $link['link_template'] );
	}

	protected function getArchiveHost( $url, &$data = [] ) {
		$value = API::isArchive( $url, $data );
		if( $value === false ) {
			return "unknown";
		} else return $data['archive_host'];
	}

	/**
	 * Get page date formatting standard
	 *
	 * @param bool $default Return default format.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string Format to be fed in time()
	 */
	protected abstract function retrieveDateFormat( $default = false );

	/**
	 * Return a unix timestamp allowing for international support through abstract functions.
	 *
	 * @param $string A timestamp
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
	 */
	public static function strtotime( $string ) {
		return strtotime( $string );
	}

	/**
	 * A customized strftime function that automatically bridges the gap between Windows, Linux, and Mac OSes.
	 *
	 * @param string $format Formatting string in the Linux format
	 * @param int|bool $time A unix epoch.  Default current time.
	 * @param bool|string Passed in recursively.  Ignore this value.
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
	 */
	public static function strftime( $format, $time = false, $convertValue = false ) {
		if( $time === false ) $time = time();

		$output = "";

		if( $convertValue !== false ) {
			$format = explode( "%$convertValue", $format );

			$noPad = false;

			switch( $convertValue ) {
				case "C":
					$convertValue = ceil( strftime( "%Y", $time ) / 100 );
					break;
				case "D":
					$convertValue = strftime( "%m/%d/%y", $time );
					break;
				case "F":
					$convertValue = strftime( "%m/%d/%y", $time );
					break;
				case "G":
					$convertValue = date( "o", $time );
					break;
				case "P":
					$convertValue = strtolower( strftime( "%p", $time ) );
					break;
				case "R":
					$convertValue = strftime( "%H:%M", $time );
					break;
				case "T":
					$convertValue = strftime( "%H:%M:%S", $time );
					break;
				case "V":
					$convertValue = date( "W", $time );
					break;
				case "e":
				case "-e":
					if( strlen( $convertValue ) == 2 ) $noPad = true;
					$convertValue = strftime( "%d", $time );
					if( (int) $convertValue < 10 ) {
						$convertValue = " " . (int) $convertValue;
					}
					if( $noPad === true ) {
						$convertValue = trim( $convertValue );
					}
					break;
				case "g":
					$convertValue = substr( date( "o", $time ), 2 );
					break;
				case "h":
					$convertValue = strftime( "%b", $time );
					break;
				case "k":
				case "-k":
					if( strlen( $convertValue ) == 2 ) $noPad = true;
					$convertValue = strftime( "%H", $time );
					if( (int) $convertValue < 10 ) {
						$convertValue = " " . (int) $convertValue;
					}
					if( $noPad === true ) {
						$convertValue = trim( $convertValue );
					}
					break;
				case "l":
				case "-l":
					if( strlen( $convertValue ) == 2 ) $noPad = true;
					$convertValue = strftime( "%I", $time );
					if( (int) $convertValue < 10 ) {
						$convertValue = " " . (int) $convertValue;
					}
					if( $noPad === true ) {
						$convertValue = trim( $convertValue );
					}
					break;
				case "m":
				case "-m":
					if( strlen( $convertValue ) == 2 ) $noPad = true;
					$convertValue = strftime( "%m", $time );
					if( $noPad === true ) {
						$convertValue = (string) (int) $convertValue;
					}
					break;
				case "n":
					$convertValue = "\n";
					break;
				case "r":
					$convertValue = strftime( "%I:%M:%S %p", $time );
					break;
				case "s":
					$convertValue = $time;
					break;
				case "t":
					$convertValue = "\t";
					break;
				case "u":
					$convertValue = date( "N", $time );
					break;
				default:
					return false;
			}

			if( !is_array( $format ) ) return false;

			foreach( $format as $segment => $string ) {
				if( !empty( $string ) ) {
					$temp = self::strftime( $string, $time );
					if( $temp === false ) {
						return false;
					}
					$output .= $temp;
				}

				if( $segment !== count( $format ) - 1 ) {
					$output .= $convertValue;
				}
			}
		} else {
			if( preg_match( '/\%(\-?[CDFGPRTVeghklnrstiu])/', $format, $match ) ) {
				return self::strftime( $format, $time, $match[1] );
			} else {
				return strftime( $format, $time );
			}
		}

		$tmp = PARSERCLASS;
		if( method_exists( $tmp, "localizeTimestamp" ) ) $output = $tmp::localizeTimestamp( $output );

		return $output;
	}

	/**
	 * Attempts to determine what language the citation template is in.
	 *
	 * @param $template The citation template to analyze
	 * @param $default The default language of the wiki the template is on
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string The language code of the template.
	 */
	protected function getCiteLanguage( $template, $default ) {
		$parameters = $template['parameters'];
		$languageMatches = [];

		foreach( $this->parameters as $lang => $langParameters ) {
			if( !isset( $languageMatches[$lang] ) ) $languageMatches[$lang] = 0;
			foreach( $langParameters as $category => $parameter ) {
				if( $category == "defaults" ) continue;
				if( $category == "localizationoverrides" ) continue;
				if( is_array( $parameter ) ) foreach( $parameter as $subParameter ) {
					if( isset( $parameters[$subParameter] ) ) $languageMatches[$lang]++;
				} else {
					if( isset( $parameters[$parameter] ) ) $languageMatches[$lang]++;
				}
			}
		}

		$mostMatches = max( $languageMatches );
		if( $mostMatches === false ) return $default;
		else {
			$bestMatches = [];
			foreach( $languageMatches as $lang => $count ) {
				if( $count == $mostMatches ) $bestMatches[] = $lang;
			}
			if( count( $bestMatches ) == 0 ) return $default;
			elseif( count( $bestMatches ) == 1 ) return $bestMatches[0];
			else {
				if( array_search( $default, $bestMatches ) !== false ) return $default;
				else return $bestMatches[0];
			}
		}
	}

	/**
	 * Returns the default used parameter when generating a citation template.
	 *
	 * @param $key Key category to lookup
	 * @param $lang The language template to use
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string|bool Returns the default alias of the parameter
	 */
	protected function getCiteDefaultKey( $key, $lang ) {
		if( !isset( $this->parameters[$lang] ) ) return false;

		if( !isset( $this->parameters[$lang]['defaults'][$key] ) ) {
			if( isset( $this->parameters[$lang]['use'] ) ) {
				return $this->getCiteDefaultKey( $key, $this->parameters[$lang]['use'] );
			}

			return false;
		} else {
			if( isset( $this->parameters[substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4 )]['localizationoverrides'] ) ) {
				if( isset( $this->parameters[substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4
					)]['localizationoverrides'][$lang][$key]
				) ) return $this->parameters[substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4
				)]['localizationoverrides'][$lang][$key];
				elseif( isset( $this->parameters[substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4
					)]['localizationoverrides'][$key]
				) ) return $this->parameters[substr( WIKIPEDIA, 0, strlen( WIKIPEDIA ) - 4
				)]['localizationoverrides'][$key];
			}
		}

		return $this->parameters[$lang]['defaults'][$key];
	}

	/**
	 * Returns the actively used alias of a citation template.
	 *
	 * @param $key Key category to lookup
	 * @param $lang The language template to use
	 * @param $template The template to analyze
	 * @param bool $default Return default parameter if no active one is being used.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string|bool Returns the actively used alias in the provided template.  False on failure.
	 */
	protected function getCiteActiveKey( $key, $lang, $template, $default = false ) {
		if( !isset( $this->parameters[$lang] ) ) return false;

		if( !isset( $this->parameters[$lang][$key] ) ) {
			if( isset( $this->parameters[$lang]['use'] ) ) {
				return $this->getCiteActiveKey( $key, $this->parameters[$lang]['use'], $template, $default );
			}

			return false;
		} elseif( is_array( $this->parameters[$lang][$key] ) ) foreach( $this->parameters[$lang][$key] as $tKey ) {
			if( isset( $template['parameters'][$tKey] ) ) return $tKey;
		}
		elseif( isset( $template['parameters'][$this->parameters[$lang][$key]] ) ) return $this->parameters[$lang][$key];

		if( $default === false ) return false;
		else return $this->getCiteDefaultKey( $key, $lang );
	}
}
