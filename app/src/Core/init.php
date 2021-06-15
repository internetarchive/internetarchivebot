<?php
/*
	Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	IABot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with IABot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

/**
 * @file
 * Initializes the bot and the web interface.
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */

if( PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION < 7.2 ) {
	echo "ERROR: Minimum requirements for correct operation is PHP 7.2.9.  You are running " . PHP_VERSION .
	     ", which will not run correctly.\n";
	exit( 1 );
}

//Establish root path
@define( 'IABOTROOT', dirname( __FILE__, 2 ) . DIRECTORY_SEPARATOR );
date_default_timezone_set( "UTC" );
ini_set( 'memory_limit', '256M' );

//Extend execution to 5 minutes
ini_set( 'max_execution_time', 300 );
@define( 'VERSION', "2.0.8" );

require_once( IABOTROOT . 'deadlink.config.inc.php' );

if( file_exists( IABOTROOT . 'deadlink.config.local.inc.php' ) ) {
	require_once( IABOTROOT . 'deadlink.config.local.inc.php' );
}

require_once( IABOTROOT . 'Core/DB.php' );

if( substr( php_uname(), 0, 7 ) == "Windows" ) {
	$callingFile = explode( "\\", $_SERVER['SCRIPT_FILENAME'] );
} else $callingFile = explode( "/", $_SERVER['SCRIPT_FILENAME'] );
$callingFile = $callingFile[count( $callingFile ) - 1];

@define( 'HOST', $host );
@define( 'PORT', $port );
@define( 'USER', $user );
@define( 'PASS', $pass );
@define( 'DB', $db );

@define( 'TESTMODE', $testMode );
if( !defined( 'IAVERBOSE' ) ) {
	if( $debug ) {
		@define( 'IAVERBOSE', true );
	} else @define( 'IAVERBOSE', false );
}

DB::createConfigurationTable();

if( !defined( 'IGNOREVERSIONCHECK' ) ) {
	$versionSupport = DB::getConfiguration( 'global', 'versionData' );

	$versionSupport['backwardsCompatibilityVersions'] =
		[ '2.0', '2.0.0', '2.0.1', '2.0.2', '2.0.3', '2.0.4', '2.0.5', '2.0.6', '2.0.7' ];

	$rollbackVersions = [];

	if( empty( $versionSupport['currentVersion'] ) ) {
		DB::setConfiguration( 'global', 'versionData', 'currentVersion', VERSION );
		DB::setConfiguration( 'global', 'versionData', 'rollbackVersions', $rollbackVersions );
	} else {
		if( $versionSupport['currentVersion'] != VERSION ) {
			if( VERSION > $versionSupport['currentVersion'] ) {
				if( !in_array( $versionSupport['currentVersion'], $versionSupport['backwardsCompatibilityVersions']
				) ) {
					echo "Fatal Error: You are upgrading from v{$versionSupport['currentVersion']} to v" . VERSION .
					     ".  This update requires a clean install, or an update script.  Please contact the developer.\n";
					exit( 1 );
				}
				DB::setConfiguration( 'global', 'versionData', 'currentVersion', VERSION );
				DB::setConfiguration( 'global', 'versionData', 'rollbackVersions', $rollbackVersions );

				if( empty( $rollbackVersions ) ) echo "ATTENTION: This update cannot be rolled back!\n";

				echo "Successfully upgraded to v" . VERSION . "\n";
			} else {
				if( !in_array( VERSION, $versionSupport['rollbackVersions'] ) ) {
					echo "Fatal Error: You are downgrading from {$versionSupport['currentVersion']} to " . VERSION .
					     ".  InternetArchiveBot cannot be rolled back to this version due to compatibility issues.  Please upgrade to one of the following versions: " .
					     ( !empty( $versionSupport['rollbackVersions'] ) ?
						     implode( ', ', $versionSupport['rollbackVersions'] ) .
						     ", or " : "" ) . "{$versionSupport['currentVersion']}\n";
					exit( 1 );
				}
			}
		}
	}
}

$configuration = DB::getConfiguration( "global", "systemglobals" );

$typeCast = [
	'taskname' => 'string', 'disableEdits' => 'bool', 'userAgent' => 'string', 'enableAPILogging' => 'bool',
	'expectedValue' => 'string', 'decodeFunction' => 'string', 'enableMail' => 'bool',
	'to' => 'string', 'from' => 'string', 'useCIDservers' => 'bool', 'cidServers' => 'array',
	'cidAuthCode' => 'string', 'enableProfiling' => 'bool', 'defaultWiki' => 'string',
	'autoFPReport' => 'bool', 'guifrom' => 'string', 'guidomainroot' => 'string', 'disableInterface' => 'bool',
	'cidUserAgent' => 'string', 'availabilityThrottle' => 'int'
];

