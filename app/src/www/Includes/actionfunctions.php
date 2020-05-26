<?php
function validatePermission( $permission, $messageBox = true, &$jsonOut = false ) {
	global $userObject, $mainHTML, $userGroups;
	if( $userObject->validatePermission( $permission ) === false ) {
		header( "HTTP/1.1 403 Forbidden", true, 403 );
		if( $messageBox === true && $jsonOut === false ) {
			$mainHTML->setMessageBox( "danger", "{{{permissionerror}}}", "{{{permissionerrormessage}}}" );
			$mainHTML->assignAfterElement( "userflag", $permission );
		} elseif( $jsonOut !== false ) {
			$getInherit = [];
			$groupList = [];
			foreach( $userGroups as $group => $details ) {
				if( in_array( $permission, $details['inheritsflags'] ) ) {
					$groupList[] = $group;
					$getInherit[] = $group;
				}
			}
			$repeat = true;
			while( $repeat === true ) {
				$repeat = false;
				foreach( $userGroups as $group => $details ) {
					foreach( $getInherit as $tgroup ) {
						if( !in_array( $group, $groupList ) && in_array( $tgroup, $details['inheritsgroups'] ) ) {
							$groupList[] = $group;
							$getInherit[] = $group;
							$repeat = true;
						}
					}
				}
			}
			$jsonOut['missingpermission'] = $permission;
			$jsonOut['accessibletogroups'] = $groupList;
		}

		return false;
	}

	return true;
}

function mailHTML( $to, $subject, $body, $highpriority = false ) {
	$headers = [];
	$headers[] = "From: " . GUIFROM;
	$headers[] = "Reply-To: <>";
	$headers[] = "X-Mailer: PHP/" . phpversion();
	$headers[] = "Useragent: " . USERAGENT;
	$headers[] = "X-Accept-Language: en-us, en";
	$headers[] = "MIME-Version: 1.0";
	$headers[] = "Content-Type: text/html; charset=UTF-8";
	if( $highpriority === true ) {
		$headers[] = "X-Priority: 2 (High)";
		$headers[] = "X-MSMail-Priority: High";
		$headers[] = "Importance: High";
	}

	return mail( $to, $subject, $body, implode( "\r\n", $headers ) );
}

function validateChecksum( &$jsonOut = false ) {
	global $loadedArguments, $oauthObject, $mainHTML;
	if( isset( $loadedArguments['checksum'] ) ) {
		if( $loadedArguments['checksum'] != $oauthObject->getChecksumToken() ) {
			header( "HTTP/1.1 409 Conflict", true, 409 );
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{checksumerrorheader}}}",
			                                                   "{{{checksumerrormessage}}}"
			);
			else {
				$jsonOut['requesterror'] = "invalidchecksum";
				$jsonOut['errormessage'] = "The checksum provided for this request is invalid.";
			}

			return false;
		}
	} else {
		header( "HTTP/1.1 428 Precondition Required", true, 428 );
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{checksumneededheader}}}",
		                                                   "{{{checksumneededmessage}}}"
		);
		else {
			$jsonOut['requesterror'] = "missingchecksum";
			$jsonOut['errormessage'] = "A checksum is needed to fulfill this request.";
		}

		return false;
	}
	$oauthObject->createChecksumToken();

	return true;
}

function validateNotBlocked( &$jsonOut = false ) {
	global $mainHTML, $userObject;
	if( $userObject->isBlocked() === true ) {
		header( "HTTP/1.1 403 Blocked", true, 403 );
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{blockederror}}}", "{{{blockederrormessage}}}"
		);
		else {
			$jsonOut['requesterror'] = "blocked";
			$jsonOut['errormessage'] = "You are blocked from using the interface and API.";
		}

		return false;
	}

	return true;
}

function validateToken( &$jsonOut = false ) {
	global $loadedArguments, $oauthObject, $mainHTML;
	if( isset( $loadedArguments['token'] ) ) {
		if( $loadedArguments['token'] != $oauthObject->getCSRFToken() ) {
			header( "HTTP/1.1 412 Precondition Failed", true, 412 );
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{tokenerrorheader}}}",
			                                                   "{{{tokenerrormessage}}}"
			);
			else {
				$jsonOut['requesterror'] = "invalidtoken";
				$jsonOut['errormessage'] = "The CSRF token provided is invalid.";
			}

			return false;
		}
	} else {
		header( "HTTP/1.1 428 Precondition Required", true, 428 );
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{tokenneededheader}}}",
		                                                   "{{{tokenneededmessage}}}"
		);
		else {
			$jsonOut['requesterror'] = "missingtoken";
			$jsonOut['errormessage'] = "A CSRF token is needed to fulfill this request.";
		}

		return false;
	}

	return true;
}

function changeUserPermissions() {
	global $loadedArguments, $mainHTML, $userObject, $oauthObject, $userGroups, $dbObject, $accessibleWikis;
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
	$wiki = WIKIPEDIA;
	if( isset( $loadedArguments['assignglobally'] ) && $loadedArguments['assignglobally'] == "on" ) {
		if( !validatePermission( "changeglobalpermissions" ) ) return false;
		else {
			$wiki = "global";
		}
	}
	$removedGroups = [];
	$addedGroups = [];
	$removedFlags = [];
	$addedFlags = [];
	foreach( $userGroups as $group => $junk ) {
		$disabledChange = false;
		if( !isset( $loadedArguments[$group] ) ) continue;
		if( ( $userObject2->validateGroup( $group ) && ( !$userObject2->validateGroup( $group, true ) ||
		                                                 !in_array( $group, $userObject->getRemovableGroups() ) ) ) ||
		    !in_array( $group, $userObject->getAddableGroups() )
		) $disabledChange = true;
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
		if( !isset( $loadedArguments[$flag] ) ) continue;
		if( ( $userObject2->validatePermission( $flag ) && ( !$userObject2->validatePermission( $flag, true ) ||
		                                                     !in_array( $flag, $userObject->getRemovableFlags() ) ) ) ||
		    !in_array( $flag, $userObject->getAddableFlags() )
		) $disabledChange = true;
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
	foreach( $removedFlags as $flag ) {
		if( $userObject2->validatePermission( $flag, true, true ) && $wiki != "global" ) {
			$removedFlags = array_diff( $removedFlags, [ $flag ] );
		}
	}
	foreach( $removedGroups as $group ) {
		if( $userObject2->validateGroup( $group, true, true ) && $wiki != "global" ) {
			$removedGroups = array_diff( $removedGroups, [ $group ] );
		}
	}
	if( $userObject2->getAssignedPermissions() ==
	    array_merge( array_diff( $userObject2->getAssignedPermissions(), array_merge( $removedFlags, $removedGroups ) ),
	                 $addedGroups, $addedFlags
	    )
	) {
		return false;
	}
	if( !$dbObject->removeFlags( $userObject2->getUserLinkID(), $wiki, array_merge( $removedFlags, $removedGroups )
	)
	) {
		$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{unknownerror}}}" );

		return false;
	}
	if( !$dbObject->addFlags( $userObject2->getUserLinkID(), $wiki, array_merge( $addedFlags, $addedGroups ) ) ) {
		$dbObject->addFlags( $userObject2->getUserLinkID(), $wiki, array_merge( $removedFlags, $removedGroups ) );
		$mainHTML->setMessageBox( "danger", "{{{permissionchangeerror}}}", "{{{unknownerror}}}" );

		return false;
	}
	$dbObject->insertLogEntry( $wiki, WIKIPEDIA, "permissionchange",
	                           "permissionchange" . ( $wiki == "global" ? "global" : "" ),
	                           $userObject2->getUserLinkID(),
	                           $userObject2->getUsername(), $userObject->getUserLinkID(),
	                           serialize( $userObject2->getAssignedPermissions() ),
	                           serialize( array_merge( array_diff( $userObject2->getAssignedPermissions(),
	                                                               array_merge( $removedFlags, $removedGroups )
	                                                   ), $addedGroups, $addedFlags
	                                      )
	                           ), $loadedArguments['reason']
	);
	if( $oauthObject->getUserID() == $loadedArguments['id'] ) {
		$userObject = new User( $dbObject, $oauthObject );
	}

	if( $userObject2->getEmailPermissionsStatus() === true && $userObject2->hasEmail() == true ) {
		$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
		$body = ( $wiki == "global" ? "{{{permissionschangeglobal}}}:" : "{{{permissionschange}}}:" ) . "<br><br>\n";
		$body .= "{{{permissionsaddedgroups}}}:<br><ul>\n";
		if( !empty( $addedGroups ) ) foreach( $addedGroups as $group ) {
			$body .= "<li>$group</li>\n";
		} else {
			$body .= "&mdash;";
		}
		$body .= "</ul><br>\n";
		$body .= "{{{permissionsaddedflags}}}:<br><ul>\n";
		if( !empty( $addedFlags ) ) foreach( $addedFlags as $flag ) {
			$body .= "<li>$flag - {{{$flag}}}</li>\n";
		} else {
			$body .= "&mdash;";
		}
		$body .= "</ul><br>\n";
		$body .= "{{{permissionsremovedgroups}}}:<br><ul>\n";
		if( !empty( $removedGroups ) ) foreach( $removedGroups as $group ) {
			$body .= "<li>$group</li>\n";
		} else {
			$body .= "&mdash;";
		}
		$body .= "</ul><br>\n";
		$body .= "{{{permissionsremovedflags}}}:<br><ul>\n";
		if( !empty( $removedFlags ) ) foreach( $removedFlags as $flag ) {
			$body .= "<li>$flag - {{{$flag}}}</li>\n";
		} else {
			$body .= "&mdash;";
		}
		$body .= "</ul><br>{{{emailreason}}}: <i>" . htmlspecialchars( $loadedArguments['reason'] ) . "</i>\n";

		$bodyObject = new HTMLLoader( $body, $userObject2->getLanguage() );
		$bodyObject->assignAfterElement( "locale",
		                                 "{{{" . $accessibleWikis[WIKIPEDIA]['i18nsourcename'] . WIKIPEDIA . "name}}}"
		);
		$bodyObject->assignAfterElement( "actionuserlink",
		                                 ROOTURL . "index.php?page=user&id=" . $userObject->getUserID() . "&wiki=" .
		                                 WIKIPEDIA
		);
		$bodyObject->assignAfterElement( "actionuser", $userObject->getUsername() );
		$bodyObject->finalize();
		$subjectObject = new HTMLLoader( "{{{permissionssubject}}}", $userObject2->getLanguage() );
		$subjectObject->finalize();
		$mailObject->assignElement( "body", $bodyObject->getLoadedTemplate() );
		$mailObject->finalize();
		mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(), $mailObject->getLoadedTemplate() );
	}

	$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{permissionchangesuccess}}}" );
	$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
	$userObject->setLastAction( time() );

	return true;
}

function toggleBlockStatus() {
	global $loadedArguments, $mainHTML, $userObject, $oauthObject, $dbObject, $accessibleWikis;
	if( !validateToken() ) return false;
	if( !validateChecksum() ) return false;
	if( $userObject->getUserID() == $loadedArguments['id'] ) {
		if( $userObject->isBlocked() && !validatePermission( "unblockme" ) ) {
			return false;
		} elseif( $userObject->isBlocked() && $userObject->getBlockSource() == "wiki" ) {
			$mainHTML->setMessageBox( "danger", "{{{unblockerrorheader}}}", "{{{unblockwikierror}}}" );
			$mainHTML->assignAfterElement( "username", $userObject->getUsername() );

			return false;
		} elseif( $userObject->isBlocked() ) {
			$userObject->unblock();
			$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "block", "selfunblock", $userObject->getUserLinkID(),
			                           $userObject->getUsername(), $userObject->getUserLinkID(), null, null,
			                           $loadedArguments['reason']
			);
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
				$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "block", "unblock", $userObject2->getUserLinkID(),
				                           $userObject2->getUsername(), $userObject->getUserLinkID(), null, null,
				                           $loadedArguments['reason']
				);
				if( $userObject2->getEmailBlockStatus() === true && $userObject2->hasEmail() == true ) {
					$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
					$body = "{{{unblockemail}}}<br>\n";
					$body .= "{{{emailreason}}}: <i>" . htmlspecialchars( $loadedArguments['reason'] ) . "</i>\n";
					$bodyObject = new HTMLLoader( $body, $userObject2->getLanguage() );
					$bodyObject->assignAfterElement( "locale",
					                                 "{{{" . $accessibleWikis[WIKIPEDIA]['i18nsourcename'] . WIKIPEDIA .
					                                 "name}}}"
					);
					$bodyObject->assignAfterElement( "actionuserlink",
					                                 ROOTURL . "index.php?page=user&id=" . $userObject->getUserID() .
					                                 "&wiki=" .
					                                 WIKIPEDIA
					);
					$bodyObject->assignAfterElement( "actionuser", $userObject->getUsername() );
					$bodyObject->finalize();
					$subjectObject = new HTMLLoader( "{{{unblocksubject}}}", $userObject2->getLanguage() );
					$subjectObject->finalize();
					$mailObject->assignElement( "body", $bodyObject->getLoadedTemplate() );
					$mailObject->finalize();
					mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(),
					          $mailObject->getLoadedTemplate()
					);
				}
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
			$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "block", "block", $userObject2->getUserLinkID(),
			                           $userObject2->getUsername(), $userObject->getUserLinkID(), null, null,
			                           $loadedArguments['reason']
			);
			if( $userObject2->getEmailBlockStatus() === true && $userObject2->hasEmail() == true ) {
				$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
				$body = "{{{blockemail}}}<br>\n";
				$body .= "{{{emailreason}}}: <i>" . htmlspecialchars( $loadedArguments['reason'] ) . "</i>\n";
				$bodyObject = new HTMLLoader( $body, $userObject2->getLanguage() );
				$bodyObject->assignAfterElement( "locale",
				                                 "{{{" . $accessibleWikis[WIKIPEDIA]['i18nsourcename'] . WIKIPEDIA .
				                                 "name}}}"
				);
				$bodyObject->assignAfterElement( "actionuserlink",
				                                 ROOTURL . "index.php?page=user&id=" . $userObject->getUserID() .
				                                 "&wiki=" .
				                                 WIKIPEDIA
				);
				$bodyObject->assignAfterElement( "actionuser", $userObject->getUsername() );
				$bodyObject->finalize();
				$subjectObject = new HTMLLoader( "{{{blocksubject}}}", $userObject2->getLanguage() );
				$subjectObject->finalize();
				$mailObject->assignElement( "body", $bodyObject->getLoadedTemplate() );
				$mailObject->finalize();
				mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(),
				          $mailObject->getLoadedTemplate()
				);
			}
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{blocksuccess}}}" );
			$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
			if( $oauthObject->getUserID() == $loadedArguments['id'] ) $userObject = $userObject2;
			$userObject->setLastAction( time() );

			return true;
		} else return false;
	}
}

