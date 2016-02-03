<?php
  
abstract class Parser {
	
	public $commObject;
	
	protected $deadCheck;
	
	public function __construct( $commObject ) {
		$this->commObject = $commObject;	
		$this->deadCheck = new checkIfDead();
	}
	
	public abstract function getExternalLinks();
	
	public abstract function getLinkDetails( $linkString, $remainder );
	
	public abstract function getReferences();
	
	public abstract function generateString( $link );
	
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

	//Read and parse the reference string
	public function getReferenceParameters( $refparamstring ) {
	    $returnArray = array();
	    preg_match_all( '/(\S*)\s*=\s*(".*?"|\'.*?\'|\S*)/i', $refparamstring, $params );
	    foreach( $params[0] as $tid => $tvalue ) {
	        $returnArray[$params[1][$tid]] = $params[2][$tid];   
	    }
	    return $returnArray;
	}

	//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.
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
    
    public function __destruct() {
        $this->deadCheck = null;
        $this->commObject = null;
    }
}