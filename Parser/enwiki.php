<?php

/*
	Copyright (c) 2016, Maximilian Doerr
	
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
* enwikiParser object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr  
*/
/**
* enwikiParser class
* Extension of the master parser class specifically for en.wikipedia.org
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class enwikiParser extends Parser {
	
	/**
	* Master page analyzer function.  Analyzes the entire page's content,
	* retrieves specified URLs, and analyzes whether they are dead or not.
	* If they are dead, the function acts based on onwiki specifications.
	* 
	* @static
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array containing analysis statistics of the page
	*/
	public function analyzePage() {
		if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA, serialize( array( 'title' => $this->commObject->page, 'id' => $this->commObject->pageid ) ) );
		unset($tmp);
		if( WORKERS === false ) echo "Analyzing {$this->commObject->page} ({$this->commObject->pageid})...\n";
		$modifiedLinks = array();
		$archiveProblems = array();
		$archived = 0;
		$rescued = 0;
		$notrescued = 0;
		$tagged = 0;
		$analyzed = 0;
		$newlyArchived = array();
		$timestamp = date( "Y-m-d\TH:i:s\Z" ); 
		$history = array(); 
		$newtext = $this->commObject->content;
		if( preg_match( '/\{\{((U|u)se)?\s?(D|d)(MY|my)\s?(dates)?/i', $this->commObject->content ) ) $df = true;
		else $df = false;
		if( $this->commObject->LINK_SCAN == 0 ) $links = $this->getExternalLinks();
		else $links = $this->getReferences();
		$analyzed = $links['count'];
		unset( $links['count'] );
									   
		//Process the links
		$checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = array();
		foreach( $links as $tid=>$link ) {
			if( $link['link_type'] == "reference" ) $reference = true;
			else $reference = false;
			$id = 0;
			do {
				if( $reference === true ) $link = $links[$tid]['reference'][$id];
				else $link = $link[$link['link_type']];
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;
				if( ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 ) {
					if( $reference === false ) $toArchive[$tid] = $link['url'];
					else $toArchive["$tid:$id"] = $link['url'];
				}
			} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );
		}
		if( !empty( $toArchive ) ) {
			$checkResponse = $this->commObject->isArchived( $toArchive );
			$checkResponse = $checkResponse['result'];
			$toArchive = array();
		}
		foreach( $links as $tid=>$link ) {
			if( $link['link_type'] == "reference" ) $reference = true;
			else $reference = false;
			$id = 0;
			do {
				if( $reference === true ) $link = $links[$tid]['reference'][$id];
				else $link = $link[$link['link_type']];
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;
				if( $reference === true && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 && !$checkResponse["$tid:$id"] ) {
					$toArchive["$tid:$id"] = $link['url']; 
				} elseif( ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 && !$checkResponse[$tid] ) {
					$toArchive[$tid] = $link['url'];
				}
				if( $this->commObject->TOUCH_ARCHIVE == 1 || $link['has_archive'] === false || ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ) {
					if( $link['link_type'] != "x" ) {
						if( ($link['tagged_dead'] === true && ( $this->commObject->TAG_OVERRIDE == 1 || $link['is_dead'] === true ) && ( ( $link['has_archive'] === true && $link['archive_type'] != "parameter" ) || $this->commObject->TOUCH_ARCHIVE == 1 || $link['has_archive'] === false ) ) || ( $link['is_dead'] === true && $this->commObject->DEAD_ONLY == 2 ) || ( $this->commObject->DEAD_ONLY == 0 ) ) {
							if( $reference === false ) $toFetch[$tid] = array( $link['url'], ( $this->commObject->ARCHIVE_BY_ACCESSDATE == 1 ? ( $link['access_time'] != "x" ? $link['access_time'] : null ) : null ) );
							else $toFetch["$tid:$id"] = array( $link['url'], ( $this->commObject->ARCHIVE_BY_ACCESSDATE == 1 ? ( $link['access_time'] != "x" ? $link['access_time'] : null ) : null ) );  
						}
					}
				}
			} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );

		}
		$errors = array();
		if( !empty( $toArchive ) ) {
			$archiveResponse = $this->commObject->requestArchive( $toArchive );
			$errors = $archiveResponse['errors'];
			$archiveResponse = $archiveResponse['result'];
		}
		if( !empty( $toFetch ) ) {
			$fetchResponse = $this->commObject->retrieveArchive( $toFetch );
			$fetchResponse = $fetchResponse['result'];
		} 
		foreach( $links as $tid=>$link ) {
			if( $link['link_type'] == "reference" ) $reference = true;
			else $reference = false;
			$id = 0;
			do {
				if( $reference === true ) $link = $links[$tid]['reference'][$id];
				else $link = $link[$link['link_type']];
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;
				if( $reference === true && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 && !$checkResponse["$tid:$id"] ) {
					if( $archiveResponse["$tid:$id"] === true ) {
						$archived++;  
					} elseif( $archiveResponse["$tid:$id"] === false ) {
						$archiveProblems["$tid:$id"] = $link['url'];
					}
				} elseif( ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 && !$checkResponse["$tid:$id"] ) {
					if( $archiveResponse[$tid] === true ) {
						$archived++;  
					} elseif( $archiveResponse[$tid] === false ) {
						$archiveProblems[$tid] = $link['url'];
					}
				}
				
				if( $this->commObject->TOUCH_ARCHIVE == 1 || $link['has_archive'] === false || ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ) {
					if( $link['link_type'] != "x" ) {
						if( ($link['tagged_dead'] === true && ( $this->commObject->TAG_OVERRIDE == 1 || $link['is_dead'] === true ) && ( ( $link['has_archive'] === true && $link['archive_type'] != "parameter" ) || $this->commObject->TOUCH_ARCHIVE == 1 || $link['has_archive'] === false ) ) || ( $link['is_dead'] === true && $this->commObject->DEAD_ONLY == 2 ) || ( $this->commObject->DEAD_ONLY == 0 ) ) {
							if( ($reference === false && ($temp = $fetchResponse[$tid]) !== false) || ($reference === true && ($temp = $fetchResponse["$tid:$id"]) !== false) ) {
								$rescued++;
								$modifiedLinks["$tid:$id"]['type'] = "addarchive";
								$modifiedLinks["$tid:$id"]['link'] = $link['url'];
								$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];
								if( $link['has_archive'] === true ) {
									$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
									$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
								}
								$link['newdata']['has_archive'] = true;
								$link['newdata']['archive_url'] = $temp['archive_url'];
								$link['newdata']['archive_time'] = $temp['archive_time'];
								if( $link['link_type'] == "link" ) {
									if( trim( $link['link_string'], " []" ) == $link['url'] ) {
										$link['newdata']['archive_type'] = "parameter";
										$link['newdata']['link_template']['name'] = "cite web";
										$link['newdata']['link_template']['parameters']['url'] = str_replace( parse_url($link['url'], PHP_URL_QUERY), urlencode( urldecode( parse_url($link['url'], PHP_URL_QUERY) ) ), $link['url'] ) ;
										if( $df === true ) {
											$link['newdata']['link_template']['parameters']['accessdate'] = date( 'j F Y', $link['access_time'] );
										} else {
											$link['newdata']['link_template']['parameters']['accessdate'] = date( 'F j, Y', $link['access_time'] );
										}
										if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
										else $link['newdata']['tagged_dead'] = false;
										$link['newdata']['tag_type'] = "parameter";
										if( $link['tagged_dead'] === true || $link['is_dead'] === true ) {
											if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
											else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
										}
										else {
											if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
											else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
										}
										if( !isset( $link['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
										else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];
										if( $df === true ) {
											if( !isset( $link['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'j F Y', $temp['archive_time'] );
											else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'j F Y', $temp['archive_time'] );
										} else {
											if( !isset( $link['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'F j, Y', $temp['archive_time'] );
											else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'F j, Y', $temp['archive_time'] );	
										}
										
										if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
											if( !isset( $link['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] = $link['url'];
											else $link['newdata']['link_template']['parameters']['url'] = $link['template_url'];
											$modifiedLinks["$tid:$id"]['type'] = "fix";
										}
										$link['link_type'] = "template";
									} else {
										$link['newdata']['archive_type'] = "template";
										$link['newdata']['tagged_dead'] = false;
										$link['newdata']['archive_template']['name'] = "wayback";
										if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) unset( $link['archive_template']['parameters'] );
										$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
										$link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
										if( $df === true ) $link['newdata']['archive_template']['parameters']['df'] = "y";
									}
								} elseif( $link['link_type'] == "template" ) {
									$link['newdata']['archive_type'] = "parameter";
									if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
									else $link['newdata']['tagged_dead'] = false;
									$link['newdata']['tag_type'] = "parameter";
									if( $link['tagged_dead'] === true || $link['is_dead'] === true ) {
										if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
										else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
									}
									else {
										if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
										else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
									}
									if( !isset( $link['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
									else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];
									if( $df === true ) {
										if( !isset( $link['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'j F Y', $temp['archive_time'] );
										else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'j F Y', $temp['archive_time'] );
									} else {
										if( !isset( $link['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'F j, Y', $temp['archive_time'] );
										else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'F j, Y', $temp['archive_time'] );	
									}
									
									if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
										if( !isset( $link['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] = $link['url'];
										else $link['newdata']['link_template']['parameters']['url'] = $link['template_url'];
										$modifiedLinks["$tid:$id"]['type'] = "fix";
									}
								}
								unset( $temp );
							} else {
								$notrescued++;
								if( $link['tagged_dead'] !== true ) $link['newdata']['tagged_dead'] = true;
								else continue;
								$tagged++;
								$modifiedLinks["$tid:$id"]['type'] = "tagged";
								$modifiedLinks["$tid:$id"]['link'] = $link['url'];
								if( $link['link_type'] == "link" ) {
									$link['newdata']['tag_type'] = "template";
									$link['newdata']['tag_template']['name'] = "dead link";
									$link['newdata']['tag_template']['parameters']['date'] = date( 'F Y' );
									$link['newdata']['tag_template']['parameters']['bot'] = USERNAME;	
								} elseif( $link['link_type'] == "template" ) {
									$link['newdata']['tag_type'] = "parameter";
									if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
									else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
								}
							}	
						} elseif( $link['tagged_dead'] === true && $link['is_dead'] == false ) {
							$rescued++;
							$modifiedLinks["$tid:$id"]['type'] = "tagremoved";
							$modifiedLinks["$tid:$id"]['link'] = $link['url'];
							$link['newdata']['tagged_dead'] = false;
						}   
					}
				}
				if( isset( $link['template_url'] ) ) {
					$link['url'] = $link['template_url'];
					unset( $link['template_url'] );
				}
				if( $reference === true ) $links[$tid]['reference'][$id] = $link;
				else $links[$tid][$links[$tid]['link_type']] = $link;
			} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );
			
			if( Core::newIsNew( $links[$tid] ) ) {
				$links[$tid]['newstring'] = $this->generateString( $links[$tid] );
				$newtext = str_replace( $links[$tid]['string'], $links[$tid]['newstring'], $newtext );
			}
		}
		$archiveResponse = $checkResponse = $fetchResponse = null;
		unset( $archiveResponse, $checkResponse, $fetchResponse );
		if( WORKERS === true ) {
			echo "Analyzed {$this->commObject->page} ({$this->commObject->pageid})\n";
		}
		echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: ".(memory_get_usage( true )/1048576)." MB; Max System Memory Used: ".(memory_get_peak_usage(true)/1048576)." MB\n";
		if( !empty( $archiveProblems ) && $this->commObject->NOTIFY_ERROR_ON_TALK == 1 ) {
			$out = "";
			foreach( $archiveProblems as $id=>$problem ) {
				$magicwords = array();
				$magicwords['problem'] = $problem;
				$magicwords['error'] = $errors[$id];
				$out .= "* ".$this->commObject->getConfigText( "PLERROR", $magicwords )."\n";
			} 
			$body = $this->commObject->getConfigText( "TALK_ERROR_MESSAGE", array( 'problematiclinks' => $out ) )."~~~~";
			API::edit( "Talk:{$this->commObject->page}", $body, $this->commObject->getConfigText( "ERRORTALKEDITSUMMARY", array() )." #IABot", false, true, "new", $this->commObject->getConfigText( "TALK_ERROR_MESSAGE_HEADER", array() ) );  
		}
		$pageModified = false;
		if( $this->commObject->content != $newtext ) {
			$pageModified = true;
			$magicwords = array();
			$magicwords['namespacepage'] = $this->commObject->page;
			$magicwords['linksmodified'] = $tagged+$rescued;
			$magicwords['linksrescued'] = $rescued;
			$magicwords['linksnotrescued'] = $notrescued;
			$magicwords['linkstagged'] = $tagged;
			$magicwords['linksarchived'] = $archived;
			$magicwords['linksanalyzed'] = $analyzed;
			$magicwords['pageid'] = $this->commObject->pageid;
			$magicwords['title'] = urlencode($this->commObject->page);
			$magicwords['logstatus'] = "fixed";
			if( $this->commObject->NOTIFY_ON_TALK_ONLY == 0 ) $revid = API::edit( $this->commObject->page, $newtext, $this->commObject->getConfigText( "MAINEDITSUMMARY", $magicwords )." #IABot", false, $timestamp );
			else $magicwords['logstatus'] = "posted";
			if( isset( $revid ) ) {
				$magicwords['diff'] = "https://en.wikipedia.org/w/index.php?diff=prev&oldid=$revid";
				$magicwords['revid'] = $revid;
			} else {
				$magicwords['diff'] = "";
				$magicwords['revid'] = "";
			}
			if( ($this->commObject->NOTIFY_ON_TALK == 1 && $revid !== false) || $this->commObject->NOTIFY_ON_TALK_ONLY == 1 ) {
				$out = "";
				foreach( $modifiedLinks as $link ) {
					$magicwords2 = array();
					$magicwords2['link'] = $link['link'];
					if( isset( $link['oldarchive'] ) ) $magicwords2['oldarchive'] = $link['oldarchive'];
					if( isset( $link['newarchive'] ) ) $magicwords2['newarchive'] = $link['newarchive'];
					$out .= "*";
					switch( $link['type'] ) {
						case "addarchive":
						$out .= $this->commObject->getConfigText( "MLADDARCHIVE", $magicwords2 );
						break;
						case "modifyarchive":
						$out .= $this->commObject->getConfigText( "MLMODIFYARCHIVE", $magicwords2 );
						break;
						case "fix":
						$out .= $this->commObject->getConfigText( "MLFIX", $magicwords2 );
						break;
						case "tagged":
						$out .= $this->commObject->getConfigText( "MLTAGGED", $magicwords2 );
						break;
						case "tagremoved":
						$out .= $this->commObject->getConfigText( "MLTAGREMOVED", $magicwords2 );
						break;
						default:
						$out .= $this->commObject->getConfigText( "MLDEFAULT", $magicwords2 );
						break;
					}
					$out .= "\n";	 
				}
				$magicwords['modifiedlinks'] = $out;
				$header = $this->commObject->getConfigText( "TALK_MESSAGE_HEADER", $magicwords );
				$body = $this->commObject->getConfigText( "TALK_MESSAGE", $magicwords )."~~~~";
				API::edit( "Talk:{$this->commObject->page}", $body, $this->commObject->getConfigText( "TALKEDITSUMMARY", $magicwords )." #IABot", false, false, true, "new", $header );
			}
			$this->commObject->logCentralAPI( $magicwords );
		}
		$this->commObject->db->updateDBValues();
		
		echo "\n";
		
		$newtext = $history = null;
		unset( $this->commObject, $newtext, $history, $res, $db );
		$returnArray = array( 'linksanalyzed'=>$analyzed, 'linksarchived'=>$archived, 'linksrescued'=>$rescued, 'linkstagged'=>$tagged, 'pagemodified'=>$pageModified );
		return $returnArray;
	}
	
	/**
	* Parses a given refernce/external link string and returns details about it.
	* 
	* @param string $linkString Primary reference string
	* @param string $remainder Left over stuff that may apply
	* @access public
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array	Details about the link
	*/
	public function getLinkDetails( $linkString, $remainder ) {
		$returnArray = array();
		$returnArray['link_string'] = $linkString;
		$returnArray['remainder'] = $remainder;			  
		if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->IGNORE_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $remainder, $params ) || preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->IGNORE_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $linkString, $params ) ) {
			return array( 'ignore' => true );
		}
		if( strpos( $linkString, "archive.org" ) !== false && !preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $linkString, $params ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "link";
			$returnArray['link_type'] = "x";
			if( preg_match( '/archive\.org\/(web\/)?(\d{14}|\*)\/(\S*)\s/i', $linkString, $returnArray['url'] ) ) {
				if( $returnArray['url'][2] != "*" ) $returnArray['archive_time'] = strtotime( $returnArray['url'][2] );
				else $returnArray['archive_time'] = "x";
				$returnArray['archive_url'] = "https://web.".trim( $returnArray['url'][0] );
				if( !preg_match( '/(?:https?:)?\/\//i', substr( $returnArray['url'][3], 0, 8 ) ) ) $returnArray['url'] = "//".$returnArray['url'][3];
				else $returnArray['url'] = $returnArray['url'][3]; 
			} else {
				return array( 'ignore' => true );  
			}
			$returnArray['access_time'] = $returnArray['archive_time'];
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "implied"; 
		} elseif( strpos( $linkString, "archiveurl" ) === false && strpos( $linkString, "archive-url" ) === false && strpos( $linkString, "web.archive.org" ) !== false && preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $linkString, $params ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "invalid";
			$returnArray['link_type'] = "template";
			$returnArray['link_template'] = array();
			$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
			$returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
			$returnArray['link_template']['string'] = $params[0];
			if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)/i', $returnArray['link_template']['parameters']['url'], $params2 ) ) {
				$returnArray['archive_time'] = strtotime( $params2[2] );
				$returnArray['archive_url'] = "https://web.".trim( $params2[0] );
				if( !preg_match( '/(?:https?:)?\/\//i', substr( $params2[3], 0, 8 ) ) ) $returnArray['url'] = "//".$params2[3];
				else $returnArray['url'] = $params2[3];	
			} else {
				return array( 'ignore' => true );
			}
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "implied";
			if( !isset( $returnArray['link_template']['parameters']['accessdate'] ) && !isset( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = $returnArray['archive_time'];   
			else {
				if( isset( $returnArray['link_template']['parameters']['accessdate'] ) && !empty( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
				elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) && !empty( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
				else $returnArray['access_time'] = "x";
			}
		} elseif( empty( $linkString ) && preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $remainder, $params ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "template";
			$returnArray['link_type'] = "x";
			$returnArray['archive_template'] = array();
			$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params[2] );
			$returnArray['archive_template']['name'] = str_replace( "{{", "", $params[1] );
			$returnArray['archive_template']['string'] = $params[0];
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "implied";
			if( isset( $returnArray['archive_template']['parameters']['date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
			else $returnArray['archive_time'] = "x";
			if( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['url'] ) ) { 
				$returnArray['url'] = $returnArray['archive_template']['parameters']['url'];
				$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['url']}";
			} elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters'][1] ) ) {
				$returnArray['url'] = $returnArray['archive_template']['parameters'][1];
				$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters'][1]}";
			} elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['site'] ) ) {
				$returnArray['url'] = $returnArray['archive_template']['parameters']['site'];
				$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['site']}";
			} else $returnArray['archive_url'] = "x";  
			
			//Check for a malformation or template misuse.
			if( $returnArray['archive_url'] == "x" ) {
				if( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
						$returnArray['archive_type'] = "invalid";
						$returnArray['archive_time'] = strtotime( $params3[2] );
						$returnArray['archive_url'] = "https://web.".$params3[0];
					} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
						$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
					} else {
						$returnArray['archive_type'] = "invalid";
					} 
				} elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
					if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
						$returnArray['archive_type'] = "invalid";
						$returnArray['archive_time'] = strtotime( $params3[2] );
						$returnArray['archive_url'] = "https://web.".$params3[0];
					} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
						$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
					} else {
						$returnArray['archive_type'] = "invalid";
					}
				} elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					if( preg_match( 'archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
						$returnArray['archive_type'] = "invalid";
						$returnArray['archive_time'] = strtotime( $params3[2] );
						$returnArray['archive_url'] = "https://web.".$params3[0];
					} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
						$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
					} else {
						$returnArray['archive_type'] = "invalid";
					}
				}
			}
			$returnArray['access_time'] = $returnArray['archive_time'];
		} elseif( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $linkString, $params ) ) {
			$returnArray['tagged_dead'] = false;
			if( !empty( $remainder ) ) {
				$returnArray['has_archive'] = false;
				$returnArray['is_archive'] = false;
				if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $linkString, $params2 ) ) {
					$returnArray['has_archive'] = true;
					$returnArray['is_archive'] = false;
					$returnArray['archive_type'] = "template";
					$returnArray['archive_template'] = array();
					$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
					$returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
					$returnArray['archive_template']['string'] = $params2[0];
					if( isset( $returnArray['archive_template']['parameters']['date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
					else $returnArray['archive_time'] = "x";
					if( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['url'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['url']}";
					elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters'][1] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters'][1]}";
					elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['site'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['site']}";
					else $returnArray['archive_url'] = "x";  
					
					//Check for a malformation or template misuse.
					if( $returnArray['archive_url'] == "x" ) {
						if( isset( $returnArray['archive_template']['parameters'][1] ) ) {
							if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
								$returnArray['archive_type'] = "invalid";
								$returnArray['archive_time'] = strtotime( $params3[2] );
								$returnArray['archive_url'] = "https://web.".$params3[0];
							} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
								$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
							} else {
								$returnArray['archive_type'] = "invalid";
							} 
						} elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
							if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
								$returnArray['archive_type'] = "invalid";
								$returnArray['archive_time'] = strtotime( $params3[2] );
								$returnArray['archive_url'] = "https://web.".$params3[0];
							} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
								$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
							} else {
								$returnArray['archive_type'] = "invalid";
							}
						} elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
							if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
								$returnArray['archive_type'] = "invalid";
								$returnArray['archive_time'] = strtotime( $params3[2] );
								$returnArray['archive_url'] = "https://web.".$params3[0];
							} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
								$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
							} else {
								$returnArray['archive_type'] = "invalid";
							}
						}
					}
				}
				if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->DEADLINK_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $remainder, $params2 ) ) {
					$returnArray['tagged_dead'] = true;
					$returnArray['tag_type'] = "template";
					$returnArray['tag_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
					$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
					$returnArray['tag_template']['string'] = $params2[0];
				} else {
					$returnArray['tagged_dead'] = false;
				}  
			} else {
				$returnArray['has_archive'] = false;
				$returnArray['is_archive'] = false;
			} 
			$returnArray['link_type'] = "template";
			$returnArray['link_template'] = array();
			$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
			$returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
			$returnArray['link_template']['string'] = $params[0];
			if( isset( $returnArray['link_template']['parameters']['url'] ) && !empty( $returnArray['link_template']['parameters']['url'] ) ) $returnArray['url'] = $returnArray['link_template']['parameters']['url'];
			else return array( 'ignore' => true );
			if( isset( $returnArray['link_template']['parameters']['accessdate']) && !empty( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
			elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) && !empty( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
			else $returnArray['access_time'] = "x";
			if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) && !empty( $returnArray['link_template']['parameters']['archiveurl'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archiveurl'];  
			if( isset( $returnArray['link_template']['parameters']['archive-url'] ) && !empty( $returnArray['link_template']['parameters']['archive-url'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archive-url'];
			if( (isset( $returnArray['link_template']['parameters']['archiveurl'] ) && !empty( $returnArray['link_template']['parameters']['archiveurl'] )) || (isset( $returnArray['link_template']['parameters']['archive-url'] ) && !empty( $returnArray['link_template']['parameters']['archive-url'] )) ) {
				$returnArray['archive_type'] = "parameter";
				$returnArray['has_archive'] = true;
				$returnArray['is_archive'] = true;
			}
			if( isset( $returnArray['link_template']['parameters']['archivedate'] ) && !empty( $returnArray['link_template']['parameters']['archivedate'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['link_template']['parameters']['archivedate'] );
			if( isset( $returnArray['link_template']['parameters']['archive-date'] ) && !empty( $returnArray['link_template']['parameters']['archive-date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['link_template']['parameters']['archive-date'] );
			if( ( isset( $returnArray['link_template']['parameters']['deadurl'] ) && $returnArray['link_template']['parameters']['deadurl'] == "yes" ) || ( ( isset( $returnArray['link_template']['parameters']['dead-url'] ) && $returnArray['link_template']['parameters']['dead-url'] == "yes" ) ) ) {
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "parameter";
			}
		} elseif( preg_match( '/((?:https?:)?\/\/([!#$&-;=?-Z_a-z~]|%[0-9a-f]{2})+)/i', $linkString, $params ) ) {
			$returnArray['url'] = $params[1];
			$returnArray['link_type'] = "link"; 
			$returnArray['access_time'] = "x";
			$returnArray['is_archive'] = false;
			$returnArray['tagged_dead'] = false;
			$returnArray['has_archive'] = false;
			if( !empty( $remainder ) ) {
				if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $remainder, $params2 ) ) {
					$returnArray['has_archive'] = true;
					$returnArray['is_archive'] = false;
					$returnArray['archive_type'] = "template";
					$returnArray['archive_template'] = array();
					$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
					$returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
					$returnArray['archive_template']['string'] = $params2[0];
					if( isset( $returnArray['archive_template']['parameters']['date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
					else $returnArray['archive_time'] = "x";
					if( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['url'] ) ) { 
						$returnArray['url'] = $returnArray['archive_template']['parameters']['url'];
						$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['url']}";
					} elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters'][1] ) ) {
						$returnArray['url'] = $returnArray['archive_template']['parameters'][1];
						$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters'][1]}";
					} elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['site'] ) ) {
						$returnArray['url'] = $returnArray['archive_template']['parameters']['site'];
						$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['site']}";
					} else $returnArray['archive_url'] = "x";   
					
					//Check for a malformation or template misuse.
					if( $returnArray['archive_url'] == "x" ) {
						if( isset( $returnArray['archive_template']['parameters'][1] ) ) {
							if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
								$returnArray['archive_type'] = "invalid";
								$returnArray['archive_time'] = strtotime( $params3[2] );
								$returnArray['archive_url'] = "https://web.".$params3[0];
							} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
								$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
							} else {
								$returnArray['archive_type'] = "invalid";
							} 
						} elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
							if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
								$returnArray['archive_type'] = "invalid";
								$returnArray['archive_time'] = strtotime( $params3[2] );
								$returnArray['archive_url'] = "https://web.".$params3[0];
							} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
								$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
							} else {
								$returnArray['archive_type'] = "invalid";
							}
						} elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
							if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
								$returnArray['archive_type'] = "invalid";
								$returnArray['archive_time'] = strtotime( $params3[2] );
								$returnArray['archive_url'] = "https://web.".$params3[0];
							} elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
								$returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
							} else {
								$returnArray['archive_type'] = "invalid";
							}
						}
					}
				}
				if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->DEADLINK_TAGS ) ).')[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i', $remainder, $params2 ) ) {
					$returnArray['tagged_dead'] = true;
					$returnArray['tag_type'] = "template";
					$returnArray['tag_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
					$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
					$returnArray['tag_template']['string'] = $params2[0];
				} else {
					$returnArray['tagged_dead'] = false;
				}	
			} else {
				$returnArray['has_archive'] = false;
			}
		} else {
			$returnArray['ignore'] = true;
		}
		if( isset( $returnArray['url'] ) && strpos( $returnArray['url'], "{{" ) !== false ) {
			preg_match( '/\{\{[\s\S\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i', $returnArray['url'], $params );
			$returnArray['template_url'] = $returnArray['url'];
			$returnArray['url'] = API::resolveExternalLink( $returnArray['template_url'] );
			if( $returnArray['url'] === false ) $returnArray['url'] = API::resolveExternalLink( "https:".$returnArray['template_url'] );
			if( $returnArray['url'] === false ) $returnArray['url'] = $returnArray['template_url'];  
		}
		if( !isset( $returnArray['ignore'] ) && $returnArray['access_time'] === false ) {
			$returnArray['access_time'] = "x";
		}
		if( !isset( $returnArray['ignore'] ) && isset( $returnArray['archive_time'] ) && $returnArray['archive_time'] === false ) {
			$returnArray['archive_time'] = strtotime( preg_replace( '/(?:https?:)?\/?\/?(web.)?archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', '$3', $returnArray['archive_url'] ) );
		}
		//A redundant check in case the archive URL is still mangled after link analysis.
		if( $returnArray['has_archive'] === true && strpos( $returnArray['archive_url'], "//web." ) === false ) {
			$returnArray['archive_url'] = "https://web.".$returnArray['archive_url'];
		}
		return $returnArray;
	}
	
	/**
	* Generate a string to replace the old string
	* 
	* @param array $link Details about the new link including newdata being injected.
	* @access public
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string New source string
	*/
	public function generateString( $link ) {
		$out = "";
		if( $link['link_type'] != "reference" ) {
			$mArray = Core::mergeNewData( $link[$link['link_type']] );
			$tArray = array_merge( $this->commObject->DEADLINK_TAGS, $this->commObject->ARCHIVE_TAGS, $this->commObject->IGNORE_TAGS ); 
			$regex = '/('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}/i';
			$remainder = preg_replace( $regex, "", $mArray['remainder'] );
		}
		//Beginning of the string
		if( $link['link_type'] == "reference" ) {
			$out .= "<ref";
			if( isset( $link['reference']['parameters'] ) ) {
				foreach( $link['reference']['parameters'] as $parameter => $value ) {
					$out .= " $parameter=$value";
				}
				unset( $link['reference']['parameters'] );
			}
			$out .= ">";
			$tout = $link['reference']['link_string'];
			unset( $link['reference']['link_string'] );
			foreach( $link['reference'] as $tid=>$tlink ) {
				$ttout = "";
				if( isset( $tlink['ignore'] ) && $tlink['ignore'] === true ) continue;
				$mArray = Core::mergeNewData( $tlink );
				$tArray = array_merge( $this->commObject->DEADLINK_TAGS, $this->commObject->ARCHIVE_TAGS, $this->commObject->IGNORE_TAGS ); 
				$regex = '/('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}/i';
				$remainder = preg_replace( $regex, "", $mArray['remainder'] );
				if( $mArray['link_type'] == "link" || ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" ) ) $ttout .= $mArray['link_string'];
				elseif( $mArray['link_type'] == "template" ) {
					$ttout .= "{{".$mArray['link_template']['name'];
					foreach( $mArray['link_template']['parameters'] as $parameter => $value ) $ttout .= "|$parameter=$value ";
					$ttout .= "}}";
				} 
				if( $mArray['tagged_dead'] === true ) {
					if( $mArray['tag_type'] == "template" ) {
						$ttout .= "{{".$mArray['tag_template']['name'];
						foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $ttout .= "|$parameter=$value ";
						$ttout .= "}}";
					}
				}
				$ttout .= $remainder;
				if( $mArray['has_archive'] === true ) {
					if( $link['link_type'] == "externallink" ) {
						$ttout = str_replace( $mArray['url'], $mArray['archive_url'], $tout );
					} elseif( $mArray['archive_type'] == "template" ) {
						$ttout .= " {{".$mArray['archive_template']['name'];
						foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) $ttout .= "|$parameter=$value ";
						$ttout .= "}}";  
					}
				}
				$tout = str_replace( $tlink['string'], $ttout, $tout );
			}
			
			$out .= $tout;
			$out .= "</ref>";
			
			return $out;
			 
		} elseif( $link['link_type'] == "externallink" ) {
			$out .= str_replace( $link['externallink']['remainder'], "", $link['string'] );
		} elseif( $link['link_type'] == "template" ) {
			$out .= "{{".$link['template']['name'];
			foreach( $mArray['link_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
			$out .= "}}";
		}
		if( $mArray['tagged_dead'] === true ) {
			if( $mArray['tag_type'] == "template" ) {
				$out .= "{{".$mArray['tag_template']['name'];
				foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
				$out .= "}}";
			}
		}
		$out .= $remainder;
		if( $mArray['has_archive'] === true ) {
			if( $link['link_type'] == "externallink" ) {
				$out = str_replace( $mArray['url'], $mArray['archive_url'], $out );
			} elseif( $mArray['archive_type'] == "template" ) {
				$out .= " {{".$mArray['archive_template']['name'];
				foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
				$out .= "}}";  
			}
		}
		return $out;
	}
	
}