unset( $disableEdits, $userAgent, $apiURL, $oauthURL, $taskname, $nobots, $enableAPILogging, $apiCall,
	$expectedValue, $decodeFunction, $enableMail, $to, $from, $useCIDservers, $cidServers, $cidAuthCode,
	$enableProfiling, $accessibleWikis, $defaultWiki, $autoFPReport, $guifrom, $guidomainroot, $disableInterface, $availabilityThrottle
);

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

//HTTP referrer autodetection.  Attempt to @define the correct based on the HTTP_REFERRER
require_once( IABOTROOT . "Core/localization.php" );

$accessibleWikis = DB::getConfiguration( "global", "systemglobals-allwikis" );

if( empty( $accessibleWikis ) ) {
	@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
	@header( "Location: setup.php", true, 307 );
	echo "No wiki has been setup yet.  Please use the web interface to set up the bot.";
	exit( 1 );
}

ksort( $accessibleWikis );

if( !defined( 'WIKIPEDIA' ) ) {
	if( !empty( $_SERVER['HTTP_REFERER'] ) ) {
		$root = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST );
		if( !empty( $root ) ) {
			$root = "https://$root/";
			foreach( $accessibleWikis as $wiki => $wikiData ) {
				if( $root == $wikiData['rooturl'] ) {
					@define( 'WIKIPEDIA', $wiki );
					break;
				}
			}
			if( !defined( 'WIKIPEDIA' ) ) @define( 'WIKIPEDIA', $defaultWiki );
		} else @define( 'WIKIPEDIA', $defaultWiki );
	} else @define( 'WIKIPEDIA', $defaultWiki );
}

if( empty( $accessibleWikis[WIKIPEDIA]['i18nsource'] ) || empty( $accessibleWikis[WIKIPEDIA]['i18nsourcename'] ) ||
    empty( $accessibleWikis[WIKIPEDIA]['language'] ) || empty( $accessibleWikis[WIKIPEDIA]['rooturl'] ) ||
    empty( $accessibleWikis[WIKIPEDIA]['apiurl'] ) ||
    empty( $accessibleWikis[WIKIPEDIA]['oauthurl'] ) || empty( $accessibleWikis[WIKIPEDIA]['nobots'] ) ||
    !isset( $accessibleWikis[WIKIPEDIA]['apiCall'] ) ) {
	throw new Exception( "Missing configuration keys for this Wiki", 2 );
} else {
	@define( 'APICALL', $accessibleWikis[WIKIPEDIA]['apiCall'] );
	@define( 'API', $accessibleWikis[WIKIPEDIA]['apiurl'] );
	@define( 'OAUTH', $accessibleWikis[WIKIPEDIA]['oauthurl'] );
	@define( 'NOBOTS', $accessibleWikis[WIKIPEDIA]['nobots'] );
	@define( 'BOTLANGUAGE', $accessibleWikis[WIKIPEDIA]['language'] );
	if( isset( $locales[$accessibleWikis[WIKIPEDIA]['language']] ) ) {
		@define( 'BOTLOCALE',
		         serialize( $locales[$accessibleWikis[WIKIPEDIA]['language']]
		         )
		);
	} else @define( 'BOTLOCALE', serialize( $locales['en'] ) );

	if( !isset( $useKeys ) ) $useKeys = $accessibleWikis[WIKIPEDIA]['usekeys'];
	if( !isset( $useWikiDB ) ) $useWikiDB = $accessibleWikis[WIKIPEDIA]['usewikidb'];
	if( $useWikiDB == 0 ) $useWikiDB = false;
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
	@define( 'CONSUMERKEY', $oauthKeys[$useKeys]['bot']['consumerkey'] );
	@define( 'CONSUMERSECRET', $oauthKeys[$useKeys]['bot']['consumersecret'] );
	@define( 'ACCESSTOKEN', $oauthKeys[$useKeys]['bot']['accesstoken'] );
	@define( 'ACCESSSECRET', $oauthKeys[$useKeys]['bot']['accesssecret'] );
	@define( 'USERNAME', $oauthKeys[$useKeys]['bot']['username'] );
}

