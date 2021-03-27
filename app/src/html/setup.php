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

define( 'TESTMODE', false );
define( 'IAVERBOSE', false );

ini_set( 'memory_limit', '256M' );

date_default_timezone_set( "UTC" );

//Create a file named setpath.php in the same directory as this file and set the $path to the root directory containing IABot's library.
$path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . "../";

define( 'IAVERBOSE', false );
define( 'TESTMODE', false );

if( file_exists( 'setpath.php' ) ) require_once( 'setpath.php' );

require_once( $path . 'sessions.config.inc.php' );

if( $sessionSecure === true && isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] != "https" ) {
	$redirect = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
	header( "HTTP/1.1 101 Switching Protocols", true, 101 );
	header( "Location: $redirect" );
	exit( 0 );
}

require_once( 'Includes/session.php' );

$sessionObject = new Session();

$sessionObject->start();
if( isset( $_GET['wiki'] ) ) {
	$_SESSION['setwiki'] = $_GET['wiki'];
}
if( isset( $_GET['lang'] ) ) {
	$_SESSION['setlang'] = $_GET['lang'];
}

date_default_timezone_set( "UTC" );
if( !defined( 'USEWEBINTERFACE' ) ) define( 'USEWEBINTERFACE', 1 );
error_reporting( E_ALL );

require_once( $path . 'deadlink.config.inc.php' );

if( file_exists( $path . 'deadlink.config.local.inc.php'
) ) {
	require_once( $path . 'deadlink.config.local.inc.php' );
}

@define( 'HOST', $host );
@define( 'PORT', $port );
@define( 'USER', $user );
@define( 'PASS', $pass );
@define( 'DB', $db );

@define( 'ROOTURL', "http://localhost/" );

require_once( $path . 'Core/DB.php' );

DB::createConfigurationTable();

require_once( $path . 'Core/APII.php' );

session_write_close();
require_once( 'Includes/OAuth.php' );
require_once( 'Includes/DB2.php' );
require_once( 'Includes/User.php' );
require_once( 'Includes/HTMLLoader.php' );
require_once( 'Includes/pagefunctions.php' );
require_once( 'Includes/actionfunctions.php' );

$typeCast1 = [
	'disableEdits' => 'bool', 'userAgent' => 'string', 'cidUserAgent' => 'string', 'taskname' => 'string',
	'enableAPILogging' => 'bool',
	'expectedValue' => 'string', 'decodeFunction' => 'string', 'enableMail' => 'bool',
	'to' => 'string', 'from' => 'string', 'useCIDservers' => 'bool', 'cidServers' => 'string',
	'cidAuthCode' => 'string', 'enableProfiling' => 'bool', 'defaultWiki' => 'string',
	'autoFPReport' => 'bool', 'guifrom' => 'string', 'guidomainroot' => 'string',
	'disableInterface' => 'bool', 'availabilityThrottle' => 'int'
];
$typeCast2 = [
	'wikiName' => 'string', 'i18nsource' => 'string', 'i18nsourcename' => 'string', 'language' => 'string',
	'rooturl' => 'string', 'apiurl' => 'string', 'oauthurl' => 'string',
	'runpage' => 'bool', 'nobots' => 'bool', 'apiCall' => 'string', 'usekeys' => 'string',
	'usewikidb' => 'string'
];

$mainHTML = new HTMLLoader( "mainsetup", "en" );

$configuration1 = DB::getConfiguration( "global", "systemglobals" );
if( !empty( $configuration1 ) ) {
	$configuration2 =
		DB::getConfiguration( 'global', "systemglobals-allwikis", $configuration1['defaultWiki'] );
}

if( !empty( $configuration1 ) ) {
	foreach( $typeCast1 as $variable => $type ) {
		if( !isset( $configuration1[$variable] ) ) {
			$configuration1 = [];
			$configuration2 = [];
			break;
		}
	}
}

if( !empty( $configuration2 ) ) {
	foreach( $typeCast2 as $variable => $type ) {
		if( !isset( $configuration2[$variable] ) ) {
			$configuration2 = [];
			break;
		}
	}
}

