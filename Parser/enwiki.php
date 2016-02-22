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
	along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
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
	    foreach( $links as $id=>$link ) {
	        if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
	        if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 ) $toArchive[$id] = $link[$link['link_type']]['url'];
	    }
	    $checkResponse = $this->commObject->isArchived( $toArchive );
	    $checkResponse = $checkResponse['result'];
	    $toArchive = array();
	    foreach( $links as $id=>$link ) {
	        if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
	        if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 && !$checkResponse[$id] ) {
	            $toArchive[$id] = $link[$link['link_type']]['url']; 
	        }
	        if( $this->commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
	            if( $link[$link['link_type']]['link_type'] != "x" ) {
	                if( ($link[$link['link_type']]['tagged_dead'] === true && ( $this->commObject->TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $this->commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $this->commObject->DEAD_ONLY == 2 ) || ( $this->commObject->DEAD_ONLY == 0 ) ) {
	                    $toFetch[$id] = array( $link[$link['link_type']]['url'], ( $this->commObject->ARCHIVE_BY_ACCESSDATE == 1 ? ( $link[$link['link_type']]['access_time'] != "x" ? $link[$link['link_type']]['access_time'] : null ) : null ) );  
	                }
	            }
	        }
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
	    foreach( $links as $id=>$link ) {
	        if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
	        if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $this->commObject->ARCHIVE_ALIVE == 1 && !$checkResponse[$id] ) {
	            if( $archiveResponse[$id] === true ) {
	                $archived++;  
	            } elseif( $archiveResponse[$id] === false ) {
	                $archiveProblems[$id] = $link[$link['link_type']]['url'];
	            }
	        }
	        if( $this->commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
	            if( $link[$link['link_type']]['link_type'] != "x" ) {
	                if( ($link[$link['link_type']]['tagged_dead'] === true && ( $this->commObject->TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $this->commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $this->commObject->DEAD_ONLY == 2 ) || ( $this->commObject->DEAD_ONLY == 0 ) ) {
	                    if( ($temp = $fetchResponse[$id]) !== false ) {
	                        $rescued++;
	                        $modifiedLinks[$id]['type'] = "addarchive";
	                        $modifiedLinks[$id]['link'] = $link[$link['link_type']]['url'];
	                        $modifiedLinks[$id]['newarchive'] = $temp['archive_url'];
	                        if( $link[$link['link_type']]['has_archive'] === true ) {
	                            $modifiedLinks[$id]['type'] = "modifyarchive";
	                            $modifiedLinks[$id]['oldarchive'] = $link[$link['link_type']]['archive_url'];
	                        }
	                        $link['newdata']['has_archive'] = true;
	                        $link['newdata']['archive_url'] = $temp['archive_url'];
	                        $link['newdata']['archive_time'] = $temp['archive_time'];
	                        if( $link[$link['link_type']]['link_type'] == "link" ) {
	                            if( trim( $link[$link['link_type']]['link_string'], " []" ) == $link[$link['link_type']]['url'] ) {
	                                $link['newdata']['archive_type'] = "parameter";
	                                $link['newdata']['link_template']['name'] = "cite web";
	                                $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['url'];
	                                if( $df === true ) {
	                                    $link['newdata']['link_template']['parameters']['accessdate'] = date( 'j F Y', $link[$link['link_type']]['access_time'] );
	                                } else {
	                                    $link['newdata']['link_template']['parameters']['accessdate'] = date( 'F j, Y', $link[$link['link_type']]['access_time'] );
	                                }
	                                if( $link[$link['link_type']]['tagged_dead'] === true || $link[$link['link_type']]['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
	                                else $link['newdata']['tagged_dead'] = false;
	                                $link['newdata']['tag_type'] = "parameter";
	                                if( $link[$link['link_type']]['tagged_dead'] === true || $link[$link['link_type']]['is_dead'] === true ) {
	                                    if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
	                                    else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
	                                }
	                                else {
	                                    if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
	                                    else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
	                                }
	                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
	                                else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];
	                                if( $df === true ) {
	                                    if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'j F Y', $temp['archive_time'] );
	                                    else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'j F Y', $temp['archive_time'] );
	                                } else {
	                                    if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'F j, Y', $temp['archive_time'] );
	                                    else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'F j, Y', $temp['archive_time'] );    
	                                }
	                                
	                                if( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) {
	                                    if( !isset( $link[$link['link_type']]['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['url'];
	                                    else $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['template_url'];
	                                    $modifiedLinks[$id]['type'] = "fix";
	                                }
	                                $link[$link['link_type']]['link_type'] = "template";
	                            } else {
	                                $link['newdata']['archive_type'] = "template";
	                                $link['newdata']['tagged_dead'] = false;
	                                $link['newdata']['archive_template']['name'] = "wayback";
	                                if( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) unset( $link[$link['link_type']]['archive_template']['parameters'] );
	                                $link['newdata']['archive_template']['parameters']['url'] = $link[$link['link_type']]['url'];
	                                $link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
	                                if( $df === true ) $link['newdata']['archive_template']['parameters']['df'] = "y";
	                            }
	                        } elseif( $link[$link['link_type']]['link_type'] == "template" ) {
	                            $link['newdata']['archive_type'] = "parameter";
	                            if( $link[$link['link_type']]['tagged_dead'] === true || $link[$link['link_type']]['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
	                            else $link['newdata']['tagged_dead'] = false;
	                            $link['newdata']['tag_type'] = "parameter";
	                            if( $link[$link['link_type']]['tagged_dead'] === true || $link[$link['link_type']]['is_dead'] === true ) {
	                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
	                                else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
	                            }
	                            else {
	                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
	                                else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
	                            }
	                            if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
	                            else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];
	                            if( $df === true ) {
	                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'j F Y', $temp['archive_time'] );
	                                else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'j F Y', $temp['archive_time'] );
	                            } else {
	                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'F j, Y', $temp['archive_time'] );
	                                else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'F j, Y', $temp['archive_time'] );    
	                            }
	                            
	                            if( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) {
	                                if( !isset( $link[$link['link_type']]['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['url'];
	                                else $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['template_url'];
	                                $modifiedLinks[$id]['type'] = "fix";
	                            }
	                        }
	                        unset( $temp );
	                    } else {
	                        if( $link[$link['link_type']]['tagged_dead'] !== true ) $link['newdata']['tagged_dead'] = true;
	                        else continue;
	                        $tagged++;
	                        $modifiedLinks[$id]['type'] = "tagged";
	                        $modifiedLinks[$id]['link'] = $link[$link['link_type']]['url'];
	                        if( $link[$link['link_type']]['link_type'] == "link" ) {
	                            $link['newdata']['tag_type'] = "template";
	                            $link['newdata']['tag_template']['name'] = "dead link";
	                            $link['newdata']['tag_template']['parameters']['date'] = date( 'F Y' );
	                            $link['newdata']['tag_template']['parameters']['bot'] = USERNAME;    
	                        } elseif( $link[$link['link_type']]['link_type'] == "template" ) {
	                            $link['newdata']['tag_type'] = "parameter";
	                            if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
	                            else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
	                        }
	                    }    
	                } elseif( $link[$link['link_type']]['tagged_dead'] === true && $link[$link['link_type']]['is_dead'] == false ) {
	                    $rescued++;
	                    $modifiedLinks[$id]['type'] = "tagremoved";
	                    $modifiedLinks[$id]['link'] = $link[$link['link_type']]['url'];
	                    $link['newdata']['tagged_dead'] = false;
	                }   
	            }
	        }
	        if( isset( $link['newdata'] ) && Core::newIsNew( $link ) ) {
	            if( isset( $link[$link['link_type']]['template_url'] ) ) {
	                $link[$link['link_type']]['url'] = $link[$link['link_type']]['template_url'];
	                unset( $link[$link['link_type']]['template_url'] );
	            }
	            $link['newstring'] = $this->generateString( $link );
	            $newtext = str_replace( $link['string'], $link['newstring'], $newtext );
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
	        $magicwords['linkstagged'] = $tagged;
	        $magicwords['linksarchived'] = $archived;
	        $magicwords['linksanalyzed'] = $analyzed;
	        if( $this->commObject->NOTIFY_ON_TALK_ONLY == 0 ) $revid = API::edit( $this->commObject->page, $newtext, $this->commObject->getConfigText( "MAINEDITSUMMARY", $magicwords )." #IABot", false, $timestamp );
	        if( ($this->commObject->NOTIFY_ON_TALK == 1 && $revid !== false) || $this->commObject->NOTIFY_ON_TALK_ONLY == 1 ) {
	            $out = "";
	            if( isset( $revid ) ) $magicwords['diff'] = "https://en.wikipedia.org/w/index.php?diff=prev&oldid=$revid";
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
	    }
	    $this->commObject->db->updateDBValues();
	    
	    echo "\n";
	    
	    $newtext = $history = null;
	    unset( $this->commObject, $newtext, $history, $res, $db );
	    $returnArray = array( 'linksanalyzed'=>$analyzed, 'linksarchived'=>$archived, 'linksrescued'=>$rescued, 'linkstagged'=>$tagged, 'pagemodified'=>$pageModified );
	    return $returnArray;
	}
	
	/**
	* Parses the pages for refences, citation templates, and bare links.
	* 
	* @param bool $referenceOnly
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array All parsed links
	*/
	protected function parseLinks( $referenceOnly = false ) {
	    $returnArray = array();
	    $tArray = array_merge( $this->commObject->DEADLINK_TAGS, $this->commObject->ARCHIVE_TAGS, $this->commObject->IGNORE_TAGS );
	    $scrapText = preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $this->commObject->content );
	    if( preg_match_all( '/<ref([^\/]*?)>((.|\n)*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .')(.|\n)*?\}\}(.|\n)*?)?<\/ref\s*?>((\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})*)/i', $scrapText, $matches ) ) {
	        foreach( $matches[0] as $tid=>$fullmatch ) {
	            $returnArray[$tid]['string'] = $fullmatch;
	            $returnArray[$tid]['link_string'] = $matches[2][$tid];
	            $returnArray[$tid]['remainder'] = $matches[4][$tid].$matches[8][$tid];
	            $returnArray[$tid]['type'] = "reference";
	            $returnArray[$tid]['parameters'] = $this->getReferenceParameters( $matches[1][$tid] );
	        } 
	        $scrapText = preg_replace( '/<ref([^\/]*?)>((.|\n)*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .')(.|\n)*?\}\}(.|\n)*?)?<\/ref\s*?>((\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})*)/i', "", $scrapText );     
	    }
	    if( $referenceOnly === false ) {
	        $arrayoffset = count( $returnArray );    
	        if( preg_match_all( '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).').*?\}\})\s*?((\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})*)/i', $scrapText, $matches ) ) {
	            foreach( $matches[0] as $tid=>$fullmatch ) {
	                $returnArray[$tid+$arrayoffset]['string'] = $fullmatch;
	                $returnArray[$tid+$arrayoffset]['link_string'] = $matches[1][$tid];
	                $returnArray[$tid+$arrayoffset]['remainder'] = $matches[3][$tid];
	                $returnArray[$tid+$arrayoffset]['type'] = "template";
	                $returnArray[$tid+$arrayoffset]['name'] = str_replace( "{{", "", $matches[2][$tid] );
	            } 
	            $scrapText = preg_replace( '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).').*?\}\})\s*?((\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})*)/i', "", $scrapText );     
	        }
	        $arrayoffset = count( $returnArray );
	        if( preg_match_all( '/[\[]?((?:https?:)?\/\/[^\]|\s|\[|\{]*)/i', $scrapText, $matches ) ) {
	            $start = 0;
	            foreach( $matches[0] as $tid=>$fullmatch ) {
	                $returnArray[$tid+$arrayoffset]['type'] = "externallink";
	                $start = strpos( $scrapText, $fullmatch, $start );
	                if( substr( $fullmatch, 0, 1 ) == "[" ) {
	                    $end = strpos( $scrapText, "]", $start ) + 1;    
	                } else {
	                    $end = $start + strlen( $fullmatch );
	                }  
	                $returnArray[$tid+$arrayoffset]['link_string'] = substr( $scrapText, $start, $end-$start );
	                $returnArray[$tid+$arrayoffset]['remainder'] = "";
	                while( preg_match( '/(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})/i', $scrapText, $match, null, $end ) ) {
	                    $match = $match[0];
	                    $snippet = substr( $scrapText, $end, strpos( $scrapText, $match, $end ) - $end );
	                    if( !preg_match( '/[^\s]{1}/i', $snippet ) ){
	                        $end = strpos( $scrapText, $match, $end ) + strlen( $match );
	                        $returnArray[$tid+$arrayoffset]['remainder'] .= $match;
	                    } else {
	                        break;
	                    }
	                }
	                $returnArray[$tid+$arrayoffset]['string'] = substr( $scrapText, $start, $end-$start );
	                $start = $end;
	            }    
	        }   
	    }
	    return $returnArray;
	}
	
	/**
	* Fetch all links in an article
	* 
	* @abstract
	* @param bool $referenceOnly Fetch references only
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Details about every link on the page
	*/
	public function getExternalLinks( $referenceOnly = false ) {
		$linksAnalyzed = 0;
		$returnArray = array();
		$toCheck = array();
		$parseData = $this->parseLinks( $referenceOnly );
		foreach( $parseData as $tid=>$parsed ){
	    	if( empty( $parsed['link_string'] ) && empty( $parsed['remainder'] ) ) continue;
			$linksAnalyzed++;
			$returnArray[$tid]['link_type'] = $parsed['type'];
			$returnArray[$tid][$parsed['type']] = $this->getLinkDetails( $parsed['link_string'], $parsed['remainder'] );
			$returnArray[$tid]['string'] = $parsed['string'];
			if( $parsed['type'] == "reference" ) {
				if( !empty( $parsed['parameters'] ) ) $returnArray[$tid]['reference']['parameters'] = $parsed['parameters'];
			}
			if( $parsed['type'] == "template" ) {
				$returnArray[$tid]['template']['name'] = $parsed['name'];
			}
			if( !isset( $returnArray[$tid][$parsed['type']]['ignore'] ) || $returnArray[$tid][$parsed['type']]['ignore'] === false ) {
				$this->commObject->db->retrieveDBValues( $returnArray[$tid][$parsed['type']], $tid );
				$returnArray[$tid][$parsed['type']] = $this->updateLinkInfo( $returnArray[$tid][$parsed['type']], $tid );
				$toCheck[$tid] = $returnArray[$tid][$parsed['type']];
			}
		}
		$toCheck = $this->updateAccessTimes( $toCheck );
		foreach( $toCheck as $tid=>$link ) {
			$returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
		}
		$returnArray['count'] = $linksAnalyzed;
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
		        $returnArray['archive_url'] = trim( $returnArray['url'][0] );
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
		                $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
		            } else {
		                $returnArray['archive_type'] = "invalid";
		            } 
		        } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
		            if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
		                $returnArray['archive_type'] = "invalid";
		                $returnArray['archive_time'] = strtotime( $params3[2] );
		                $returnArray['archive_url'] = "https://web.".$params3[0];
		            } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
		                $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
		            } else {
		                $returnArray['archive_type'] = "invalid";
		            }
		        } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
		            if( preg_match( 'archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
		                $returnArray['archive_type'] = "invalid";
		                $returnArray['archive_time'] = strtotime( $params3[2] );
		                $returnArray['archive_url'] = "https://web.".$params3[0];
		            } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
		                $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
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
		                        $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
		                    } else {
		                        $returnArray['archive_type'] = "invalid";
		                    } 
		                } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
		                    if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
		                        $returnArray['archive_type'] = "invalid";
		                        $returnArray['archive_time'] = strtotime( $params3[2] );
		                        $returnArray['archive_url'] = "https://web.".$params3[0];
		                    } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
		                        $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
		                    } else {
		                        $returnArray['archive_type'] = "invalid";
		                    }
		                } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
		                    if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
		                        $returnArray['archive_type'] = "invalid";
		                        $returnArray['archive_time'] = strtotime( $params3[2] );
		                        $returnArray['archive_url'] = "https://web.".$params3[0];
		                    } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
		                        $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
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
		} elseif( preg_match( '/((?:https?:)?\/\/.*?)(\s|\]|\{)/i', $linkString, $params ) ) {
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
		                        $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
		                    } else {
		                        $returnArray['archive_type'] = "invalid";
		                    } 
		                } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
		                    if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
		                        $returnArray['archive_type'] = "invalid";
		                        $returnArray['archive_time'] = strtotime( $params3[2] );
		                        $returnArray['archive_url'] = "https://web.".$params3[0];
		                    } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
		                        $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
		                    } else {
		                        $returnArray['archive_type'] = "invalid";
		                    }
		                } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
		                    if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
		                        $returnArray['archive_type'] = "invalid";
		                        $returnArray['archive_time'] = strtotime( $params3[2] );
		                        $returnArray['archive_url'] = "https://web.".$params3[0];
		                    } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
		                        $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
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
	        preg_match( '/\{\{\s*?(.*?)\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $returnArray['url'], $params );
	        $returnArray['template_url'] = $returnArray['url'];
	        $returnArray['url'] = $this->templatePointer->getURL( strtolower( $params[1] ), $this->getTemplateParameters( $params[2] ) );
	        if( $returnArray['url'] === false ) $returnArray['url'] = $returnArray['template_url'];  
	    }
	    if( !isset( $returnArray['ignore'] ) && $returnArray['access_time'] === false ) {
			$returnArray['access_time'] = "x";
	    }
	    if( !isset( $returnArray['ignore'] ) && isset( $returnArray['archive_time'] ) && $returnArray['archive_time'] === false ) {
			$returnArray['archive_time'] = strtotime( preg_replace( '/(?:https?:)?\/?\/?(web.)?archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', '$3', $returnArray['archive_url'] ) );
	    }
		return $returnArray;
	}
	
	/**
	* Fetches all references only
	* 
	* @access public
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Details about every reference found
	*/
	public function getReferences() {
		return $this->getExternallinks( true );
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
		$mArray = Core::mergeNewData( $link );
		$tArray = array_merge( $this->commObject->DEADLINK_TAGS, $this->commObject->ARCHIVE_TAGS, $this->commObject->IGNORE_TAGS ); 
		$regex = '/('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}/i';
		$remainder = preg_replace( $regex, "", $mArray['remainder'] );
		//Beginning of the string
		if( $link['link_type'] == "reference" ) {
		    $tArray = array();
		    if( isset( $link['reference']['parameters'] ) && isset( $link['newdata']['parameters'] ) ) $tArray = array_merge( $link['reference']['parameters'], $link['newdata']['parameters'] );
		    elseif( isset( $link['reference']['parameters'] ) ) $tArray = $link['reference']['parameters'];
		    elseif( isset( $link['newdata']['parameters'] ) ) $tArray = $link['reference']['parameters'];
		    $out .= "<ref";
		    foreach( $tArray as $parameter => $value ) {
		        $out .= " $parameter=$value";
		    }
		    $out .= ">";
		    if( $mArray['link_type'] == "link" || ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" ) ) $out .= $mArray['link_string'];
		    elseif( $mArray['link_type'] == "template" ) {
		        $out .= "{{".$mArray['link_template']['name'];
		        foreach( $mArray['link_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
		        $out .= "}}";
		    }  
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
		if( $link['link_type'] == "reference" ) $out .= "</ref>";
		return $out;
	}
	
}