<?php

function validatePermission( $permission, $messageBox = true ) {
	global $userObject, $mainHTML;
	if( $userObject->validatePermission( $permission ) === false ) {
		if( $messageBox === true ) {
			$mainHTML->setMessageBox( "danger", "{{{permissionerror}}}", "{{{permissionerrormessage}}}" );
			$mainHTML->assignAfterElement( "userflag", $permission );
		}
		return false;
	}
	return true;
}

function validateChecksum() {
	global $loadedArguments, $oauthObject, $mainHTML;
	if( isset( $loadedArguments['checksum'] ) ) {
		if( $loadedArguments['checksum'] != $oauthObject->getChecksumToken() ) {
			$mainHTML->setMessageBox( "danger", "{{{checksumerrorheader}}}", "{{{checksumerrormessage}}}" );
			return false;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{checksumneededheader}}}", "{{{checksumneededmessage}}}" );
		return false;
	}
	$oauthObject->createChecksumToken();
	return true;
}

function validateNotBlocked() {
	global $mainHTML, $userObject;
	if( $userObject->isBlocked() === true ) {
		$mainHTML->setMessageBox( "danger", "{{{blockederror}}}", "{{{blockederrormessage}}}" );
		return false;
	}
	return true;
}

function validateToken() {
	global $loadedArguments, $oauthObject, $mainHTML;
	if( isset( $loadedArguments['token'] ) ) {
		if( $loadedArguments['token'] != $oauthObject->getCSRFToken() ) {
			$mainHTML->setMessageBox( "danger", "{{{tokenerrorheader}}}", "{{{tokenerrormessage}}}" );
			return false;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{tokenneededheader}}}", "{{{tokenneededmessage}}}" );
		return false;
	}
	return true;
}

function changeUserPermissions() {
	global $loadedArguments, $mainHTML, $userObject, $oauthObject, $userGroups, $dbObject;
	if( !validateToken() ) return false;
	if( !validatePermission( "changepermissions" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	if( $oauthObject->getUserID() == $loadedArguments['id'] ) $userObject2 = $userObject;
	else $userObject2 = new User( $dbObject, $oauthObject, $loadedArguments['id'] );
	if( is_null( $userObject2->getUsername() ) ) {
		$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{permissionuser404errormessage}}}" );
		return false;
	}

	$removedGroups = [];
	$addedGroups =[];
	$removedFlags =[];
	$addedFlags =[];

	foreach( $userGroups as $group=>$junk ) {
		$disabledChange = false;
		if( !isset( $loadedArguments[$group])) continue;
		if( ($userObject2->validateGroup( $group ) && (!$userObject2->validateGroup( $group, true ) || !in_array( $group, $userObject->getRemovableGroups() ))) || !in_array( $group, $userObject->getAddableGroups()) ) $disabledChange = true;
		if( !$userObject2->validateGroup( $group ) === true && $loadedArguments[$group] == "on" ) {
			if( $disabledChange === true ) {
				$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{permissionchangeoverstep}}}" );
				return false;
			} else {
				$addedGroups[] = $group;
			}
		}
		if( $userObject2->validateGroup( $group ) === true && $loadedArguments[$group] == "off" ) {
			if( $disabledChange === true ) {
				$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{permissionchangeoverstep}}}" );
				return false;
			} else {
				$removedGroups[] = $group;
			}
		}
	}

	foreach( $userObject->getAllFlags() as $flag ) {
		$disabledChange = false;
		if( !isset( $loadedArguments[$flag])) continue;
		if( ($userObject2->validatePermission( $flag ) && (!$userObject2->validatePermission( $flag, true ) || !in_array( $flag, $userObject->getRemovableFlags() ))) || !in_array( $flag, $userObject->getAddableFlags()) ) $disabledChange = true;
		if( !$userObject2->validatePermission( $flag ) === true && $loadedArguments[$flag] == "on" ) {
			if( $disabledChange === true ) {
				$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{permissionchangeoverstep}}}" );
				return false;
			} else {
				$addedFlags[] = $flag;
			}
		}
		if( $userObject2->validatePermission( $flag ) === true && $loadedArguments[$flag] == "off" ) {
			if( $disabledChange === true ) {
				$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{permissionchangeoverstep}}}" );
				return false;
			} else {
				$removedFlags[] = $flag;
			}
		}
	}

	if( $userObject2->getAssignedPermissions() == array_merge( array_diff( $userObject2->getAssignedPermissions(), array_merge( $removedFlags, $removedGroups ) ), $addedGroups, $addedFlags ) ) {
		return false;
	}

	if( !$dbObject->removeFlags( $userObject2->getUserID(), WIKIPEDIA, array_merge( $removedFlags, $removedGroups ) ) ) {
		$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{unknownerror}}}" );
		return false;
	}
	if( !$dbObject->addFlags( $userObject2->getUserID(), WIKIPEDIA, array_merge( $addedFlags, $addedGroups ) ) ) {
		$dbObject->addFlags( $userObject2->getUserID(), WIKIPEDIA, array_merge( $removedFlags, $removedGroups ) );
		$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{unknownerror}}}" );
		return false;
	}
	$dbObject->insertLogEntry( WIKIPEDIA, "permissionchange", "permissionchange", $userObject2->getUserID(), $userObject2->getUsername(), $userObject->getUserID(), serialize( $userObject2->getAssignedPermissions() ), serialize( array_merge( array_diff( $userObject2->getAssignedPermissions(), array_merge( $removedFlags, $removedGroups ) ), $addedGroups, $addedFlags ) ), $loadedArguments['reason'] );

	if( $oauthObject->getUserID() == $loadedArguments['id'] ) {
		$userObject = new User( $dbObject, $oauthObject );
	}

	$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{permissionchangesuccess}}}" );
	$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
	$userObject->setLastAction( time() );
	return true;
}