function toggleFPStatus() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject, $accessibleWikis;
	if( !validateToken() ) return false;
	if( !validatePermission( "changefpreportstatus" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	$res =
		$dbObject->queryDB( "SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id=externallinks_global.url_id LEFT JOIN externallinks_user ON externallinks_fpreports.report_user_id=externallinks_user.user_link_id WHERE `report_id` = '" .
		                    $dbObject->sanitize( $loadedArguments['id'] ) . "';"
		);
	if( $result = mysqli_fetch_assoc( $res ) ) {
		if( $result['report_status'] == 0 ) {
			$res =
				$dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_status` = 2,`status_timestamp` = CURRENT_TIMESTAMP WHERE `report_id` = '" .
				                    $dbObject->sanitize( $loadedArguments['id'] ) . "';"
				);
			if( $res === true ) {
				$userObject->setLastAction( time() );
				$dbObject->insertLogEntry( "global", WIKIPEDIA, "fpreport", "decline", $result['url_id'],
				                           $result['url'],
				                           $userObject->getUserLinkID(), null, null, ""
				);
				$userObject2 = new User( $dbObject, $oauthObject, $result['user_id'], $result['wiki'] );
				if( $userObject2->getEmailFPDeclined() === true && $userObject2->hasEmail() == true ) {
					$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
					$body = "{{{fpdeclined}}}";
					$bodyObject = new HTMLLoader( $body, $userObject2->getLanguage() );
					$bodyObject->assignAfterElement( "locale",
					                                 "{{{" . $accessibleWikis[WIKIPEDIA]['i18nsourcename'] . WIKIPEDIA .
					                                 "name}}}"
					);
					$bodyObject->assignAfterElement( "actionuserlink",
					                                 ROOTURL . "index.php?page=user&id=" . $userObject->getUserID() .
					                                 "&wiki=" .
					                                 WIKIPEDIA
					);
					$bodyObject->assignAfterElement( "actionuser", $userObject->getUsername() );
					$bodyObject->assignAfterElement( "htmlurl", htmlspecialchars( $result['url'] ) );
					$bodyObject->assignAfterElement( "url", $result['url'] );
					$bodyObject->finalize();
					$subjectObject = new HTMLLoader( "{{{fpreportsubject}}}", $userObject2->getLanguage() );
					$subjectObject->finalize();
					$mailObject->assignElement( "body", $bodyObject->getLoadedTemplate() );
					$mailObject->finalize();
					mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(),
					          $mailObject->getLoadedTemplate()
					);
				}
				$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{fpdeclinesuccess}}}" );
				$mainHTML->assignAfterElement( "url", $result['url'] );

				return true;
			} else {
				$mainHTML->setMessageBox( "danger", "{{{fpstatuschangeerror}}}", "{{{fpdeclinefailure}}}" );

				return false;
			}
		} else {
			$res =
				$dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_status` = 0,`status_timestamp` = CURRENT_TIMESTAMP WHERE `report_id` = '" .
				                    $dbObject->sanitize( $loadedArguments['id'] ) . "';"
				);
			if( $res === true ) {
				$userObject->setLastAction( time() );
				$dbObject->insertLogEntry( "global", WIKIPEDIA, "fpreport", "open", $result['url_id'], $result['url'],
				                           $userObject->getUserLinkID(), null, null, ""
				);
				$userObject2 = new User( $dbObject, $oauthObject, $result['user_id'], $result['wiki'] );
				if( $userObject2->getEmailFPOpened() === true && $userObject2->hasEmail() == true ) {
					$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
					$body = "{{{fpopened}}}";
					$bodyObject = new HTMLLoader( $body, $userObject2->getLanguage() );
					$bodyObject->assignAfterElement( "locale",
					                                 "{{{" . $accessibleWikis[WIKIPEDIA]['i18nsourcename'] . WIKIPEDIA .
					                                 "name}}}"
					);
					$bodyObject->assignAfterElement( "actionuserlink",
					                                 ROOTURL . "index.php?page=user&id=" . $userObject->getUserID() .
					                                 "&wiki=" .
					                                 WIKIPEDIA
					);
					$bodyObject->assignAfterElement( "actionuser", $userObject->getUsername() );
					$bodyObject->assignAfterElement( "htmlurl", htmlspecialchars( $result['url'] ) );
					$bodyObject->assignAfterElement( "url", $result['url'] );
					$bodyObject->finalize();
					$subjectObject = new HTMLLoader( "{{{fpreportsubject}}}", $userObject2->getLanguage() );
					$subjectObject->finalize();
					$mailObject->assignElement( "body", $bodyObject->getLoadedTemplate() );
					$mailObject->finalize();
					mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(),
					          $mailObject->getLoadedTemplate()
					);
				}
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
	global $dbObject, $userObject, $mainHTML, $loadedArguments, $oauthObject;
	if( !validateToken() ) return false;
	if( !validatePermission( "fpruncheckifdeadreview" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	$sql =
		"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id=externallinks_global.url_id LEFT JOIN externallinks_user ON externallinks_fpreports.report_user_id=externallinks_user.user_link_id AND externallinks_fpreports.wiki=externallinks_user.wiki LEFT JOIN externallinks_paywall on externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `report_status` = '0';";
	$res = $dbObject->queryDB( $sql );
	if( ( $result = mysqli_fetch_all( $res, MYSQLI_ASSOC ) ) !== false ) {
		$mailinglist = [];
		$alreadyReset = [];
		do {
			$toCheck = [];
			$counter = 0;
			foreach( $result as $id => $reportedFP ) {
				$counter++;
				if( $reportedFP['paywall_status'] != 3 && $reportedFP['live_state'] != 7 ) $toCheck[] =
					$reportedFP['url'];
				if( $counter >= 50 ) break;
			}
			$checkedResult = $checkIfDead->areLinksDead( $toCheck );
			$errors = $checkIfDead->getErrors();
			$counter = 0;
			$escapedURLs = [];
			foreach( $result as $reportedFP ) {
				$counter++;
				if( $reportedFP['paywall_status'] == 3 || $reportedFP['live_state'] == 7 ||
				    $checkedResult[$reportedFP['url']] === false
				) {
					if( $reportedFP['paywall_status'] != 3 && $reportedFP['live_state'] != 7 ) {
						if( !in_array( $reportedFP['paywall_id'], $alreadyReset ) ) {
							$escapedURLs[] = $reportedFP['paywall_id'];
							$alreadyReset[] = $reportedFP['paywall_id'];
						}
					}
				}
				if( $counter >= 50 ) break;
			}
			if( !empty( $escapedURLs ) ) {
				$sql = "UPDATE externallinks_global SET `live_state` = 3 WHERE `paywall_id` IN ( " .
				       implode( ", ", $escapedURLs ) . " );";
				if( !$dbObject->queryDB( $sql ) ) {
					$mainHTML->setMessageBox( "danger", "{{{fpcheckifdeaderror}}}", "{{{unknownerror}}}" );

					return false;
				}
			}
			$counter = 0;
			foreach( $result as $reportedFP ) {
				$counter++;
				unset( $result[$id] );
				if( $reportedFP['paywall_status'] == 3 || $reportedFP['live_state'] == 7 ||
				    $reportedFP['live_state'] == 3 ||
				    $checkedResult[$reportedFP['url']] === false
				) {
					$res =
						$dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_status` = 1,`status_timestamp` = CURRENT_TIMESTAMP WHERE `report_id` = '" .
						                    $dbObject->sanitize( $reportedFP['report_id'] ) . "';"
						);
					if( $res === true ) {
						$dbObject->insertLogEntry( "global", WIKIPEDIA, "fpreport", "fix", $reportedFP['url_id'],
						                           $reportedFP['url'], $userObject->getUserLinkID(), null, null, ""
						);
						if( !isset( $mailinglist[$reportedFP['user_id'] . $reportedFP['wiki']] ) ) {
							$mailinglist[$reportedFP['user_id'] . $reportedFP['wiki']]['userobject'] =
								new User( $dbObject, $oauthObject, $reportedFP['user_id'], $reportedFP['wiki'] );
						}
						$mailinglist[$reportedFP['user_id'] . $reportedFP['wiki']]['urls'][] = $reportedFP['url'];
					} else {
						$mainHTML->setMessageBox( "danger", "{{{fpcheckifdeaderror}}}",
						                          "{{{fpcheckifdeaderrormessage}}}"
						);

						return false;
					}
				} else {
					$res = $dbObject->queryDB( "UPDATE externallinks_fpreports SET `report_version` = '" .
					                           CHECKIFDEADVERSION .
					                           "',`status_timestamp` = CURRENT_TIMESTAMP,`report_error` = '" .
					                           $dbObject->sanitize( $errors[$reportedFP['url']] ) .
					                           "' WHERE `report_id` = '" .
					                           $dbObject->sanitize( $reportedFP['report_id'] ) . "';"
					);
					if( $res !== true ) {
						$mainHTML->setMessageBox( "danger", "{{{fpcheckifdeaderror}}}",
						                          "{{{fpcheckifdeaderrormessage}}}"
						);

						return false;
					}
				}
				if( $counter >= 50 ) break;
			}
		} while( count( $toCheck ) >= 50 );
	} else {
		$mainHTML->setMessageBox( "danger", "{{{fpcheckifdeaderror}}}", "{{{fpcheckifdeaderrormessage}}}" );

		return false;
	}
	if( !empty( $mailinglist ) ) foreach( $mailinglist as $id => $stuff ) {
		$userObject2 = $stuff['userobject'];
		$urls = $stuff['urls'];
		if( $userObject2->hasEmail() && $userObject2->getEmailFPFixed() ) {
			$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
			$body = "{{{fpfixed}}}:<br><ul>\n";
			foreach( $urls as $url ) {
				$body .= "<li><a href=\"$url\">" . htmlspecialchars( $url ) . "</a></li>\n";
			}
			$body .= "</ul>";
			$mailObject->assignElement( "body", $body );
			$mailObject->finalize();
			$subjectObject = new HTMLLoader( "{{{fpreportsubject}}}", $userObject2->getLanguage() );
			$subjectObject->finalize();
			mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(), $mailObject->getLoadedTemplate() );
		}
	}
	$userObject->setLastAction( time() );
	$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{fpcheckifdeadsuccessmessage}}}" );

	return true;
}

function massChangeBQJobs() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject, $accessibleWikis;
	if( !validateToken() ) return false;
	if( !validatePermission( "changemassbq" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	if( !isset( $loadedArguments['massaction'] ) ) {
		$mainHTML->setMessageBox( "danger", "{{{bqnoaction}}}", "{{{bqnoactionmessage}}}" );

		return false;
	}
	$sqlcheck =
		"SELECT * FROM externallinks_botqueue LEFT JOIN externallinks_user ON externallinks_botqueue.wiki=externallinks_user.wiki AND externallinks_botqueue.queue_user=externallinks_user.user_link_id LEFT JOIN externallinks_userpreferences ON externallinks_user.user_link_id=externallinks_userpreferences.user_link_id WHERE `queue_status` IN ";
	switch( $loadedArguments['massaction'] ) {
		case "kill":
			$sql =
				"UPDATE externallinks_botqueue SET `queue_status`=3,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_status` IN (0,1,4);";
			$sqlcheck .= "(0,1,4);";
			break;
		case "suspend":
			$sql =
				"UPDATE externallinks_botqueue SET `queue_status`=4,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_status` IN (0,1);";
			$sqlcheck .= "(0,1);";
			break;
		case "unsuspend":
			$sql =
				"UPDATE externallinks_botqueue SET `queue_status`=0,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_status` IN (4);";
			$sqlcheck .= "(4);";
			break;
		default:
			$mainHTML->setMessageBox( "danger", "{{{bqinvalidaction}}}", "{{{bqinvalidactionmessage}}}" );

			return false;
	}
	$res = $dbObject->queryDB( $sqlcheck );
	$count = 0;
	$mailinglist = [];
	while( $result = mysqli_fetch_assoc( $res ) ) {
		if( isset( $mailinglist[$result['user_id'] . $result['wiki']] ) ) {
			$mailinglist[$result['user_id'] . $result['wiki']]['userobject'] =
				new User( $dbObject, $oauthObject, $result['user_id'], $result['wiki'] );
		}
		$mailinglist[$result['user_id'] . $result['wiki']]['jobs'][] = $result['queue_id'];
		$count++;

	}
	if( $result === false ) {
		$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{unknownerror}}}" );

		return false;
	}
	if( $count <= 0 ) return false;
	if( $dbObject->queryDB( $sql ) ) {
		$userObject->setLastAction( time() );
		$dbObject->insertLogEntry( "global", WIKIPEDIA, "bqmasschange", $loadedArguments['massaction'], 0, "",
		                           $userObject->getUserLinkID(), null, null, $loadedArguments['reason']
		);
		$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{bqmasschange{$loadedArguments['massaction']}1}}}"
		);
		if( !empty( $mailinglist ) ) foreach( $mailinglist as $id => $stuff ) {
			$userObject2 = $stuff['userobject'];
			$jobs = $stuff['jobs'];
			if( $userObject2->hasEmail() &&
			    ( ( $userObject2->getEmailBQKilled() && $loadedArguments['massaction'] == "kill" ) ||
			      ( $userObject2->getEmailBQSuspended() && $loadedArguments['massaction'] == "suspend" ) ||
			      ( $userObject2->getEmailBQUnsuspended() && $loadedArguments['massaction'] == "unsuspend" ) )
			) {
				$mailObject = new HTMLLoader( "emailmain", $userObject2->getLanguage() );
				$body = "{{{bqemail{$loadedArguments['massaction']}}}}:<br><ul>\n";
				foreach( $jobs as $job ) {
					$body .= "<li><a href=\"" . ROOTURL . "index.php?page=viewjob&id=$job\">" .
					         htmlspecialchars( $job ) . "</a></li>\n";
				}
				$body .= "</ul><br>\n";
				$body .= "{{{emailreason}}}: <i>" . htmlspecialchars( $loadedArguments['reason'] ) . "</i>\n";
				$bodyObject = new HTMLLoader( $body, $userObject2->getLanguage() );
				$bodyObject->assignAfterElement( "locale",
				                                 "{{{" . $accessibleWikis[$userObject2->getWiki()]['i18nsourcename'] .
				                                 $userObject2->getWiki() . "name}}}"
				);
				$bodyObject->assignAfterElement( "actionuserlink",
				                                 ROOTURL . "index.php?page=user&id=" . $userObject->getUserID() .
				                                 "&wiki=" .
				                                 WIKIPEDIA
				);
				$bodyObject->assignAfterElement( "actionuser", $userObject->getUsername() );
				$bodyObject->finalize();
				$mailObject->assignElement( "body", $bodyObject->getLoadedTemplate() );
				$mailObject->finalize();
				$subjectObject = new HTMLLoader( "{{{bqstatuschangesubject}}}", $userObject2->getLanguage() );
				$subjectObject->finalize();
				mailHTML( $userObject2->getEmail(), $subjectObject->getLoadedTemplate(),
				          $mailObject->getLoadedTemplate()
				);
			}
		}

		return true;
	}
	$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{unknownerror}}}" );

	return false;
}

