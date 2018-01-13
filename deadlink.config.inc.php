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
// Set to true to disable the edit function of the bot.
$disableEdits = false;
//Set the bot's UA
$userAgent = '';
//Progress memory file.  This allows the bot to resume where it left off in the event of a shutdown or a crash.
$memoryFile = "";
//Wiki connection setup.  Uses the defined constant WIKIPEDIA.
switch( ( defined( 'WIKIPEDIA' ) ? WIKIPEDIA : "" ) ) {
	default:
		$apiURL = "https://en.wikipedia.org/w/api.php";
		$oauthURL = "https://en.wikipedia.org/w/index.php?title=Special:OAuth";
		$consumerKey = "";
		$consumerSecret = "";
		$accessToken = "";
		$accessSecret = "";
		$username = "";
		$wikirunpageURL =
			false; //Optional: Forces the run page to be read from another wiki.  Specify the index.php url of the wiki to be read from.
		$runpage = "";
		$taskname = "";
		$nobots = false;
		break;
}
//Log central API
$enableAPILogging = false;
$apiCall = "";
$expectedValue = true;
$decodeFunction = 'unserialize';        //Either json_decode or unserialize
//IA Error Mailing List
$enableMail = false;
$to = "";
$from = "";
//GUI eMail
$guifrom = "IABot Mailer <do_not_reply@iabot.org>";
$guidomainroot = "http://localhost/";
//DB connection setup
$host = "";
$port = "";
$user = "";
$pass = "";
$db = "";
//Wikipedia DB setup
$useWikiDB = false;
$wikihost = "";
$wikiport = "";
$wikiuser = "";
$wikipass = "";
$wikidb = "";
$revisiontable = "";
$texttable = "";
$pagetable = "";

//Addtional servers to run the CheckIfDead class on
$useCIDservers = false;
$cidServers = [];
$cidAuthCode = "";

//Webapp variables
//These are defaults for the web interface portion of the bot.

//This controls the bot, but requires a setup interface
$autoFPReport = false;
$publicHTMLPath = "";

