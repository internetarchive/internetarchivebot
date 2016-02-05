<?php
  
class enwikiParser extends Parser {
	
	public function getExternalLinks() {
	    $linksAnalyzed = 0;
	    $tArray = array_merge( $this->commObject->DEADLINK_TAGS, $this->commObject->ARCHIVE_TAGS, $this->commObject->IGNORE_TAGS );
	    $returnArray = array();
	    $toCheck = array();
	    $regex = '/(<ref([^\/]*?)>(.*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}.*?)?<\/ref>(('.str_replace( "\}\}", "", implode( '|', $tArray ) ).').*?\}\})*|\[{1}?((?:https?:)?\/\/.*?)\s?.*?\]{1}?.*?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}\s*?)*?|(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).').*?\}\})\s*?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}\s*?)*?)/i';
	    preg_match_all( $regex, preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $this->commObject->content ), $params );
	    foreach( $params[0] as $tid=>$fullmatch ) {
	        $linksAnalyzed++;
	        if( !empty( $params[2][$tid] ) || !empty( $params[2][$tid] ) || !empty( $params[3][$tid] ) ) {
	            $returnArray[$tid]['link_type'] = "reference";
	            //Fetch parsed reference content
	            $returnArray[$tid]['reference'] = $this->getLinkDetails( $params[3][$tid], $params[4][$tid].$params[6][$tid] ); 
	            if( !isset( $returnArray[$tid]['reference']['ignore'] ) || $returnArray[$tid]['reference']['ignore'] === false ) {
					$this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'], $tid );
	                $returnArray[$tid]['reference'] = $this->updateLinkInfo( $returnArray[$tid]['reference'], $tid );
	            }
	            $returnArray[$tid]['string'] = $params[0][$tid];
	            //Fetch reference parameters
	            if( !empty( $params[2][$tid] ) ) $returnArray[$tid]['reference']['parameters'] = $this->getReferenceParameters( $params[2][$tid] );
	            if( !isset( $returnArray[$tid]['reference']['ignore'] ) || $returnArray[$tid]['reference']['ignore'] === false ) $toCheck[$tid] = $returnArray[$tid]['reference'];
                if( empty( $params[3][$tid] ) && empty( $params[4][$tid] ) ) {
	                unset( $returnArray[$tid] );
	                continue;
	            }
	        } elseif( !empty( $params[8][$tid] ) ) {
	            $returnArray[$tid]['link_type'] = "externallink";
	            //Fetch parsed reference content
	            $returnArray[$tid]['externallink'] = $this->getLinkDetails( $params[0][$tid], $params[9][$tid] ); 
	            if( !isset( $returnArray[$tid]['externallink']['ignore'] ) || $returnArray[$tid]['externallink']['ignore'] === false ) {
	                $this->commObject->db->retrieveDBValues( $returnArray[$tid]['externallink'], $tid );
	                $returnArray[$tid]['externallink'] = $this->updateLinkInfo( $returnArray[$tid]['externallink'], $tid );
                    $toCheck[$tid] = $returnArray[$tid]['externallink'];
	            }
	            $returnArray[$tid]['string'] = $params[0][$tid];
	        } elseif( !empty( $params[11][$tid] ) || !empty( $params[13][$tid] ) ) {
	            $returnArray[$tid]['link_type'] = "template";
	            //Fetch parsed reference content
	            $returnArray[$tid]['template'] = $this->getLinkDetails( $params[11][$tid], $params[13][$tid] );
	            if( !isset( $returnArray[$tid]['template']['ignore'] ) || $returnArray[$tid]['template']['ignore'] === false ) {
	                $this->commObject->db->retrieveDBValues( $returnArray[$tid]['template'], $tid );
	                $returnArray[$tid]['template'] = $this->updateLinkInfo( $returnArray[$tid]['template'], $tid );
                    $toCheck[$tid] = $returnArray[$tid]['template'];
	            }
	            $returnArray[$tid]['name'] = str_replace( "{{", "", $params[12][$tid] );
	            $returnArray[$tid]['string'] = $params[0][$tid];
	        }
	    }
	    $toCheck = $this->updateAccessTimes( $toCheck );
	    foreach( $toCheck as $tid=>$link ) {
			$returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
	    }
	    $returnArray['count'] = $linksAnalyzed;
	    return $returnArray; 
	}
	
	//This is the parsing engine.  It picks apart the string in every detail, so the bot can accurately construct an appropriate reference.
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
	            $returnArray['archive_url'] = trim( $params2[0] );
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
	                    $returnArray['archive_url'] = $params3[0];
	                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
	                } else {
	                    $returnArray['archive_type'] = "invalid";
	                } 
	            } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
	                if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
	                    $returnArray['archive_type'] = "invalid";
	                    $returnArray['archive_time'] = strtotime( $params3[2] );
	                    $returnArray['archive_url'] = $params3[0];
	                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
	                } else {
	                    $returnArray['archive_type'] = "invalid";
	                }
	            } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
	                if( preg_match( 'archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
	                    $returnArray['archive_type'] = "invalid";
	                    $returnArray['archive_time'] = strtotime( $params3[2] );
	                    $returnArray['archive_url'] = $params3[0];
	                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
	                } else {
	                    $returnArray['archive_type'] = "invalid";
	                }
	            }
	        }
	        $returnArray['access_time'] = $returnArray['archive_time'];
	    } elseif( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
	        $returnArray['tagged_dead'] = false;
	        if( !empty( $remainder ) ) {
	            $returnArray['has_archive'] = false;
	            $returnArray['is_archive'] = false;
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params2 ) ) {
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
	                            $returnArray['archive_url'] = $params3[0];
	                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
	                        } else {
	                            $returnArray['archive_type'] = "invalid";
	                        } 
	                    } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
	                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
	                            $returnArray['archive_type'] = "invalid";
	                            $returnArray['archive_time'] = strtotime( $params3[2] );
	                            $returnArray['archive_url'] = $params3[0];
	                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
	                        } else {
	                            $returnArray['archive_type'] = "invalid";
	                        }
	                    } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
	                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
	                            $returnArray['archive_type'] = "invalid";
	                            $returnArray['archive_time'] = strtotime( $params3[2] );
	                            $returnArray['archive_url'] = $params3[0];
	                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
	                        } else {
	                            $returnArray['archive_type'] = "invalid";
	                        }
	                    }
	                }
	            }
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->DEADLINK_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
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
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->ARCHIVE_TAGS ) ).')\s?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
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
	                            $returnArray['archive_url'] = $params3[0];
	                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
	                        } else {
	                            $returnArray['archive_type'] = "invalid";
	                        } 
	                    } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
	                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
	                            $returnArray['archive_type'] = "invalid";
	                            $returnArray['archive_time'] = strtotime( $params3[2] );
	                            $returnArray['archive_url'] = $params3[0];
	                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
	                        } else {
	                            $returnArray['archive_type'] = "invalid";
	                        }
	                    } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
	                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
	                            $returnArray['archive_type'] = "invalid";
	                            $returnArray['archive_time'] = strtotime( $params3[2] );
	                            $returnArray['archive_url'] = $params3[0];
	                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
	                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
	                        } else {
	                            $returnArray['archive_type'] = "invalid";
	                        }
	                    }
	                }
	            }
	            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $this->commObject->DEADLINK_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
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
	
	//Gather and parse all references and return as organized array
	public function getReferences() {
	    $linksAnalyzed = 0;
	    $tArray = array_merge( $this->commObject->DEADLINK_TAGS, $this->commObject->ARCHIVE_TAGS, $this->commObject->IGNORE_TAGS );
	    $returnArray = array(); 
        $toCheck = array();
	    $regex = '/<ref([^\/]*?)>(.*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}.*?)?<\/ref>(('.str_replace( "\}\}", "", implode( '|', $tArray ) ).').*?\}\})*/i';
	    preg_match_all( $regex, preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $page ), $params );
	    foreach( $params[0] as $tid=>$fullmatch ) {
	        $linksAnalyzed++;
	        if( !isset( $returnArray[$tid] ) ) {
	            $returnArray[$tid]['link_type'] = "reference";
	            //Fetch parsed reference content
	            $returnArray[$tid]['reference'] = $this->getLinkDetails( $params[2][$tid], $params[3][$tid].$params[5][$tid] ); 
	            if( !isset( $returnArray[$tid]['reference']['ignore'] ) || $returnArray[$tid]['reference']['ignore'] === false ) {
	                $this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'], $tid );
	                $returnArray[$tid]['reference'] = $this->updateLinkInfo( $returnArray[$tid]['reference'], $tid );
	            }
	            $returnArray[$tid]['string'] = $params[0][$tid];
	        }
	        //Fetch reference parameters
	        if( !empty( $params[1][$tid] ) ) $returnArray[$tid]['reference']['parameters'] = $this->getReferenceParameters( $params[1][$tid] );
	        if( !isset( $returnArray[$tid]['reference']['ignore'] ) || $returnArray[$tid]['reference']['ignore'] === false ) $toCheck[$tid] = $returnArray[$tid]['reference'];
            if( empty( $params[2][$tid] ) && empty( $params[3][$tid] ) ) {
	            unset( $returnArray[$tid] );
	            continue;
	        }
	    }
        $toCheck = $this->updateAccessTimes( $toCheck );
        foreach( $toCheck as $tid=>$link ) {
            $returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
        }
	    $returnArray['count'] = $linksAnalyzed;
	    return $returnArray;   
	}
	
	//Construct string
	public function generateString( $link ) {
	    $out = "";
	    $mArray = mergeNewData( $link );
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
	        $out .= "{{".$link['name'];
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