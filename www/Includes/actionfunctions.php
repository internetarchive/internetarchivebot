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
		$bodyObject->assignAfterElement( "locale", $accessibleWikis[WIKIPEDIA]['name'] );
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
					$bodyObject->assignAfterElement( "locale", $accessibleWikis[WIKIPEDIA]['name'] );
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
				$bodyObject->assignAfterElement( "locale", $accessibleWikis[WIKIPEDIA]['name'] );
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
					$bodyObject->assignAfterElement( "locale", $accessibleWikis[WIKIPEDIA]['name'] );
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
					$bodyObject->assignAfterElement( "locale", $accessibleWikis[WIKIPEDIA]['name'] );
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
	if( isset( $loadedArguments['forceall'] ) && isset( $loadedArguments['forceallconfirm'] ) ) $sql =
		"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id=externallinks_global.url_id LEFT JOIN externallinks_user ON externallinks_fpreports.report_user_id=externallinks_user.user_link_id AND externallinks_fpreports.wiki=externallinks_user.wiki LEFT JOIN externallinks_paywall on externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `report_status` = '0';";
	else $sql =
		"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id=externallinks_global.url_id LEFT JOIN externallinks_user ON externallinks_fpreports.report_user_id=externallinks_user.user_link_id AND externallinks_fpreports.wiki=externallinks_user.wiki LEFT JOIN externallinks_paywall on externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `report_status` = '0' AND NOT `report_version` = '" .
		CHECKIFDEADVERSION . "';";
	$res = $dbObject->queryDB( $sql );
	if( ( $result = mysqli_fetch_all( $res, MYSQLI_ASSOC ) ) !== false ) {
		$mailinglist = [];
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
			foreach( $result as $reportedFP ) {
				$counter++;
				unset( $result[$id] );
				if( $reportedFP['paywall_status'] == 3 || $reportedFP['live_state'] == 7 ||
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
				$bodyObject->assignAfterElement( "locale", $accessibleWikis[$userObject2->getWiki()]['name'] );
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

function reportFalsePositive() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject;
	if( !validateToken() ) return false;
	if( !validatePermission( "reportfp" ) ) return false;
	$checksum = $oauthObject->getChecksumToken();
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	if( isset( $_SESSION['precheckedfplistsrorted'] ) ) {
		if( $_SESSION['precheckedfplistsrorted']['toreporthash'] ==
		    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['toreport'] ) ) &&
		    $_SESSION['precheckedfplistsrorted']['toreporterrorshash'] ==
		    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['toreporterrors'] )
		    ) &&
		    $_SESSION['precheckedfplistsrorted']['toresethash'] ==
		    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['toreset'] ) ) &&
		    $_SESSION['precheckedfplistsrorted']['alreadyreportedhash'] ==
		    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['alreadyreported'] )
		    ) &&
		    $_SESSION['precheckedfplistsrorted']['notfoundhash'] ==
		    md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $_SESSION['precheckedfplistsrorted']['notfound'] ) ) &&
		    $_SESSION['precheckedfplistsrorted']['finalhash'] ==
		    md5( $_SESSION['precheckedfplistsrorted']['toreporthash'] .
		         $_SESSION['precheckedfplistsrorted']['toreporterrorshash'] .
		         $_SESSION['precheckedfplistsrorted']['toresethash'] .
		         $_SESSION['precheckedfplistsrorted']['alreadyreportedhash'] .
		         $_SESSION['precheckedfplistsrorted']['notfoundhash'] . $checksum
		    )
		) {
			$URLCache = [];
			$toReport = $_SESSION['precheckedfplistsrorted']['toreport'];
			$errors = $_SESSION['precheckedfplistsrorted']['toreporterrors'];
			$toReset = $_SESSION['precheckedfplistsrorted']['toreset'];
			if( empty( $toReport ) && empty( $toReset ) ) {
				$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{nofpurlerror}}}" );

				return false;
			}
			$escapedURLs = [];
			foreach( array_merge( $toReset, $toReport ) as $url ) {
				$escapedURLs[] = $dbObject->sanitize( $url );
			}
			$sql = "SELECT * FROM externallinks_global WHERE `url` IN ( '" . implode( "', '", $escapedURLs ) . "' );";
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
				$sql = "UPDATE externallinks_global SET `live_state` = 3 WHERE `url_id` IN ( " .
				       implode( ", ", $escapedURLs ) . " );";
				if( $dbObject->queryDB( $sql ) ) {
					foreach( $toReset as $reset ) {
						$dbObject->insertLogEntry( "global", WIKIPEDIA, "urldata", "changestate",
						                           $URLCache[$reset]['url_id'],
						                           $reset, $userObject->getUserLinkID(), 0, 3
						);
					}
				} else {
					$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );

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
			$mainHTML->setMessageBox( "success", "{{{doneheader}}}", "{{{fpreportsuccess}}}" );

			return true;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );

			return false;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{reportfperror}}}", "{{{unknownerror}}}" );

		return false;
	}
	/* Disabled for now
	$schemelessURLRegex = '(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\/\?\#\[\]]+)*\/?(?:\?[^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';
	if( isset( $loadedArguments['fplist'] ) && !empty( $loadedArguments['fplist'] ) ) {
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
	*/
}