$disableInterface = false;
$interfaceMaster = [
	'inheritsgroups' => [ 'root' ],
	'inheritsflags'  => [],
	'assigngroups'   => [ 'root' ],
	'assignflags'    => [],
	'removegroups'   => [ 'root' ],
	'removeflags'    => [],
	'members'        => []
];
$userGroups = [
	'basicuser' => [
		'inheritsgroups' => [],
		'inheritsflags'  => [ 'reportfp', 'analyzepage', 'submitbotjobs' ],
		'assigngroups'   => [],
		'removegroups'   => [],
		'assignflags'    => [],
		'removeflags'    => [],
		'labelclass'     => "default",
		'autoacquire'    => [
			'registered'    => strtotime( "-10 days" ),
			'editcount'     => 10,
			'withwikigroup' => [],
			'withwikiright' => []
		]
	],
	'user'      => [
		'inheritsgroups' => [ 'basicuser' ],
		'inheritsflags'  => [ 'alterarchiveurl', 'changeurldata', 'alteraccesstime', 'botsubmitlimit5000' ],
		'assigngroups'   => [],
		'removegroups'   => [],
		'assignflags'    => [],
		'removeflags'    => [],
		'labelclass'     => "primary",
		'autoacquire'    => [
			'registered'    => strtotime( "-3 months" ),
			'editcount'     => 1000,
			'withwikigroup' => [],
			'withwikiright' => []
		]
	],
	'admin'     => [
		'inheritsgroups' => [ 'user' ],
		'inheritsflags'  => [
			'blockuser', 'changepermissions', 'unblockuser', 'changedomaindata', 'botsubmitlimit50000',
			'deblacklisturls', 'dewhitelisturls',
			'blacklisturls', 'whitelisturls', 'overridearchivevalidation'
		],
		'assigngroups'   => [ 'user', 'basicuser' ],
		'removegroups'   => [ 'user', 'basicuser' ],
		'assignflags'    => [
			'analyzepage', 'changepermissions', 'reportfp', 'alterarchiveurl', 'changeurldata', 'alteraccesstime',
			'changedomaindata', 'submitbotjobs', 'botsubmitlimit5000', 'botsubmitlimit50000',
			'overridearchivevalidation'
		],
		'removeflags'    => [
			'analyzepage', 'changepermissions', 'reportfp', 'alterarchiveurl', 'changeurldata', 'alteraccesstime',
			'changedomaindata', 'submitbotjobs', 'botsubmitlimit5000', 'botsubmitlimit50000',
			'overridearchivevalidation'
		],
		'labelclass'     => "success",
		'autoacquire'    => [
			'registered'    => strtotime( "-6 months" ),
			'editcount'     => 6000,
			'withwikigroup' => [ 'sysop' ],
			'withwikiright' => []
		]
	],
	'root'      => [
		'inheritsgroups' => [ 'admin', 'bot' ],
		'inheritsflags'  => [
			'unblockme', 'viewfpreviewpage', 'changefpreportstatus', 'fpruncheckifdeadreview', 'changemassbq',
			'viewbotqueue', 'changebqjob', 'changeglobalpermissions', 'deblacklistdomains', 'dewhitelistdomains',
			'blacklistdomains', 'whitelistdomains', 'botsubmitlimitnolimit', 'overridelockout'
		],
		'assigngroups'   => [ 'admin', 'bot' ],
		'removegroups'   => [ 'admin', 'bot' ],
		'assignflags'    => [
			'blockuser', 'unblockuser', 'unblockme', 'viewfpreviewpage', 'changefpreportstatus',
			'fpruncheckifdeadreview', 'changemassbq', 'viewbotqueue', 'changebqjob', 'changeglobalpermissions',
			'deblacklisturls', 'dewhitelisturls', 'blacklisturls', 'whitelisturls', 'deblacklistdomains',
			'overridelockout',
			'dewhitelistdomains',
			'blacklistdomains', 'whitelistdomains', 'botsubmitlimitnolimit', 'highapilimit'
		],
		'removeflags'    => [
			'blockuser', 'unblockuser', 'unblockme', 'viewfpreviewpage', 'changefpreportstatus',
			'fpruncheckifdeadreview', 'changemassbq', 'viewbotqueue', 'changebqjob', 'changeglobalpermissions',
			'deblacklisturls', 'dewhitelisturls', 'blacklisturls', 'whitelisturls', 'deblacklistdomains',
			'dewhitelistdomains',
			'blacklistdomains', 'whitelistdomains', 'botsubmitlimitnolimit', 'highapilimit', 'overridelockout'
		],
		'labelclass'     => "danger",
		'autoacquire'    => [
			'registered'    => 0,
			'editcount'     => 0,
			'withwikigroup' => [],
			'withwikiright' => []
		]
	],
	'bot'       => [
		'inheritsgroups' => [ 'user' ],
		'inheritsflags'  => [ 'highapilimit' ],
		'assigngroups'   => [],
		'removegroups'   => [],
		'assignflags'    => [],
		'removeflags'    => [],
		'labelclass'     => "info",
		'autoacquire'    => [
			'registered'    => time(),
			'editcount'     => 0,
			'withwikigroup' => [],
			'withwikiright' => [ 'bot' ]
		]
	]
];

