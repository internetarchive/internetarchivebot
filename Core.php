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
	    $log = API::getPageText( "User:".USERNAME."/Dead-Links Log" );
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
	    API::edit( "User:".USERNAME."/Dead-Links Log", $log, "Updating run log with run statistics #IABot" );
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
	    $newdata = $link['newdata'];
	    unset( $link['newdata'] );
	    foreach( $link as $parameter => $value ) {
	        if( isset( $newdata[$parameter] ) && !is_array( $newdata[$parameter] )  && !is_array( $value ) ) $returnArray[$parameter] = $newdata[$parameter];
	        elseif( isset( $newdata[$parameter] ) && is_array( $newdata[$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = self::mergeNewData( $value, $newdata[$parameter] );
	        elseif( isset( $newdata[$parameter] ) ) $returnArray[$parameter] = $newdata[$parameter];
	        else $returnArray[$parameter] = $value;    
	    }
	    foreach( $newdata as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
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
	    if( $link['link_type'] == "reference" ) {
	    	foreach( $link['reference'] as $tid => $tlink) {
				if( isset( $tlink['newdata'] ) ) foreach( $tlink['newdata'] as $parameter => $value ) {
			        if( !isset( $tlink[$parameter] ) || $value != $tlink[$parameter] ) $t = true;
			    }
			}
	    }
	    elseif( isset( $link[$link['link_type']]['newdata'] ) ) foreach( $link[$link['link_type']]['newdata'] as $parameter => $value ) {
	        if( !isset( $link[$link['link_type']][$parameter] ) || $value != $link[$link['link_type']][$parameter] ) $t = true;
	    }
	    return $t;
	}
	
	/**
	* Escape the regex for all the tags and get redirect tags
	* 
	* @param array $DEADLINK_TAGS All dead tags
	* @param array $ARCHIVE_TAGS All archive tags
	* @param array $IGNORE_TAGS All ignore tags
	* @param array $CITATION_TAGS All citation tags
	* @access public
	* @static                                                          
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public static function escapeTags ( &$DEADLINK_TAGS, &$ARCHIVE_TAGS, &$IGNORE_TAGS, &$CITATION_TAGS ) {
	    $marray = $tarray = array();
	    foreach( $DEADLINK_TAGS as $tag ) {
	        $marray[] = "Template:".str_replace( "{", "", str_replace( "}", "", $tag ) );
	        $tarray[] = preg_quote( $tag, '/' );
	        if( strpos( $tag, " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", $tag ), '/' );
	    }
	    do {
	        $redirects = API::getRedirects( $marray );
	        $marray = array();
	        foreach( $redirects as $tag ) {
	            $marray[] = $tag['title'];
	            $tarray[] = preg_quote( str_replace( "Template:", "{{", $tag['title'] )."}}", '/' );
	            if( strpos( $tag['title'], " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", str_replace( "Template:", "{{", $tag['title'] )."}}" ), '/' );
	        }
	    } while( !empty( $redirects ) );
	    $DEADLINK_TAGS = $tarray;
	    $tarray = array();
	    $marray = array();
	    foreach( $CITATION_TAGS as $tag ) {
	        $marray[] = "Template:".str_replace( "{", "", str_replace( "}", "", $tag ) );
	        $tarray[] = preg_quote( $tag, '/' );
	        if( strpos( $tag, " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", $tag ), '/' );
	    }
	    do {
	        $redirects = API::getRedirects( $marray );
	        $marray = array();
	        foreach( $redirects as $tag ) {
	            $marray[] = $tag['title'];
	            $tarray[] = preg_quote( str_replace( "Template:", "{{", $tag['title'] )."}}", '/' );
	            if( strpos( $tag['title'], " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", str_replace( "Template:", "{{", $tag['title'] )."}}" ), '/' );
	        }
	    } while( !empty( $redirects ) );
	    $CITATION_TAGS = $tarray;
	    $tarray = array();
	    $marray = array();
	    foreach( $ARCHIVE_TAGS as $tag ) {
	        $marray[] = "Template:".str_replace( "{", "", str_replace( "}", "", $tag ) );
	        $tarray[] = preg_quote( $tag, '/' );
	        if( strpos( $tag, " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", $tag ), '/' );
	    }
	    do {
	        $redirects = API::getRedirects( $marray );
	        $marray = array();
	        foreach( $redirects as $tag ) {
	            $marray[] = $tag['title'];
	            $tarray[] = preg_quote( str_replace( "Template:", "{{", $tag['title'] )."}}", '/' );
	            if( strpos( $tag['title'], " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", str_replace( "Template:", "{{", $tag['title'] )."}}" ), '/' );
	        }
	    } while( !empty( $redirects ) );
	    $ARCHIVE_TAGS = $tarray;
	    $tarray = array();
	    $marray = array();
	    foreach( $IGNORE_TAGS as $tag ) {
	        $marray[] = "Template:".str_replace( "{", "", str_replace( "}", "", $tag ) );
	        $tarray[] = preg_quote( $tag, '/' );
	        if( strpos( $tag, " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", $tag ), '/' );
	    }
	    do {
	        $redirects = API::getRedirects( $marray );
	        $marray = array();
	        foreach( $redirects as $tag ) {
	            $marray[] = $tag['title'];
	            $tarray[] = preg_quote( str_replace( "Template:", "{{", $tag['title'] )."}}", '/' );
	            if( strpos( $tag['title'], " " ) ) $tarray[] = preg_quote( str_replace( " ", "_", str_replace( "Template:", "{{", $tag['title'] )."}}" ), '/' );
	        }
	    } while( !empty( $redirects ) );
	    $IGNORE_TAGS = $tarray;
	    unset( $marray, $tarray );    
	}
}