@define( 'TASKNAME', replaceMagicInitWords( $taskname ) );
@define( 'USERAGENT', replaceMagicInitWords( $userAgent ) );
@define( 'COOKIE', sys_get_temp_dir() . '/' . $oauthKeys[$useKeys]['bot']['username'] . WIKIPEDIA . TASKNAME );

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

require_once( IABOTROOT . 'Core/APII.php' );
require_once( IABOTROOT . 'Core/parse.php' );
require_once( IABOTROOT . 'Core/generator.php' );
require_once( IABOTROOT . 'Core/ISBN.php' );
require_once( IABOTROOT . 'Core/Memory.php' );
require_once( IABOTROOT . 'Core/FalsePositives.php' );
require_once( IABOTROOT . 'Core/CiteMap.php' );

$wikiConfig       = API::fetchConfiguration( $behaviordefined, false );
$archiveTemplates = CiteMap::getMaps( WIKIPEDIA, false, 'archive' );

if( empty( $archiveTemplates ) ) {
	@define( 'GUIREDIRECTED', true );
	if( in_array( $callingFile, [ 'index.php', 'deadlink.php' ] ) &&
	    ( !isset( $_GET['systempage'] ) || $_GET['systempage'] != "definearchives" ) ) {
		@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
		@header( "Location: index.php?page=systemconfig&systempage=definearchives", true, 307 );
		echo WIKIPEDIA . " is not set up yet.";
		exit( 1 );
	}
} elseif( $behaviordefined === false ) {
	@define( 'GUIREDIRECTED', true );
	if( in_array( $callingFile, [ 'index.php', 'deadlink.php' ] ) && ( !isset( $_GET['systempage'] ) ||
	                                                                   $_GET['systempage'] != "wikiconfig" )
	) {
		@header( "HTTP/1.1 307 Temporary Redirect", true, 307 );
		@header( "Location: index.php?page=systemconfig&systempage=wikiconfig&wiki=" . WIKIPEDIA, true, 307 );
		echo WIKIPEDIA . " is not set up yet.";
		exit( 1 );
	}
}

require_once( IABOTROOT . '../../vendor/autoload.php' );
if( isset( $accessibleWikis[WIKIPEDIA] ) && file_exists( IABOTROOT . 'extensions/' . WIKIPEDIA . '.php' ) ) {
	require_once( IABOTROOT . 'extensions/' . WIKIPEDIA . '.php' );
}

if( class_exists( WIKIPEDIA . 'Parser' ) ) {
	@define( 'PARSERCLASS', WIKIPEDIA . 'Parser' );
} else @define( 'PARSERCLASS', 'Parser' );
if( class_exists( WIKIPEDIA . 'Generator' ) ) {
	@define( 'GENERATORCLASS', WIKIPEDIA . 'DataGenerator' );
} else @define( 'GENERATORCLASS', 'DataGenerator' );
if( class_exists( WIKIPEDIA . 'API' ) ) {
	@define( 'APIICLASS', WIKIPEDIA . 'API' );
} else @define( 'APIICLASS', 'API' );
if( class_exists( WIKIPEDIA . 'DB' ) ) {
	@define( 'DBCLASS', WIKIPEDIA . 'DB' );
} else @define( 'DBCLASS', 'DB' );

@define( 'PUBLICHTML', dirname( __FILE__, 2 ) . DIRECTORY_SEPARATOR . $publicHTMLPath );
if( $autoFPReport === true ) {
	require_once( PUBLICHTML . "Includes/DB2.php" );
	require_once( PUBLICHTML . "Includes/HTMLLoader.php" );
	require_once( PUBLICHTML . "Includes/actionfunctions.php" );
}
require_once( PUBLICHTML . 'Includes/xhprof/display/xhprof.php' );