function toggleBQStatus( $kill = false ) {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject;
	if( !validateToken() ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	if( !isset( $loadedArguments['id'] ) || empty( $loadedArguments['id'] ) ) {
		$mainHTML->setMessageBox( "danger", "{{{bqinvalidid}}}", "{{{bqinvalididmessage}}}" );

		return false;
	}
	$sql =
		"SELECT * FROM externallinks_botqueue LEFT JOIN externallinks_user ON externallinks_botqueue.wiki=externallinks_user.wiki AND externallinks_botqueue.queue_user=externallinks_user.user_link_id LEFT JOIN externallinks_userpreferences ON externallinks_user.user_link_id=externallinks_userpreferences.user_link_id WHERE `queue_id` = " .
		$dbObject->sanitize( $loadedArguments['id'] ) . ";";
	$res = $dbObject->queryDB( $sql );
	if( ( $result = mysqli_fetch_assoc( $res ) ) !== false ) {
		$mailObject = new HTMLLoader( "emailmain", $result['language'] );
		$sendMail = false;
		if( $result['queue_status'] == 0 || $result['queue_status'] == 1 ||
		    ( $kill === true && $result['queue_status'] == 4 )
		) {
			if( $kill === false ) {
				if( !validatePermission( "changebqjob" ) ) return false;
				$sql =
					"UPDATE externallinks_botqueue SET `queue_status` = 4,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_id` = " .
					$dbObject->sanitize( $loadedArguments['id'] ) . ";";
				$type = "suspend";
				if( $result['user_email_bqstatussuspended'] == 1 && $result['user_email_confirmed'] == 1 ) $sendMail =
					true;
				$status = 4;
			} else {
				if( $userObject->getUserLinkID() != $result['queue_user'] &&
				    !validatePermission( "changebqjob" )
				) return false;
				$sql =
					"UPDATE externallinks_botqueue SET `queue_status` = 3,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_id` = " .
					$dbObject->sanitize( $loadedArguments['id'] ) . ";";
				$type = "kill";
				if( $result['user_email_bqstatuskilled'] == 1 && $result['user_email_confirmed'] == 1 ) $sendMail =
					true;
				$status = 3;
			}
		} elseif( $kill === false && $result['queue_status'] == 4 ) {
			$sql =
				"UPDATE externallinks_botqueue SET `queue_status` = 0,`status_timestamp`=CURRENT_TIMESTAMP WHERE `queue_id` = " .
				$dbObject->sanitize( $loadedArguments['id'] ) . ";";
			$type = "unsuspend";
			if( $result['user_email_bqstatusresume'] == 1 && $result['user_email_confirmed'] == 1 ) $sendMail = true;
			$status = 0;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{bqstatuschangeerrormessage}}}" );

			return false;
		}
		$mailbodysubject = new HTMLLoader( "{{{bqmailjob{$type}msg}}}", $result['language'] );
		$mailbodysubject->assignAfterElement( "logobject", $result['queue_id'] );
		$mailbodysubject->assignAfterElement( "joburl", ROOTURL . "index.php?page=viewjob&id={$result['queue_id']}" );
		$mailbodysubject->finalize();
		$mailObject->assignAfterElement( "rooturl", ROOTURL );
		$mailObject->assignAfterElement( "joburl", ROOTURL . "index.php?page=viewjob&id={$result['queue_id']}" );
		$mailObject->assignElement( "body", $mailbodysubject->getLoadedTemplate() );
		$mailObject->finalize();
		if( $sendMail === true ) {
			mailHTML( $result['user_email'], preg_replace( '/\<.*?\>/i', "", $mailbodysubject->getLoadedTemplate() ),
			          $mailObject->getLoadedTemplate()
			);
		}
	}

	if( !isset( $loadedArguments['reason'] ) ) $loadedArguments['reason'] = "";

	if( $dbObject->queryDB( $sql ) ) {
		$userObject->setLastAction( time() );
		$dbObject->insertLogEntry( $result['wiki'], WIKIPEDIA, "bqchangestatus", $type, $loadedArguments['id'], "",
		                           $userObject->getUserLinkID(), $result['queue_status'], $status,
		                           $loadedArguments['reason']
		);
		$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{bqchangestatus$type}}}" );
		$mainHTML->assignAfterElement( "logobject", $result['queue_id'] );

		return true;
	}
	$mainHTML->setMessageBox( "danger", "{{{bqstatuschangeerror}}}", "{{{unknownerror}}}" );

	return false;
}

function reportFalsePositive( &$jsonOut = false ) {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject, $checkIfDead;
	if( !validateToken( $jsonOut ) ) return false;
	if( !validatePermission( "reportfp", true, $jsonOut ) ) return false;
	$checksum = $oauthObject->getChecksumToken();
	if( !validateChecksum( $jsonOut ) ) return false;
	if( !validateNotBlocked( $jsonOut ) ) return false;

	if( $jsonOut === false ) {
		if( isset( $_SESSION['precheckedfplistsrorted'] ) ) {
			if( $_SESSION['precheckedfplistsrorted']['toreporthash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['toreport'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['toreporterrorshash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET .
			         implode( ":", $_SESSION['precheckedfplistsrorted']['toreporterrors'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['toresethash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['toreset'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['towhitelisthash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['towhitelist'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['alreadyreportedhash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET .
			         implode( ":", $_SESSION['precheckedfplistsrorted']['alreadyreported'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['notfoundhash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['notfound'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['notdeadhash'] ==
			    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['notdead'] )
			    ) &&
			    $_SESSION['precheckedfplistsrorted']['finalhash'] ==
			    md5( $_SESSION['precheckedfplistsrorted']['toreporthash'] .
			         $_SESSION['precheckedfplistsrorted']['toreporterrorshash'] .
			         $_SESSION['precheckedfplistsrorted']['toresethash'] .
			         $_SESSION['precheckedfplistsrorted']['towhitelisthash'] .
			         $_SESSION['precheckedfplistsrorted']['alreadyreportedhash'] .
			         $_SESSION['precheckedfplistsrorted']['notfoundhash'] .
			         $_SESSION['precheckedfplistsrorted']['notdeadhash'] . $checksum
			    )
			) {
				$URLCache = [];
				$toReport = $_SESSION['precheckedfplistsrorted']['toreport'];
				$errors = $_SESSION['precheckedfplistsrorted']['toreporterrors'];
				$toReset = $_SESSION['precheckedfplistsrorted']['toreset'];
				$toWhitelist = $_SESSION['precheckedfplistsrorted']['towhitelist'];
			}
		} else {
			$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );

			return false;
		}
	} else {
		$schemelessURLRegex =
			'(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';
		if( isset( $loadedArguments['fplist'] ) ) {
			$urls = explode( "\n", $loadedArguments['fplist'] );
			foreach( $urls as $id => $url ) {
				if( !preg_match( '/' . $schemelessURLRegex . '/i', $url, $garbage ) ) {
					unset( $urls[$id] );
				} else {
					$urls[$id] = $garbage[0];
				}
			}
			$loadedArguments['fplist'] = implode( "\n", $urls );
		} else {
			header( "HTTP/1.1 400 Bad Request", true, 400 );
			$jsonOut['result'] = "fail";
			$jsonOut['missingvalue'] = "fplist";
			$jsonOut['errormessage'] =
				"The fplist is a newline separated parameter of URLs that is required for this function.";

			return false;
		}

		if( isset( $loadedArguments['fplist'] ) ) {
			$toReport = [];
			$toReset = [];
			$toWhitelist = [];
			$alreadyReported = [];
			$escapedURLs = [];
			$notDead = [];
			foreach( $urls as $url ) {
				$escapedURLs[] = $dbObject->sanitize( $url );
			}
			$sql =
				"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_paywall.paywall_id=externallinks_global.paywall_id WHERE `url` IN ( '" .
				implode(
					"', '",
					$escapedURLs
				) . "' );";
			$res = $dbObject->queryDB( $sql );
			$notfound = array_flip( $urls );
			while( $result = mysqli_fetch_assoc( $res ) ) {
				if( $result['live_state'] != 0 && $result['live_state'] != 6 && $result['paywall_status'] != 2 ) {
					$notDead[] = $result['url'];
				}
				unset( $notfound[$result['url']] );
			}
			$notfound = array_flip( $notfound );
			$sql =
				"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id WHERE `url` IN ( '" .
				implode( "', '", $escapedURLs ) . "' ) AND `report_status` = 0;";
			$res = $dbObject->queryDB( $sql );
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$alreadyReported[] = $result['url'];
			}
			$urls = array_diff( $urls, $alreadyReported, $notfound, $notDead );
			$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
			$results = $checkIfDead->areLinksDead( $urls );
			$errors = $checkIfDead->getErrors();
			$whitelisted = [];
			if( USEADDITIONALSERVERS === true ) {
				$toValidate = [];
				foreach( $urls as $tid => $url ) {
					if( $results[$url] === true ) {
						$toValidate[] = $url;
					}
				}
				if( !empty( $toValidate ) ) foreach( explode( "\n", CIDSERVERS ) as $server ) {
					$serverResults = API::runCIDServer( $server, $toValidate );
					$toValidate = array_flip( $toValidate );
					foreach( $serverResults['results'] as $surl => $sResult ) {
						if( $surl == "errors" ) continue;
						if( $sResult === false ) {
							$whitelisted[] = $surl;
							unset( $toValidate[$surl] );
						} else {
							$errors[$surl] = $serverResults['results']['errors'][$surl];
						}
					}
					$toValidate = array_flip( $toValidate );
				}
			}

			foreach( $urls as $id => $url ) {
				if( $results[$url] !== true ) {
					$toReset[] = $url;
				} else {
					if( !in_array( $url, $whitelisted ) ) $toReport[] = $url;
					else $toWhitelist[] = $url;
				}
			}
		}
	}

	if( empty( $toReport ) && empty( $toReset ) && empty( $toWhitelist ) ) {
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{nofpurlerror}}}" );
		else {
			header( "HTTP/1.1 400 Bad Request", true, 400 );
			$jsonOut['result'] = "fail";
			$jsonOut['reportfperror'] = "noaction";
			$jsonOut['errormessage'] = "There is nothing to report or action.";
			$jsonOut['toreport'] = $toReport;
			$jsonOut['toreset'] = $toReset;
			$jsonOut['notdead'] = $notDead;
			$jsonOut['notfound'] = $notfound;
			$jsonOut['alreadyreported'] = $alreadyReported;
			$jsonOut['towhitelist'] = $toWhitelist;
		}

		return false;
	}
	$escapedURLs = [];
	foreach( array_merge( $toReset, $toReport, $toWhitelist ) as $url ) {
		$escapedURLs[] = $dbObject->sanitize( $url );
	}
	$sql =
		"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url` IN ( '" .
		implode( "', '", $escapedURLs ) . "' );";
	$res = $dbObject->queryDB( $sql );
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$URLCache[$result['url']] = $result;
	}
	foreach( $toReport as $report ) {
		if( $dbObject->insertFPReport( WIKIPEDIA, $userObject->getUserLinkID(), $URLCache[$report]['url_id'],
		                               CHECKIFDEADVERSION, $errors[$report]
		)
		) {
			$dbObject->insertLogEntry( "global", WIKIPEDIA, "fpreport", "report", $URLCache[$report]['url_id'],
			                           $report,
			                           $userObject->getUserLinkID()
			);
		} else {
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );
			else {
				header( "HTTP/1.1 520 Unknown Error", true, 520 );
				$jsonOut['result'] = "fail";
				$jsonOut['reportfperror'] = "unknownerror";
				$jsonOut['errormessage'] = "An unknown error occurred.";
			}

			return false;
		}
	}
	$escapedURLs = [];
	$domains = [];
	foreach( $toReset as $report ) {
		if( $URLCache[$report]['paywall_status'] == 3 ) {
			continue;
		} elseif( $URLCache[$report]['live_state'] != 0 ) {
			continue;
		} elseif( in_array( $URLCache[$report]['paywall_id'], $escapedURLs ) ) {
			continue;
		} else {
			$escapedURLs[] = $URLCache[$report]['paywall_id'];
			$domains[] = $checkIfDead->parseURL( $report )['host'];
		}
	}
	if( !empty( $escapedURLs ) ) {
		$sql = "UPDATE externallinks_global SET `live_state` = 3 WHERE `paywall_id` IN ( " .
		       implode( ", ", $escapedURLs ) . " );";
		if( $dbObject->queryDB( $sql ) ) {
			foreach( $escapedURLs as $id => $paywallID ) {
				$dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changestate", $paywallID, $domains[$id],
				                           $userObject->getUserLinkID(), -1, 3
				);
			}
		} else {
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );
			else {
				header( "HTTP/1.1 520 Unknown Error", true, 520 );
				$jsonOut['result'] = "fail";
				$jsonOut['reportfperror'] = "unknownerror";
				$jsonOut['errormessage'] = "An unknown error occurred.";
			}

			return false;
		}
	}
	$escapedURLs = [];
	$domains = [];
	$paywallStatuses = [];
	foreach( $toWhitelist as $report ) {
		if( $URLCache[$report]['paywall_status'] == 3 ) {
			continue;
		} elseif( in_array( $URLCache[$report]['paywall_id'], $escapedURLs ) ) {
			continue;
		} else {
			$escapedURLs[] = $URLCache[$report]['paywall_id'];
			$domains[] = $checkIfDead->parseURL( $report )['host'];
			$paywallStatuses[] = $URLCache[$report]['paywall_status'];
		}
	}
	if( !empty( $escapedURLs ) ) {
		$sql = "UPDATE externallinks_paywall SET `paywall_status` = 3 WHERE `paywall_id` IN ( " .
		       implode( ", ", $escapedURLs ) . " );";
		if( $dbObject->queryDB( $sql ) ) {
			foreach( $escapedURLs as $id => $paywallID ) {
				$dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changeglobalstate", $paywallID,
				                           $domains[$id], $userObject->getUserLinkID(), $paywallStatuses[$id], 3
				);
			}
		} else {
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );
			else {
				header( "HTTP/1.1 520 Unknown Error", true, 520 );
				$jsonOut['result'] = "fail";
				$jsonOut['reportfperror'] = "unknownerror";
				$jsonOut['errormessage'] = "An unknown error occurred.";
			}

			return false;
		}
	}
	$userObject->setLastAction( time() );
	unset( $loadedArguments['fplist'] );
	if( !empty( $toReport ) ) {
		$sql =
			"SELECT * FROM externallinks_user LEFT JOIN externallinks_userpreferences ON externallinks_userpreferences.user_link_id= externallinks_user.user_link_id WHERE `user_email_confirmed` = 1 AND `user_email_fpreport` = 1 AND `wiki` = '" .
			WIKIPEDIA . "';";
		$res = $dbObject->queryDB( $sql );
		while( $result = mysqli_fetch_assoc( $res ) ) {
			$mailObject = new HTMLLoader( "emailmain", $result['language'] );
			$body = "{{{fpreportedstarter}}}:<br>\n";
			$body .= "<ul>\n";
			foreach( $toReport as $report ) {
				$body .= "<li><a href=\"$report\">" . htmlspecialchars( $report ) . "</a></li>\n";
			}
			$body .= "</ul>";
			$mailObject->assignElement( "body", $body );
			$mailObject->assignAfterElement( "rooturl", ROOTURL );
			$mailObject->assignAfterElement( "targetusername", $userObject->getUsername() );
			$mailObject->assignAfterElement( "targetuserid", $userObject->getUserID() );
			$mailObject->finalize();
			$subjectObject = new HTMLLoader( "{{{fpreportedsubject}}}", $result['language'] );
			$subjectObject->finalize();
			mailHTML( $result['user_email'], $subjectObject->getLoadedTemplate(),
			          $mailObject->getLoadedTemplate(), true
			);
		}
	}
	if( $jsonOut === false ) $mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{fpreportsuccess}}}" );
	else {
		$jsonOut['result'] = "success";
		$jsonOut['toreport'] = $toReport;
		$jsonOut['toreset'] = $toReset;
		$jsonOut['notdead'] = $notDead;
		$jsonOut['notfound'] = $notfound;
		$jsonOut['alreadyreported'] = $alreadyReported;
	}

	return true;
}

