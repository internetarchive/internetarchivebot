<?php
/*
This software has been created by Cyberpower678
This software analyzes dead-links and attempts to reliably find the proper archived page for it.
This software uses the MW API
This software uses the Wayback API
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
                if( WORKERS === false ) $stats = analyzePage( $commObject );
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
    if( DEBUG === false && LIMITEDRUN === false ) generateLogReport();
    if( file_exists( IAPROGRESS.WIKIPEDIA."stats" ) && LIMITEDRUN === false ) unlink( IAPROGRESS.WIKIPEDIA."stats" );  
    if( DEBUG === false && LIMITEDRUN === false ) sleep(10);
    if( DEBUG === true || LIMITEDRUN === true ) exit(0);                                           
}

//Create run log information
function generateLogReport() {
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

//Merge the new data in a custom array_merge function
function mergeNewData( $link, $recurse = false ) {
    $returnArray = array();
    if( $recurse !== false ) {
        foreach( $link as $parameter => $value ) {
            if( isset( $recurse[$parameter] ) && !is_array( $recurse[$parameter] ) && !is_array( $value ) ) $returnArray[$parameter] = $recurse[$parameter];
            elseif( isset($recurse[$parameter] ) && is_array( $recurse[$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = mergeNewData( $value, $recurse[$parameter] );
            elseif( isset( $recurse[$parameter] ) ) $returnArray[$parameter] = $recurse[$parameter];
            else $returnArray[$parameter] = $value; 
        }
        foreach( $recurse as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
        return $returnArray;
    }
    foreach( $link[$link['link_type']] as $parameter => $value ) {
        if( isset( $link['newdata'][$parameter] ) && !is_array( $link['newdata'][$parameter] )  && !is_array( $value ) ) $returnArray[$parameter] = $link['newdata'][$parameter];
        elseif( isset( $link['newdata'][$parameter] ) && is_array( $link['newdata'][$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = mergeNewData( $value, $link['newdata'][$parameter] );
        elseif( isset( $link['newdata'][$parameter] ) ) $returnArray[$parameter] = $link['newdata'][$parameter];
        else $returnArray[$parameter] = $value;    
    }
    foreach( $link['newdata'] as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
    return $returnArray;
}

//Verify that newdata is actually different from old
function newIsNew( $link ) {
    $t = false;
    foreach( $link['newdata'] as $parameter => $value ) {
        if( !isset( $link[$link['link_type']][$parameter] ) || $value != $link[$link['link_type']][$parameter] ) $t = true;
    }
    return $t;
}

function analyzePage( $commObject ) {
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
                                $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['url'];
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
                            $link['newdata']['tag_template']['parameters']['bot'] = "Cyberbot II";    
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
        if( isset( $link['newdata'] ) && newIsNew( $link ) ) {
            $link['newstring'] = $parser->generateString( $link );
            $newtext = str_replace( $link['string'], $link['newstring'], $newtext );
        }
    }
    $archiveResponse = $checkResponse = $fetchResponse = null;
    unset( $archiveResponse, $checkResponse, $fetchResponse );
    if( WORKERS === true ) {
        echo "Analyzed {$commObject->page} ({$commObject->pageid})\n";
    }
    echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: ".(memory_get_usage( true )/1048576)." MB; Max System Memory Used: ".(memory_get_peak_usage(true)/1048576)." MB\n\n";
    if( !empty( $archiveProblems ) && $commObject->NOTIFY_ERROR_ON_TALK == 1 ) {
        $body = str_replace( "{problematiclinks}", $out, str_replace( "\\n", "\n", $commObject->TALK_ERROR_MESSAGE ) )."~~~~";
        $out = "";
        foreach( $archiveProblems as $id=>$problem ) {
            $out .= "* $problem with error {$errors[$id]}\n";
        } 
        $body = str_replace( "{problematiclinks}", $out, str_replace( "\\n", "\n", $commObject->TALK_ERROR_MESSAGE ) )."~~~~";
        API::edit( "Talk:{$commObject->page}", $body, "Notifications of sources failing to archive. #IABot", $timestamp, true, "new", $commObject->TALK_ERROR_MESSAGE_HEADER );  
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
            API::edit( "Talk:{$commObject->page}", $body, "Notification of altered sources needing review #IABot", false, $timestamp, true, "new", $header );
        }
    }
    $commObject->db->updateDBValues();
    
    $commObject->__destruct();
    $parser->__destruct();
    
    $commObject = $parser = $newtext = $history = null;
    unset( $commObject, $parser, $newtext, $history, $res, $db );
    $returnArray = array( 'linksanalyzed'=>$analyzed, 'linksarchived'=>$archived, 'linksrescued'=>$rescued, 'linkstagged'=>$tagged, 'pagemodified'=>$pageModified );
    return $returnArray;
}