function changePreferences() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject, $interfaceLanguages, $accessibleWikis;
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
	if( isset( $loadedArguments['user_global_language'] ) ) {
		if( isset( $interfaceLanguages[$loadedArguments['user_global_language']] ) ) {
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

function changeURLData() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $oauthObject;
	if( !validateToken() ) return false;
	if( !validatePermission( "changeurldata" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();

	if( isset( $loadedArguments['urlid'] ) && !empty( $loadedArguments['urlid'] ) ) {
		$sqlURL =
			"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url_id` = '" .
			$dbObject->sanitize( $loadedArguments['urlid'] ) . "';";
		if( ( $res = $dbObject->queryDB( $sqlURL ) ) && ( $result = mysqli_fetch_assoc( $res ) ) ) {
			$loadedArguments['url'] = $result['url'];
			$toChange = [];
			if( isset( $loadedArguments['accesstime'] ) &&
			    date( 'H\:i j F Y', strtotime( $loadedArguments['accesstime'] ) ) !=
			    date( 'H\:i j F Y', strtotime( $result['access_time'] ) )
			) {
				if( validatePermission( "alteraccesstime" ) ) {
					if( strtotime( $loadedArguments['accesstime'] ) === false ||
					    strtotime( $loadedArguments['accesstime'] ) < 978307200
					) {
						$mainHTML->setMessageBox( "danger", "{{{urldataerror}}}", "{{{urlaccesstimeillegal}}}" );

						return false;
					}
					$toChange['access_time'] = date( 'Y-m-d H:i:s', strtotime( $loadedArguments['accesstime'] ) );
				} else {
					return false;
				}
			}
			if( isset( $loadedArguments['livestateselect'] ) &&
			    $loadedArguments['livestateselect'] != $result['live_state']
			) {
				switch( $result['paywall_status'] ) {
					case 1:
					case 2:
					case 3:
						$mainHTML->setMessageBox( "danger", "{{{urldataerror}}}", "{{{urlpaywallillegal}}}" );

						return false;
				}
				switch( $result['live_state'] ) {
					case 6:
						if( !validatePermission( "deblacklisturls" ) ) return false;
						break;
					case 7:
						if( !validatePermission( "dewhitelisturls" ) ) return false;
						break;
				}
				switch( $loadedArguments['livestateselect'] ) {
					case 0:
					case 3:
					case 5:
						$toChange['live_state'] = $loadedArguments['livestateselect'];
						break;
					case 6:
						if( !validatePermission( "blacklisturls" ) ) return false;
						$toChange['live_state'] = $loadedArguments['livestateselect'];
						break;
					case 7:
						if( !validatePermission( "whitelisturls" ) ) return false;
						$toChange['live_state'] = $loadedArguments['livestateselect'];
						break;
					default:
						$mainHTML->setMessageBox( "danger", "{{{urldataerror}}}", "{{{illegallivestate}}}" );

						return false;
				}
			}
			if( isset( $loadedArguments['archiveurl'] ) && $loadedArguments['archiveurl'] != $result['archive_url'] ) {
				if( !validatePermission( "alterarchiveurl" ) ) return false;
				if( !empty( $loadedArguments['archiveurl'] ) &&
				    API::isArchive( $loadedArguments['archiveurl'], $data )
				) {
					if( !isset( $loadedArguments['overridearchivevalidation'] ) ||
					    $loadedArguments['overridearchivevalidation'] != "on" ) {
						if( isset( $data['archive_type'] ) && $data['archive_type'] == "invalid" ) {
							$mainHTML->setMessageBox( "danger", "{{{urldataerror}}}", "{{{invalidarchive}}}" );

							return false;
						}
						if( $data['url'] ==
						    $checkIfDead->sanitizeURL( $loadedArguments['url'] )
						) {
							$toChange['archive_url'] = $dbObject->sanitize( $data['archive_url'] );
							$toChange['archive_time'] = date( 'Y-m-d H:i:s', $data['archive_time'] );
							if( $result['has_archive'] != 1 ) $toChange['has_archive'] = 1;
							if( $result['archived'] != 1 ) $toChange['archived'] = 1;
							if( $result['reviewed'] != 1 ) $toChange['reviewed'] = 1;
						} else {
							$mainHTML->setMessageBox( "danger", "{{{urldataerror}}}", "{{{urlmismatch}}}" );

							return false;
						}
					} else {
						if( !validatePermission( "overridearchivevalidation" ) ) {
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
					$mainHTML->setMessageBox( "danger", "{{{urldataerror}}}", "{{{invalidarchive}}}" );

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
			$mainHTML->setMessageBox( "danger", "{{{404url}}}", "{{{404urlmessage}}}" );
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
						                           $userObject->getUserLinkID(), strtotime( $result['access_time'] ),
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
			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{urlchangesuccess}}}" );
			$userObject->setLastAction( time() );

			return true;
		}
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
		$paywalls = [];
		if( ( $res = $dbObject->queryDB( $sqlURL ) ) ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
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
		if( $deblacklistDomain === true && !validatePermission( "deblacklistdomains" ) ) return false;
		if( $dewhitelistDomain === true && !validatePermission( "dewhitelistdomains" ) ) return false;
		if( isset( $loadedArguments['livestateselect'] ) ) {
			switch( $loadedArguments['livestateselect'] ) {
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
				default:
					$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{illegallivestate}}}" );

					return false;
			}
			if( isset( $resetsql ) && !$dbObject->queryDB( $resetsql ) ) {
				$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{unknownerror}}}" );

				return false;
			}
			if( !$dbObject->queryDB( $sql ) ) {
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
		} else {
			$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{illegallivestate}}}" );

			return false;
		}
	} else {
		$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{invaliddomaindata}}}" );

		return false;
	}

	$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{domainchangesuccess}}}" );
	$userObject->setLastAction( time() );

	return true;
}