function changePreferences() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject, $accessibleWikis, $languages;
	if( !isset( $loadedArguments['confirmationhash'] ) && !validateToken() ) return false;
	if( !isset( $loadedArguments['confirmationhash'] ) && !validateChecksum() ) return false;

	if( !isset( $loadedArguments['confirmationhash'] ) && isset( $loadedArguments['email'] ) &&
	    $loadedArguments['email'] != $userObject->getEmail()
	) {
		if( empty( $loadedArguments['email'] ) ||
		    preg_match( '/^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i',
		                $loadedArguments['email'], $garbage
		    )
		) {
			$toChange['user_email'] = $dbObject->sanitize( $loadedArguments['email'] );
			$toChange['user_email_confirm_hash'] =
				md5( CONSUMERSECRET . CONSUMERKEY . time() . $loadedArguments['email'] );
			$toChange['user_email_confirmed'] = 0;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{preferenceserror}}}", "{{{invalidemail}}}" );

			return false;
		}
	} elseif( isset( $loadedArguments['email'] ) && $loadedArguments['email'] == $userObject->getEmail() &&
	          $userObject->hasEmail() !== true
	) {
		$toChange['user_email'] = $dbObject->sanitize( $loadedArguments['email'] );
		$toChange['user_email_confirm_hash'] =
			md5( CONSUMERSECRET . CONSUMERKEY . time() . $loadedArguments['email'] );
		$toChange['user_email_confirmed'] = 0;
	}
	if( isset( $loadedArguments['confirmationhash'] ) ) {
		if( $userObject->validateEmailHash( $loadedArguments['confirmationhash'] ) ) {
			$toChange['user_email_confirmed'] = 1;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{preferenceserror}}}", "{{{hashmismatch}}}" );

			return false;
		}
		goto executeprefSQL;
	}
	$toChange['user_email_fpreport'] = 0;
	$toChange['user_email_blockstatus'] = 0;
	$toChange['user_email_permissions'] = 0;
	$toChange['user_email_fpreportstatusfixed'] = 0;
	$toChange['user_email_fpreportstatusdeclined'] = 0;
	$toChange['user_email_fpreportstatusopened'] = 0;
	$toChange['user_email_bqstatuscomplete'] = 0;
	$toChange['user_email_bqstatuskilled'] = 0;
	$toChange['user_email_bqstatussuspended'] = 0;
	$toChange['user_email_bqstatusresume'] = 0;
	$toChange['user_new_tab_one_tab'] = 0;
	$toChange['user_allow_analytics'] = 0;
	if( isset( $loadedArguments['user_email_fpreport'] ) && $userObject->validatePermission( 'viewfpreviewpage' ) ) {
		$toChange['user_email_fpreport'] = 1;
	}
	if( isset( $loadedArguments['user_email_blockstatus'] ) ) {
		$toChange['user_email_blockstatus'] = 1;
	}
	if( isset( $loadedArguments['user_email_permissions'] ) ) {
		$toChange['user_email_permissions'] = 1;
	}
	if( isset( $loadedArguments['user_email_fpreportstatusfixed'] ) ) {
		$toChange['user_email_fpreportstatusfixed'] = 1;
	}
	if( isset( $loadedArguments['user_email_fpreportstatusdeclined'] ) ) {
		$toChange['user_email_fpreportstatusdeclined'] = 1;
	}
	if( isset( $loadedArguments['user_email_fpreportstatusopened'] ) ) {
		$toChange['user_email_fpreportstatusopened'] = 1;
	}
	if( isset( $loadedArguments['user_email_bqstatuscomplete'] ) ) {
		$toChange['user_email_bqstatuscomplete'] = 1;
	}
	if( isset( $loadedArguments['user_email_bqstatuskilled'] ) ) {
		$toChange['user_email_bqstatuskilled'] = 1;
	}
	if( isset( $loadedArguments['user_email_bqstatussuspended'] ) ) {
		$toChange['user_email_bqstatussuspended'] = 1;
	}
	if( isset( $loadedArguments['user_email_bqstatusresume'] ) ) {
		$toChange['user_email_bqstatusresume'] = 1;
	}
	if( isset( $loadedArguments['user_new_tab_one_tab'] ) ) {
		$toChange['user_new_tab_one_tab'] = 1;
	}
	if( isset( $loadedArguments['user_allow_analytics'] ) ) {
		$toChange['user_allow_analytics'] = 1;
	}
	if( isset( $loadedArguments['user_default_theme'] ) ) {
		$toChange['user_default_theme'] = $userObject->setTheme( $loadedArguments['user_default_theme'] );
	}
	if( isset( $loadedArguments['user_global_language'] ) ) {
		if( isset( $languages[$loadedArguments['user_global_language']] ) ) {
			$toChange['user_default_language'] = $loadedArguments['user_global_language'];
		} elseif( $loadedArguments['user_global_language'] == "null" ) {
			$toChange['user_default_language'] = null;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{preferenceserror}}}", "{{{unknownerror}}}" );

			return false;
		}
	}
	if( isset( $loadedArguments['user_default_wiki'] ) ) {
		if( isset( $accessibleWikis[$loadedArguments['user_default_wiki']] ) ) {
			$toChange['user_default_wiki'] = $loadedArguments['user_default_wiki'];
		} elseif( $loadedArguments['user_default_wiki'] == "null" ) {
			$toChange['user_default_wiki'] = null;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{preferenceserror}}}", "{{{unknownerror}}}" );

			return false;
		}
	}

	executeprefSQL:
	$sql = "UPDATE externallinks_userpreferences SET ";
	foreach( $toChange as $column => $value ) {
		$sql .= "`$column`=" . ( is_null( $value ) ? "NULL" : "'$value'" ) . ",";
	}

	$sql = substr( $sql, 0, strlen( $sql ) - 1 );
	$sql .= " WHERE `user_link_id` = " . $userObject->getUserLinkID() . ";";

	if( $dbObject->queryDB( $sql ) ) {
		$userObject = new User( $dbObject, $oauthObject );
		if( isset( $toChange['user_email'] ) && !empty( $toChange['user_email'] ) ) {
			$mailObject = new HTMLLoader( "emailmain", $userObject->getLanguage() );
			$mailObject->assignAfterElement( "confirmurl", "<a href=\"" . ROOTURL .
			                                               "index.php?page=userpreferences&action=changepreferences&confirmationhash=" .
			                                               $toChange['user_email_confirm_hash'] . "\">" . ROOTURL .
			                                               "index.php?page=userpreferences&action=changepreferences&confirmationhash=" .
			                                               $toChange['user_email_confirm_hash'] . "</a>"
			);
			$mailObject->assignElement( "body", "{{{emailconfirmmessage}}}" );
			$mailObject->assignAfterElement( "rooturl", ROOTURL );
			$mailSubjectObject = new HTMLLoader( "{{{emailconfirmsubject}}}", $userObject->getLanguage() );
			$mailSubjectObject->finalize();
			$mailObject->finalize();
			mailHTML( $toChange['user_email'], $mailSubjectObject->getLoadedTemplate(), $mailObject->getLoadedTemplate()
			);

			$mainHTML->setMessageBox( "warning", "{{{successheader}}}", "{{{emailconfirmrequired}}}" );
			$mainHTML->assignAfterElement( "sender", htmlspecialchars( GUIFROM ) );
			$userObject->setLastAction( time() );

			return true;
		}
		if( isset( $loadedArguments['confirmationhash'] ) ) {
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{emailconfirmed}}}" );

			return true;
		}
		$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{settingssaved}}}" );
		$userObject->setLastAction( time() );

		return true;
	} else {
		$mainHTML->setMessageBox( "danger", "{{{preferenceserror}}}", "{{{unknownerror}}}" );

		return false;
	}
}

function invokeBot( &$jsonOut = false ) {
	global $loadedArguments, $oauthKeys, $useKeys, $userObject;

	if( $jsonOut !== false ) $jsonOut['result'] = "fail";
	if( !validateToken( $jsonOut ) ) return false;
	if( !validatePermission( "invokebot", true, $jsonOut ) ) return false;
	if( !validateChecksum( $jsonOut ) ) return false;
	if( !validateNotBlocked( $jsonOut ) ) return false;

	if( empty( $loadedArguments['summary'] ) ) {
		$jsonOut['missingvalue'] = "summary";
		$jsonOut['errormesage'] = "This parameter is required to describe the edit being made.";
		return false;
	}

	if( empty( $loadedArguments['text'] ) ) {
		$jsonOut['missingvalue'] = "text";
		$jsonOut['errormesage'] = "This parameter is required to make an edit to a page.  Blanking pages are not allowed.";
		return false;
	}

	if( empty( $loadedArguments['page'] ) ) {
		$jsonOut['missingvalue'] = "page";
		$jsonOut['errormesage'] = "This parameter is required to make an edit to a page.";
		return false;
	}

	if( !empty( $loadedArguments['minor'] ) ) {
		$minor = true;
	} else $minor = false;

	if( !empty( $loadedArguments['timestamp'] ) ) {
		$timestamp = $loadedArguments['timestamp'];
	} else $timestamp = false;

	$keys = $oauthKeys[$useKeys]['bot'];

	define( 'REQUESTEDBY', $userObject->getUsername() );

	$revID = API::edit( $loadedArguments['page'], $loadedArguments['text'], $loadedArguments['summary'], $minor, $timestamp, true, false, "", $error, $keys );

	if( $revID ) {
		$jsonOut['result'] = "success";
		$jsonOut['revid'] = $revID;
	}

	$jsonOut['errors'] = $error;

	if( !$revID ) return false;
	else return true;
}

