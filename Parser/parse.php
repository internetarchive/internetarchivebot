<?php
/**
* @file
* Parser object  
* @author Maximilian Doerr (Cyberpower678)
* @license http://www.gnu.org/licenses/gpl-3.0.html
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* Parser class
* Allows for the parsing on project specific wiki pages
* @abstract
* @author Maximilian Doerr (Cyberpower678)
* @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * The TemplatePointer class
    * 
    * @var TemplatePointer
    * @access protected
    */
    protected $templatePointer;
	
	/**
	* Parser class constructor
	* 
	* @param API $commObject
	* @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
	*/
	public function __construct( API $commObject ) {
		$this->commObject = $commObject;	
		$this->deadCheck = new checkIfDead();
        $tmp = TEMPLATECLASS;
        $this->templatePointer = new $tmp();
        unset( $tmp );
	}
	
	/**
	* Fetch all links in an article
	* 
	* @abstract
	* @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return array Details about every link on the page
	*/
	public abstract function getExternalLinks();
	
	/**
	* Parses a given refernce/external link string and returns details about it.
	* 
	* @param string $linkString Primary reference string
	* @param string $remainder Left over stuff that may apply
	* @access public
	* @abstract
    * @author Maximilian Doerr (Cyberpower678)
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return array	Details about the link
	*/
	public abstract function getLinkDetails( $linkString, $remainder );
	
	/**
	* Fetches all references only
	* 
	* @access public
	* @abstract
    * @author Maximilian Doerr (Cyberpower678)
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return array Details about every reference found
	*/
	public abstract function getReferences();
	
	/**
	* Generate a string to replace the old string
	* 
	* @param array $link Details about the new link including newdata being injected.
	* @access public
	* @abstract
    * @author Maximilian Doerr (Cyberpower678)
    * @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return array Template parameters with respective values
	*/
	public function getTemplateParameters( $templateString ) {
	    $returnArray = array();
	    $tArray = array();
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
    */
    public function __destruct() {
        $this->deadCheck = null;
        $this->commObject = null;
    }
}