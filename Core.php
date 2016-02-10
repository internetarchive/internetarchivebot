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
* Core object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/

/**
* Core class
* Core functions for analyzing pages
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class Core {
    
    /**
    * Master page analyzer function.  Analyzes the entire page's content,
    * retrieves specified URLs, and analyzes whether they are dead or not.
    * If they are dead, the function acts based on onwiki specifications.
    * 
    * @param API $commObject An API object created for the page
    * @static
    * @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return array containing analysis statistics of the page
    */
    public static function analyzePage( API $commObject ) {
        if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA, serialize( array( 'title' => $commObject->page, 'id' => $commObject->pageid ) ) );
        $tmp = PARSERCLASS;
        $parser = new $tmp( $commObject );
        unset($tmp);
        if( WORKERS === false ) echo "Analyzing {$commObject->page} ({$commObject->pageid})...\n";
        $modifiedLinks = array();
        $archiveProblems = array();
        $archived = 0;
        $rescued = 0;
        $tagged = 0;
        $analyzed = 0;
        $newlyArchived = array();
        $timestamp = date( "Y-m-d\TH:i:s\Z" ); 
        $history = array(); 
        $newtext = $commObject->content;
        if( preg_match( '/\{\{((U|u)se)?\s?(D|d)(MY|my)\s?(dates)?/i', $commObject->content ) ) $df = true;
        else $df = false;
        if( $commObject->LINK_SCAN == 0 ) $links = $parser->getExternalLinks();
        else $links = $parser->getReferences();
        $analyzed = $links['count'];
        unset( $links['count'] );
                                       
        //Process the links
        $checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = array();
        foreach( $links as $id=>$link ) {
            if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
            if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $commObject->ARCHIVE_ALIVE == 1 ) $toArchive[$id] = $link[$link['link_type']]['url'];
        }
        $checkResponse = $commObject->isArchived( $toArchive );
        $checkResponse = $checkResponse['result'];
        $toArchive = array();
        foreach( $links as $id=>$link ) {
            if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
            if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $commObject->ARCHIVE_ALIVE == 1 && !$checkResponse[$id] ) {
                $toArchive[$id] = $link[$link['link_type']]['url']; 
            }
            if( $commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
                if( $link[$link['link_type']]['link_type'] != "x" ) {
                    if( ($link[$link['link_type']]['tagged_dead'] === true && ( $commObject->TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $commObject->DEAD_ONLY == 2 ) || ( $commObject->DEAD_ONLY == 0 ) ) {
                        $toFetch[$id] = array( $link[$link['link_type']]['url'], ( $commObject->ARCHIVE_BY_ACCESSDATE == 1 ? ( $link[$link['link_type']]['access_time'] != "x" ? $link[$link['link_type']]['access_time'] : null ) : null ) );  
                    }
                }
            }
        }
        $errors = array();
        if( !empty( $toArchive ) ) {
            $archiveResponse = $commObject->requestArchive( $toArchive );
            $errors = $archiveResponse['errors'];
            $archiveResponse = $archiveResponse['result'];
        }
        if( !empty( $toFetch ) ) {
            $fetchResponse = $commObject->retrieveArchive( $toFetch );
            $fetchResponse = $fetchResponse['result'];
        } 
        foreach( $links as $id=>$link ) {
            if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
            if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $commObject->ARCHIVE_ALIVE == 1 && !$checkResponse[$id] ) {
                if( $archiveResponse[$id] === true ) {
                    $archived++;  
                } elseif( $archiveResponse[$id] === false ) {
                    $archiveProblems[$id] = $link[$link['link_type']]['url'];
                }
            }
            if( $commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
                if( $link[$link['link_type']]['link_type'] != "x" ) {
                    if( ($link[$link['link_type']]['tagged_dead'] === true && ( $commObject->TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $commObject->TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $commObject->DEAD_ONLY == 2 ) || ( $commObject->DEAD_ONLY == 0 ) ) {
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
                                $link['newdata']['archive_type'] = "template";
                                $link['newdata']['tagged_dead'] = false;
                                $link['newdata']['archive_template']['name'] = "wayback";
                                if( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) unset( $link[$link['link_type']]['archive_template']['parameters'] );
                                $link['newdata']['archive_template']['parameters']['url'] = $link[$link['link_type']]['url'];
                                $link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
                                if( $df === true ) $link['newdata']['archive_template']['parameters']['df'] = "y";
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
            if( isset( $link['newdata'] ) && self::newIsNew( $link ) ) {
                if( isset( $link[$link['link_type']]['template_url'] ) ) {
                    $link[$link['link_type']]['url'] = $link[$link['link_type']]['template_url'];
                    unset( $link[$link['link_type']]['template_url'] );
                }
                $link['newstring'] = $parser->generateString( $link );
                $newtext = str_replace( $link['string'], $link['newstring'], $newtext );
            }
        }
        $archiveResponse = $checkResponse = $fetchResponse = null;
        unset( $archiveResponse, $checkResponse, $fetchResponse );
        if( WORKERS === true ) {
            echo "Analyzed {$commObject->page} ({$commObject->pageid})\n";
        }
        echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: ".(memory_get_usage( true )/1048576)." MB; Max System Memory Used: ".(memory_get_peak_usage(true)/1048576)." MB\n";
        if( !empty( $archiveProblems ) && $commObject->NOTIFY_ERROR_ON_TALK == 1 ) {
            $body = str_replace( "{problematiclinks}", $out, str_replace( "\\n", "\n", $commObject->TALK_ERROR_MESSAGE ) )."~~~~";
            $out = "";
            foreach( $archiveProblems as $id=>$problem ) {
                $out .= "* $problem with error {$errors[$id]}\n";
            } 
            $body = str_replace( "{problematiclinks}", $out, str_replace( "\\n", "\n", $commObject->TALK_ERROR_MESSAGE ) )."~~~~";
            API::edit( "Talk:{$commObject->page}", $body, "Notifications of sources failing to archive. #IABot", false, true, "new", $commObject->TALK_ERROR_MESSAGE_HEADER );  
        }
        $pageModified = false;
        if( $commObject->content != $newtext ) {
            $pageModified = true;
            $revid = API::edit( $commObject->page, $newtext, "Rescuing $rescued sources, flagging $tagged as dead, and archiving $archived sources. #IABot", false, $timestamp );
            if( $commObject->NOTIFY_ON_TALK == 1 && $revid !== false ) {
                $out = "";
                foreach( $modifiedLinks as $link ) {
                    $out .= "*";
                    switch( $link['type'] ) {
                        case "addarchive":
                        $out .= "Added archive {$link['newarchive']} to ";
                        break;
                        case "modifyarchive":
                        $out .= "Replaced archive link {$link['oldarchive']} with {$link['newarchive']} on ";
                        break;
                        case "fix":
                        $out .= "Attempted to fix sourcing for ";
                        break;
                        case "tagged":
                        $out .= "Added {{tlx|dead link}} tag to ";
                        break;
                        case "tagremoved":
                        $out .= "Removed dead tag from ";
                        break;
                        default:
                        $out .= "Modified source for ";
                        break;
                    }
                    $out .= $link['link'];
                    $out .= "\n";     
                }
                $header = str_replace( "{namespacepage}", $commObject->page, str_replace( "{linksmodified}", $tagged+$rescued, str_replace( "{linksrescued}", $rescued, str_replace( "{linkstagged}", $tagged, $commObject->TALK_MESSAGE_HEADER ) ) ) );
                $body = str_replace( "{diff}", "https://en.wikipedia.org/w/index.php?diff=prev&oldid=$revid", str_replace( "{modifiedlinks}", $out, str_replace( "{namespacepage}", $commObject->page, str_replace( "{linksmodified}", $tagged+$rescued, str_replace( "{linksrescued}", $rescued, str_replace( "{linkstagged}", $tagged, str_replace( "\\n", "\n", $commObject->TALK_MESSAGE ) ) ) ) ) ) )."~~~~";
                API::edit( "Talk:{$commObject->page}", $body, "Notification of altered sources needing review #IABot", false, false, true, "new", $header );
            }
        }
        $commObject->db->updateDBValues();
        
        echo "\n";
        
        $commObject->closeResources();
        $parser->__destruct();
        
        $commObject = $parser = $newtext = $history = null;
        unset( $commObject, $parser, $newtext, $history, $res, $db );
        $returnArray = array( 'linksanalyzed'=>$analyzed, 'linksarchived'=>$archived, 'linksrescued'=>$rescued, 'linkstagged'=>$tagged, 'pagemodified'=>$pageModified );
        return $returnArray;
    }
    
    /**
    * Generates a log entry and posts it to the bot log on wiki
    * @access public
    * @static
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
    * @global $NOTIFY_ON_TALK, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $runtime, $pagesAnalyzed, $pagesModified
    */
    public static function generateLogReport() {
        global $NOTIFY_ON_TALK, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $runtime, $pagesAnalyzed, $pagesModified;
        $log = API::getPageText( "User:Cyberbot II/Dead-Links Log" );
        $entry = "|-\n|";
        $entry .= date( 'H:i, j F Y (\U\T\C)', $runstart );
        $entry .= "||";
        $entry .= date( 'H:i, j F Y (\U\T\C)', $runend );
        $entry .= "||";
        $entry .= date( 'z:H:i:s', $runend-$runstart );
        $entry .= "||";
        $entry .= $pagesAnalyzed;
        $entry .= "||";
        $entry .= $pagesModified;
        $entry .= "||";
        $entry .= $linksAnalyzed;
        $entry .= "||";
        $entry .= $linksFixed;
        $entry .= "||";
        $entry .= $linksTagged;
        $entry .= "||";
        $entry .= $linksArchived;
        $entry .= "\n";
        $log = str_replace( "|}", $entry."|}", $log );
        API::edit( "User:Cyberbot II/Dead-Links Log", $log, "Updating run log with run statistics #IABot" );
        return;
    }
    
    /**
    * Merge the new data in a custom array_merge function
    * @access public
    * @param array $link An array containing details and newdata about a specific reference.
    * @param bool $recurse Is this function call a recursive call?
    * @static                                                          
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
    * @global $NOTIFY_ON_TALK, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $runtime, $pagesAnalyzed, $pagesModified
    */
    public static function mergeNewData( $link, $recurse = false ) {
        $returnArray = array();
        if( $recurse !== false ) {
            foreach( $link as $parameter => $value ) {
                if( isset( $recurse[$parameter] ) && !is_array( $recurse[$parameter] ) && !is_array( $value ) ) $returnArray[$parameter] = $recurse[$parameter];
                elseif( isset($recurse[$parameter] ) && is_array( $recurse[$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = self::mergeNewData( $value, $recurse[$parameter] );
                elseif( isset( $recurse[$parameter] ) ) $returnArray[$parameter] = $recurse[$parameter];
                else $returnArray[$parameter] = $value; 
            }
            foreach( $recurse as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
            return $returnArray;
        }
        foreach( $link[$link['link_type']] as $parameter => $value ) {
            if( isset( $link['newdata'][$parameter] ) && !is_array( $link['newdata'][$parameter] )  && !is_array( $value ) ) $returnArray[$parameter] = $link['newdata'][$parameter];
            elseif( isset( $link['newdata'][$parameter] ) && is_array( $link['newdata'][$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = self::mergeNewData( $value, $link['newdata'][$parameter] );
            elseif( isset( $link['newdata'][$parameter] ) ) $returnArray[$parameter] = $link['newdata'][$parameter];
            else $returnArray[$parameter] = $value;    
        }
        foreach( $link['newdata'] as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
        return $returnArray;
    }
    
    /**
    * Verify that newdata is actually different from old data
    * 
    * @access public
    * @static                                                          
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @param mixed $link
    * @return bool Whether the data in the link array contains new data from the old data.
    */
    public static function newIsNew( $link ) {
        $t = false;
        foreach( $link['newdata'] as $parameter => $value ) {
            if( !isset( $link[$link['link_type']][$parameter] ) || $value != $link[$link['link_type']][$parameter] ) $t = true;
        }
        return $t;
    }
}