function analyzePage() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $modifiedLinks, $runStats;

	if( !validateToken() ) return false;
	if( !validatePermission( "analyzepage" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

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
		'format' => 'php'
	];
	$get = http_build_query( $get );
	curl_setopt( $ch, CURLOPT_URL, API . "?$get" );
	curl_setopt( $ch, CURLOPT_HTTPHEADER,
	             [ API::generateOAuthHeader( 'GET', API . "?$get" ) ]
	);
	curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
	curl_setopt( $ch, CURLOPT_POST, 0 );
	$data = curl_exec( $ch );
	$data = unserialize( $data );

	if( isset( $data['query']['pages'] ) ) {
		foreach( $data['query']['pages'] as $page ) {
			if( isset( $page['missing'] ) ) {
				$mainHTML->setMessageBox( "danger", "{{{page404}}}", "{{{page404message}}}" );

				return false;
			} elseif( isset( $page['pageid'] ) ) {
				break;
			} else {
				$mainHTML->setMessageBox( "danger", "{{{apierror}}}", "{{{unknownerror}}}" );

				return false;
			}
		}
	}

	$ratelimitCounter = 0;
	if( isset( $_SESSION['pageanalysislog'] ) ) foreach( $_SESSION['pageanalysislog'] as $time ) {
		if( time() - $time < 60 ) $ratelimitCounter++;
	}

	if( $ratelimitCounter >= 5 ) {
		$mainHTML->setMessageBox( "danger", "{{{ratelimiterror}}}", "{{{ratelimiterrormessage}}}" );

		return false;
	}

	$_SESSION['pageanalysislog'][] = time();

	$overrideConfig['notify_on_talk'] = 0;
	$overrideConfig['notify_on_talk_only'] = 0;

	$runstart = microtime( true );

	echo "<!--\n";

	if( !API::botLogon() ) {
		$mainHTML->setMessageBox( "danger", "{{{apierror}}}", "{{{sessionerror}}}" );
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

	$commObject = new API( $page['title'], $page['pageid'], $config );
	$tmp = PARSERCLASS;
	$parser = new $tmp( $commObject );
	$runStats = $parser->analyzePage( $modifiedLinks );
	$commObject->closeResources();
	$parser = $commObject = null;

	echo "-->\n";

	$runStats['runtime'] = microtime( true ) - $runstart;

	$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "analyzepage", "analyzepage",
	                           $page['pageid'], $page['title'],
	                           $userObject->getUserLinkID(), -1, -1, $loadedArguments['reason']
	);

	$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{analyzepagesuccess}}}" );
	$userObject->setLastAction( time() );

	return true;
}

