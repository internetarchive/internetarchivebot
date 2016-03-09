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
* Parser object  
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* Parser class
* Allows for the parsing on project specific wiki pages
* @abstract
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
abstract class Parser {
	
	/**
	* The API class
	* 
	* @var API
	* @access public
	*/
	public $commObject;
	
	/**
	* The checkIfDead class
	* 
	* @var checkIfDead
	* @access protected
	*/
	protected $deadCheck;
	
	/**
	* Parser class constructor
	* 
	* @param API $commObject
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __construct( API $commObject ) {
		$this->commObject = $commObject;	
		$this->deadCheck = new checkIfDead();
	}
	
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
	public abstract function analyzePage();

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
	public abstract function getLinkDetails( $linkString, $remainder );
	
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
	public abstract function generateString( $link );
	
	/**
	* Look for stored access times in the DB, or update the DB with a new access time
	* Adds access time to the link details.
	* 
	* @param array $links A collection of links with respective details
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Returns the same array with the access_time parameters updated
	*/
	public function updateAccessTimes( $links ) {
		$toGet = array();
		foreach( $links as $tid=>$link ) {
			if( !isset( $this->commObject->db->dbValues[$tid]['create'] ) && $link['access_time'] == "x" ) $links[$tid]['access_time'] = $this->commObject->db->dbValues[$tid]['access_time'];
			elseif( $link['access_time'] == "x" ) {
		    	$toGet[$tid] = $link['url'];
			} else {
				$this->commObject->db->dbValues[$tid]['access_time'] = $link['access_time'];	
				if( !isset( $this->commObject->db->dbValues[$tid]['create'] ) ) $this->commObject->db->dbValues[$tid]['update'] = true;
			}	
		}	
		if( !empty( $toGet ) ) $toGet = $this->commObject->getTimesAdded( $toGet );
		foreach( $toGet as $tid=>$time ) { 
			if( !isset( $this->commObject->db->dbValues[$tid]['create'] ) ) $this->commObject->db->dbValues[$tid]['update'] = true;     
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
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Returns the same array with updated values, if any
	*/
	public function updateLinkInfo( $link, $tid ) {
		if( ( $this->commObject->TOUCH_ARCHIVE == 1 || $link['has_archive'] === false ) && $this->commObject->VERIFY_DEAD == 1 ) {
	        $link['is_dead'] = $this->deadCheck->checkDeadlink( $link['url'] );
	        if( $link['tagged_dead'] === false && $link['is_dead'] === true && $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) {
		        $this->commObject->db->dbValues[$tid]['live_state']--;
		        if( !isset( $this->commObject->db->dbValues[$tid]['create'] ) ) $this->commObject->db->dbValues['update'] = true;
		    } elseif( $link['tagged_dead'] === true && ( $this->commObject->TAG_OVERRIDE == 1 || $link['is_dead'] === true ) && $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) {
		        $this->commObject->db->dbValues[$tid]['live_state'] = 0;
		        if( !isset( $this->commObject->db->dbValues[$tid]['create'] ) ) $this->commObject->db->dbValues['update'] = true;
		    } elseif( $link['tagged_dead'] === false && $link['is_dead'] === false && $this->commObject->db->dbValues[$tid]['live_state'] != 0 && $this->commObject->db->dbValues[$tid]['live_state'] != 3 ) {
		        $this->commObject->db->dbValues[$tid]['live_state'] = 3; 
		        if( !isset( $this->commObject->db->dbValues[$tid]['create'] ) ) $this->commObject->db->dbValues['update'] = true;
		    }   
		    if( $this->commObject->db->dbValues[$tid]['live_state'] == 0 ) $link['is_dead'] = true;
		    if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) $link['is_dead'] = false;
		    if( !isset( $this->commObject->db->dbValues[$tid]['live_state'] ) || $this->commObject->db->dbValues[$tid]['live_state'] == 4 ) $link['is_dead'] = null;
	    } else $link['is_dead'] = null;
		return $link;
	}

	/**
	* Read and parse the reference string.
	* Extract the reference parameters
	* 
	* @param string $refparamstring reference string
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Contains the parameters as an associative array
	*/
	public function getReferenceParameters( $refparamstring ) {
		$returnArray = array();
		preg_match_all( '/(\S*)\s*=\s*(".*?"|\'.*?\'|\S*)/i', $refparamstring, $params );
		foreach( $params[0] as $tid => $tvalue ) {
		    $returnArray[$params[1][$tid]] = $params[2][$tid];   
		}
		return $returnArray;
	}

	//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.
	/**
	* Fetch the parameters of the template
	* 
	* @param string $templateString String of the template without the {{example bit
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Template parameters with respective values
	*/
	public function getTemplateParameters( $templateString ) {
		$returnArray = array();
		$tArray = array();
	    if( empty( $templateString ) ) return $returnArray;
	    $templateString = trim( $templateString );
		while( true ) {
		    $offset = 0;        
		    $loopcount = 0;
		    $pipepos = strpos( $templateString, "|", $offset);
		    $tstart = strpos( $templateString, "{{", $offset );   
		    $tend = strpos( $templateString, "}}", $offset );
		    $lstart = strpos( $templateString, "[[", $offset );
		    $lend = strpos( $templateString, "]]", $offset );
		    while( true ) {
		        $loopcount++;
		        if( $lend !== false && $tend !== false ) $offset = min( array( $tend, $lend ) ) + 1;
		        elseif( $lend === false ) $offset = $tend + 1;
		        else $offset = $lend + 1;     
		        while( ( $tstart < $pipepos && $tend > $pipepos ) || ( $lstart < $pipepos && $lend > $pipepos ) ) $pipepos = strpos( $templateString, "|", $pipepos + 1 );
		        $tstart = strpos( $templateString, "{{", $offset );   
		        $tend = strpos( $templateString, "}}", $offset );
		        $lstart = strpos( $templateString, "[[", $offset );
 		        $lend = strpos( $templateString, "]]", $offset );
		        if( ( $pipepos < $tstart || $tstart === false ) && ( $pipepos < $lstart || $lstart === false ) ) break;
		        if( $loopcount >= 500 ) return false;
		    }
		    if( $pipepos !== false ) {  
		        $tArray[] = substr( $templateString, 0, $pipepos  );
		        $templateString = substr_replace( $templateString, "", 0, $pipepos + 1 );
		    } else {
		        $tArray[] = $templateString;
		        break;
		    }
		}
		$count = 0;
		foreach( $tArray as $tid => $tstring ) $tArray[$tid] = explode( '=', $tstring, 2 );
		foreach( $tArray as $array ) {
		    $count++;
		    if( count( $array ) == 2 ) $returnArray[trim( $array[0] )] = trim( $array[1] );
		    else $returnArray[ $count ] = trim( $array[0] );
		}
		return $returnArray;
	}
	
	/**
	* Destroys the class
	* 
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __destruct() {
	    $this->deadCheck = null;
	    $this->commObject = null;
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
	    //$scrapText = preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $this->commObject->content );
	    $scrapText = $this->commObject->content;
	    if( preg_match_all( '/<ref([^\/]*?)>((.|\n)*?)<\/ref\s*?>\s*?((\s*\{\{.*?[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\})*)/i', $scrapText, $matches ) ) {
	        foreach( $matches[0] as $tid=>$fullmatch ) {
	        	//We want to stop at neighboring citation templates.
	        	if( preg_match( '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\})/i', $matches[4][$tid], $tpos ) ) {
	    			$matches[4][$tid] = trim( substr( $matches[4][$tid], 0, strpos( $matches[4][$tid], $tpos[0] ) ) );
	    			$fullmatch = trim( substr( $fullmatch, 0, strpos( $fullmatch, $tpos[0] ) ) );
				}
	            $returnArray[$tid]['string'] = $fullmatch;
	            $returnArray[$tid]['link_string'] = $matches[2][$tid];
	            $returnArray[$tid]['remainder'] = $matches[4][$tid];
	            $returnArray[$tid]['type'] = "reference";
	            $returnArray[$tid]['parameters'] = $this->getReferenceParameters( $matches[1][$tid] );
	            $returnArray[$tid]['contains'] = array();
	            while( ($temp = $this->getNonReference( $matches[2][$tid] )) !== false ) {
					$returnArray[$tid]['contains'][] = $temp;
	            }
	            $scrapText = str_replace( $fullmatch, "", $scrapText );
	        } 
	    }
	    if( $referenceOnly === false ) {
	        while( ($temp = $this->getNonReference( $scrapText )) !== false ) {
				$returnArray[] = $temp;
	        }
	    }
	    return $returnArray;
	}
	
	/**
	* Fetch all links in an article
	* 
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
			if( $parsed['type'] == "reference" && empty( $parsed['contains'] ) ) continue;
			$returnArray[$tid]['link_type'] = $parsed['type'];
			$returnArray[$tid]['string'] = $parsed['string'];
			if( $parsed['type'] == "reference" ) {
				foreach( $parsed['contains'] as $parsedlink ) $returnArray[$tid]['reference'][] = array_merge( $this->getLinkDetails( $parsedlink['link_string'], $parsedlink['remainder'].$parsed['remainder'] ), array( 'string'=>$parsedlink['string'] ) );
			} else {
				$returnArray[$tid][$parsed['type']] = $this->getLinkDetails( $parsed['link_string'], $parsed['remainder'] );
			}
			if( $parsed['type'] == "reference" ) {
				if( !empty( $parsed['parameters'] ) ) $returnArray[$tid]['reference']['parameters'] = $parsed['parameters'];
				$returnArray[$tid]['reference']['link_string'] = $parsed['link_string'];
			}
			if( $parsed['type'] == "template" ) {
				$returnArray[$tid]['template']['name'] = $parsed['name'];
			}
			if( !isset( $returnArray[$tid][$parsed['type']]['ignore'] ) || $returnArray[$tid][$parsed['type']]['ignore'] === false ) {
				if( $parsed['type'] == "reference" ) {
					foreach( $returnArray[$tid]['reference'] as $id=>$link ) {
						if( !is_int( $id ) || isset( $link['ignore'] ) ) continue;
						$linksAnalyzed++;
						$this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'][$id], "$tid:$id" );
						$returnArray[$tid]['reference'][$id] = $this->updateLinkInfo( $returnArray[$tid]['reference'][$id], "$tid:$id" );
						$toCheck["$tid:$id"] = $returnArray[$tid][$parsed['type']][$id];
					}	
				} else {
					$linksAnalyzed++;
					$this->commObject->db->retrieveDBValues( $returnArray[$tid][$parsed['type']], $tid );
					$returnArray[$tid][$parsed['type']] = $this->updateLinkInfo( $returnArray[$tid][$parsed['type']], $tid );
					$toCheck[$tid] = $returnArray[$tid][$parsed['type']];
				}
			}
		}
		$toCheck = $this->updateAccessTimes( $toCheck );
		foreach( $toCheck as $tid=>$link ) {
			if( is_int( $tid ) ) $returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
			else {
				$tid = explode( ":", $tid );
				$returnArray[$tid[0]][$returnArray[$tid[0]]['link_type']][$tid[1]] = $link;
			}
		}
		$returnArray['count'] = $linksAnalyzed;
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
	* Fetches the first non-reference it finds in the supplied text and returns it.
	* This function will remove the text it found in the passed parameter.
	* 
	* @param string $scrapText Text to look at.
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Details of the first non-reference found.  False on failure.
	*/
	protected function getNonReference( &$scrapText = "" ) {
		$returnArray = array();    
		$regex = '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\})\s*?((\s*\{\{.*?[\s\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\})*)/i';
	    if( preg_match( $regex, $scrapText, $match ) ) {
	    	//We want to stop at neighboring citation templates.
	    	if( preg_match( '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\})/i', $match[5], $tpos ) ) {
	    		$match[5] = trim( substr( $match[5], 0, strpos( $match[5], $tpos[0] ) ) );
	    		$match[0] = trim( substr( $match[0], 0, strpos( $match[0], $tpos[0] ) ) );
			}
	        $returnArray['string'] = $match[0];
	        $returnArray['link_string'] = $match[1];
	        $returnArray['remainder'] = $match[5];
	        $returnArray['type'] = "template";
	        $returnArray['name'] = str_replace( "{{", "", $match[2] );
	        $scrapText = str_replace( $returnArray['string'], "", $scrapText ); 
	        return $returnArray;   
	    }
	    if( preg_match( '/[\[]?((?:https?:)?\/\/[^\]|\s|\[|\{]*)/i', $scrapText, $match ) ) {
	        $start = 0;
	        $returnArray['type'] = "externallink";
	        $start = strpos( $scrapText, $match[0], $start );
	        if( substr( $match[0], 0, 1 ) == "[" ) {
	            $end = strpos( $scrapText, "]", $start ) + 1;    
	        } else {
	            $end = $start + strlen( $match[0] );
	        }  
	        $returnArray['link_string'] = substr( $scrapText, $start, $end-$start );
	        $returnArray['remainder'] = "";
	        while( !preg_match( '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->CITATION_TAGS ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\})/i', $scrapText, $match, null, $end ) && preg_match( '/(\{\{.*?\}\})/i', $scrapText, $match, null, $end ) ) {
	            $match = $match[0];
	            $snippet = substr( $scrapText, $end, strpos( $scrapText, $match, $end ) - $end );
	            if( !preg_match( '/[^\s]{1}/i', $snippet ) ){
	                $end = strpos( $scrapText, $match, $end ) + strlen( $match );
	                $returnArray['remainder'] .= $match;
	            } else {
	                break;
	            }
	        }
	        $returnArray['string'] = substr( $scrapText, $start, $end-$start );
	        $scrapText = str_replace( $returnArray['string'], "", $scrapText ); 
	        return $returnArray;
	    }  
	    return false; 
	}
}