function toggleBlockStatus() {
	global $loadedArguments, $mainHTML, $userObject, $oauthObject, $dbObject;
	if( !validateToken() ) return false;
	if( !validateChecksum() ) return false;
	if( $oauthObject->getUserID() == $loadedArguments['id'] )  {
		if( $userObject->isBlocked() && !validatePermission( "unblockme" ) ) {
			return false;
		} elseif( $userObject->isBlocked() && $userObject->getBlockSource() == "wiki" ) {
			$mainHTML->setMessageBox( "danger", "{{{unblockerrorheader}}}", "{{{unblockwikierror}}}" );
			$mainHTML->assignAfterElement( "username", $userObject->getUsername() );
			return false;
		} elseif( $userObject->isBlocked() ) {
			$userObject->unblock();
			$dbObject->insertLogEntry( WIKIPEDIA, "block", "selfunblock", $userObject->getUserID(), $userObject->getUsername(), $userObject->getUserID(), null, null, $loadedArguments['reason'] );
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{unblockselfsuccess}}}" );
			$mainHTML->assignAfterElement( "username", $userObject->getUsername() );
			$userObject->setLastAction( time() );
			return true;
		}
	}
	if( !validateNotBlocked() ) return false;
	$userObject2 = new User( $dbObject, $oauthObject, $loadedArguments['id'] );
	if( is_null( $userObject2->getUsername() ) ) {
		$mainHTML->setMessageBox( "danger", "{{{blockerror}}}", "{{{block404errormessage}}}" );
		return false;
	}
	if( $userObject2->isBlocked() ) {
		if( $userObject2->getBlockSource() != "wiki" ) {
			if( validatePermission( "unblockuser" ) ) {
				$userObject2->unblock();
				$dbObject->insertLogEntry( WIKIPEDIA, "block", "unblock", $userObject2->getUserID(), $userObject2->getUsername(), $userObject->getUserID(), null, null, $loadedArguments['reason'] );
				$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{unblocksuccess}}}" );
				$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
				$userObject->setLastAction( time() );
				return true;
			} else return false;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{unblockerrorheader}}}", "{{{unblockwikierror}}}" );
			$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
			return false;
		}
	} else {
		if( validatePermission( "blockuser" ) ) {
			$userObject2->block();
			$dbObject->insertLogEntry( WIKIPEDIA, "block", "block", $userObject2->getUserID(), $userObject2->getUsername(), $userObject->getUserID(), null, null, $loadedArguments['reason'] );
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{blocksuccess}}}" );
			$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
			if( $oauthObject->getUserID() == $loadedArguments['id'] ) $userObject = $userObject2;
			$userObject->setLastAction( time() );
			return true;
		} else return false;
	}
}

