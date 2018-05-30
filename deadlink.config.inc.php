<?php
//Create a file in the same directory as this on named deadlink.config.local.inc.php and copy the stuff below.
//Activate this to run the bot on a specific page(s) for debugging purposes.
$debug = false;
$limitedRun = false;
$debugPage = [ 'title' => "", 'pageid' => 0 ];
$debugStyle = 20;   //Use an int to run through a limited amount of articles.  Use "test" to run the test pages.
// Set to true to disable writing to database and editing wiki (dry run)
// And write what would be edited on the page to stdout
$testMode = false;
//Progress memory file.  This allows the bot to resume where it left off in the event of a shutdown or a crash.
$memoryFile = "";
//Wiki connection setup.  Keys are grouped in sets of 3, and given a name to be referred to by the wiki setup parameters.
$oauthKeys = [
	'default' => [
		'bot'         => [
			'consumerkey' => "", 'consumersecret' => "", 'accesstoken' => "", 'accesssecret' => "", 'username' => ""
		],
		'webappfull'  => [ 'consumerkey' => "", 'consumersecret' => "" ],
		'webappbasic' => [ 'consumerkey' => "", 'consumersecret' => "" ]
	]
];

$accessibleWikis = [
	'namewiki' => [
		'i18nsource'      => '',
		'i18nsourcename'  => '',
		'language'        => '',
		'rooturl'         => '',
		'apiurl'          => '',
		'oauthurl'        => '',
		'nobots'          => true,
		'usekeys'         => '',
		'usewikidb'       => '',
		'apicall'         => '',
		'runpage'         => false,
		'runpagelocation' => ''
	]
];

//Wikipedia DB setup
$wikiDBs = [
	'default' => [
		'host'      => "", 'port' => "", 'user' => "", 'pass' => "", 'db' => "", 'revisiontable' => "",
		'texttable' => "", 'pagetable' => ""
	]
];

//DB connection setup
$host = "";
$port = "";
$user = "";
$pass = "";
$db = "";

//Webapp variables
//These are defaults for the web interface portion of the bot.

//Directory path to the www/public_html directory.
$publicHTMLPath = "";

$disableInterface = false;
$interfaceMaster = [
	'inheritsgroups' => [ 'root' ],
	'inheritsflags'  => [ 'defineusergroups', 'configurewiki' ],
	'assigngroups'   => [ 'root' ],
	'assignflags'    => [ 'defineusergroups', 'configurewiki' ],
	'removegroups'   => [ 'root' ],
	'removeflags'    => [ 'defineusergroups', 'configurewiki' ],
	'members'        => []
];