function submitBotJob() {
	global $loadedArguments, $dbObject, $userObject, $mainHTML, $modifiedLinks, $runStats;

	if( !validateToken() ) return false;
	if( !validatePermission( "submitbotjobs" ) ) return false;
	if( !validateChecksum() ) return false;
	if( !validateNotBlocked() ) return false;

	if( isset( $loadedArguments['pagelist'] ) && !empty( $loadedArguments['pagelist'] ) ) {

		$pages = explode( "\n", $loadedArguments['pagelist'] );

		if( count( $pages ) > 50000 && !validatePermission( "botsubmitlimitnolimit" ) ) {
			return false;
		} elseif( count( $pages ) > 5000 && !validatePermission( "botsubmitlimit50000", false ) ) {
			return false;
		} elseif( count( $pages ) > 500 && !validatePermission( "botsubmitlimit5000", false ) ) {
			return false;
		}

		$sql = "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_user` = " .
		       $userObject->getUserLinkID() . " AND (`queue_status` < 2 OR `queue_status` = 4);";
		$res = $dbObject->queryDB( $sql );
		$count = mysqli_fetch_assoc( $res );
		mysqli_free_result( $res );
		if( $count['count'] >= 5 ) {
			$mainHTML->setMessageBox( "danger", "{{{ratelimiterror}}}", "{{{botqueuerateexceeded}}}" );

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
			'pagesModified' => 0
		];

		$totalPages = count( $pages );

		$queueSQL =
			"INSERT INTO externallinks_botqueue (`wiki`, `queue_user`, `queue_pages`, `run_stats`, `worker_target`) VALUES ('" .
			WIKIPEDIA . "', " . $userObject->getUserLinkID() . ", '" . $dbObject->sanitize( serialize( $queuePages ) ) .
			"', '" . $dbObject->sanitize( serialize( $runStats ) ) . "', $totalPages );";

		if( $dbObject->queryDB( $queueSQL ) ) {
			$loadedArguments['id'] = $dbObject->getInsertID();
			$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "bqchangestatus", "submit",
			                           $dbObject->getInsertID(), "",
			                           $userObject->getUserLinkID(), null, null, ""
			);

			$mainHTML->setMessageBox( "success", "{{{successheader}}}", "{{{botqueuesuccess}}}" );
			$userObject->setLastAction( time() );
			loadJobViewer();

			return true;
		} else {
			$mainHTML->setMessageBox( "danger", "{{{bqsubmiterror}}}", "{{{unknownerror}}}" );

			return false;
		}
	}


}