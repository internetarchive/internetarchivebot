<?php
echo "Beginning update process\n";

//Establish root path
@define( 'IABOTROOT', dirname( __FILE__, 1 ) . DIRECTORY_SEPARATOR );
date_default_timezone_set( "UTC" );
ini_set( 'memory_limit', '256M' );

//Extend execution to 5 minutes
ini_set( 'max_execution_time', 300 );

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

@define( 'PUBLICHTML', dirname( __FILE__, 1 ) . DIRECTORY_SEPARATOR . $publicHTMLPath );

require_once( PUBLICHTML . "Includes/DB2.php" );

error_reporting( E_ALL );

ini_set( 'max_execution_time', 0 );

$dbObject = new DB2();

DB::createConfigurationTable();
$configuration = DB::getConfiguration( "global", "systemglobals" );

try {
	$versionSupport = DB::getConfiguration( 'global', 'versionData' );

	if( empty( $versionSupport['currentVersion'] ) ) {
		echo "Error: This appears to be a clean install.  This script is not needed.\n";
		exit( 1 );
	}

	echo "Performing necessary updates from v{$versionSupport['currentVersion']}\n";
} catch( Exception $e ) {
	echo "Error: This appears to be a clean install.  This script is not needed.\n";
	exit( 1 );
}

define( 'THROTTLECDXREQUESTS', true );
define( 'WIKIPEDIA', $configuration['defaultWiki'] );

DB::checkDB();
DB::checkDB( 'tarb' );

$accessibleWikis = DB::getConfiguration( "global", "systemglobals-allwikis" );

$queries = [];

if( $versionSupport['currentVersion'] < '2.0.9' ) {

	echo "This update will require updates to the table indices.  This may take several hours, or even days.\n";

	$queries[] = [
		'sql'              => "alter table " . SECONDARYDB . ".externallinks_userpreferences add user_email_runpage_status_global tinyint(1) default 0 null after user_email_fpreport;",
		'allowable_errors' => [ 1060 ],
		'local_tables'     => false,
		'message'          => "Updating user preferences table"
	];

	$queries[] = [
		'sql'              => [
			"drop index PAYWALLID on " . DB . ".externallinks_global;",
			"alter table " . DB . ".externallinks_global add constraint PAYWALLID foreign key (paywall_id) references " . DB . ".externallinks_paywall (paywall_id) on update cascade on delete cascade;"
		],
		'allowable_errors' => [
			[ 1091, 1553 ],
			[ 1005 ]
		],
		'local_tables'     => false,
		'message'          => "Updating global table"
	];

	$queries[] = [
		'sql'              => [
			"delete from " . DB . ".externallinks___WIKIPEDIA__ where url_id not in (select url_id from " . DB . ".externallinks_global);",
			"drop index URLID on " . DB . ".externallinks___WIKIPEDIA__;",
			"alter table " . DB . ".externallinks___WIKIPEDIA__ add constraint URLID___WIKIPEDIA__ foreign key (url_id) references " . DB . ".externallinks_global (url_id) on update cascade on delete cascade;"
		],
		'allowable_errors' => [
			[],
			[ 1091, 1553 ],
			[ 1005 ]
		],
		'local_tables'     => true,
		'message'          => "Updating local tables"
	];
	$queries[] = [
		'sql'              => [
			"drop index USER on " . SECONDARYDB . ".externallinks_botqueue;",
			"alter table " . SECONDARYDB . ".externallinks_botqueue add constraint USER_botqueue foreign key (queue_user) references " . SECONDARYDB . ".externallinks_user (user_link_id) on update cascade;",
			"drop index QUEUEID on " . SECONDARYDB . ".externallinks_botqueuepages;",
			"alter table " . SECONDARYDB . ".externallinks_botqueuepages add constraint QUEUEID_botqueuepages foreign key (queue_id) references " . SECONDARYDB . ".externallinks_botqueue (queue_id);"
		],
		'allowable_errors' => [
			[ 1091, 1553 ],
			[ 1005 ],
			[ 1091, 1553 ],
			[ 1005 ]
		],
		'local_tables'     => false,
		'message'          => "Updating bot queue tables"
	];
	$queries[] = [
		'sql'              => [
			"delete from " . SECONDARYDB . ".externallinks_fpreports where report_user_id = 0;",
			"drop index USER on " . SECONDARYDB . ".externallinks_fpreports;",
			"alter table " . SECONDARYDB . ".externallinks_fpreports add constraint URL_fpreports foreign key (report_url_id) references " . DB . ".externallinks_global (url_id) on update cascade on delete cascade;",
			"alter table " . SECONDARYDB . ".externallinks_fpreports add constraint USER_fpreports foreign key (report_user_id) references " . SECONDARYDB . ".externallinks_user (user_link_id) on update cascade;"
		],
		'allowable_errors' => [
			[],
			[ 1091, 1553 ],
			[ 1005 ],
			[ 1005 ]
		],
		'local_tables'     => false,
		'message'          => "Updating the false positive reports table"
	];
	$queries[] = [
		'sql'              => [
			"drop index URLID on " . SECONDARYDB . ".externallinks_scan_log;",
			"alter table " . SECONDARYDB . ".externallinks_scan_log add constraint URLID_scan_log foreign key (url_id) references " . DB . ".externallinks_global (url_id) on update cascade on delete cascade;"
		],
		'allowable_errors' => [
			[ 1091, 1553 ],
			[ 1005 ]
		],
		'local_tables'     => false,
		'message'          => "Updating scan log"
	];
	$queries[] = [
		'sql'              => [
			"drop index USERID on " . SECONDARYDB . ".externallinks_userflags;",
			"alter table " . SECONDARYDB . ".externallinks_userflags add constraint USERID_userflags foreign key (user_id) references " . SECONDARYDB . ".externallinks_user (user_link_id) on update cascade on delete cascade;",
			"delete from " . SECONDARYDB . ".externallinks_userlog where log_user NOT IN (select user_link_id from " . SECONDARYDB . ".externallinks_user);",
			"drop index LOGUSER on " . SECONDARYDB . ".externallinks_userlog;",
			"alter table " . SECONDARYDB . ".externallinks_userlog modify log_user int unsigned not null;",
			"alter table " . SECONDARYDB . ".externallinks_userlog add constraint LOGUSER_userlog foreign key (log_user) references " . SECONDARYDB . ".externallinks_user (user_link_id) on update cascade;",
			"alter table " . SECONDARYDB . ".externallinks_user add constraint USERID_user foreign key (user_link_id) references " . SECONDARYDB . ".externallinks_user (user_link_id) on update cascade on delete cascade;"
		],
		'allowable_errors' => [
			[ 1091, 1553 ],
			[ 1005 ],
			[],
			[ 1091, 1553 ],
			[],
			[ 1005 ],
			[],
			[ 1005 ]
		],
		'local_tables'     => false,
		'message'          => "Updating user tables"
	];
	$queries[] = [
		'sql'              => [
			"drop index ID on " . DB . ".books_collection_members;",
			"alter table " . DB . ".books_collection_members add constraint ID_collection_members foreign key (book_id) references " . DB . ".books_global (book_id) on update cascade on delete cascade;",
			"drop index ID on " . DB . ".books_isbn;",
			"alter table " . DB . ".books_isbn add constraint ID_isbn foreign key (book_id) references " . DB . ".books_global (book_id) on update cascade on delete cascade;"
		],
		'allowable_errors' => [
			[ 1091, 1553 ],
			[ 1005 ],
			[ 1091, 1553 ],
			[ 1005 ]
		],
		'local_tables'     => false,
		'message'          => "Updating book tables"
	];
	$queries[] = [
		'setVersion' => '2.0.9'
	];
}

