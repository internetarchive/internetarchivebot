<?php
/*
ATTENTION: If you are running InternetArchiveBot within Docker, please use this file and rename it to deadlink.config.local.inc.php
Some setup variables are preconfigured for you.  Only modify them if you know what you are doing.
*/

//Activate this to run the bot on a specific page(s) for debugging purposes.
$debug = false;
$limitedRun = false;
$debugPage = ['title' => "page_name_here", 'pageid' => "page_id_here"];
$debugStyle = "test";   //Use an int to run through a limited amount of articles.  Use "test" to run the test page.

//Enable run pages on this installation
$runpage = false;

/*
 * These are required to initiate a save page request for the Wayback Machine.
 * You can leave them blank if you do not intend the bot to submit pages to the Wayback Machine.
 * To get keys an Internet Archive account is required.
 *
 * Contact Internet Archive for more information on getting account keys.
 */
$waybackKeys = [
	'accesstoken'=>"", 'accesssecret'=>""
];

//OAuth keys are required.  You need to setup at least two OAuth consumers.
/*
 * You need an owner-only consumer for the bot which consists of 4 keys and is defined under the bot array.
 * You need a write-access consumer with the ability to edit protected pages which consists of 2 keys and is defined unde the webappfull array.
 * You can optionally create a consumer for identification purposes only which can be defined under the webappbasic array.
 * webappbasic can be left blank.
 *
 * You can have multiple groups of keys for different MediaWiki installations.
 * The group shown below will be identified as 'default'.  It can be renamed to anything.
 * Do NOT rename after the bot has been configured.  It will cause corruption.
 */
$oauthKeys = [
	'default' => [
		'bot' => [
			'consumerkey' => "",
			'consumersecret' => "",
			'accesstoken' => "",
			'accesssecret' => "",
			'username' => ""
		],
		'webappfull' => [
			'consumerkey' => "",
			'consumersecret' => ""
		],
		'webappbasic' => [
			'consumerkey' => "",
			'consumersecret' => ""
		]
	]
];

/*
 * Put credentials here to connect to the database hosting the MediaWiki installation.
 *
 * This is optional.
 *
 * This supports multiple sets of credentials.
 * Refer to the deadlink.config.inc.php file for details on how to set the appropriate values.
 */
$wikiDBs = [];

//Put your wiki username here.  Otherwise you won't be let in to the tool during setup.
$interfaceMaster['members'][] = "";

//DO NOT MODIFY BELOW UNLESS YOU KNOW WHAT YOU ARE DOING

//Progress memory file.  This allows the bot to resume where it left off in the event of a shutdown or a crash.
$memoryFile = "/var/www/html/memory/";

//DB connection setup
$host = "db";
$port = 3306;
$user = getenv( "MYSQL_USER" );
$pass = getenv( "MYSQL_PASSWORD" );
$db = getenv( "MYSQL_DB" );

$publicHTMLPath = "www/";