<?php
/*
	Copyright (c) 2015-2018, Maximilian Doerr
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
if( !empty( $argv[1] ) ) {
	echo "Set to run on {$argv[1]}\n";
	define( 'WIKIPEDIA', $argv[1] );
}
if( !empty( $argv[2] ) ) {
	echo "ID set to {$argv[2]}\n";
	define( 'UNIQUEID', $argv[2] );
	if( UNIQUEID == "dead" ) $overrideConfig['page_scan'] = 1;
}

set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
date_default_timezone_set( "UTC" );
ini_set( 'memory_limit', '256M' );

echo "----------STARTING UP SCRIPT----------\nStart Timestamp: " . date( 'r' ) . "\n\n";
require_once( 'init.php' );

$locale = setlocale( LC_ALL, unserialize( BOTLOCALE ) );
if( (isset( $locales[BOTLANGUAGE] ) && !in_array( $locale, $locales[BOTLANGUAGE] ) ) || !isset( $locales[BOTLANGUAGE] ) ) {
	//Uh-oh!! None of the locale definitions are supported on this system.
	echo "Missing locale for \"" . BOTLANGUAGE . "\"\n";
	if( !method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE ) ) {
		echo "No fallback function found, application will attempt to use \"en\"\n";
		$locale = setlocale( LC_ALL, $locales['en'] );
		if( !in_array( $locale, $locales['en'] ) ) {
			echo "Missing locale for \"en\"\n";
			if( !method_exists( "IABotLocalization", "localize_en" ) ) {
				echo "No fallback function found, application will use system default\n";
			} else {
				echo "Internal locale profile available in application.  Using that instead\n";
			}
		}
	} else {
		echo "Internal locale profile available in application.  Using that instead\n";
	}
	if( isset( $locales[BOTLANGUAGE] ) ) unset( $locales[BOTLANGUAGE] );
}
if( !API::botLogon() ) exit( 1 );

DB::checkDB();

$runpagecount = 0;
$lastpage = false;
if( file_exists( IAPROGRESS . WIKIPEDIA . UNIQUEID ) ) $lastpage =
	unserialize( file_get_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID ) );
if( file_exists( IAPROGRESS . WIKIPEDIA . UNIQUEID . "c" ) ) {
	$tmp = unserialize( file_get_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID . "c" ) );
	if( empty( $tmp ) || ( empty( $tmp['return'] ) && empty( $tmp['pages'] ) ) ) {
		$return = [];
		$pages = false;
	} else {
		$return = $tmp['return'];
		$pages = $tmp['pages'];
	}
	$tmp = null;
	unset( $tmp );
} else {
	$pages = false;
	$return = [];
}
if( $lastpage === false || empty( $lastpage ) || is_null( $lastpage ) ) $lastpage = false;

while( true ) {
	echo "----------RUN TIMESTAMP: " . date( 'r' ) . "----------\n\n";
	$runstart = time();
	if( !file_exists( IAPROGRESS . WIKIPEDIA . UNIQUEID . "stats" ) ) {
		$pagesAnalyzed = 0;
		$linksAnalyzed = 0;
		$linksFixed = 0;
		$linksTagged = 0;
		$pagesModified = 0;
		$linksArchived = 0;
		$waybackadded = 0;
		$otheradded = 0;
	} else {
		$tmp = unserialize( file_get_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID . "stats" ) );
		$pagesAnalyzed = $tmp['pagesAnalyzed'];
		$linksAnalyzed = $tmp['linksAnalyzed'];
		$linksFixed = $tmp['linksFixed'];
		$linksTagged = $tmp['linksTagged'];
		$pagesModified = $tmp['pagesModified'];
		$linksArchived = $tmp['linksArchived'];
		$runstart = $tmp['runstart'];
		$waybackadded = $tmp['waybacksAdded'];
		$otheradded = $tmp['othersAdded'];
		$tmp = null;
		unset( $tmp );
	}
	$iteration = 0;
	//Get started with the run
	do {
		$config = API::fetchConfiguration();

		if( isset( $overrideConfig ) && is_array( $overrideConfig ) ) {
			foreach( $overrideConfig as $variable => $value ) {
				if( isset( $config[$variable] ) ) $config[$variable] = $value;
			}
		}

		API::escapeTags( $config );

		if( empty( $titles ) ) $titles =
			explode( '|', str_replace( "{{", API::getTemplateNamespaceName() . ":", str_replace( "}}", "",
			                                                                                     str_replace( "\\", "",
			                                                                                                  str_replace( "[\s_]+",
			                                                                                                               " ",
			                                                                                                               implode( "|",
			                                                                                                                        $config['deadlink_tags']
			                                                                                                               )
			                                                                                                  )
			                                                                                     )
			                               )
			            )
			);

		$iteration++;
		if( $iteration !== 1 ) {
			$lastpage = false;
			$pages = false;
		}
		//fetch the pages we want to analyze and edit.  This fetching process is done in batches to preserve memory.
		if( DEBUG === true && $debugStyle == "test" ) {     //This fetches a specific page for debugging purposes
			echo "Fetching test pages...\n";
			$pages = [ $debugPage ];
		} elseif( $config['page_scan'] ==
		          0
		) {                       //This fetches all the articles, or a batch of them.
			echo "Fetching";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " " . $debugStyle;
			echo " article pages...\n";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
				$pages = API::getAllArticles( 5000, $return );
				$return = $pages[1];
				$pages = $pages[0];
			} elseif( $iteration !== 1 || $pages === false ) {
				$pages = API::getAllArticles( 5000, $return );
				$return = $pages[1];
				$pages = $pages[0];
				file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID . "c",
				                   serialize( [ 'pages' => $pages, 'return' => $return ] )
				);
			} else {
				if( $lastpage !== false ) {
					foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
					$pages = array_slice( $pages, $tcount + 1 );
				}
			}
			echo "Round $iteration: Fetched " . count( $pages ) . " articles!!\n\n";
		} elseif( $config['page_scan'] ==
		          1
		) {                       //This fetches only articles with a deadlink tag in it, or a batch of them
			echo "Fetching";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " " . $debugStyle;
			echo " articles with links marked as dead...\n";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
				$pages = API::getTaggedArticles( $titles, $debugStyle, $return
				);
				$return = $pages[1];
				$pages = $pages[0];
			} elseif( $iteration !== 1 || $pages === false ) {
				$pages = API::getTaggedArticles( $titles, 5000, $return
				);
				$return = $pages[1];
				$pages = $pages[0];
				file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID . "c",
				                   serialize( [ 'pages' => $pages, 'return' => $return ] )
				);
			} else {
				if( $lastpage !== false ) {
					foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
					$pages = array_slice( $pages, $tcount );
				}
			}
			echo "Round $iteration: Fetched " . count( $pages ) . " articles!!\n\n";
		}

		//Begin page analysis
		foreach( $pages as $tid => $tpage ) {
			$pagesAnalyzed++;
			$runpagecount++;
			API::enableProfiling();
			$commObject = new API( $tpage['title'], $tpage['pageid'], $config );
			$tmp = PARSERCLASS;
			$parser = new $tmp( $commObject );
			$stats = $parser->analyzePage();
			$commObject->closeResources();
			$parser = $commObject = null;
			API::disableProfiling( $tpage['pageid'], $tpage['title'] );
			if( $stats['pagemodified'] === true ) $pagesModified++;
			$linksAnalyzed += $stats['linksanalyzed'];
			$linksArchived += $stats['linksarchived'];
			$linksFixed += $stats['linksrescued'];
			$linksTagged += $stats['linkstagged'];
			$waybackadded += $stats['waybacksadded'];
			$otheradded += $stats['othersadded'];
			if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID .
			                                                                "stats", serialize( [
				                                                                                    'linksAnalyzed' => $linksAnalyzed,
				                                                                                    'linksArchived' => $linksArchived,
				                                                                                    'linksFixed'    => $linksFixed,
				                                                                                    'linksTagged'   => $linksTagged,
				                                                                                    'pagesModified' => $pagesModified,
				                                                                                    'pagesAnalyzed' => $pagesAnalyzed,
				                                                                                    'runstart'      => $runstart,
				                                                                                    'waybacksAdded' => $waybackadded,
				                                                                                    'othersAdded'   => $otheradded
			                                                                                    ]
			                                                                )
			);
			if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
		}

		unset( $pages );

		API::closeFileHandles();
	} while( ( !empty( $return ) || !empty( $titles ) ) && DEBUG === false && LIMITEDRUN === false );
	$pages = false;
	$runend = time();
	echo "Printing log report, and starting new run...\n\n";
	if( DEBUG === false && LIMITEDRUN === false ) DB::generateLogReport();
	if( file_exists( IAPROGRESS . WIKIPEDIA . UNIQUEID . "stats" ) && LIMITEDRUN === false ) unlink( IAPROGRESS .
	                                                                                                 WIKIPEDIA .
	                                                                                                 UNIQUEID . "stats"
	);
	if( DEBUG === false && LIMITEDRUN === false ) sleep( 10 );

	// return instead of exiting so that acceptance tests will finish
	if( DEBUG === true || LIMITEDRUN === true ) return;
}