if( empty( $configuration1 ) ) {
	$toLoad = 1;
} elseif( empty( $configuration2 ) ) {
	$toLoad = 2;
} else {
	header( "HTTP/1.1 308 Permanent Redirect", true, 308 );
	header( "Location: index.php", true, 308 );
	die( "Sorry, but this is only meant to initially setup the interface.  Configuration values already exist." );
}

if( isset( $_POST['action'] ) && $_POST['action'] == "submitvalues" ) {
	if( $_POST['setuptype'] == "setup1" && $toLoad == 1 ) {
		unset( $_POST['setuptype'], $_POST['action'] );
		foreach( $typeCast1 as $key => $type ) {
			if( empty( $_POST[$key] ) ) {
				switch( $key ) {
					case "expectedValue":
					case "decodeFunction":
						if( $_POST['enableAPILogging'] == 1 ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);
							goto finishSetupLoad;
						}
						break;
					case "to":
					case "from":
						if( $_POST['enableMail'] == 1 ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);
							goto finishSetupLoad;
						}
						break;
					case "cidServers":
					case "cidAuthCode":
						if( $_POST['useCIDservers'] == 1 ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);
							goto finishSetupLoad;
						}
						break;
					case "disableEdits":
					case "disableInterface":
					case "enableAPILogging":
					case "useCIDservers":
					case "enableMail":
					case "enableProfiling":
					case "autoFPReport":
						if( !isset( $_POST[$key] ) ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);
							goto finishSetupLoad;
						}
						break;
					default:
						$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
						                          "{{{missingdata}}}"
						);
						goto finishSetupLoad;
				}
			}
			switch( $type ) {
				case 'bool':
					$_POST[$key] = (bool) $_POST[$key];
					break;
				case 'int':
					$_POST[$key] = (int) $_POST[$key];
					break;
				case 'string':
					$_POST[$key] = (string) $_POST[$key];
					break;
				case 'float':
					$_POST[$key] = (float) $_POST[$key];
					break;
			}
		}
		$configuration1 = [];
		foreach( $_POST as $key => $value ) {
			if( $key == "cidServers" ) {
				$value = explode( " ", $value );
			}
			DB::setConfiguration( "global", "systemglobals", $key, $value );
			$configuration1[$key] = $value;
		}
		$toLoad = 2;
	} elseif( $_POST['setuptype'] == "setup2" && $toLoad == 2 ) {
		unset( $_POST['setuptype'], $_POST['action'] );
		if( empty( $_POST['usewikidb'] ) || $_POST['usewikidb'] == "0" ) {
			$typeCast['usewikidb'] = "bool";
		}
		$_POST['wikiName'] = $configuration1['defaultWiki'];
		foreach( $typeCast2 as $key => $type ) {
			if( empty( $_POST[$key] ) ) {
				switch( $key ) {
					case "apiCall":
						if( $configuration1['enableAPILogging'] !== true ) break;
						else {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);
							goto finishSetupLoad;
						}
					case "usewikidb":
					case "runpage":
					case "nobots":
						if( !isset( $_POST[$key] ) ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);
							goto finishSetupLoad;
						}
						break;
					default:
						$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
						                          "{{{missingdata}}}"
						);
						goto finishSetupLoad;
				}
			}
			switch( $type ) {
				case 'bool':
					$_POST[$key] = (bool) $_POST[$key];
					break;
				case 'int':
					$_POST[$key] = (int) $_POST[$key];
					break;
				case 'string':
					$_POST[$key] = (string) $_POST[$key];
					break;
				case 'float':
					$_POST[$key] = (float) $_POST[$key];
					break;
			}
		}
		define( 'WIKIPEDIA', $_POST['wikiName'] );
		$_POST['runpagelocation'] = $_POST['i18nsourcename'] . $_POST['wikiName'];
		$wikiName = $_POST['wikiName'];
		unset( $_POST['wikiName'] );
		foreach( $_POST as $key => $value ) {
			DB::setConfiguration( "global", "systemglobals-allwikis", WIKIPEDIA, $_POST );
		}
		DB::setConfiguration( $wikiName, "wikiconfig", "runpage", "disable" );
		header( "HTTP/1.1 302 Found", true, 302 );
		header( "Location: index.php?page=configurewiki&configurewiki={$_POST['runpagelocation']}", true, 302 );
		die( "Setup complete" );
	}
}