@define( 'RUNPAGE', $runpage );
@define( 'EXPECTEDRETURN', $expectedValue );
@define( 'DECODEMETHOD', $decodeFunction );
@define( 'LOGAPI', $enableAPILogging );
@define( 'IAPROGRESS', replaceMagicInitWords( $memoryFile ) );
@define( 'DEBUG', $debug );
@define( 'LIMITEDRUN', $limitedRun );
@define( 'DISABLEEDITS', $disableEdits );
@define( 'USEWIKIDB', $useWikiDB );
if( USEWIKIDB !== false ) {
	if( empty( $wikiDBs[USEWIKIDB]['host'] ) || empty( $wikiDBs[USEWIKIDB]['port'] ) ||
	    !isset( $wikiDBs[USEWIKIDB]['user'] ) || !isset( $wikiDBs[USEWIKIDB]['pass'] ) ||
	    empty( $wikiDBs[USEWIKIDB]['db'] ) || !isset( $wikiDBs[USEWIKIDB]['revisiontable'] ) ||
	    !isset( $wikiDBs[USEWIKIDB]['pagetable'] ) || !isset( $wikiDBs[USEWIKIDB]['texttable'] ) ) {
		throw new Exception( "Missing database keys for this Wiki", 2 );
	}
	@define( 'WIKIHOST', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['host'] ) );
	@define( 'WIKIPORT', $wikiDBs[USEWIKIDB]['port'] );
	@define( 'WIKIUSER', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['user'] ) );
	@define( 'WIKIPASS', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['pass'] ) );
	@define( 'WIKIDB', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['db'] ) );
	@define( 'REVISIONTABLE', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['revisiontable'] ) );
	@define( 'TEXTTABLE', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['texttable'] ) );
	@define( 'PAGETABLE', replaceMagicInitWords( $wikiDBs[USEWIKIDB]['pagetable'] ) );
}

@define( 'WAYBACKACCESSKEY', $waybackKeys['accesstoken'] );
@define( 'WAYBACKACCESSSECRET', $waybackKeys['accesssecret'] );
@define( 'ENABLEMAIL', $enableMail );
@define( 'TO', $to );
@define( 'FROM', replaceMagicInitWords( $from ) );
@define( 'GUIFROM', replaceMagicInitWords( $guifrom ) );
@define( 'ROOTURL', $guidomainroot );
@define( 'USEADDITIONALSERVERS', $useCIDservers );
@define( 'CIDSERVERS', implode( "\n", $cidServers ) );
@define( 'CIDAUTHCODE', $cidAuthCode );
@define( 'CIDUSERAGENT', $cidUserAgent );
@define( 'AUTOFPREPORT', $autoFPReport );
@define( 'PROFILINGENABLED', $enableProfiling );
if( $availabilityThrottle === 0 ) {
	@define( 'THROTTLECDXREQUESTS', false );
} else @define( 'THROTTLECDXREQUESTS', $availabilityThrottle );
if( !defined( 'UNIQUEID' ) ) @define( 'UNIQUEID', "" );
unset( $autoFPReport, $wikirunpageURL, $enableAPILogging, $apiCall, $expectedValue, $decodeFunction, $enableMail,
	$to, $from, $oauthURL, $accessSecret, $accessToken, $consumerSecret, $consumerKey, $db, $user, $pass, $port,
	$host, $texttable, $pagetable, $revisiontable, $wikidb, $wikiuser, $wikipass, $wikiport, $wikihost, $useWikiDB, $limitedRun, $testMode, $disableEdits, $debug, $runpage, $memoryFile, $taskname, $username, $nobots, $apiURL, $userAgent, $useCIDservers, $cidServers, $cidAuthCode, $rateLimited
);

register_shutdown_function( [ 'Memory', 'destroyStore' ] );
register_shutdown_function( [ 'DB', 'unsetWatchDog' ] );