function changeURLData( &$jsonOut = false ) {
	global $loadedArguments, $dbObject, $userObject, $mainHTML;
	if( $jsonOut !== false ) $jsonOut['result'] = "fail";
	if( !validateToken( $jsonOut ) ) return false;
	if( !validatePermission( "changeurldata", true, $jsonOut ) ) return false;
	if( !validateChecksum( $jsonOut ) ) return false;
	if( !validateNotBlocked( $jsonOut ) ) return false;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	$parser = PARSERCLASS;

	if( isset( $loadedArguments['urlid'] ) && !empty( $loadedArguments['urlid'] ) ) {
		$sqlURL =
			"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url_id` = '" .
			$dbObject->sanitize( $loadedArguments['urlid'] ) . "';";
		if( ( $res = $dbObject->queryDB( $sqlURL ) ) && ( $result = mysqli_fetch_assoc( $res ) ) ) {
			$loadedArguments['url'] = $result['url'];
			$toChange = [];

			if( isset( $loadedArguments['accesstime'] ) ) {
				$dateFormats = DB::getConfiguration( WIKIPEDIA, "wikiconfig", "dateformat" );

				foreach( $dateFormats['syntax'] as $index => $rule ) {
					if( DataGenerator::strptime( $loadedArguments['accesstime'], $rule['format'] ) !== false ) $dateFormat =
						$rule['format'];
				}

				if( empty( $dateFormat ) ) {
					$givenEpoch = strtotime( $loadedArguments['accesstime'] );

					if( $givenEpoch === false ) {
						if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
						                                                   "{{{urlaccesstimeillegal}}}"
						);
						else {
							$jsonOut['urldataerror'] = "illegalaccesstime";
							$jsonOut['errormesage'] = "The provided access time is illegal.";
						}

						return false;
					}
				} else {
					$givenEpoch =
						DataGenerator::strptimetoepoch( DataGenerator::strptime( $loadedArguments['accesstime'], $dateFormat,
						                                                         false
						)
						);

					if( !is_numeric( $givenEpoch ) ) {
						$givenEpoch = strtotime( $loadedArguments['accesstime'] );

						if( $givenEpoch === false ) {
							if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
							                                                   "{{{urlaccesstimeillegal}}}"
							);
							else {
								$jsonOut['urldataerror'] = "illegalaccesstime";
								$jsonOut['errormesage'] = "The provided access time is illegal.";
							}

							return false;
						}
					}
				}
			}
			if( isset( $loadedArguments['accesstime'] ) &&
			    date( 'j F Y', $givenEpoch ) !=
			    date( 'j F Y', (int) strtotime( $result['access_time'] ) )
			) {
				if( validatePermission( "alteraccesstime", true, $jsonOut ) ) {
					if( $givenEpoch === false || $givenEpoch < 978307200 || is_null( $givenEpoch ) ) {
						if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
						                                                   "{{{urlaccesstimeillegal}}}"
						);
						else {
							$jsonOut['urldataerror'] = "illegalaccesstime";
							$jsonOut['errormesage'] = "The provided access time is illegal.";
						}

						return false;
					}
					$toChange['access_time'] =
						date( 'Y-m-d H:i:s', $givenEpoch );
				} else {
					return false;
				}
			}
			if( isset( $loadedArguments['livestateselect'] ) &&
			    $loadedArguments['livestateselect'] != $result['live_state']
			) {
				switch( $result['paywall_status'] ) {
					//case 1:
					//if( $result['live_state'] != 5 ) break;
					case 2:
					case 3:
						if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
						                                                   "{{{urlpaywallillegal}}}"
						);
						else {
							$jsonOut['urldataerror'] = "stateblockedatdomain";
							$jsonOut['errormesage'] =
								"The live state of the URL is set at the domain level and cannot be changed.";
						}

						return false;
				}
				switch( $result['live_state'] ) {
					case 6:
						if( !validatePermission( "deblacklisturls", true, $jsonOut ) ) return false;
						break;
					case 7:
						if( !validatePermission( "dewhitelisturls", true, $jsonOut ) ) return false;
						break;
				}
				switch( $loadedArguments['livestateselect'] ) {
					case 0:
					case 3:
					case 5:
						$toChange['live_state'] = $loadedArguments['livestateselect'];
						break;
					case 6:
						if( !validatePermission( "blacklisturls", true, $jsonOut ) ) return false;
						$toChange['live_state'] = $loadedArguments['livestateselect'];
						break;
					case 7:
						if( !validatePermission( "whitelisturls", true, $jsonOut ) ) return false;
						$toChange['live_state'] = $loadedArguments['livestateselect'];
						break;
					default:
						if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
						                                                   "{{{illegallivestate}}}"
						);
						else {
							$jsonOut['urldataerror'] = "illegalstate";
							$jsonOut['errormesage'] = "The provided state is not a valid state to change the URL to.";
						}

						return false;
				}
			}
			if( ( empty( $loadedArguments['archiveurl'] ) ? "" :
					$checkIfDead->sanitizeURL( $loadedArguments['archiveurl'], true, true ) ) !=
			    ( is_null( $result['archive_url'] ) ? null :
				    $checkIfDead->sanitizeURL( $result['archive_url'], true, true ) )
			) {
				if( !validatePermission( "alterarchiveurl", true, $jsonOut ) ) return false;
				if( !empty( $loadedArguments['archiveurl'] ) &&
				    API::isArchive( $loadedArguments['archiveurl'], $data )
				) {
					if( !isset( $loadedArguments['overridearchivevalidation'] ) ||
					    ( $loadedArguments['overridearchivevalidation'] != "on" &&
					      $loadedArguments['overridearchivevalidation'] != 1 )
					) {
						if( isset( $data['archive_type'] ) && $data['archive_type'] == "invalid" ) {
							if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
							                                                   "{{{invalidarchive}}}"
							);
							else {
								$jsonOut['urldataerror'] = "invalidarchive";
								$jsonOut['errormesage'] = "The given archive URL is not a valid archive snapshot.";
							}

							return false;
						}
						if( $data['url'] ==
						    $checkIfDead->sanitizeURL( $loadedArguments['url'], true )
						) {
							$toChange['archive_url'] = $dbObject->sanitize( $data['archive_url'] );
							$toChange['archive_time'] = date( 'Y-m-d H:i:s', $data['archive_time'] );
							if( $result['has_archive'] != 1 ) $toChange['has_archive'] = 1;
							if( $result['archived'] != 1 ) $toChange['archived'] = 1;
							if( $result['reviewed'] != 1 ) $toChange['reviewed'] = 1;
						} else {
							if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
							                                                   "{{{urlmismatch}}}"
							);
							else {
								$jsonOut['urldataerror'] = "urlmismatch";
								$jsonOut['errormesage'] = "The original URL and the URL of the snapshot do not match.";
							}

							return false;
						}
					} else {
						if( !validatePermission( "overridearchivevalidation", true, $jsonOut ) ) {
							return false;
						} else {
							$toChange['archive_url'] = $dbObject->sanitize( $data['archive_url'] );
							$toChange['archive_time'] = date( 'Y-m-d H:i:s', $data['archive_time'] );
							if( $result['has_archive'] != 1 ) $toChange['has_archive'] = 1;
							if( $result['archived'] != 1 ) $toChange['archived'] = 1;
							if( $result['reviewed'] != 1 ) $toChange['reviewed'] = 1;
						}
					}
				} elseif( !empty( $loadedArguments['archiveurl'] ) ) {
					if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{urldataerror}}}",
					                                                   "{{{invalidarchive}}}"
					);
					else {
						$jsonOut['urldataerror'] = "invalidarchive";
						$jsonOut['errormesage'] =
							"The provided archive URL is not recognized as a supported archive URL.";
					}

					return false;
				} elseif( empty( $loadedArguments['archiveurl'] ) && !empty( $result['archive_url'] ) ) {
					$toChange['archive_url'] = null;
					$toChange['archive_time'] = null;
					if( $result['has_archive'] != 0 ) $toChange['has_archive'] = 0;
					if( $result['archived'] != 0 ) $toChange['archived'] = 0;
					if( $result['reviewed'] != 1 ) $toChange['reviewed'] = 1;
				}
			}
		} else {
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{404url}}}", "{{{404urlmessage}}}" );
			else {
				$jsonOut['urldataerror'] = "404";
				$jsonOut['errormesage'] = "The URL was not found in the DB.";
			}

			return false;
		}

		$updateSQL = "UPDATE externallinks_global SET ";
		foreach( $toChange as $column => $value ) {
			$updateSQL .= "`$column` = " . ( is_null( $value ) ? "NULL" : "'$value'" ) . ",";
		}
		$updateSQL = substr( $updateSQL, 0, strlen( $updateSQL ) - 1 );
		$updateSQL .= " WHERE `url_id` = '" . $dbObject->sanitize( $loadedArguments['urlid'] ) . "';";
		if( $res = $dbObject->queryDB( $updateSQL ) ) {
			foreach( $toChange as $column => $value ) {
				switch( $column ) {
					case "access_time":
						$dbObject->insertLogEntry( "global", WIKIPEDIA, "urldata", "changeaccess",
						                           $loadedArguments['urlid'], $loadedArguments['url'],
						                           $userObject->getUserLinkID(), (int) strtotime( $result['access_time'] ),
						                           strtotime( $toChange['access_time'] ), $loadedArguments['reason']
						);
						break;
					case "live_state":
						$dbObject->insertLogEntry( "global", WIKIPEDIA, "urldata", "changestate",
						                           $loadedArguments['urlid'], $loadedArguments['url'],
						                           $userObject->getUserLinkID(), $result['live_state'],
						                           $toChange['live_state'], $loadedArguments['reason']
						);
						break;
					case "archive_url":
						$dbObject->insertLogEntry( "global", WIKIPEDIA, "urldata", "changearchive",
						                           $loadedArguments['urlid'], $loadedArguments['url'],
						                           $userObject->getUserLinkID(),
							( is_null( $result['archive_url'] ) ? null : $result['archive_url'] ),
							( is_null( $toChange['archive_url'] ) ? null : $toChange['archive_url'] ),
							                       $loadedArguments['reason']
						);
						break;
				}
			}
			if( $jsonOut === false ) $mainHTML->setMessageBox( "success", "{{{successheader}}}",
			                                                   "{{{urlchangesuccess}}}"
			);
			else {
				$jsonOut['result'] = "success";
			}
			$userObject->setLastAction( time() );

			return true;
		}
	} elseif( $jsonOut !== false ) {
		$jsonOut['missingvalue'] = "urlid";
		$jsonOut['errormesage'] = "This parameter is required to identify the URL being modified.";
	}
}

function changeDomainData() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject;
	if( !validateToken() ) return false;
	if( !validatePermission( "changedomaindata" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	if( isset( $loadedArguments['paywallids'] ) && !empty( $loadedArguments['paywallids'] ) ) {
		$paywallIDs = explode( '|', $loadedArguments['paywallids'] );
		if( !is_array( $paywallIDs ) || empty( $paywallIDs ) ) {
			$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{invaliddomaindata}}}" );

			return false;
		}
		$sqlURL = "SELECT * FROM externallinks_paywall WHERE `paywall_id` IN (" . implode( ",", $paywallIDs ) . ");";
		$deblacklistDomain = false;
		$dewhitelistDomain = false;
		$lastSetState = -2;
		if( isset( $loadedArguments['livestateselect'] ) ) $newSetState = $loadedArguments['livestateselect'];
		else {
			$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{illegallivestate}}}" );

			return false;
		}
		$paywalls = [];
		if( ( $res = $dbObject->queryDB( $sqlURL ) ) ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				if( $lastSetState == -2 ) $lastSetState = $result['paywall_status'];
				elseif( $lastSetState != $result['paywall_status'] ) $lastSetState = -1;

				$paywalls[$result['paywall_id']] = $result;
				if( $result['paywall_status'] == 2 ) {
					$deblacklistDomain = true;
				} elseif( $result['paywall_status'] == 3 ) {
					$dewhitelistDomain = true;
				}
			}
			foreach( $paywallIDs as $id ) {
				if( !isset( $paywalls[$id] ) ) {
					$mainHTML->setMessageBox( "danger", "{{{404domain}}}", "{{{404domainmessage}}}" );

					return false;
				}
			}
		} else {
			$mainHTML->setMessageBox( "danger", "{{{404domain}}}", "{{{404domainmessage}}}" );

			return false;
		}
		if( $lastSetState != $newSetState && $deblacklistDomain === true &&
		    !validatePermission( "deblacklistdomains" )
		) return false;
		if( $lastSetState != $newSetState && $dewhitelistDomain === true &&
		    !validatePermission( "dewhitelistdomains" )
		) return false;
		if( $lastSetState != $newSetState ) switch( $newSetState ) {
			case 0:
				$sql = "UPDATE externallinks_paywall SET `paywall_status` = 0 WHERE `paywall_id` IN (" .
				       implode( ",", $paywallIDs ) . ");";
				break;
			case 1:
				$sql = "UPDATE externallinks_paywall SET `paywall_status` = 1 WHERE `paywall_id` IN (" .
				       implode( ",", $paywallIDs ) . ");";
				break;
			case 2:
				if( !validatePermission( "blacklistdomains" ) ) return false;
				$sql = "UPDATE externallinks_paywall SET `paywall_status` = 2 WHERE `paywall_id` IN (" .
				       implode( ",", $paywallIDs ) . ");";
				break;
			case 3:
				if( !validatePermission( "whitelistdomains" ) ) return false;
				$sql = "UPDATE externallinks_paywall SET `paywall_status` = 3 WHERE `paywall_id` IN (" .
				       implode( ",", $paywallIDs ) . ");";
				break;
			case 4:
			case 5:
				$sql = "UPDATE externallinks_global SET `live_state` = " .
				       ( ( $loadedArguments['livestateselect'] - 5 ) * -3 ) . " WHERE `paywall_id` IN (" .
				       implode( ",", $paywallIDs ) . ");";
				$resetsql = "UPDATE externallinks_paywall SET `paywall_status` = 0 WHERE `paywall_id` IN (" .
				            implode( ",", $paywallIDs ) . ");";
				break;
			case -1:
			case -2:
				break;
			default:
				$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{illegallivestate}}}" );

				return false;
		}
		if( isset( $resetsql ) && !$dbObject->queryDB( $resetsql ) ) {
			$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{unknownerror}}}" );

			return false;
		}
		if( isset( $sql ) && !$dbObject->queryDB( $sql ) ) {
			$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{unknownerror}}}" );

			return false;
		}
		$alreadyDone = [];
		foreach( $paywallIDs as $id ) {
			if( in_array( $id, $alreadyDone ) ) continue;
			$alreadyDone[] = $id;
			switch( $loadedArguments['livestateselect'] ) {
				case 0:
				case 1:
				case 2:
				case 3:
					if( $paywalls[$id]['paywall_status'] != $loadedArguments['livestateselect'] ) {
						$dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changeglobalstate",
						                           $id, $paywalls[$id]['domain'],
						                           $userObject->getUserLinkID(), $paywalls[$id]['paywall_status'],
						                           $loadedArguments['livestateselect'], $loadedArguments['reason']
						);
					}
					break;
				case 4:
				case 5:
					$dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changestate",
					                           $id, $paywalls[$id]['domain'],
					                           $userObject->getUserLinkID(), -1,
						( ( $loadedArguments['livestateselect'] - 5 ) * -3 ), $loadedArguments['reason']
					);
					break;
				default:
					$mainHTML->setMessageBox( "warning", "{{{domaindataerror}}}", "{{{unknownerror}}}" );
			}
		}
		if( isset( $loadedArguments['deletearchives'] ) && $loadedArguments['deletearchives'] == "on" ) {

			switch( $loadedArguments['deletearchivesoptions'] ) {
				default:
				case 1:
					$deleteSQL =
						"UPDATE externallinks_global SET `has_archive` = 0, `archive_url` = NULL, `archive_time` = NULL, `archived` = 2 WHERE `paywall_id` IN (" .
						implode( ",", $paywallIDs ) . ");";
					break;
				case 2:
					$deleteSQL =
						"UPDATE externallinks_global SET `has_archive` = 0, `reviewed` = 1, `archive_url` = NULL, `archive_time` = NULL, `archived` = 2 WHERE `paywall_id` IN (" .
						implode( ",", $paywallIDs ) . ");";
					break;
				case 3:
					$deleteSQL =
						"UPDATE externallinks_global SET `has_archive` = 0, `archive_url` = NULL, `archive_time` = NULL, `archived` = 0 WHERE `paywall_id` IN (" .
						implode( ",", $paywallIDs ) . ");";
					break;
				case 4:
					$deleteSQL =
						"UPDATE externallinks_global SET `has_archive` = 0, `reviewed` = 1, `archive_url` = NULL, `archive_time` = NULL, `archived` = 0 WHERE `paywall_id` IN (" .
						implode( ",", $paywallIDs ) . ");";
					break;
			}

			if( !$dbObject->queryDB( $deleteSQL ) ) {
				$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{unknownerror}}}" );

				return false;
			} else {
				$alreadyDone = [];
				foreach( $paywallIDs as $id ) {
					if( in_array( $id, $alreadyDone ) ) continue;
					$alreadyDone[] = $id;
					$dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "deleteall",
					                           $id, $paywalls[$id]['domain'],
					                           $userObject->getUserLinkID(), -1, -1, $loadedArguments['reason']
					);
				}
			}
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{illegallivestate}}}" );

		return false;
	}

	$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{domainchangesuccess}}}" );
	$userObject->setLastAction( time() );

	return true;
}