//DO NOT COPY ANYTHING BELOW THIS LINE
//HTTP referrer autodetection.  Attempt to define the correct based on the HTTP_REFERRER
//Only works with the predefined values and doesn't apply to user set values.
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
			if( !defined( 'WIKIPEDIA' ) ) define( 'WIKIPEDIA', "enwiki" );
		} else define( 'WIKIPEDIA', "enwiki" );
	} else define( 'WIKIPEDIA', "enwiki" );
}
if( file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'deadlink.config.local.inc.php'
) ) {
	require_once( 'deadlink.config.local.inc.php' );
}
require_once( 'APII.php' );
require_once( 'Parser/parse.php' );
require_once( 'DB.php' );
require_once( __DIR__ . '/../vendor/autoload.php' );
if( isset( $accessibleWikis[WIKIPEDIA] ) &&
    file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'Parser/' . WIKIPEDIA . '.php' ) ) {
	require_once( 'Parser/' . WIKIPEDIA . '.php' );
	define( 'PARSERCLASS', WIKIPEDIA . 'Parser' );
} else {
	define( 'PARSERCLASS', 'Parser' );
	echo "ERROR: Unable to load local wiki parsing library.\nTerminating application...";
	exit( 40000 );
}
if( $autoFPReport === true ) {
	require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $publicHTMLPath . "Includes/DB2.php" );
	require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $publicHTMLPath . "Includes/HTMLLoader.php" );
	require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $publicHTMLPath . "Includes/actionfunctions.php" );
	define( 'PUBLICHTML', dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $publicHTMLPath );
}
define( 'USERAGENT', $userAgent );
define( 'COOKIE', sys_get_temp_dir() . $username . WIKIPEDIA . $taskname );
define( 'API', $apiURL );
define( 'OAUTH', $oauthURL );
define( 'NOBOTS', $nobots );
if( !defined( 'USEWEBINTERFACE' ) || USEWEBINTERFACE != 1 ) define( 'USERNAME', $username );
define( 'TASKNAME', $taskname );
define( 'IAPROGRESS', $memoryFile );
define( 'RUNPAGE', $runpage );
define( 'DEBUG', $debug );
define( 'LIMITEDRUN', $limitedRun );
define( 'TESTMODE', $testMode );
define( 'DISABLEEDITS', $disableEdits );
define( 'USEWIKIDB', $useWikiDB );
define( 'WIKIHOST', $wikihost );
define( 'WIKIPORT', $wikiport );
define( 'WIKIUSER', $wikiuser );
define( 'WIKIPASS', $wikipass );
define( 'WIKIDB', $wikidb );
define( 'REVISIONTABLE', $revisiontable );
define( 'TEXTTABLE', $texttable );
define( 'PAGETABLE', $pagetable );
define( 'HOST', $host );
define( 'PORT', $port );
define( 'USER', $user );
define( 'PASS', $pass );
define( 'DB', $db );
define( 'CONSUMERKEY', $consumerKey );
define( 'CONSUMERSECRET', $consumerSecret );
if( !defined( 'USEWEBINTERFACE' ) || USEWEBINTERFACE != 1 ) define( 'ACCESSTOKEN', $accessToken );
if( !defined( 'USEWEBINTERFACE' ) || USEWEBINTERFACE != 1 ) define( 'ACCESSSECRET', $accessSecret );
define( 'ENABLEMAIL', $enableMail );
define( 'TO', $to );
define( 'FROM', $from );
define( 'GUIFROM', $guifrom );
define( 'ROOTURL', $guidomainroot );
define( 'LOGAPI', $enableAPILogging );
define( 'APICALL', $apiCall );
define( 'EXPECTEDRETURN', $expectedValue );
define( 'DECODEMETHOD', $decodeFunction );
define( 'WIKIRUNPAGEURL', $wikirunpageURL );
define( 'PARAMETERS', file_get_contents( 'Parser/paramlang.json', true ) );
define( 'USEADDITIONALSERVERS', $useCIDservers );
define( 'CIDSERVERS', implode( "\n", $cidServers ) );
define( 'CIDAUTHCODE', $cidAuthCode );
define( 'AUTOFPREPORT', $autoFPReport );
define( 'VERSION', "1.6.2" );
define( 'INTERFACEVERSION', "1.2.4" );
if( !defined( 'UNIQUEID' ) ) define( 'UNIQUEID', "" );
unset( $autoFPReport, $wikirunpageURL, $enableAPILogging, $apiCall, $expectedValue, $decodeFunction, $enableMail, $to, $from, $oauthURL, $accessSecret, $accessToken, $consumerSecret, $consumerKey, $db, $user, $pass, $port, $host, $texttable, $pagetable, $revisiontable, $wikidb, $wikiuser, $wikipass, $wikiport, $wikihost, $useWikiDB, $limitedRun, $testMode, $disableEdits, $debug, $runpage, $memoryFile, $taskname, $username, $nobots, $apiURL, $userAgent, $useCIDservers, $cidServers, $cidAuthCode );