finishSetupLoad:
if( $toLoad == 1 ) {
	$bodyHTML = new HTMLLoader( "setup1", "en" );
	if( isset( $_POST['disableEdits'] ) ) {
		if( $_POST['disableEdits'] == 1 ) $bodyHTML->assignElement( "disableEdits1", "checked" );
		else $bodyHTML->assignElement( "disableEdits0", "checked" );
	}
	if( isset( $_POST['userAgent'] ) ) $bodyHTML->assignElement( "userAgent", $_POST['userAgent'] );
	if( isset( $_POST['cidUserAgent'] ) ) $bodyHTML->assignElement( "cidUserAgent", $_POST['cidUserAgent'] );
	if( isset( $_POST['taskname'] ) ) $bodyHTML->assignElement( "taskname", $_POST['taskname'] );
	if( isset( $_POST['enableAPILogging'] ) ) {
		if( $_POST['enableAPILogging'] == 1 ) $bodyHTML->assignElement( "enableAPILogging1", "checked" );
		else $bodyHTML->assignElement( "enableAPILogging0", "checked" );
	}
	if( isset( $_POST['expectedValue'] ) ) $bodyHTML->assignElement( "expectedValue", $_POST['expectedValue'] );
	if( isset( $_POST['decodeFunction'] ) ) $bodyHTML->assignElement( "decodeFunction", $_POST['decodeFunction'] );
	if( isset( $_POST['enableMail'] ) ) {
		if( $_POST['enableMail'] == 1 ) $bodyHTML->assignElement( "enableMail1", "checked" );
		else $bodyHTML->assignElement( "enableMail0", "checked" );
	}
	if( isset( $_POST['to'] ) ) $bodyHTML->assignElement( "to", $_POST['to'] );
	if( isset( $_POST['from'] ) ) $bodyHTML->assignElement( "from", $_POST['from'] );
	if( isset( $_POST['guifrom'] ) ) $bodyHTML->assignElement( "guifrom", $_POST['guifrom'] );
	if( isset( $_POST['guidomainroot'] ) ) $bodyHTML->assignElement( "guidomainroot", $_POST['guidomainroot'] );
	if( isset( $_POST['useCIDservers'] ) ) {
		if( $_POST['useCIDservers'] == 1 ) {
			$bodyHTML->assignElement( "useCIDservers1", "checked" );
		} else $bodyHTML->assignElement( "useCIDservers0", "checked" );
	}
	if( isset( $_POST['cidServers'] ) ) $bodyHTML->assignElement( "cidServers", $_POST['cidServers'] );
	if( isset( $_POST['cidAuthCode'] ) ) $bodyHTML->assignElement( "cidAuthCode", $_POST['cidAuthCode'] );
	if( isset( $_POST['enableProfiling'] ) ) {
		if( $_POST['enableProfiling'] == 1 ) {
			$bodyHTML->assignElement( "enableProfiling1", "checked" );
		} else $bodyHTML->assignElement( "enableProfiling0", "checked" );
	}
	if( isset( $_POST['defaultWiki'] ) ) $bodyHTML->assignElement( "defaultWiki", $_POST['defaultWiki'] );
	if( isset( $_POST['availabilityThrottle'] ) ) $bodyHTML->assignElement( "availabilityThrottle",
	                                                                        $_POST['availabilityThrottle']
	);
	if( isset( $_POST['autoFPReport'] ) ) {
		if( $_POST['autoFPReport'] == 1 ) {
			$bodyHTML->assignElement( "autoFPReport1", "checked" );
		} else $bodyHTML->assignElement( "autoFPReport0", "checked" );
	}
	if( isset( $_POST['disableInterface'] ) ) {
		if( $_POST['disableInterface'] == 1 ) {
			$bodyHTML->assignElement( "disableInterface1", "checked" );
		} else $bodyHTML->assignElement( "disableInterface0", "checked" );
	}
	$mainHTML->assignElement( "tooltitle", "{{{setup1}}}" );
} elseif( $toLoad == 2 ) {
	$bodyHTML = new HTMLLoader( "setup2", "en" );
	$mainHTML->assignElement( "tooltitle", "{{{setup2}}}" );
	$bodyHTML->assignElement( "wikiName", $configuration1['defaultWiki'] );
	$bodyHTML->assignElement( "wikiNameFrom", $configuration1['defaultWiki'] );
	$bodyHTML->assignElement( "wikiNameDisabled", "disabled" );
	$htmlText = "";
	foreach( $oauthKeys as $name => $set ) {
		$htmlText .= "<label class=\"radio\">
						<input type=\"radio\" name=\"usekeys\" id=\"usekeys$name\" value=\"$name\" {{{{usekeys$name}}}}>$name
					</label>\n";
	}
	$bodyHTML->assignElement( "usekeysoptions", $htmlText );
	$htmlText = "";
	foreach( $oauthKeys as $name => $set ) {
		$htmlText .= "<label class=\"radio\">
						<input type=\"radio\" name=\"usewikidb\" id=\"usewikidb$name\" value=\"$name\" {{{{usewikidb$name}}}}>$name
					</label>\n";
	}
	$bodyHTML->assignElement( "usewikidboptions", $htmlText );
	if( $configuration1['enableAPILogging'] === false ) $bodyHTML->assignElement( "apiCalldisplay", "none" );
	else $bodyHTML->assignElement( "apiCalldisplay", "block" );
	if( isset( $_POST['wikiName'] ) ) $bodyHTML->assignElement( "wikiName", $_POST['wikiName'] );
	if( isset( $_POST['i18nsource'] ) ) $bodyHTML->assignElement( "i18nsource", $_POST['i18nsource'] );
	if( isset( $_POST['i18nsourcename'] ) ) $bodyHTML->assignElement( "i18nsourcename", $_POST['i18nsourcename'] );
	if( isset( $_POST['language'] ) ) $bodyHTML->assignElement( "language", $_POST['language'] );
	if( isset( $_POST['rooturl'] ) ) $bodyHTML->assignElement( "rooturl", $_POST['rooturl'] );
	if( isset( $_POST['apiurl'] ) ) $bodyHTML->assignElement( "apiurl", $_POST['apiurl'] );
	if( isset( $_POST['oauthurl'] ) ) $bodyHTML->assignElement( "oauthurl", $_POST['oauthurl'] );
	if( isset( $_POST['runpage'] ) ) {
		if( $_POST['runpage'] == 1 ) $bodyHTML->assignElement( "runpage1", "checked" );
		else $bodyHTML->assignElement( "runpage0", "checked" );
	}
	if( isset( $_POST['nobots'] ) ) {
		if( $_POST['nobots'] == 1 ) $bodyHTML->assignElement( "nobots1", "checked" );
		else $bodyHTML->assignElement( "nobots0", "checked" );
	}
	if( isset( $_POST['apiCall'] ) ) $bodyHTML->assignElement( "apiCall", $_POST['apiCall'] );
	if( isset( $_POST['usekeys'] ) ) {
		$bodyHTML->assignElement( "usekeys{$_POST['usekeys']}", "checked" );
	}
	if( isset( $_POST['usewikidb'] ) ) {
		$bodyHTML->assignElement( "usewikidb{$_POST['usewikidb']}", "checked" );
	}
} else {
	header( "HTTP/1.1 308 Permanent Redirect", true, 308 );
	header( "Location: index.php", true, 308 );
}

$bodyHTML->assignElement( "actiontarget", "setup.php" );
$bodyHTML->finalize();

$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
$mainHTML->assignElement( "csstheme", "lumen" );
$mainHTML->finalize();
echo $mainHTML->getLoadedTemplate();