function toggleFPStatus() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML;
	if( !validateToken() ) return false;
	if( !validatePermission( "changefpreportstatus" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	$res = $dbObject->queryDB( "SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id=externallinks_global.url_id WHERE `report_id` = '".$dbObject->sanitize( $loadedArguments['id'] )."';" );
	if( $result = mysqli_fetch_assoc( $res ) ) {
		if( $result['report_status'] == 0 ) {
			$res = $dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_status` = 2,`status_timestamp` = CURRENT_TIMESTAMP WHERE `report_id` = '".$dbObject->sanitize( $loadedArguments['id'] )."';" );
			if( $res === true ) {
				$userObject->setLastAction( time() );
				$dbObject->insertLogEntry( WIKIPEDIA, "fpreport", "decline", $result['url_id'], $result['url'], $userObject->getUserID(), null, null, "" );
				$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{fpdeclinesuccess}}}" );
				$mainHTML->assignAfterElement( "url", $result['url'] );
				return true;
			} else {
				$mainHTML->setMessageBox( "danger", "{{{fpstatuschangeerror}}}", "{{{fpdeclinefailure}}}" );
				return false;
			}
		}
		else {
			$res = $dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_status` = 0,`status_timestamp` = CURRENT_TIMESTAMP WHERE `report_id` = '".$dbObject->sanitize( $loadedArguments['id'] )."';" );
			if( $res === true ) {
				$userObject->setLastAction( time() );
				$dbObject->insertLogEntry( WIKIPEDIA, "fpreport", "open", $result['url_id'], $result['url'], $userObject->getUserID(), null, null, "" );
				$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{fpopensuccess}}}" );
				$mainHTML->assignAfterElement( "url", $result['url'] );
				return true;
			} else {
				$mainHTML->setMessageBox( "danger", "{{{fpstatuschangeerror}}}", "{{{fpopenfailure}}}" );
				return false;
			}
		}

	} else {
		$mainHTML->setMessageBox( "danger", "{{{fpstatuschangeerror}}}", "{{{fpreportmissing}}}" );
		return false;
	}
}

