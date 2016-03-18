<?php 
	//Create a file in the same directory as this on named deadlink.config.local.inc.php and copy the stuff below.
	
	//Activate this to run the bot on a specific page(s) for debugging purposes.
	$debug = false;
	$limitedRun = false;
	$debugPage = array( 'title'=>"", 'pageid'=>0 );
	$debugStyle = 20;   //Use an int to run through a limited amount of articles.  Use "test" to run the test pages.

	//Set the bot's UA
	$userAgent = '';

	//Multithread settings.  Use this to speed up the bot's performance.  Do not use more than 50 workers.
	//This increases network bandwidth.  The programs speed will be limited by the CPU, or the bandwidth, whichever one is slower.
	$multithread = false;
	$workers = false;
	$workerLimit = 3;
	
	//Set Wiki to run on, define this before this gets called, to run on a different wiki.
	if( !defined( 'WIKIPEDIA' ) ) define( 'WIKIPEDIA', "enwiki" );
	
	//Progress memory file.  This allows the bot to resume where it left off in the event of a shutdown or a crash.
	$memoryFile = "";
	
	//Wiki connection setup.  Uses the defined constant WIKIPEDIA.
	switch( WIKIPEDIA ) {
		default:
		$apiURL = "https://en.wikipedia.org/w/api.php";
		$oauthURL = "https://en.wikipedia.org/w/index.php?title=Special:OAuth";
		$consumerKey = "";
		$consumerSecret = "";
		$accessToken = "";
		$accessSecret = "";
		$username = "";
		$runpage = "";
		$taskname = "";
		$nobots = false;
		break;
	}
	
	//IA Error Mailing List
	$enableMail = false;
	$to = "";
	$from = "";
	
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
	
	//Don't copy any of this below.
	if( file_exists( 'deadlink.config.local.inc.php' ) ) require_once( 'deadlink.config.local.inc.php' );
	require_once( 'API.php' );
	if( $multithread || $workers ) require_once( 'thread.php' );
	require_once( 'Parser/parse.php' );
	require_once( 'DB.php' );
	require_once( 'Core.php' );
	require_once( 'checkIfDead.php');
	if( file_exists( 'Parser/'.WIKIPEDIA.'.php' ) ) {
		require_once( 'Parser/'.WIKIPEDIA.'.php' );
		define( 'PARSERCLASS', WIKIPEDIA.'Parser' );
	} else {
		define( 'PARSERCLASS', 'Parser' );
		echo "ERROR: Unable to load local wiki parsing library.\nTerminating application...";
		exit( 40000 );
	}
	define( 'USERAGENT', $userAgent );
	define( 'COOKIE', $username.WIKIPEDIA.$taskname );
	define( 'API', $apiURL );
	define( 'OAUTH', $oauthURL );
	define( 'NOBOTS', $nobots );
	define( 'USERNAME', $username );
	define( 'TASKNAME', $taskname );
	define( 'IAPROGRESS', $memoryFile );
	define( 'RUNPAGE', $runpage );
	define( 'MULTITHREAD', $multithread );
	define( 'WORKERS', $workers );
	define( 'DEBUG', $debug );
	define( 'LIMITEDRUN', $limitedRun );
	define( 'USEWIKIDB', $useWikiDB );
	define( 'WIKIHOST', $wikihost );
	define( 'WIKIPORT', $wikiport );
	define( 'WIKIUSER', $wikiuser );
	define( 'WIKIPASS', $wikipass );
	define( 'WIKIDB', $wikidb );
	define( 'REVISIONTABLE', $revisiontable );
	define( 'TEXTTABLE', $texttable );
	define( 'HOST', $host );
	define( 'PORT', $port );
	define( 'USER', $user );
	define( 'PASS', $pass );
	define( 'DB', $db );
	define( 'CONSUMERKEY', $consumerKey );
	define( 'CONSUMERSECRET', $consumerSecret );
	define( 'ACCESSTOKEN', $accessToken );
	define( 'ACCESSSECRET', $accessSecret );
	define( 'ENABLEMAIL', $enableMail );
	define( 'TO', $to );
	define( 'FROM', $from );
	unset( $enableMail, $to, $from, $oauthURL, $accessSecret, $accessToken, $consumerSecret, $consumerKey, $db, $user, $pass, $port, $host, $texttable, $revisiontable, $wikidb, $wikiuser, $wikipass, $wikiport, $wikihost, $useWikiDB, $limitedRun, $debug, $workers, $multithread, $runpage, $memoryFile, $taskname, $username, $nobots, $apiURL, $userAgent );
?>