foreach( $queries as $query ) {
	if( isset( $query['setVersion'] ) ) {
		DB::setConfiguration( 'global', 'versionData', 'updaterVersion', $query['setVersion'] );
		continue;
	}
	if( $query['local_tables'] ) {
		echo "{$query['message']}\n";
		$updateSQL = "show tables;";

		$res = $dbObject->queryDB( $updateSQL );
		while( $result = mysqli_fetch_assoc( $res ) ) {
			$strippedTableName = str_replace( 'externallinks_', '', $result['Tables_in_' . DB] );
			if( isset( $accessibleWikis[$strippedTableName] ) ) {
				echo "\tUpdating $strippedTableName...";

				$queryID = 0;
				do {
					if( !is_array( $query['sql'] ) ) $updateSQL =
						str_replace( '__WIKIPEDIA__', $strippedTableName, $query['sql'] );
					else $updateSQL = str_replace( '__WIKIPEDIA__', $strippedTableName, $query['sql'][$queryID] );

					$res2 = $dbObject->queryDB( $updateSQL );
					$error = $dbObject->getError();

					if( !is_array( $query['sql'] ) ) $allowableErrors = $query['allowable_errors'];
					else $allowableErrors = $query['allowable_errors'][$queryID];

					if( !$res2 && !in_array( $error, $allowableErrors ) ) {
						echo "Failed\nAn error occurred updating the bot.\n";
						exit( 1 );
					}
					$queryID++;
				} while( isset( $query['sql'][$queryID] ) );

				echo "Done\n";
			}
		}
	} else {
		echo "{$query['message']}...";

		$queryID = 0;
		do {
			if( !is_array( $query['sql'] ) ) $updateSQL = $query['sql'];
			else $updateSQL = $query['sql'][$queryID];

			$res = $dbObject->queryDB( $updateSQL );
			$error = $dbObject->getError();

			if( !is_array( $query['sql'] ) ) $allowableErrors = $query['allowable_errors'];
			else $allowableErrors = $query['allowable_errors'][$queryID];

			if( !$res && !in_array( $error, $allowableErrors ) ) {
				echo "Failed\nAn error occurred updating the bot.\n";
				exit( 1 );
			}
			$queryID++;
		} while( isset( $query['sql'][$queryID] ) );

		echo "Done\n";
	}
}

echo "Update script finished\n";