function runCheckIfDead() {
	global $dbObject, $userObject, $mainHTML;
	if( !validateToken() ) return false;
	if( !validatePermission( "fpruncheckifdeadreview" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();

	do {
		$res = $dbObject->queryDB( "SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id=externallinks_global.url_id WHERE `report_status` = '0';" );
		if( ($result = mysqli_fetch_all( $res, MYSQLI_ASSOC )) !== false ) {
			$toCheck = [];
			foreach( $result as $reportedFP ) {
				$toCheck[] = $reportedFP['url'];
			}
			$checkedResult = $checkIfDead->areLinksDead( $toCheck );
			foreach( $result as $reportedFP ) {
				if( $checkedResult[$reportedFP['url']] === false ) {
					$res = $dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_status` = 1,`status_timestamp` = CURRENT_TIMESTAMP WHERE `report_id` = '".$dbObject->sanitize( $reportedFP['report_id'] )."';" );
					if( $res === true ) {
						$dbObject->insertLogEntry( WIKIPEDIA, "fpreport", "fix", $reportedFP['url_id'], $reportedFP['url'], $userObject->getUserID(), null, null, "" );
					} else {
						$mainHTML->setMessageBox( "danger", "{{{fpcheckifdeaderror}}}", "{{{fpcheckifdeaderrormessage}}}" );
						return false;
					}
				}
			}
		} else {
			$mainHTML->setMessageBox( "danger", "{{{fpcheckifdeaderror}}}", "{{{fpcheckifdeaderrormessage}}}" );
			return false;
		}
	} while( $result !== false && count( $result ) >= 50 );

	$userObject->setLastAction( time() );
	$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{fpcheckifdeadsuccessmessage}}}" );
	return true;

}

function massChangeBQJobs() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML;
	if( !validateToken() ) return false;
	if( !validatePermission( "changemassbq" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	if( !isset( $loadedArguments['massaction'] ) ) {
		$mainHTML->setMessageBox( "danger", "{{{bqnoaction}}}", "{{{bqnoactionmessage}}}" );
		return false;
	}

	$sqlcheck = "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_status` IN ";
	switch( $loadedArguments['massaction'] ) {
		case "kill":
			$sql = "UPDATE externallinks_botqueue SET `queue_status`=3,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_status` IN (0,1,4);";
			$sqlcheck .= "(0,1,4);";
			break;
		case "suspend":
			$sql = "UPDATE externallinks_botqueue SET `queue_status`=4,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_status` IN (0,1);";
			$sqlcheck .= "(0,1);";
			break;
		case "unsuspend":
			$sql = "UPDATE externallinks_botqueue SET `queue_status`=0,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_status` IN (4);";
			$sqlcheck .= "(4);";
			break;
		default:
			$mainHTML->setMessageBox( "danger", "{{{bqinvalidaction}}}", "{{{bqinvalidactionmessage}}}" );
			return false;
	}
	$res = $dbObject->queryDB( $sqlcheck );
	if( ($result = mysqli_fetch_assoc( $res ) ) !== false ) {
		if( $result['count'] <= 0 ) return false;
	} else {
		$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{unknownerror}}}" );
		return false;
	}
	if( $dbObject->queryDB( $sql ) ) {
		$userObject->setLastAction( time() );
		$dbObject->insertLogEntry( WIKIPEDIA, "bqmasschange", $loadedArguments['massaction'], 0, "", $userObject->getUserID(), null, null, $loadedArguments['reason'] );
		$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{bqmasschange{$loadedArguments['massaction']}1}}}" );
		return true;
	}

	$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{unknownerror}}}" );
	return false;
}

function toggleBQStatus( $kill = false ) {
	global $loadedArguments, $dbObject, $userObject, $mainHTML;
	if( !validateToken() ) return false;
	if( !validatePermission( "changebqjob" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	if( !isset( $loadedArguments['id'] ) || empty( $loadedArguments['id'] ) ) {
		$mainHTML->setMessageBox( "danger", "{{{bqinvalidid}}}", "{{{bqinvalididmessage}}}" );
		return false;
	}
	$sql = "SELECT * FROM externallinks_botqueue WHERE `queue_id` = ".$dbObject->sanitize( $loadedArguments['id'] ).";";
	$res = $dbObject->queryDB( $sql );
	if( ($result = mysqli_fetch_assoc( $res ) ) !== false ){
		if( $result['queue_status'] == 0 || $result['queue_status'] == 1 || ($kill === true && $result['queue_status'] == 4) ) {
			if( $kill === false ) {
				$sql = "UPDATE externallinks_botqueue SET `queue_status` = 4 WHERE `queue_id` = ".$dbObject->sanitize( $loadedArguments['id'] ).";";
				$type = "suspend";
				$status = 4;
			}
			else {
				$sql = "UPDATE externallinks_botqueue SET `queue_status` = 3 WHERE `queue_id` = ".$dbObject->sanitize( $loadedArguments['id'] ).";";
				$type = "kill";
				$status = 3;
			}
		} elseif( $kill === false && $result['queue_status'] == 4) {
			$sql = "UPDATE externallinks_botqueue SET `queue_status` = 0 WHERE `queue_id` = ".$dbObject->sanitize( $loadedArguments['id'] ).";";
			$type = "unsuspend";
			$status = 0;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{bqstatuschangeerrormessage}}}" );
			return false;
		}
	}

	if( $dbObject->queryDB( $sql ) ) {
		$userObject->setLastAction( time() );
		$dbObject->insertLogEntry( WIKIPEDIA, "bqchangestatus", $type, $loadedArguments['id'], "", $userObject->getUserID(), $result['queue_status'], $status, "" );
		$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{bqchangestatus$type}}}" );
		$mainHTML->assignAfterElement( "logobject", $result['queue_id'] );
		return true;
	}

	$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{unknownerror}}}" );
	return false;
}