function toggleRunPage() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $modifiedLinks, $runStats, $accessibleWikis, $locales, $checkIfDead;

	if( !validateToken( $jsonOut ) ) return false;
	if( !validatePermission( "togglerunpage", true ) ) return false;
	if( !validateChecksum( $jsonOut ) ) return false;
	if( !validateNotBlocked( $jsonOut ) ) return false;

	if( $accessibleWikis[WIKIPEDIA]['runpage'] !== false ) {
		$runpage = DB::getConfiguration( WIKIPEDIA, "wikiconfig", "runpage" );

		if( $runpage == "enable" ) {
			DB::setConfiguration( WIKIPEDIA, "wikiconfig", "runpage", "disable" );
			$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "runpage", "disable", 0, 0,
			                           $userObject->getUserLinkID(), "enable", "disable", $loadedArguments['reason']
			);
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{togglerunpagesuccess}}}" );
			$userObject->setLastAction( time() );

			return true;
		} else {
			DB::setConfiguration( WIKIPEDIA, "wikiconfig", "runpage", "enable" );
			$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "runpage", "enable", 0, 0,
			                           $userObject->getUserLinkID(), "disable", "enable", $loadedArguments['reason']
			);
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{togglerunpagesuccess}}}" );
			$userObject->setLastAction( time() );

			return true;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{runpageerror}}}", "{{{norunpageerror}}}" );

		return false;
	}

	$mainHTML->setMessageBox( "danger", "{{{runpageerror}}}", "{{{unknownerror}}}" );

	return false;
}

function analyzePage( &$jsonOut = false ) {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $modifiedLinks, $runStats, $accessibleWikis, $locales, $checkIfDead;

	if( $jsonOut !== false ) $jsonOut['result'] = "fail";
	if( !validateToken( $jsonOut ) ) return false;
	if( !validatePermission( "analyzepage", true, $jsonOut ) ) return false;
	if( !validateChecksum( $jsonOut ) ) return false;
	if( !validateNotBlocked( $jsonOut ) ) return false;

	$locale = setlocale( LC_ALL, unserialize( BOTLOCALE ) );
	if( ( isset( $locales[BOTLANGUAGE] ) && !in_array( $locale, $locales[BOTLANGUAGE] ) ) ||
	    !isset( $locales[BOTLANGUAGE] ) ) {
		//Uh-oh!! None of the locale definitions are supported on this system.
		echo "<!-- Missing locale for \"" . BOTLANGUAGE . "\" -->\n";
		if( !method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE ) ) {
			echo "<!-- No fallback function found, application will attempt to use \"en\" -->\n";
			$locale = setlocale( LC_ALL, $locales['en'] );
			if( !in_array( $locale, $locales['en'] ) ) {
				echo "<!-- Missing locale for \"en\" -->\n";
				if( !method_exists( "IABotLocalization", "localize_en" ) ) {
					echo "<!-- No fallback function found, application will use system default -->\n";
				} else {
					echo "<!-- Internal locale profile available in application.  Using that instead -->\n";
				}
			}
		} else {
			echo "<!-- Internal locale profile available in application.  Using that instead -->\n";
		}
		if( isset( $locales[BOTLANGUAGE] ) ) unset( $locales[BOTLANGUAGE] );
	}

	if( empty( $loadedArguments['pagesearch'] ) && $jsonOut !== false ) {
		$jsonOut['missingvalue'] = "pagesearch";
		$jsonOut['errormessage'] = "This parameter is required to identify which page to analyze.";

		return false;
	}
	$parts = $checkIfDead->parseURL( $loadedArguments['pagesearch'] );
	if( $accessibleWikis[WIKIPEDIA]['rooturl'] == "https://" . $parts['host'] . "/" ) {
		if( $parts['path'] == "/w/index.php" ) {
			$query = explode( "&", $parts['query'] );
			foreach( $query as $item ) {
				$item = explode( "=", $item, 2 );
				if( $item[0] == "title" ) {
					$loadedArguments['pagesearch'] = urldecode( $item[1] );
					break;
				}
			}
		} elseif( strpos( $parts['path'], "/wiki/" ) !== false ) {
			$loadedArguments['pagesearch'] = urldecode( str_replace( "/wiki/", "", $parts['path'] ) );
		}
	}
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_COOKIEFILE, COOKIE );
	curl_setopt( $ch, CURLOPT_COOKIEJAR, COOKIE );
	curl_setopt( $ch, CURLOPT_USERAGENT, USERAGENT );
	curl_setopt( $ch, CURLOPT_MAXCONNECTS, 100 );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
	curl_setopt( $ch, CURLOPT_ENCODING, 'gzip' );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 100 );
	curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, true );
	$get = [
		'action' => 'query',
		'titles' => $loadedArguments['pagesearch'],
		'format' => 'json'
	];
	$get = http_build_query( $get );
	curl_setopt( $ch, CURLOPT_URL, API . "?$get" );
	curl_setopt( $ch, CURLOPT_HTTPHEADER,
	             [ API::generateOAuthHeader( 'GET', API . "?$get" ) ]
	);
	curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
	curl_setopt( $ch, CURLOPT_POST, 0 );
	$data = curl_exec( $ch );
	$data = json_decode( $data, true );

	if( isset( $data['query']['pages'] ) ) {
		foreach( $data['query']['pages'] as $page ) {
			if( isset( $page['missing'] ) ) {
				if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{page404}}}", "{{{page404message}}}" );
				else {
					$jsonOut['analyzeerror'] = "404";
					$jsonOut['errormessage'] = "The page being analyzed doesn't exist.";
				}

				return false;
			} elseif( isset( $page['pageid'] ) ) {
				break;
			} else {
				if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{apierror}}}", "{{{unknownerror}}}" );
				else {
					$jsonOut['analyzeerror'] = "apierror";
					$jsonOut['errormessage'] = "An unknown MW API error occurred.";
				}

				return false;
			}
		}
	}

	$ratelimitCounter = 0;
	if( isset( $_SESSION['pageanalysislog'] ) ) foreach( $_SESSION['pageanalysislog'] as $time ) {
		if( time() - $time < 60 ) $ratelimitCounter++;
	}

	if( $ratelimitCounter >= 5 ) {
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{ratelimiterror}}}",
		                                                   "{{{ratelimiterrormessage}}}"
		);
		else {
			$jsonOut['ratelimit'] = "5/minute";
			$jsonOut['errormessage'] = "You have exceeded the max number of page runs per minute.";
		}
		header( "HTTP/1.1 429 Too Many Requests", true, 429 );

		return false;
	}

	$_SESSION['pageanalysislog'][] = time();

	$overrideConfig['notify_on_talk'] = 0;
	$overrideConfig['notify_on_talk_only'] = 0;
	if( isset( $loadedArguments['archiveall'] ) && $loadedArguments['archiveall'] == "on" ) {
		$overrideConfig['dead_only'] = 0;
		$overrideConfig['link_scan'] = 1;
		$overrideConfig['archive_alive'] = 1;
	}

	$runstart = microtime( true );

	echo "<!--\n";

	if( !API::botLogon() ) {
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{apierror}}}", "{{{sessionerror}}}" );
		else {
			$jsonOut['analyzeerror'] = "apierror";
			$jsonOut['errormessage'] = "A session error occurred.  Log out and log back into the tool.";
		}
		echo "-->\n";

		return false;
	}

	DB::checkDB();

	$config = API::fetchConfiguration();

	if( isset( $overrideConfig ) && is_array( $overrideConfig ) ) {
		foreach( $overrideConfig as $variable => $value ) {
			if( isset( $config[$variable] ) ) $config[$variable] = $value;
		}
	}

	API::escapeTags( $config );
	API::enableProfiling();

	$tmp = APIICLASS;
	$commObject = new $tmp( $page['title'], $page['pageid'], $config );
	$tmp = PARSERCLASS;
	$parser = new $tmp( $commObject );
	$runStats = $parser->analyzePage( $modifiedLinks, true );
	$commObject->closeResources();
	$parser = $commObject = null;

	API::disableProfiling( $page['pageid'], $page['title'] );

	if( isset( $locales[$userObject->getLanguage()] ) ) setlocale( LC_ALL, $locales[$userObject->getLanguage()] );

	if( $runStats !== false ) {

		$runStats['runtime'] = microtime( true ) - $runstart;

		$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "analyzepage", "analyzepage",
		                           $page['pageid'], $page['title'],
		                           $userObject->getUserLinkID(), -1, -1,
			( !empty( $loadedArguments['reason'] ) ? $loadedArguments['reason'] : "" )
		);

		if( $jsonOut === false ) $mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{analyzepagesuccess}}}"
		);
		else {
			$jsonOut['result'] = "success";
			$jsonOut = array_merge( $jsonOut, $runStats );
			$jsonOut['modifiedlinks'] = $modifiedLinks;
		}
		$userObject->setLastAction( time() );
	} else {
		if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{analysepageerrorheader}}}",
		                                                   "{{{articletoolargeerror}}}"
		);
		else {
			$jsonOut['result'] = "fail";
			$jsonOut['analyzeerror'] = "300";
			$jsonOut['errormessage'] =
				"This tool can only handle 300 elements in an article.  Please run a bot job instead.";
		}

		echo "-->\n";

		$runStats = null;

		return false;
	}

	echo "-->\n";

	return true;
}

function changeUserGroups() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML;

	if( !validateToken() ) return false;
	if( !validatePermission( "defineusergroups", true, $jsonOut ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	$userGroups = DB::getConfiguration( "global", "interface-usergroups" );

	if( isset( $userGroups[$loadedArguments['usergroupname']] ) ) {
		$toModify = $userGroups[$loadedArguments['usergroupname']];
	} else {
		$toModify = [
			'inheritsgroups' => [],
			'inheritsflags'  => [],
			'assigngroups'   => [],
			'removegroups'   => [],
			'assignflags'    => [],
			'removeflags'    => [],
			'labelclass'     => "default",
			'autoacquire'    => [
				'registered'    => 0,
				'editcount'     => 0,
				'withwikigroup' => [],
				'withwikiright' => []
			]
		];
	}

	if( !empty( $loadedArguments['inheritsgroups'] ) ) $toModify['inheritsgroups'] =
		array_map( 'trim', explode( ",", $loadedArguments['inheritsgroups'] ) );
	else $toModify['inheritsgroups'] = [];
	$toModify['inheritsflags'] = [];

	foreach( $userObject->getAllFlags() as $flag ) {
		if( isset( $loadedArguments['inherit' . $flag] ) && $loadedArguments['inherit' . $flag] == "on" )
			$toModify['inheritsflags'][] = $flag;
	}

	if( !empty( $loadedArguments['assigngroups'] ) ) $toModify['assigngroups'] =
		array_map( 'trim', explode( ",", $loadedArguments['assigngroups'] ) );
	else $toModify['assigngroups'] = [];
	if( !empty( $loadedArguments['removegroups'] ) ) $toModify['removegroups'] =
		array_map( 'trim', explode( ",", $loadedArguments['removegroups'] ) );
	else $toModify['removegroups'] = [];
	$toModify['assignflags'] = [];

	foreach( $userObject->getAllFlags() as $flag ) {
		if( isset( $loadedArguments['grant' . $flag] ) && $loadedArguments['grant' . $flag] == "on" )
			$toModify['assignflags'][] = $flag;
	}

	$toModify['removeflags'] = [];

	foreach( $userObject->getAllFlags() as $flag ) {
		if( isset( $loadedArguments['remove' . $flag] ) && $loadedArguments['remove' . $flag] == "on" )
			$toModify['removeflags'][] = $flag;
	}

	if( isset( $loadedArguments['groupclass'] ) ) switch( $loadedArguments['groupclass'] ) {
		case "danger":
		case "info":
		case "primary":
		case "warning":
		case "success":
			$toModify['labelclass'] = $loadedArguments['groupclass'];
			break;
		default:
			$toModify['labelclass'] = "default";
			break;
	}

	if( empty( $loadedArguments['registeredlatest'] ) || strtotime( $loadedArguments['registeredlatest'] ) === false )
		$toModify['autoacquire']['registered'] = 0;
	else $toModify['autoacquire']['registered'] = $loadedArguments['registeredlatest'];

	if( empty( $loadedArguments['lowesteditcount'] ) || !is_numeric( $loadedArguments['lowesteditcount'] ) )
		$toModify['autoacquire']['editcount'] = 0;
	else $toModify['autoacquire']['editcount'] = (int) $loadedArguments['lowesteditcount'];

	if( !empty( $loadedArguments['withwikigroup'] ) ) $toModify['autoacquire']['withwikigroup'] =
		array_map( 'trim', explode( ",", $loadedArguments['withwikigroup'] ) );
	else $toModify['autoacquire']['withwikigroup'] = [];
	if( !empty( $loadedArguments['withwikiright'] ) ) $toModify['autoacquire']['withwikiright'] =
		array_map( 'trim', explode( ",", $loadedArguments['withwikiright'] ) );
	else $toModify['autoacquire']['withwikiright'] = [];
	if( isset( $loadedArguments['anonymous'] ) && $loadedArguments['anonymous'] == "on" ) $toModify['anonymous'] = true;
	else unset( $toModify['anonymous'] );

	$res = DB::setConfiguration( "global", "interface-usergroups", $loadedArguments['usergroupname'], $toModify );

	$defined = $userObject->defineGroups( true );
	if( $res ) {
		if( $defined ) {
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{definegroupssuccess}}}" );
			$dbObject->insertLogEntry( "global", WIKIPEDIA, "usergroups", "define",
			                           $dbObject->getInsertID(), "",
			                           $userObject->getUserLinkID(), null, null, ""
			);
		} else $mainHTML->setMessageBox( "warning", "{{{successheader}}}", "{{{definegroupswarn}}}" );
		$userObject->setLastAction( time() );
	} else {
		$mainHTML->setMessageBox( "danger", "{{{definefroupsfailheader}}}", "{{{definegroupsfail}}}" );
	}

	return $res;
}

