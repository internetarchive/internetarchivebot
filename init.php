<?php
/*
	Copyright (c) 2015-2017, Maximilian Doerr

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
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @file
 * Initializes the bot and the web interface.
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

if( PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION < 5.4) {
	echo "ERROR: Minimum requirements for correct operation is PHP 5.4.  You are running " . PHP_VERSION . ", which will not run correctly.\n";
	exit( 1 );
}

require_once( 'deadlink.config.inc.php' );

if( file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'deadlink.config.local.inc.php'
) ) {
	require_once( 'deadlink.config.local.inc.php' );
}

require_once( 'DB.php' );

$callingFile = explode( "/", $_SERVER['SCRIPT_FILENAME'] );
$callingFile = $callingFile[count( $callingFile ) - 1];

define( 'HOST', $host );
define( 'PORT', $port );
define( 'USER', $user );
define( 'PASS', $pass );
define( 'DB', $db );

DB::createConfigurationTable();

$configuration = DB::getConfiguration( "global", "systemglobals" );

$typeCast = [
	'taskname'      => 'string', 'disableEdits' => 'bool', 'userAgent' => 'string', 'enableAPILogging' => 'bool',
	'expectedValue' => 'string', 'decodeFunction' => 'string', 'enableMail' => 'bool',
	'to'            => 'string', 'from' => 'string', 'useCIDservers' => 'bool', 'cidServers' => 'array',
	'cidAuthCode'   => 'string', 'enableProfiling' => 'bool', 'defaultWiki' => 'string',
	'autoFPReport'  => 'bool', 'guifrom' => 'string', 'guidomainroot' => 'string', 'disableInterface' => 'bool',
	'cidUserAgent'  => 'string'
];

unset( $disableEdits, $userAgent, $apiURL, $oauthURL, $taskname, $nobots, $enableAPILogging, $apiCall, $expectedValue, $decodeFunction, $enableMail, $to, $from, $useCIDservers, $cidServers, $cidAuthCode, $enableProfiling, $accessibleWikis, $defaultWiki, $autoFPReport, $guifrom, $guidomainroot, $disableInterface );

foreach( $typeCast as $variable => $type ) {
	if( !isset( $configuration[$variable] ) ) {
		@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
		@header( "Location: setup.php", true, 307 );
		echo "The bot has not been set up yet.  Please use the web interface to set up the bot.";
		exit( 1 );
	}
	if( !isset( $$variable ) ) {
		$$variable = replaceMagicInitWords( $configuration[$variable] );
	}
}

$accessibleWikis = DB::getConfiguration( "global", "systemglobals-allwikis" );

if( empty( $accessibleWikis ) ) {
	@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
	@header( "Location: setup.php", true, 307 );
	echo "No wiki has been setup yet.  Please use the web interface to set up the bot.";
	exit( 1 );
}

ksort( $accessibleWikis );

//HTTP referrer autodetection.  Attempt to define the correct based on the HTTP_REFERRER
require_once( "localization.php" );
if( !defined( 'WIKIPEDIA' ) ) {
	if( !empty( $_SERVER['HTTP_REFERER'] ) ) {
		$root = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST );
		if( !empty( $root ) ) {
			$root = "https://$root/";
			foreach( $accessibleWikis as $wiki => $wikiData ) {
				if( $root == $wikiData['rooturl'] ) {
					define( 'WIKIPEDIA', $wiki );
					break;
				}
			}
			if( !defined( 'WIKIPEDIA' ) ) define( 'WIKIPEDIA', $defaultWiki );
		} else define( 'WIKIPEDIA', $defaultWiki );
	} else define( 'WIKIPEDIA', $defaultWiki );
}

if( !isset( $accessibleWikis[WIKIPEDIA] ) ) {
	if( $callingFile == "index.php" && ( !isset( $_GET['systempage'] ) || $_GET['systempage'] != "setup2" ) ) {
		@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
		@header( "Location: index.php?page=systemconfig&systempage=setup2&wikiName=" . WIKIPEDIA . "&wiki=$defaultWiki",
		         true, 307
		);
		echo WIKIPEDIA . " is not set up yet.";
		exit( 1 );
	}
}
$language = $accessibleWikis[WIKIPEDIA]['language'];

if( isset( $accessibleWikis[WIKIPEDIA]['disabled'] ) ) {
	echo "This wiki is disabled.";
	exit( 1 );
}

if( !isset( $runpage ) ) $runpage = $accessibleWikis[WIKIPEDIA]['runpage'];

require_once( 'APII.php' );
require_once( 'parse.php' );

API::fetchConfiguration( $behaviorDefined, false );
$archiveTemplates = DB::getConfiguration( "global", "archive-templates" );

if( empty( $archiveTemplates ) ) {
	define( 'GUIREDIRECTED', true );
	if( $callingFile == "index.php" && ( !isset( $_GET['systempage'] ) || $_GET['systempage'] != "definearchives" ) ) {
		@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
		@header( "Location: index.php?page=systemconfig&systempage=definearchives", true, 307 );
		echo WIKIPEDIA . " is not set up yet.";
		exit( 1 );
	}
} elseif( $behaviorDefined === false ) {
	define( 'GUIREDIRECTED', true );
	if( $callingFile == "index.php" && ( !isset( $_GET['systempage'] ) || $_GET['systempage'] != "wikiconfig" ) ) {
		@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
		@header( "Location: index.php?page=systemconfig&systempage=wikiconfig&wiki=" . WIKIPEDIA, true, 307 );
		echo WIKIPEDIA . " is not set up yet.";
		exit( 1 );
	}
}

require_once( __DIR__ . '/../vendor/autoload.php' );
if( isset( $accessibleWikis[WIKIPEDIA] ) &&
    file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'extensions/' . WIKIPEDIA . '.php' ) ) {
	require_once( 'extensions/' . WIKIPEDIA . '.php' );
}

if( class_exists( WIKIPEDIA . 'Parser' ) ) define( 'PARSERCLASS', WIKIPEDIA . 'Parser' );
else define( 'PARSERCLASS', 'Parser' );
if( class_exists( WIKIPEDIA . 'API' ) ) define( 'APIICLASS', WIKIPEDIA.'API' );
else define( 'APIICLASS', 'API' );
if( class_exists( WIKIPEDIA . 'DB' ) ) define( 'DBCLASS', WIKIPEDIA.'DB' );
else define( 'DBCLASS', 'DB' );

define( 'PUBLICHTML', dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $publicHTMLPath );
if( $autoFPReport === true ) {
	require_once( PUBLICHTML . "Includes/DB2.php" );
	require_once( PUBLICHTML . "Includes/HTMLLoader.php" );
	require_once( PUBLICHTML . "Includes/actionfunctions.php" );
}
require_once( PUBLICHTML . 'Includes/xhprof/display/xhprof.php' );
if( empty( $accessibleWikis[WIKIPEDIA]['i18nsource'] ) || empty( $accessibleWikis[WIKIPEDIA]['i18nsourcename'] ) ||
    empty( $accessibleWikis[WIKIPEDIA]['language'] ) || empty( $accessibleWikis[WIKIPEDIA]['rooturl'] ) ||
    empty( $accessibleWikis[WIKIPEDIA]['apiurl'] ) ||
    empty( $accessibleWikis[WIKIPEDIA]['oauthurl'] ) || empty( $accessibleWikis[WIKIPEDIA]['nobots'] ) ||
    !isset( $accessibleWikis[WIKIPEDIA]['apiCall'] ) ) {
	throw new Exception( "Missing configuration keys for this Wiki", 2 );
} else {
	define( 'APICALL', $accessibleWikis[WIKIPEDIA]['apiCall'] );
	define( 'API', $accessibleWikis[WIKIPEDIA]['apiurl'] );
	define( 'OAUTH', $accessibleWikis[WIKIPEDIA]['oauthurl'] );
	define( 'NOBOTS', $accessibleWikis[WIKIPEDIA]['nobots'] );
	define( 'BOTLANGUAGE', $accessibleWikis[WIKIPEDIA]['language'] );
	if( isset( $locales[$accessibleWikis[WIKIPEDIA]['language']] ) ) define( 'BOTLOCALE',
	                                                                         serialize( $locales[$accessibleWikis[WIKIPEDIA]['language']]
	                                                                         )
	);
	else define( 'BOTLOCALE', serialize( $locales['en'] ) );

	if( !isset( $useKeys ) ) $useKeys = $accessibleWikis[WIKIPEDIA]['usekeys'];
	if( !isset( $useWikiDB ) ) $useWikiDB = $accessibleWikis[WIKIPEDIA]['usewikidb'];
}
define( 'RUNPAGE', $runpage );
define( 'EXPECTEDRETURN', $expectedValue );
define( 'DECODEMETHOD', $decodeFunction );
define( 'LOGAPI', $enableAPILogging );
define( 'TASKNAME', replaceMagicInitWords( $taskname ) );
define( 'IAPROGRESS', replaceMagicInitWords( $memoryFile ) );
define( 'DEBUG', $debug );
define( 'LIMITEDRUN', $limitedRun );
define( 'TESTMODE', $testMode );
define( 'DISABLEEDITS', $disableEdits );
define( 'USEWIKIDB', $useWikiDB );
if( USEWIKIDB !== false ) {
	if( empty( $wikiDBs[USEWIKIDB]['host'] ) || empty( $wikiDBs[USEWIKIDB]['port'] ) ||
	    !isset( $wikiDBs[USEWIKIDB]['user'] ) || !isset( $wikiDBs[USEWIKIDB]['pass'] ) ||
	    empty( $wikiDBs[USEWIKIDB]['db'] ) || !isset( $wikiDBs[USEWIKIDB]['revisiontable'] ) ||
	    !isset( $wikiDBs[USEWIKIDB]['pagetable'] ) || !isset( $wikiDBs[USEWIKIDB]['texttable'] ) ) {
		throw new Exception( "Missing database keys for this Wiki", 2 );
	}
	define( 'WIKIHOST', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['host'] ) );
	define( 'WIKIPORT', $wikiDBs[USEWIKIDB]['port'] );
	define( 'WIKIUSER', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['user'] ) );
	define( 'WIKIPASS', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['pass'] ) );
	define( 'WIKIDB', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['db'] ) );
	define( 'REVISIONTABLE', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['revisiontable'] ) );
	define( 'TEXTTABLE', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['texttable'] ) );
	define( 'PAGETABLE', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['pagetable'] ) );
}
if( !isset( $oauthKeys[$useKeys] ) ) {
	throw new Exception( "Missing authorization keys for this Wiki", 2 );
}
if( !( defined( 'USEWEBINTERFACE' ) && USEWEBINTERFACE == 1 ) ) {
	if( !isset( $oauthKeys[$useKeys]['bot']['consumerkey'] ) ||
	    !isset( $oauthKeys[$useKeys]['bot']['consumersecret'] ) ||
	    !isset( $oauthKeys[$useKeys]['bot']['accesstoken'] ) || !isset( $oauthKeys[$useKeys]['bot']['accesstoken'] ) ||
	    !isset( $oauthKeys[$useKeys]['bot']['username'] ) ) {
		throw new Exception( "Missing authorization keys for this Wiki", 2 );
	}
	define( 'CONSUMERKEY', $oauthKeys[$useKeys]['bot']['consumerkey'] );
	define( 'CONSUMERSECRET', $oauthKeys[$useKeys]['bot']['consumersecret'] );
	define( 'ACCESSTOKEN', $oauthKeys[$useKeys]['bot']['accesstoken'] );
	define( 'ACCESSSECRET', $oauthKeys[$useKeys]['bot']['accesssecret'] );
	define( 'USERNAME', $oauthKeys[$useKeys]['bot']['username'] );
}
define( 'USERAGENT', replaceMagicInitWords( $userAgent ) );
define( 'COOKIE', sys_get_temp_dir() . $oauthKeys[$useKeys]['bot']['username'] . WIKIPEDIA . TASKNAME );
define( 'ENABLEMAIL', $enableMail );
define( 'TO', $to );
define( 'FROM', replaceMagicInitWords( $from ) );
define( 'GUIFROM', replaceMagicInitWords( $guifrom ) );
define( 'ROOTURL', $guidomainroot );
define( 'USEADDITIONALSERVERS', $useCIDservers );
define( 'CIDSERVERS', implode( "\n", $cidServers ) );
define( 'CIDAUTHCODE', $cidAuthCode );
define( 'CIDUSERAGENT', $cidUserAgent );
define( 'AUTOFPREPORT', $autoFPReport );
define( 'PROFILINGENABLED', $enableProfiling );
define( 'VERSION', "2.0beta13" );
if( !defined( 'UNIQUEID' ) ) define( 'UNIQUEID', "" );
unset( $autoFPReport, $wikirunpageURL, $enableAPILogging, $apiCall, $expectedValue, $decodeFunction, $enableMail, $to, $from, $oauthURL, $accessSecret, $accessToken, $consumerSecret, $consumerKey, $db, $user, $pass, $port, $host, $texttable, $pagetable, $revisiontable, $wikidb, $wikiuser, $wikipass, $wikiport, $wikihost, $useWikiDB, $limitedRun, $testMode, $disableEdits, $debug, $runpage, $memoryFile, $taskname, $username, $nobots, $apiURL, $userAgent, $useCIDservers, $cidServers, $cidAuthCode );

register_shutdown_function( [ "API", "closeFileHandles" ] );

function replaceMagicInitWords( $input ) {
	if( !is_string( $input ) ) return $input;
	$output = $input;
	if( !defined( 'TASKNAME' ) ) global $taskname;
	else $taskname = TASKNAME;
	if( defined( 'WIKIPEDIA' ) ) $output = str_replace( "{wikipedia}", WIKIPEDIA, $output );
	$output = str_replace( "{taskname}", $taskname, $output );

	return $output;
}