if( !function_exists( 'strptime' ) ) {
	function strptime( $date, $format )
	{
		$masks = array(
			'%r' => '%I:%M:%S %p',
			'%R' => '%H:%M',
			'%T' => '%H:%M:%S',
			'%D' => '%m/%d/%y',
			'%F' => '%Y-%m-%d',
			'%d' => '(?P<d>[0-9]{2})',
			'%e' => '\s?(?P<d>[0-9]{1,2})',
			'%-e' => '(?P<d>[0-9]{1,2})',
			'%j' => '(?P<dy>[0-9]{3}',
			'%m' => '(?P<m>[0-9]{2})',
			'%Y' => '(?P<Y>[0-9]{4})',
			'%H' => '(?P<H>[0-9]{2})',
			'%M' => '(?P<M>[0-9]{2})',
			'%S' => '(?P<S>[0-9]{2})',
			'%a' => '(?:\S*?)',
			'%A' => '(?:\S*?)',
			'%u' => '(?:\d)',
			'%w' => '(?:\d)',
			'%U' => '(?:\d{1,2})',
			'%V' => '(?:\d{2})',
			'%W' => '(?:\d{1,2})',
			'%b' => '(?P<anm>Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)',
			'%B' => '(?P<nm>January|February|March|April|May|June|July|August|September|October|November|December)',
			'%h' => '(?P<anm>Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)',
			'%C' => '(?P<c>[0-9]{2})',
			'%g' => '(?P<ay>[0-9]{2})',
			'%y' => '(?P<ay>[0-9]{2})',
			'%G' => '(?P<Y>[0-9]{4})',
			'%-k' => '(?P<H>[0-9]{1,2})',
			'%k' => '\s?(?P<H>[0-9]{1,2})',
			'%I' => '(?P<APH>[0-9]{2})',
			'%l' => '\s?(?P<APH>[0-9]{1,2})',
			'%p' => '(?P<AP>AM|PM)',
			'%P' => '(?P<AP>am|pm)',
			'%z' => '(?:\-?[0-9]{4})',
			'%Z' => '(?:\S{1,5})',
			'%s' => '(?P<e>[0-9]{1,11}',
			'%n' => '\n',
			'%t' => '\t',
			'%%' => '%'
		);

		$rexep = "#" . strtr( preg_quote( $format ), $masks ) . "#";
		if( !preg_match( $rexep, $date, $out ) ) {
			return false;
		}
		/* Notes
		dy converts to d and m
		nm are named months
		anm are abbreviated months
		c and ay converts to Y; c is assumed when omitted
		AP and APH converts to H
		*/

		if( empty( $out['Y'] ) ) {
			if( !empty( $out['ay'] ) ) {
				if( !empty( $out['c'] ) ) {
					$year = $out['c'];
				} else {
					if( $out['ay'] < 70 ) {
						$year = '20';
					} else {
						$year = '19';
					}
				}
				$year     .= $out['ay'];
				$out['Y'] = $year;
			} else return false;
		}
		if( empty( $out['m'] ) ) {
			if( !empty( $out['anm'] ) ) {
				$map      = [
					'Jan' => 1,
					'Feb' => 2,
					'Mar' => 3,
					'Apr' => 4,
					'May' => 5,
					'Jun' => 6,
					'Jul' => 7,
					'Aug' => 8,
					'Sep' => 9,
					'Oct' => 10,
					'Nov' => 11,
					'Dec' => 12
				];
				$out['m'] = $map[$out['anm']];
			} elseif( !empty( $out['nm'] ) ) {
				$map      = [
					'January' => 1,
					'February' => 2,
					'March' => 3,
					'April' => 4,
					'May' => 5,
					'June' => 6,
					'July' => 7,
					'August' => 8,
					'September' => 9,
					'October' => 10,
					'November' => 11,
					'December' => 12
				];
				$out['m'] = $map[$out['nm']];
			}
		}
		if( empty( $out['H'] ) ) {
			if( !empty( $out['AP'] ) && !empty( $out['APH'] ) ) {
				$hour = 0;
				if( strtolower( $out['ap'] ) == 'pm' ) $hour += 12;
				if( $out['APH'] == 12 ) $out['APH'] = 0;
				$hour     += $out['APH'];
				$out['H'] = $hour;
			} else $out['H'] = 0;
		}
		if( empty( $out['d'] ) || empty( $out['m'] ) ) {
			if( !empty( $day['dy'] ) ) {
				if( $out['Y'] % 4 == 0 ) $out['dy']--;
				$map = [
					1 => 31,
					2 => 28,
					3 => 31,
					4 => 30,
					5 => 31,
					6 => 30,
					7 => 31,
					8 => 31,
					9 => 30,
					10 => 31,
					11 => 30,
					12 => 31
				];

				foreach( $map as $month => $days ) {
					if( $out['dy'] < $days ) {
						$out['m'] = $month;
						$out['d'] = $days;
						break;
					}
					$out['dy'] -= $days;
				}
			} else return false;
		}
		if( empty( $out['M'] ) ) $out['M'] = 0;
		if( empty( $out['S'] ) ) $out['S'] = 0;

		$ret = array(
			"tm_sec" => (int) $out['S'],
			"tm_min" => (int) $out['M'],
			"tm_hour" => (int) $out['H'],
			"tm_mday" => (int) $out['d'],
			"tm_mon" => $out['m'] ? $out['m'] - 1 : 0,
			"tm_year" => $out['Y'] > 1900 ? $out['Y'] - 1900 : 0,
		);

		return $ret;
	}
}

function replaceMagicInitWords( $input )
{
	if( !is_string( $input ) ) return $input;
	$output = $input;
	if( !defined( 'TASKNAME' ) ) {
		global $taskname;
	} else $taskname = TASKNAME;
	if( defined( 'WIKIPEDIA' ) ) $output = str_replace( "{wikipedia}", WIKIPEDIA, $output );
	$output = str_replace( "{taskname}", $taskname, $output );

	return $output;
}