function changeArchiveRules() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $accessibleWikis, $enableAPILogging, $oauthKeys, $wikiDBs;

	if( !validateToken() ) return false;
	if( !validatePermission( "defineusergroups", true, $jsonOut ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	$archiveTemplates = DB::getConfiguration( "global", "archive-templates" );

	if( isset( $archiveTemplates[$loadedArguments['templatename']] ) ) {
		$toModify = $archiveTemplates[$loadedArguments['templatename']];
	} else {
		$toModify = [
			'templatebehavior'           => "append",
			'archivetemplatedefinitions' => ""
		];
	}

	if( !empty( $loadedArguments['templatebehavior'] ) ) {
		switch( $loadedArguments['templatebehavior'] ) {
			case "swallow":
			case "append":
				$toModify['templatebehavior'] = $loadedArguments['templatebehavior'];
				break;
			default:
				$toModify['templatebehavior'] = "append";
				break;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}", "{{{missingdata}}}" );

		return false;
	}

	if( !empty( $loadedArguments['archivetemplatedefinitions'] ) ) {
		$toModify['archivetemplatedefinitions'] =
			DataGenerator::renderTemplateData( $loadedArguments['archivetemplatedefinitions'],
			                                $loadedArguments['templatename'], true
			);
		if( $toModify['archivetemplatedefinitions'] === false ) {
			$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{archivetemplatesyntaxerror}}}" );

			return false;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}", "{{{missingdata}}}" );

		return false;
	}

	$res = DB::setConfiguration( "global", "archive-templates", $loadedArguments['templatename'], $toModify );

	if( $res ) {
		$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{archivedefinesuccess}}}" );
		$userObject->setLastAction( time() );
	} else {
		$mainHTML->setMessageBox( "danger", "{{{dberror}}}", "{{{unknownerror}}}" );
	}

	return $res;
}

function importCiteRules( $calledFromParent = false ) {
	global $userObject, $mainHTML;

	if( !validateToken() ) return false;
	if( !validatePermission( "configurecitationrules", true ) ) return false;
	if( $calledFromParent === false && !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	$citoidData = API::retrieveCitoidDefinitions();

	$templateDefinitions = DB::getConfiguration( "global", "citation-rules" );

	if( isset( $citoidData['unique_templates'] ) ) {
		foreach( $citoidData['unique_templates'] as $template ) {
			if( isset( $citoidData['template_data'][$template]['maps']['citoid']['url'] ) &&
			    !in_array( "{{{$template}}}", $templateDefinitions['template-list'] ) ) {
				$templateDefinitions['template-list'][] = "{{{$template}}}";
				if( !isset( $templateDefinitions[$template]['existsOn'] ) ||
				    !in_array( WIKIPEDIA, $templateDefinitions[$template]['existsOn'] ) )
					$templateDefinitions[$template]['existsOn'][] = WIKIPEDIA;
			}
		}

		sort( $templateDefinitions['template-list'] );
	}

	if( isset( $citoidData['mapped_templates']['webpage'] ) ) $templateDefinitions[WIKIPEDIA]['default-template'] =
		$citoidData['mapped_templates']['webpage'];

	foreach( $templateDefinitions['template-list'] as $template ) {
		$template = trim( $template, "{}" );
		if( !isset( $citoidData['template_data'][$template] ) ) $citoidData['template_data'][$template] =
			API::getTemplateData( $template );

		if( $citoidData['template_data'][$template] !== false &&
		    ( !isset( $templateDefinitions[$template]['existsOn'] ) ||
		      !in_array( WIKIPEDIA, $templateDefinitions[$template]['existsOn'] ) ) )
			$templateDefinitions[$template]['existsOn'][] = WIKIPEDIA;
		elseif( $citoidData['template_data'][$template] === false ) {
			if( in_array( WIKIPEDIA, $templateDefinitions[$template]['existsOn'] ) ) {
				unset( $templateDefinitions[$template]['existsOn'][array_search( WIKIPEDIA,
				                                                                 $templateDefinitions[$template]['existsOn']
					)]
				);
			}
			unset( $templateDefinitions[$template][WIKIPEDIA] );
			continue;
		}

		if( isset( $citoidData['template_data'][$template]['params'] ) ) $params =
			$citoidData['template_data'][$template]['params'];
		else $params = [];

		if( isset( $citoidData['template_data'][$template]['maps']['citoid'] ) ) $citoid =
			$citoidData['template_data'][$template]['maps']['citoid'];
		else $citoid = [];

		foreach( $templateDefinitions[$template]['existsOn'] as $wiki ) {
			if( !isset( $templateDefinitions[$template][$wiki]['redirect'] ) && $wiki != WIKIPEDIA ) continue;

			if( !empty( $templateDefinitions[$template][$wiki]['mapString'] ) ) $mapString =
				$templateDefinitions[$template][$wiki]['mapString'];
			elseif( !empty( $templateDefinitions[$wiki]['default-mapString'] ) ) $mapString =
				$templateDefinitions[$wiki]['default-mapString'];
			else $mapString = "";

			if( $mapString == "NULL" ) {
				if( in_array( $wiki, $templateDefinitions[$template]['existsOn'] ) ) {
					unset( $templateDefinitions[$template]['existsOn'][array_search( $wiki,
					                                                                 $templateDefinitions[$template]['existsOn']
						)]
					);
				}
				continue;
			}

			$isRedirect = preg_match( '/\#REDIRECT\[\[(.*?)\]\]/i', $mapString, $redirectTo );

			if( $isRedirect ) {
				$templateDefinitions[$template][$wiki]['redirect'] = $redirectTo[1];

				$test = $templateDefinitions[$template][$wiki];

				while( isset( $test['redirect'] ) ) {
					if( isset( $templateDefinitions[$template][$test['redirect']] ) ) {
						$target = $test['redirect'];
						$test = $templateDefinitions[$template][$test['redirect']];
					} else {
						$target = "";
						break;
					}
				}

				if( !empty( $templateDefinitions[$template][$target]['mapString'] ) ) $mapString =
					$templateDefinitions[$template][$target]['mapString'];
				elseif( !empty( $templateDefinitions[$target]['default-mapString'] ) ) $mapString =
					$templateDefinitions[$target]['default-mapString'];
				else $mapString = "";
			} else {
				unset( $templateDefinitions[$template][$wiki]['redirect'] );
			}

			if( $wiki == WIKIPEDIA ) $tmp = DataGenerator::processCiteTemplateData( $params, $citoid, $mapString );
			else {
				if( !empty( $templateDefinitions[$template][$wiki]['citoid'] ) ) $tcitoid =
					$templateDefinitions[$template][$wiki]['citoid'];
				else $tcitoid = [];

				if( !empty( $templateDefinitions[$template][$wiki]['template_params'] ) ) $tParams =
					$templateDefinitions[$template][$wiki]['template_params'];
				else $tParams = [];

				$tmp = DataGenerator::processCiteTemplateData( $tParams, $tcitoid, $mapString );
			}

			if( empty( $templateDefinitions[$template][$wiki] ) ) $templateDefinitions[$template][$wiki] = [];
			if( !empty( $tmp ) ) $templateDefinitions[$template][$wiki] =
				array_merge( $templateDefinitions[$template][$wiki], $tmp );
			elseif( !empty( $mapString ) ) {
				if( isset( $templateDefinitions[$template][$wiki]['mapString'] ) ) unset( $templateDefinitions[$template][$wiki]['mapString'] );
				elseif( !empty( $templateDefinitions[$wiki]['default-mapString'] ) ) unset( $templateDefinitions[$wiki]['default-mapString'] );
			}
		}


	}

	$res = true;

	foreach( $templateDefinitions as $key => $data ) {
		$res = $res && DB::setConfiguration( "global", "citation-rules", $key, $data );
	}

	if( $res ) {
		$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{configsuccess}}}" );
		$userObject->setLastAction( time() );
	} else {
		$mainHTML->setMessageBox( "success", "{{{dberror}}}", "{{{unknownerror}}}" );
	}

	return $res;
}

function updateCiteRules() {
	global $loadedArguments, $userObject, $mainHTML;

	if( !validateToken() ) return false;
	if( !validatePermission( "configurecitationrules", true ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	if( !empty( $loadedArguments['whichForm'] ) ) {
		if( $loadedArguments['whichForm'] == 1 ) {
			$citeList = array_unique( explode( "\n", $loadedArguments['cite_list'] ) );
			sort( $citeList );
			$res = DB::setConfiguration( "global", "citation-rules", "template-list", $citeList );
		} elseif( $loadedArguments['whichForm'] == 2 ) {
			$templateDefinitions = DB::getConfiguration( "global", "citation-rules" );

			$res = true;
			foreach( $templateDefinitions['template-list'] as $template ) {
				$template = trim( $template, "{}" );
				$htmlTemplate = str_replace( " ", "_", $template );

				if( isset( $templateDefinitions[$template]['existsOn'] ) &&
				    in_array( WIKIPEDIA, $templateDefinitions[$template]['existsOn'] ) ) {
					if( isset( $loadedArguments[$htmlTemplate] ) ) {
						$toUpdate = $templateDefinitions[$template];
						$toUpdate[WIKIPEDIA]['mapString'] = $loadedArguments[$htmlTemplate];
						$res = $res && DB::setConfiguration( "global", "citation-rules", $template, $toUpdate );
					}
				}
			}
		} elseif( $loadedArguments['whichForm'] == 3 ) {
			if( !empty( $loadedArguments['defaultCite'] ) ) $toUpdate['default-template'] =
				$loadedArguments['defaultCite'];
			if( !empty( $loadedArguments['defaultMap'] ) ) {
				$toUpdate['default-mapString'] = $loadedArguments['defaultMap'];
			} else $res = true;
			if( !empty( $loadedArguments['defaultArchiveTitle'] ) ) $toUpdate['default-title'] =
				$loadedArguments['defaultArchiveTitle'];

			$res = DB::setConfiguration( "global", "citation-rules", WIKIPEDIA, $toUpdate );
		} else {
			$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{unknownerror}}}" );

			return false;
		}

		$res = $res && importCiteRules( true );

		if( $res === true ) {
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{configsuccess}}}" );
			$userObject->setLastAction( time() );
		} else {
			$mainHTML->setMessageBox( "danger", "{{{dberror}}}", "{{{unknownerror}}}" );
		}

		return $res;

	} else {
		$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{unknownerror}}}" );

		return false;
	}

	$mainHTML->setMessageBox( "danger", "{{{dberror}}}", "{{{unknownerror}}}" );

	return false;
}

