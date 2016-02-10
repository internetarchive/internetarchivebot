<?php
/*
    Copyright (c) 2016, Maximilian Doerr
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

ini_set('memory_limit','1G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( 'deadlink.config.inc.php' );

if( !API::botLogon() ) exit( 1 ); 

DB::checkDB();

$LINK_SCAN = 0;
$DEAD_ONLY = 2;
$TAG_OVERRIDE = 1;
$PAGE_SCAN = 0;
$ARCHIVE_BY_ACCESSDATE = 1;
$TOUCH_ARCHIVE = 0;
$NOTIFY_ON_TALK = 1;
$NOTIFY_ERROR_ON_TALK = 1;
$TALK_MESSAGE_HEADER = "Links modified on main page";
$TALK_MESSAGE = "Please review the links modified on the main page...";
$TALK_ERROR_MESSAGE = "There were problems archiving a few links on the page.";
$TALK_ERROR_MESSAGE_HEADER = "Notification of problematic links";
$DEADLINK_TAGS = array( "{{dead-link}}" );
$CITATION_TAGS = array( "{{cite web}}" );
$ARCHIVE_TAGS = array( "{{wayback}}" );
$IGNORE_TAGS = array( "{{cbignore}}" );
$DEAD_RULES = array();
$VERIFY_DEAD = 1;
$ARCHIVE_ALIVE = 1;
$runpagecount = 0;
$lastpage = false;
if( file_exists( IAPROGRESS.WIKIPEDIA ) ) $lastpage = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA ) );
if( file_exists( IAPROGRESS.WIKIPEDIA."c" ) ) {
    $tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA."c" ) );
    if( is_null($tmp) || empty($tmp) || empty($tmp['return']) || empty($tmp['pages'] ) ) {
        $return = "";
        $pages = false;
    } else {
        $return = $tmp['return'];
        $pages = $tmp['pages'];
    }
    $tmp = null;
    unset( $tmp );
} else {
    $pages = false;
    $return = "";
}
if( $lastpage === false || empty( $lastpage ) || is_null( $lastpage ) ) $lastpage = false;

while( true ) {
    echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
    $runstart = time();
    $runtime = 0;
    if( !file_exists( IAPROGRESS.WIKIPEDIA."stats" ) ) {
        $pagesAnalyzed = 0;
        $linksAnalyzed = 0;
        $linksFixed = 0;
        $linksTagged = 0;
        $pagesModified = 0;
        $linksArchived = 0;
    } else {
        $tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA."stats" ) );
        $pagesAnalyzed = $tmp['pagesAnalyzed'];
        $linksAnalyzed = $tmp['linksAnalyzed'];
        $linksFixed = $tmp['linksFixed'];
        $linksTagged = $tmp['linksTagged'];
        $pagesModified = $tmp['pagesModified'];
        $linksArchived = $tmp['linksArchived'];
        $runstart = $tmp['runstart'];
        $tmp = null;
        unset( $tmp );
    }
    $iteration = 0;
    //$config = $site->initPage( "User:Cyberbot II/Dead-links" )->get_text( true );
    $config = API::getPageText( "User:Cyberbot II/Dead-links" );
    preg_match( '/\n\|LINK_SCAN\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $LINK_SCAN = $param1[1];
    preg_match( '/\n\|DEAD_ONLY\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $DEAD_ONLY = $param1[1];
    preg_match( '/\n\|TAG_OVERRIDE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TAG_OVERRIDE = $param1[1];
    preg_match( '/\n\|PAGE_SCAN\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $PAGE_SCAN = $param1[1];
    preg_match( '/\n\|ARCHIVE_BY_ACCESSDATE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_BY_ACCESSDATE = $param1[1];
    preg_match( '/\n\|TOUCH_ARCHIVE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TOUCH_ARCHIVE = $param1[1];
    preg_match( '/\n\|NOTIFY_ON_TALK\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $NOTIFY_ON_TALK = $param1[1];
    preg_match( '/\n\|NOTIFY_ERROR_ON_TALK\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $NOTIFY_ERROR_ON_TALK = $param1[1];
    preg_match( '/\n\|TALK_MESSAGE_HEADER\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_MESSAGE_HEADER = $param1[1];
    preg_match( '/\n\|TALK_MESSAGE\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_MESSAGE = $param1[1];
    preg_match( '/\n\|TALK_ERROR_MESSAGE_HEADER\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_ERROR_MESSAGE_HEADER = $param1[1];
    preg_match( '/\n\|TALK_ERROR_MESSAGE\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_ERROR_MESSAGE = $param1[1];
    preg_match( '/\n\|DEADLINK_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $DEADLINK_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|CITATION_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $CITATION_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|ARCHIVE_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|IGNORE_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $IGNORE_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|VERIFY_DEAD\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $VERIFY_DEAD = $param1[1];
    preg_match( '/\n\|ARCHIVE_ALIVE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_ALIVE = $param1[1];
    foreach( $DEAD_RULES as $tid => $rule ) $DEAD_RULES[$tid] = explode( ":", $rule );
    foreach( $DEADLINK_TAGS as $tid=>$tag ) $DEADLINK_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $CITATION_TAGS as $tid=>$tag ) $CITATION_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $ARCHIVE_TAGS as $tid=>$tag ) $ARCHIVE_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $IGNORE_TAGS as $tid=>$tag ) $IGNORE_TAGS[$tid] = preg_quote( $tag, '/' );
    
    //Get started with the run
    do {
        $iteration++;
        if( $iteration !== 1 ) {
            $lastpage = false;
            $pages = false;
        }
        //fetch the pages we want to analyze and edit.  This fetching process is done in batches to preserve memory. 
        if( DEBUG === true && $debugStyle == "test" ) {     //This fetches a specific page for debugging purposes
            echo "Fetching test pages...\n";
            $pages = array( $debugPage );   
        } elseif( $PAGE_SCAN == 0 ) {                       //This fetches all the articles, or a batch of them.
            echo "Fetching";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " ".$debugStyle;
            echo " article pages...\n";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
                 $pages = API::getAllArticles( 5000, $return );
                 $return = $pages[1];
                 $pages = $pages[0];
            } elseif( $iteration !== 1 || $pages === false ) {
                $pages = API::getAllArticles( 5000, $return );
                $return = $pages[1];
                $pages = $pages[0];
                file_put_contents( IAPROGRESS.WIKIPEDIA."c", serialize( array( 'pages' => $pages, 'return' => $return ) ) );     
            } else {
                if( $lastpage !== false ) {
                    foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
                    $pages = array_slice( $pages, $tcount + 1 );
                }
            }
            echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n";
        } elseif( $PAGE_SCAN == 1 ) {                       //This fetches only articles with a deadlink tag in it, or a batch of them
            echo "Fetching";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " ".$debugStyle;
            echo " articles with links marked as dead...\n";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
                $pages = API::getTaggedArticles( str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $DEADLINK_TAGS ) ) ) ), $debugStyle, $return );
                $return = $pages[1];
                $pages = $pages[0];
            } elseif( $iteration !== 1 || $pages === false ) {
                $pages = API::getTaggedArticles( str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $DEADLINK_TAGS ) ) ) ), 5000, $return );
                $return = $pages[1];
                $pages = $pages[0];
                file_put_contents( IAPROGRESS.WIKIPEDIA."c", serialize( array( 'pages' => $pages, 'return' => $return ) ) );
            } else {
                if( $lastpage !== false ) {
                    foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
                    $pages = array_slice( $pages, $tcount );
                }
            }
            echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n"; 
        }
        
        //Begin page analysis
        if( WORKERS === false || DEBUG === true ) {
            foreach( $pages as $tid => $tpage ) {
                $pagesAnalyzed++;
                $runpagecount++;
                if( WORKERS === false ) $commObject = new API( $tpage['title'], $tpage['pageid'], $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN );
                if( WORKERS === false ) $stats = Core::analyzePage( $commObject );
                if( WORKERS === false ) $commObject = null;
                else {
                    $testbot[$tid] = new ThreadedBot( $tpage['title'], $tpage['pageid'], $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN, "test" );
                    $testbot[$tid]->run();
                    $stats = $testbot[$tid]->result;
                }
                if( $stats['pagemodified'] === true ) $pagesModified++;
                $linksAnalyzed += $stats['linksanalyzed'];
                $linksArchived += $stats['linksarchived'];
                $linksFixed += $stats['linksrescued'];
                $linksTagged += $stats['linkstagged'];
                if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
                if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
            }
        } else {   
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) &&  $handle = opendir( IAPROGRESS.WIKIPEDIA."workers" ) ) {
                 while( false !== ( $entry = readdir( $handle ) ) ) {
                    if( $entry == "." || $entry == ".." ) continue;
                    $tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA."workers/$entry" ) );
                    if( $tmp === false ) {
                        $tmp = null;
                        unlink( IAPROGRESS.WIKIPEDIA."workers/$entry" );
                        continue;
                    }
                    $pagesAnalyzed++;
                    if( $tmp['pagemodified'] === true ) $pagesModified++;
                    $linksAnalyzed += $tmp['linksanalyzed'];
                    $linksArchived += $tmp['linksarchived'];
                    $linksFixed += $tmp['linksrescued'];
                    $linksTagged += $tmp['linkstagged'];
                    $tmp = null;
                    unlink( IAPROGRESS.WIKIPEDIA."workers/$entry" ); 
                }
                unset( $tmp ); 
                file_put_contents( IAPROGRESS.WIKIPEDIA."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) ); 
            }
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) ) closedir( $handle );
            $workerQueue = new Pool( $workerLimit );
            foreach( $pages as $tid => $tpage ) {
                $pagesAnalyzed++;
                $runpagecount++;
                echo "Submitted {$tpage['title']}, job ".($tid+1)." for analyzing...\n";
                $workerQueue->submit( new ThreadedBot( $tpage['title'], $tpage['pageid'], $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN, $tid ) );       
                if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
            }
            $workerQueue->shutdown();  
            $workerQueue->collect(
            function( $thread ) {  
                global $pagesModified, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged;
                $stats = $thread->result;
                if( $stats['pagemodified'] === true ) $pagesModified++;
                $linksAnalyzed += $stats['linksanalyzed'];
                $linksArchived += $stats['linksarchived'];
                $linksFixed += $stats['linksrescued'];
                $linksTagged += $stats['linkstagged'];
                $stats = null;
                unset( $stats );
                return $thread->isGarbage();
            });
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) &&  $handle = opendir( IAPROGRESS.WIKIPEDIA."workers" ) ) {
                 while( false !== ( $entry = readdir( $handle ) ) ) {
                    if( $entry == "." || $entry == ".." ) continue;
                    unlink( IAPROGRESS.WIKIPEDIA."workers/$entry" ); 
                }
            }
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) ) closedir( $handle );
            echo "STATUS REPORT:\nLinks analyzed so far: $linksAnalyzed\nLinks archived so far: $linksArchived\nLinks fixed so far: $linksFixed\nLinks tagged so far: $linksTagged\nPages modified so far: $pagesModified\n\n";
            file_put_contents( IAPROGRESS.WIKIPEDIA."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
        }
        unset( $pages );
    } while( !empty( $return ) && DEBUG === false && LIMITEDRUN === false ); 
    $runend = time();
    $runtime = $runend-$runstart;
    echo "Updating list of failed archive attempts...\n\n";
    $out = DB::getUnarchivable();
    if( DEBUG === false || LIMITEDRUN === true ) API::edit( "User:Cyberbot II/Links that won't archive", $out, "Updating list of links that won't archive. #IABot", true, false, true, "append" );
    echo "Printing log report, and starting new run...\n\n";
    if( DEBUG === false && LIMITEDRUN === false ) Core::generateLogReport();
    if( file_exists( IAPROGRESS.WIKIPEDIA."stats" ) && LIMITEDRUN === false ) unlink( IAPROGRESS.WIKIPEDIA."stats" );  
    if( DEBUG === false && LIMITEDRUN === false ) sleep(10);
    if( DEBUG === true || LIMITEDRUN === true ) exit(0);                                           
}