function reportFalsePositive() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML;
	if( !validateToken() ) return false;
	if( !validatePermission( "reportfp" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	if( isset( $_SESSION['precheckedfplistsrorted'] ) ) {

	}

	$schemelessURLRegex = '(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\/\?\#\[\]]+)*\/?(?:\?[^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';
	if( isset( $loadedArguments['fplist'] ) || empty( $loadedArguments['fplist'] ) ) {
		$urls = explode( "\n", $loadedArguments['fplist'] );
		foreach( $urls as $id=>$url ) {
			if( !preg_match( '/'.$schemelessURLRegex.'/i', $url, $garbage ) ) {
				unset( $urls[$id] );
			} else {
				$urls[$id] = $garbage[0];
			}
		}
		unset( $loadedArguments['fplist'] );
	} else {
		$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{nofpurlerror}}}" );
		return false;
	}

	$URLCache = [];
	$toReport = [];
	$toReset = [];
	$alreadyReported = [];
	$escapedURLs = [];
	foreach( $urls as $url ) {
		$escapedURLs[] = $dbObject->sanitize( $url );
	}
	$sql = "SELECT * FROM externallinks_global WHERE `url` IN ( '".implode( "', '", $escapedURLs )."' );";
	$res = $dbObject->queryDB( $sql );
	$notfound = array_flip($urls);
	while( $result = mysqli_fetch_assoc( $res ) ) {
		unset( $notfound[$result['url']] );
		$URLCache[$result['url']] = $result;
	}
	$notfound = array_flip( $notfound );
	$sql = "SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id WHERE `url` IN ( '".implode( "', '", $escapedURLs )."' ) AND `report_status` = 0;";
	$res = $dbObject->queryDB( $sql );
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$alreadyReported[] = $result['url'];
	}
	$urls = array_diff( $urls, $alreadyReported, $notfound );
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	$results = $checkIfDead->areLinksDead( $urls );
	foreach( $urls as $id=>$url ) {
		if( $results[$url] === false ) {
			$toReset[] = $url;
		} else {
			$toReport[] = $url;
		}
	}

	foreach( $toReport as $report ) {
		if( $dbObject->insertFPReport( WIKIPEDIA, $userObject->getUserID(), $URLCache[$report]['url_id'], CHECKIFDEADVERSION ) ) {
			$dbObject->insertLogEntry( WIKIPEDIA, "fpreport", "report", $URLCache[$report]['url_id'], $report, $userObject->getUserID() );
		} else {
			$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );
			return false;
		}
	}

	$escapedURLs = [];
	foreach( $toReset as $report ) {
		if( $URLCache[$report]['live_state'] != 0 ) {
			continue;
		} else {
			$escapedURLs[] = $URLCache[$report]['url_id'];
		}
	}
	if( !empty( $escapedURLs ) ) {
		$sql = "UPDATE externallinks_global SET `live_state` = 3 WHERE `url_id` IN ( ".implode( ", ", $escapedURLs )." );";
		if( $dbObject->queryDB( $sql ) ) {
			foreach( $toReset as $reset ) {
				$dbObject->insertLogEntry( WIKIPEDIA, "urldata", "changestate", $URLCache[$reset]['url_id'], $reset, $userObject->getUserID(), 0, 3 );
			}
		} else {
			$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );
			return false;
		}
	}

	$userObject->setLastAction( time() );
	$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{fpreportsuccess}}}" );
	return true;
}