function changeConfiguration() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $accessibleWikis, $enableAPILogging, $oauthKeys, $wikiDBs;

	if( empty( $loadedArguments['setuptype'] ) ) {
		$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{unknownerror}}}" );

		return false;
	}

	if( !validateToken() ) return false;
	if( $loadedArguments['setuptype'] == "setup1" ) {
		if( !validatePermission( "configuresystemsetup", true, $jsonOut ) ) return false;
	} elseif( $loadedArguments['setuptype'] == "setup2" ) {
		if( !validatePermission( "definewiki", true, $jsonOut ) ) return false;
	} elseif( $loadedArguments['setuptype'] == "wikiconfig" ) {
		if( !validatePermission( "configurewiki", true, $jsonOut ) ) return false;
	} else {
		$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{unknownerror}}}" );

		return false;
	}
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	if( $loadedArguments['setuptype'] == "setup1" ) {
		unset( $loadedArguments['setuptype'], $loadedArguments['action'], $loadedArguments['token'], $loadedArguments['checksum'] );
		$typeCast = [
			'disableEdits'     => 'bool', 'userAgent' => 'string', 'cidUserAgent' => 'string', 'taskname' => 'string',
			'enableAPILogging' => 'bool',
			'expectedValue'    => 'string', 'decodeFunction' => 'string', 'enableMail' => 'bool',
			'to'               => 'string', 'from' => 'string', 'useCIDservers' => 'bool', 'cidServers' => 'string',
			'cidAuthCode'      => 'string', 'enableProfiling' => 'bool', 'defaultWiki' => 'string',
			'autoFPReport'     => 'bool', 'guifrom' => 'string', 'guidomainroot' => 'string',
			'disableInterface' => 'bool'
		];

		foreach( $typeCast as $key => $type ) {
			if( empty( $loadedArguments[$key] ) ) {
				switch( $key ) {
					case "expectedValue":
					case "decodeFunction":
						if( $loadedArguments['enableAPILogging'] == 1 ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
						break;
					case "to":
					case "from":
						if( $loadedArguments['enableMail'] == 1 ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
						break;
					case "cidServers":
					case "cidAuthCode":
						if( $loadedArguments['useCIDservers'] == 1 ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
						break;
					case "disableEdits":
					case "disableInterface":
					case "enableAPILogging":
					case "useCIDservers":
					case "enableMail":
					case "enableProfiling":
					case "autoFPReport":
						if( !isset( $loadedArguments[$key] ) ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
						break;
					default:
						$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
						                          "{{{missingdata}}}"
						);

						return false;
				}
			}
			switch( $type ) {
				case 'bool':
					$loadedArguments[$key] = (bool) $loadedArguments[$key];
					break;
				case 'int':
					$loadedArguments[$key] = (int) $loadedArguments[$key];
					break;
				case 'string':
					$loadedArguments[$key] = (string) $loadedArguments[$key];
					break;
				case 'float':
					$loadedArguments[$key] = (float) $loadedArguments[$key];
					break;
			}
		}

		if( !isset( $accessibleWikis[$loadedArguments['defaultWiki']] ) ) {
			$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}",
			                          "{{{defaultwikierror}}}"
			);

			return false;
		}

		$configuration1 = [];
		foreach( $loadedArguments as $key => $value ) {
			if( $key == "cidServers" ) {
				$value = explode( " ", $value );
			}
			$res = DB::setConfiguration( "global", "systemglobals", $key, $value );
			$configuration1[$key] = $value;
		}

		if( $res === true ) {
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{configsuccess}}}" );
			$userObject->setLastAction( time() );
		} else {
			$mainHTML->setMessageBox( "success", "{{{dberror}}}", "{{{unknownerror}}}" );
		}

		return $res;
	} elseif( $loadedArguments['setuptype'] == "setup2" ) {
		unset( $loadedArguments['setuptype'], $loadedArguments['action'], $loadedArguments['token'], $loadedArguments['checksum'] );
		$typeCast = [
			'wikiName'  => 'string', 'i18nsource' => 'string', 'i18nsourcename' => 'string', 'language' => 'string',
			'rooturl'   => 'string', 'apiurl' => 'string', 'oauthurl' => 'string',
			'runpage'   => 'bool', 'nobots' => 'bool', 'apiCall' => 'string', 'usekeys' => 'string',
			'usewikidb' => 'string'
		];
		if( !isset( $loadedArguments['wikiName'] ) ) $loadedArguments['wikiName'] = $loadedArguments['wikiNameFrom'];
		if( empty( $loadedArguments['usewikidb'] ) ) {
			$typeCast['usewikidb'] = "bool";
		}
		foreach( $typeCast as $key => $type ) {
			if( empty( $loadedArguments[$key] ) ) {
				switch( $key ) {
					case "apiCall":
						if( $enableAPILogging !== true ) break;
						else {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
					case "runpage":
					case "nobots":
					case "usewikidb":
						if( !isset( $loadedArguments[$key] ) ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
						break;
					default:
						$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
						                          "{{{missingdata}}}"
						);

						return false;
				}
			}
			switch( $type ) {
				case 'bool':
					$loadedArguments[$key] = (bool) $loadedArguments[$key];
					break;
				case 'int':
					$loadedArguments[$key] = (int) $loadedArguments[$key];
					break;
				case 'string':
					$loadedArguments[$key] = (string) $loadedArguments[$key];
					break;
				case 'float':
					$loadedArguments[$key] = (float) $loadedArguments[$key];
					break;
			}
		}
		if( !isset( $oauthKeys[$loadedArguments['usekeys']] ) ) {
			$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{unknownerror}}}" );

			return false;
		}
		if( !isset( $wikiDBs[$loadedArguments['usewikidb']] ) ) {
			$loadedArguments['usewikidb'] = "0";
		}
		if( $loadedArguments['usewikidb'] == "0" ) $loadedArguments['usewikidb'] = false;

		$res =
			DB::setConfiguration( "global", "systemglobals-allwikis", $loadedArguments['wikiName'], $loadedArguments );
		$res = $res && DB::setConfiguration( $loadedArguments['wikiName'], "wikiconfig", "runpage", "disable" );

		if( $res === true ) {
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{configsuccess}}}" );
			$userObject->setLastAction( time() );
		} else {
			$mainHTML->setMessageBox( "success", "{{{dberror}}}", "{{{unknownerror}}}" );
		}

		return $res;
	} elseif( $loadedArguments['setuptype'] == "wikiconfig" ) {
		unset( $loadedArguments['setuptype'], $loadedArguments['action'], $loadedArguments['token'], $loadedArguments['checksum'] );
		$typeCast = [
			'link_scan'                 => 'bool', 'dead_only' => 'int', 'tag_override' => 'bool',
			'page_scan'                 => 'bool',
			'archive_by_accessdate'     => 'bool', 'touch_archive' => 'bool', 'notify_on_talk' => 'bool',
			'notify_on_talk_only'       => 'int', 'notify_error_on_talk' => 'bool', 'talk_message_verbose' => 'bool',
			'talk_message_header'       => 'string',
			'talk_message'              => 'string', 'talk_message_header_talk_only' => 'string',
			'talk_message_talk_only'    => 'string', 'talk_error_message_header' => 'string',
			'talk_error_message'        => 'string', 'ignore_tags' => 'string', 'talk_only_tags' => 'string',
			'no_talk_tags'              => 'string', 'paywall_tags' => 'string', 'deadlink_tags' => 'string',
			'verify_dead'               => 'bool', 'archive_alive' => 'bool', 'convert_archives' => 'bool',
			'convert_archives_encoding' => 'bool', 'convert_to_cites' => 'bool', 'mladdarchivetalkonly' => 'string',
			'mltaggedtalkonly'          => 'string', 'mltagremovedtalkonly' => 'string', 'mladdarchive' => 'string',
			'mlmodifyarchive'           => 'string', 'mlfix' => 'string', 'mltagged' => 'string',
			'mltagremoved'              => 'string', 'mldefault' => 'string', 'plerror' => 'string',
			'maineditsummary'           => 'string', 'errortalkeditsummary' => 'string', 'talkeditsummary' => 'string',
			'notify_domains'            => 'string', 'deadlink_tags_data' => 'string', 'templatebehavior' => 'string',
			'dateformat'                => 'string', 'tag_cites' => 'bool', 'ref_tags' => 'string'
		];

		$archiveTemplates = DB::getConfiguration( "global", "archive-templates" );
		$configuration = [];

		foreach( $archiveTemplates as $name => $templateData ) {
			$name = str_replace( " ", "_", $name );
			if( !empty( $loadedArguments[$name] ) ) {
				$tmp = array_map( "trim", explode( ",", $loadedArguments[$name] ) );
				foreach( $tmp as $template ) {
					if( substr( $template, 0, 2 ) != "{{" || substr( $template, strlen( $template ) - 2, 2 ) != "}}" ) {
						$mainHTML->setMessageBox( "success", "{{{configerrorheader}}}", "{{{syntaxerror}}}" );

						return false;
					}
				}
				$configuration["darchive_$name"] = $tmp;
				if( isset( $loadedArguments["deprecated$name"] ) && $loadedArguments["deprecated$name"] == "on" ) {
					$configuration['deprecated_archives'][] = $name;
				}
			}
		}
		foreach( $typeCast as $key => $type ) {
			switch( $key ) {
				case "dead_only":
				case "notify_on_talk_only":
					if( $loadedArguments[$key] > 2 || $loadedArguments[$key] < 0 ) {
						$mainHTML->setMessageBox( "danger", "{{{configerrorheader}}}", "{{{unknownerror}}}" );

						return false;
					}
					break;
			}
			if( empty( $loadedArguments[$key] ) ) {
				switch( $key ) {
					case "ignore_tags":
					case "talk_only_tags":
					case "no_talk_tags":
					case "paywall_tags":
					case "deadlink_tags":
					case "ref_tags":
					case "notify_domains":
						$configuration[$key] = [];
						break;
					case "deadlink_tags_data":
						$configuration[$key] = null;
						break;
					case "link_scan":
					case "dead_only":
					case "tag_override":
					case "page_scan":
					case "archive_by_accessdate":
					case "touch_archive":
					case "notify_on_talk":
					case "notify_on_talk_only":
					case "notify_error_on_talk":
					case "talk_message_verbose":
					case "verify_dead":
					case "archive_alive":
					case "convert_archives":
					case "convert_archives_encoding":
					case "convert_to_cites":
					case "tag_cites":
						if( !isset( $loadedArguments[$key] ) ) {
							$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
							                          "{{{missingdata}}}"
							);

							return false;
						}
						break;
					default:
						$mainHTML->setMessageBox( "danger", "{{{missingdataheader}}}",
						                          "{{{missingdata}}}"
						);

						return false;
				}
			}
			if( isset( $loadedArguments[$key] ) && !array_key_exists( $key, $configuration ) ) switch( $type ) {
				case 'bool':
					$configuration[$key] = (int) (bool) $loadedArguments[$key];
					break;
				case 'int':
					$configuration[$key] = (int) $loadedArguments[$key];
					break;
				case 'string':
					if( $key == "dateformat" ) {
						$dateformat = explode( "\n", $loadedArguments['dateformat'] );
						$processedSyntax = [];
						foreach( $dateformat as $logic ) {
							$saveDefault = false;
							if( substr( $logic, 0, 9 ) == "@default:" ) {
								$logic = trim( substr_replace( $logic, "", 0, 9 ) );
								$saveDefault = true;
							}
							$logic = explode( " on match ", $logic );

							if( $saveDefault === true ) {
								if( isset( $logic[1] ) ) $processedSyntax['@default'] =
									[ 'format' => $logic[0], 'regex' => $logic[1] ];
								else $processedSyntax['@default'] = [ 'format' => $logic[0] ];
							} else {
								if( isset( $logic[1] ) ) $processedSyntax[] =
									[ 'format' => $logic[0], 'regex' => $logic[1] ];
								else $processedSyntax[] = [ 'format' => $logic[0] ];
							}
						}
						$configuration[$key]['raw_syntax'] = $loadedArguments['dateformat'];
						$configuration[$key]['syntax'] = $processedSyntax;
						break;
					}
					if( ( strpos( $key, "tags" ) !== false && $key != "deadlink_tags_data" ) ||
					    $key == "notify_domains" ) $configuration[$key] =
						array_map( "trim", explode( ",", $loadedArguments[$key] ) );
					elseif( $key == "deadlink_tags_data" ) {
						$configuration[$key] =
							DataGenerator::renderTemplateData( $loadedArguments[$key], "", true, "dead" );
						if( $configuration[$key] === false ) unset( $configuration[$key] );
					} else $configuration[$key] = (string) $loadedArguments[$key];
					break;
			}
		}

		$res = true;
		foreach( $configuration as $key => $value ) {
			$res = $res && DB::setConfiguration( WIKIPEDIA, "wikiconfig", $key, $value );
		}

		if( $res === true ) {
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{configsuccess}}}" );
			$userObject->setLastAction( time() );

			/*$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "wikiconfig", "change",
			                           $dbObject->getInsertID(), "",
			                           $userObject->getUserLinkID(), null, null, ""
			);*/
		} else {
			$mainHTML->setMessageBox( "success", "{{{dberror}}}", "{{{unknownerror}}}" );
		}
	}
}

function submitBotJob( &$jsonOut = false ) {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $runStats;

	if( $jsonOut !== false ) $jsonOut['result'] = "fail";
	if( !validateToken( $jsonOut ) ) return false;
	if( !validatePermission( "submitbotjobs", true, $jsonOut ) ) return false;
	if( !validateChecksum( $jsonOut ) ) return false;
	if( !validateNotBlocked( $jsonOut ) ) return false;

	if( !empty( $loadedArguments['pagelist'] ) ) {

		$pages = array_unique( explode( "\n", trim( $loadedArguments['pagelist'] ) ) );

		$filteredPages = [];
		foreach( $pages as $page ) {
			if( !in_array( ucfirst( $page ), $filteredPages ) ) {
				$filteredPages[] = ucfirst( $page );
			}
		}
		$pages = $filteredPages;

		foreach( $pages as $page ) {
			if( strpos( $page, ":" ) !== false ) {
				if( $catPages = API::getArticlesFromCategory( [ $page ] ) ) {
					foreach( $catPages as $catPage ) {
						if( !in_array( ucfirst( $catPage['title'] ), $pages ) ) {
							$pages[] = ucfirst( $catPage['title'] );
						}
					}
					unset( $pages[array_search( $page, $pages )] );
				}
			}
		}

		if( count( $pages ) > 50000 && !validatePermission( "botsubmitlimitnolimit", true, $jsonOut ) ) {
			return false;
		} elseif( count( $pages ) > 5000 && !validatePermission( "botsubmitlimit50000", true, $jsonOut ) ) {
			return false;
		} elseif( count( $pages ) > 500 && !validatePermission( "botsubmitlimit5000", true, $jsonOut ) ) {
			return false;
		}

		$sql = "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_user` = " .
		       $userObject->getUserLinkID() . " AND (`queue_status` < 2 OR `queue_status` = 4);";
		$res = $dbObject->queryDB( $sql );
		$count = mysqli_fetch_assoc( $res );
		mysqli_free_result( $res );
		if( $count['count'] >= 5 ) {
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{ratelimiterror}}}",
			                                                   "{{{botqueuerateexceeded}}}"
			);
			else {
				$jsonOut['ratelimit'] = "5 active jobs/user";
				$jsonOut['errormessage'] = "Users are only allowed a maximum of 5 active or queued jobs at a time.";
			}
			header( "HTTP/1.1 429 Too Many Requests", true, 429 );

			return false;
		}

		foreach( $pages as $page ) {
			$queuePages[] = [ 'title' => trim( $page ), 'status' => "wait" ];
		}

		$runStats = [
			'linksanalyzed' => 0,
			'linksarchived' => 0,
			'linksrescued'  => 0,
			'linkstagged'   => 0,
			'pagesModified' => 0,
			'pagesanalyzed' => 0,
			'waybacksadded' => 0,
			'othersadded'   => 0
		];

		$totalPages = count( $pages );

		$queueSQL =
			"INSERT INTO externallinks_botqueue (`wiki`, `queue_user`, `run_stats`, `worker_target`) VALUES ('" .
			WIKIPEDIA . "', " . $userObject->getUserLinkID() . ", '" . $dbObject->sanitize( serialize( $runStats ) ) .
			"', $totalPages );";

		if( $dbObject->queryDB( $queueSQL ) ) {
			$loadedArguments['id'] = $dbObject->getInsertID();
			$queueSQL = "INSERT INTO externallinks_botqueuepages (`queue_id`, `page_title`) VALUES ";
			foreach( $pages as $page )
				$queueSQL .= "('" . $loadedArguments['id'] . "', '" . $dbObject->sanitize( trim( $page ) ) . "'),";
			$queueSQL = substr( $queueSQL, 0, strlen( $queueSQL ) - 1 ) . ";";
			$dbObject->queryDB( $queueSQL );
			$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "bqchangestatus", "submit",
			                           $loadedArguments['id'], "",
			                           $userObject->getUserLinkID(), null, null, ""
			);

			if( $jsonOut === false ) $mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{botqueuesuccess}}}"
			);
			else {
				$jsonOut['result'] = "success";
			}
			$userObject->setLastAction( time() );
			loadJobViewer( $jsonOut );

			return true;
		} else {
			if( $jsonOut === false ) $mainHTML->setMessageBox( "danger", "{{{bqsubmiterror}}}", "{{{unknownerror}}}" );
			else {
				$jsonOut['bqsubmiterror'] = "unknownerror";
				$jsonOut['errormessage'] = "An unknown error occurred.";
			}

			return false;
		}
	} elseif( $jsonOut !== false ) {
		$jsonOut['missingvalue'] = "pagelist";
		$jsonOut['errormessage'] =
			"This parameter is a newline separated list of page titles as recognized by the MW parser that is required for this request.";
	}
}
