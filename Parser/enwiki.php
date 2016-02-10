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
	    if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->IGNORE_TAGS ) ).')\s*?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params ) || preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->IGNORE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
	        return array( 'ignore' => true );
	    }
	    if( strpos( $linkString, "archive.org" ) !== false && !preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
	        $returnArray['has_archive'] = true;
	        $returnArray['is_archive'] = true;
	        $returnArray['archive_type'] = "link";
	        $returnArray['link_type'] = "x";
	        if( preg_match( '/archive\.org\/(web\/)?(\d{14}|\*)\/(\S*)\s/i', $linkString, $returnArray['url'] ) ) {
	            if( $returnArray['url'][2] != "*" ) $returnArray['archive_time'] = strtotime( $returnArray['url'][2] );
	            else $returnArray['archive_time'] = "x";
	            $returnArray['archive_url'] = trim( $returnArray['url'][0] );
	            $returnArray['url'] = $returnArray['url'][3];
	        } else {
	            return array( 'ignore' => true );  
	        }
	        $returnArray['access_time'] = $returnArray['archive_time'];
	        $returnArray['tagged_dead'] = true;
	        $returnArray['tag_type'] = "implied"; 
	    } elseif( strpos( $linkString, "archiveurl" ) === false && strpos( $linkString, "archive-url" ) === false && strpos( $linkString, "web.archive.org" ) !== false && preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
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
	            $returnArray['url'] = $params2[3];    
	        } else {
	            return array( 'ignore' => true );
	        }
	        $returnArray['tagged_dead'] = true;
	        $returnArray['tag_type'] = "implied";
	        if( !isset( $returnArray['link_template']['parameters']['accessdate'] ) && !isset( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = $returnArray['archive_time'];   
	        else {
	            if( isset( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
	            else $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
	        }
	    } elseif( empty( $linkString ) && preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params ) ) {
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
	    } elseif( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')\s*?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
	        $returnArray['tagged_dead'] = false;
	        if( !empty( $remainder ) ) {
	            $returnArray['has_archive'] = false;
	            $returnArray['is_archive'] = false;
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')\s*?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params2 ) ) {
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
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->DEADLINK_TAGS ) ).')\s*?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
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
	        if( isset( $returnArray['link_template']['parameters']['url'] ) ) $returnArray['url'] = $returnArray['link_template']['parameters']['url'];
	        else return array( 'ignore' => true );
	        if( isset( $returnArray['link_template']['parameters']['accessdate']) && !empty( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
	        elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) && !empty( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
	        else $returnArray['access_time'] = "x";
	        if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archiveurl'];  
	        if( isset( $returnArray['link_template']['parameters']['archive-url'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archive-url'];
	        if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) || isset( $returnArray['link_template']['parameters']['archive-url'] ) ) {
	            $returnArray['archive_type'] = "parameter";
	            $returnArray['has_archive'] = true;
	            $returnArray['is_archive'] = true;
	        }
	        if( isset( $returnArray['link_template']['parameters']['archivedate'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['link_template']['parameters']['archivedate'] );
	        if( isset( $returnArray['link_template']['parameters']['archive-date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['link_template']['parameters']['archive-date'] );
	        if( ( isset( $returnArray['link_template']['parameters']['deadurl'] ) && $returnArray['link_template']['parameters']['deadurl'] == "yes" ) || ( ( isset( $returnArray['link_template']['parameters']['dead-url'] ) && $returnArray['link_template']['parameters']['dead-url'] == "yes" ) ) ) {
	            $returnArray['tagged_dead'] = true;
	            $returnArray['tag_type'] = "parameter";
	        }
	    } elseif( preg_match( '/((?:https?:)?\/\/.*?)(\s|\])/i', $linkString, $params ) ) {
	        $returnArray['url'] = $params[1];
	        $returnArray['link_type'] = "link"; 
	        $returnArray['access_time'] = "x";
	        $returnArray['is_archive'] = false;
	        $returnArray['tagged_dead'] = false;
	        $returnArray['has_archive'] = false;
	        if( !empty( $remainder ) ) {
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')\s?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
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
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->DEADLINK_TAGS ) ).')\s*?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
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