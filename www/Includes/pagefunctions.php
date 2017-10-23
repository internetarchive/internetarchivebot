<?php

function getLogText( $logEntry ) {
	global $userObject, $userCache;
	$logTemplate = Parser::strftime( '%H:%M, %-e %B %Y', strtotime( $logEntry['log_timestamp'] ) ) . " " .
	               ( !isset( $userCache[$logEntry['log_user']]['user_name'] ) ||
	                 isset( $userCache[$logEntry['log_user']]['missing_local'] ) ? "" :
		               "<a href=\"index.php?page=user&id=" . $userCache[$logEntry['log_user']]['user_id'] . "\">" ) .
	               ( isset( $userCache[$logEntry['log_user']]['user_name'] ) ?
		               $userCache[$logEntry['log_user']]['user_name'] : "{{{unknownuser}}}" ) .
	               ( !isset( $userCache[$logEntry['log_user']]['user_name'] ) ||
	                 isset( $userCache[$logEntry['log_user']]['missing_local'] ) ? "" : "</a>" );
	if( $logEntry['locale'] != WIKIPEDIA ) $logTemplate .= "<small>({$logEntry['locale']})</small>";
	$logTemplate .= " {{{" . $logEntry['log_type'] . $logEntry['log_action'] . "}}}";
	if( !empty( $logEntry['log_reason'] ) ) {
		$logTemplate .= " <i>(" . htmlspecialchars( $logEntry['log_reason'] ) . ")</i>";
	}
	$logText = new HTMLLoader( $logTemplate, $userObject->getLanguage() );
	if( $logEntry['log_type'] == "permissionchange" || $logEntry['log_type'] == "block" ) {
		$logText->assignAfterElement( "targetusername", $userCache[$logEntry['log_object']]['user_name'] );
		$logText->assignAfterElement( "targetuserid", $userCache[$logEntry['log_object']]['user_id'] );
	}
	if( $logEntry['log_type'] == "permissionchange" ) {
		$added = array_diff( unserialize( $logEntry['log_to'] ), unserialize( $logEntry['log_from'] ) );
		$removed = array_diff( unserialize( $logEntry['log_from'] ), unserialize( $logEntry['log_to'] ) );
		$logText->assignAfterElement( "logfrom", implode( ", ", $added ) );
		$logText->assignAfterElement( "logto", implode( ", ", $removed ) );
	} elseif( $logEntry['log_action'] == "changestate" ) {
		switch( $logEntry['log_from'] ) {
			case 0:
				$logText->assignAfterElement( "logfrom", "{{{dead}}}" );
				break;
			case 1:
			case 2:
				$logText->assignAfterElement( "logfrom", "{{{dying}}}" );
				break;
			case 3:
				$logText->assignAfterElement( "logfrom", "{{{alive}}}" );
				break;
			case 4:
				$logText->assignAfterElement( "logfrom", "{{{unknown}}}" );
				break;
			case 5:
				$logText->assignAfterElement( "logfrom", "{{{paywall}}}" );
				break;
			case 6:
				$logText->assignAfterElement( "logfrom", "{{{blacklisted}}}" );
				break;
			case 7:
				$logText->assignAfterElement( "logfrom", "{{{whitelisted}}}" );
				break;
			default:
				$logText->assignAfterElement( "logfrom", "{{{unknown}}}" );
				break;
		}
		switch( $logEntry['log_to'] ) {
			case 0:
				$logText->assignAfterElement( "logto", "{{{dead}}}" );
				break;
			case 1:
			case 2:
				$logText->assignAfterElement( "logto", "{{{dying}}}" );
				break;
			case 3:
				$logText->assignAfterElement( "logto", "{{{alive}}}" );
				break;
			case 4:
				$logText->assignAfterElement( "logto", "{{{unknown}}}" );
				break;
			case 5:
				$logText->assignAfterElement( "logto", "{{{paywall}}}" );
				break;
			case 6:
				$logText->assignAfterElement( "logto", "{{{blacklisted}}}" );
				break;
			case 7:
				$logText->assignAfterElement( "logto", "{{{whitelisted}}}" );
				break;
			default:
				$logText->assignAfterElement( "logto", "{{{unknown}}}" );
				break;
		}

	} elseif( $logEntry['log_action'] == "changeglobalstate" ) {
		switch( $logEntry['log_from'] ) {
			case 0:
				$logText->assignAfterElement( "logfrom", "{{{none}}}" );
				break;
			case 1:
				$logText->assignAfterElement( "logfrom", "{{{paywall}}}" );
				break;
			case 2:
				$logText->assignAfterElement( "logfrom", "{{{blacklisted}}}" );
				break;
			case 3:
				$logText->assignAfterElement( "logfrom", "{{{whitelisted}}}" );
				break;
			default:
				$logText->assignAfterElement( "logfrom", "{{{unknown}}}" );
				break;
		}
		switch( $logEntry['log_to'] ) {
			case 0:
				$logText->assignAfterElement( "logto", "{{{none}}}" );
				break;
			case 1:
				$logText->assignAfterElement( "logto", "{{{paywall}}}" );
				break;
			case 2:
				$logText->assignAfterElement( "logto", "{{{blacklisted}}}" );
				break;
			case 3:
				$logText->assignAfterElement( "logto", "{{{whitelisted}}}" );
				break;
			default:
				$logText->assignAfterElement( "logto", "{{{unknown}}}" );
				break;
		}

	} elseif( $logEntry['log_action'] == "changeaccess" ) {
		$logText->assignAfterElement( "logfrom", Parser::strftime( '%H:%M, %-e %B %Y (UTC)', $logEntry['log_from'] ) );
		$logText->assignAfterElement( "logto", Parser::strftime( '%H:%M, %-e %B %Y (UTC)', $logEntry['log_to'] ) );
	} else {
		$logText->assignAfterElement( "logfrom",
			( is_null( $logEntry['log_from'] ) ? "{{{none}}}" : $logEntry['log_from'] )
		);
		$logText->assignAfterElement( "logto", ( is_null( $logEntry['log_to'] ) ? "{{{none}}}" : $logEntry['log_to'] )
		);
		$logText->assignAfterElement( "htmllogfrom",
			( is_null( $logEntry['log_from'] ) ? "{{{none}}}" : htmlspecialchars( $logEntry['log_from'] ) )
		);
		$logText->assignAfterElement( "htmllogto",
			( is_null( $logEntry['log_to'] ) ? "{{{none}}}" : htmlspecialchars( $logEntry['log_to'] ) )
		);
	}
	$logText->assignAfterElement( "logobject", $logEntry['log_object'] );
	$logText->assignAfterElement( "logobjecttext", $logEntry['log_object_text'] );
	$logText->assignAfterElement( "htmllogobjecttext", htmlspecialchars( $logEntry['log_object_text'] ) );
	$logText->finalize();

	return $logText->getLoadedTemplate();
}

function loadLogUsers( $logEntries ) {
	global $userCache, $dbObject;
	$toFetch = [];
	foreach( $logEntries as $logEntry ) {
		if( !isset( $userCache[$logEntry['log_user']] ) ) {
			$toFetch[] = $logEntry['log_user'];
		}
		if( $logEntry['log_type'] == "permissionchange" || $logEntry['log_type'] == "block" ) {
			if( !isset( $userCache[$logEntry['log_object']] ) ) {
				$toFetch[] = $logEntry['log_object'];
			}
		}
	}
	$res =
		$dbObject->queryDB( "SELECT * FROM `externallinks_user` WHERE `user_link_id` IN (" . implode( ", ", $toFetch ) .
		                    ") AND `wiki` = '" . WIKIPEDIA . "';"
		);
	if( $res ) while( $result = mysqli_fetch_assoc( $res ) ) {
		$userCache[$result['user_link_id']] = $result;
	}
	$toFetch = [];
	foreach( $logEntries as $logEntry ) {
		if( !isset( $userCache[$logEntry['log_user']] ) ) {
			$toFetch[] = $logEntry['log_user'];
		}
		if( $logEntry['log_type'] == "permissionchange" || $logEntry['log_type'] == "block" ) {
			if( !isset( $userCache[$logEntry['log_object']] ) ) {
				$toFetch[] = $logEntry['log_object'];
			}
		}
	}
	if( !empty( $toFetch ) ) {
		$res = $dbObject->queryDB( "SELECT * FROM `externallinks_user` WHERE `user_link_id` IN (" .
		                           implode( ", ", $toFetch ) . ")"
		);
		while( $result = mysqli_fetch_assoc( $res ) ) {
			$userCache[$result['user_link_id']] = $result;
			$userCache[$result['user_link_id']]['missing_local'] = true;
		}
	}
}

function loadConstructionPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "construction", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{underconstruction}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function load404Page() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "404", $userObject->getLanguage() );
	header( "HTTP/1.1 404 Not Found", true, 404 );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{404}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function load404UserPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "404User", $userObject->getLanguage() );
	header( "HTTP/1.1 404 Not Found", true, 404 );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{404User}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadHomePage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "home", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{startpagelabel}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadDisabledInterface() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "homedisabled", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{interacedisabled}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadMaintenanceProgress() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "MaintenanceProgress", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{maintenanceheader}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadLoginNeededPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "loginneeded", $userObject->getLanguage() );
	header( "HTTP/1.1 401 Unauthorized", true, 401 );
	$bodyHTML->assignAfterElement( "returnto", "https://" .
	                                           $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
	);
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{loginrequired}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadUserPreferences() {
	global $mainHTML, $userObject, $interfaceLanguages, $accessibleWikis;
	$bodyHTML = new HTMLLoader( "userpreferences", $userObject->getLanguage() );
	$bodyHTML->loadWikisi18n();
	if( $userObject->hasEmail() ) {
		$mainHTML->assignAfterElement( "useremailconfirmed", $userObject->getEmail() );
	} else {
		$mainHTML->assignAfterElement( "useremailconfirmed", "" );
	}
	$mainHTML->assignAfterElement( "useremail", $userObject->getEmail() );
	if( $userObject->getEmailNewFPReport() ) $bodyHTML->assignElement( "user_email_fpreport", "checked=\"checked\"" );
	if( !$userObject->validatePermission( "viewfpreviewpage" ) ) $bodyHTML->assignElement( "fpreporterdisabled",
	                                                                                       "disabled=\"disabled\""
	);
	if( $userObject->getEmailBlockStatus() ) $bodyHTML->assignElement( "user_email_blockstatus", "checked=\"checked\""
	);
	if( $userObject->getEmailPermissionsStatus() ) $bodyHTML->assignElement( "user_email_permissions",
	                                                                         "checked=\"checked\""
	);
	if( $userObject->getEmailFPFixed() ) $bodyHTML->assignElement( "user_email_fpreportstatusfixed",
	                                                               "checked=\"checked\""
	);
	if( $userObject->getEmailFPDeclined() ) $bodyHTML->assignElement( "user_email_fpreportstatusdeclined",
	                                                                  "checked=\"checked\""
	);
	if( $userObject->getEmailFPOpened() ) $bodyHTML->assignElement( "user_email_fpreportstatusopened",
	                                                                "checked=\"checked\""
	);
	if( $userObject->getEmailBQComplete() ) $bodyHTML->assignElement( "user_email_bqstatuscomplete",
	                                                                  "checked=\"checked\""
	);
	if( $userObject->getEmailBQKilled() ) $bodyHTML->assignElement( "user_email_bqstatuskilled", "checked=\"checked\""
	);
	if( $userObject->getEmailBQSuspended() ) $bodyHTML->assignElement( "user_email_bqstatussuspended",
	                                                                   "checked=\"checked\""
	);
	if( $userObject->getEmailBQUnsuspended() ) $bodyHTML->assignElement( "user_email_bqstatusresume",
	                                                                     "checked=\"checked\""
	);

	$options = "<option value=\"null\"";
	if( $userObject->getDefaultLanguage() == null ) $options .= " selected";
	$options .= ">{{{none}}}</option>\n";
	foreach( $_SESSION['intLanguages']['languages'] as $langCode => $language ) {
		$options .= "<option value=\"$langCode\"";
		if( $userObject->getDefaultLanguage() == $langCode ) $options .= " selected";
		$options .= ">$language</option>\n";
	}
	$bodyHTML->assignElement( "selectlanguagebody", $options );

	$options = "<option value=\"null\">{{{none}}}</option>\n";
	foreach( $accessibleWikis as $wiki => $data ) {
		$options .= "<option value=\"$wiki\"";
		if( $userObject->getDefaultWiki() == $wiki ) $options .= " selected";
		$options .= ">{$data['name']}</option>\n";
	}
	$bodyHTML->assignElement( "selectwikibody", $options );
	if( !is_null( $userObject->getTheme() ) ) {
		$bodyHTML->assignElement( $userObject->getTheme() . "selected", "selected" );
	} else {
		$bodyHTML->assignElement( "noneselected", "selected" );
	}
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{userpreferencesheader}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	$mainHTML->assignElement( "onloadfunction",
	                          "validateEmail( '{{useremailconfirmed}}', '{{{confirmedemail}}}','{{{confirmemailwarning" .
	                          ( empty( $userObject->getEmail() ) ? "1" : "2" ) . "}}}','{{{invalidemail}}}')"
	);
}

function loadToSPage() {
	global $mainHTML, $userObject, $oauthObject, $loadedArguments, $dbObject;
	if( isset( $loadedArguments['tosaccept'] ) ) {
		if( isset( $loadedArguments['token'] ) ) {
			if( $loadedArguments['token'] == $oauthObject->getCSRFToken() ) {
				if( $loadedArguments['tosaccept'] == "yes" ) {
					$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "tos", "accept", 0, "",
					                           $userObject->getUserLinkID()
					);
					$userObject->setLastAction( time() );
					$mainHTML->setMessageBox( "info", "{{{welcome}}}", "{{{welcomemessage}}}" );
					loadUserPreferences();

					return true;
				} else {
					$dbObject->insertLogEntry( WIKIPEDIA, WIKIPEDIA, "tos", "decline", 0, "",
					                           $userObject->getUserLinkID()
					);
					$oauthObject->logout();

					return true;
				}
			} else {
				$mainHTML->setMessageBox( "danger", "{{{tokenerrorheader}}}:", "{{{tokenerrormessage}}}" );
			}
		} else {
			$mainHTML->setMessageBox( "danger", "{{{tokenneededheader}}}:", "{{{tokenneededmessage}}}" );
		}
	}
	$bodyHTML = new HTMLLoader( "tos", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{tosheader}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );

	return false;
}

function loadUserPage( $returnLoader = false ) {
	global $mainHTML, $oauthObject, $loadedArguments, $dbObject, $userGroups, $userObject;
	if( $oauthObject->getUserID() == $loadedArguments['id'] ) $userObject2 = $userObject;
	else $userObject2 = new User( $dbObject, $oauthObject, $loadedArguments['id'] );
	if( is_null( $userObject2->getUsername() ) ) {
		load404UserPage();

		return;
	}
	$bodyHTML = new HTMLLoader( "user", $userObject->getLanguage() );
	$bodyHTML->assignElement( "userid", $userObject2->getUserID() );
	$bodyHTML->assignElement( "username", $userObject2->getUsername() );
	$mainHTML->assignAfterElement( "username", $userObject2->getUsername() );
	if( $userObject2->getLastAction() > 0 ) $bodyHTML->assignElement( "lastactivitytimestamp",
	                                                                  Parser::strftime( '%k:%M %-e %B %Y (UTC)',
	                                                                                    $userObject2->getLastAction()
	                                                                  )
	);
	if( $userObject2->getAuthTimeEpoch() > 0 ) $bodyHTML->assignElement( "lastlogontimestamp",
	                                                                     Parser::strftime( '%k:%M %-e %B %Y (UTC)',
	                                                                                       $userObject2->getAuthTimeEpoch(
	                                                                                       )
	                                                                     )
	);
	$text = "";
	foreach( $userObject2->getGroups() as $group ) {
		$text .= "<span class=\"label label-{$userGroups[$group]['labelclass']}\">$group</span>";
	}
	$bodyHTML->assignElement( "groupmembers", $text );
	if( $userObject2->isBlocked() === true ) {
		$bodyHTML->assignElement( "blockstatus", "{{{yes}}}</li>\n<li>{{{blocksource}}}: {{{{blocksource}}}}" );
		switch( $userObject2->getBlockSource() ) {
			case "internal":
				$bodyHTML->assignElement( "blocksource", "{{{blockedinternally}}}" );
				break;
			case "wiki":
				$bodyHTML->assignElement( "blocksource", "{{{blockedonwiki}}}" );
				break;
			default:
				$bodyHTML->assignElement( "blocksource", "{{{blockedunknown}}}" );
		}
	} else {
		$bodyHTML->assignElement( "blockstatus", "{{{no}}}" );
	}
	$bodyHTML->assignElement( "userflags", implode( ", ", $userObject2->getFlags() ) );
	$result =
		$dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'analyzepage' AND `log_user` = " .
		                    $userObject2->getUserLinkID() . ";"
		);
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "pagesrescued", $res['count'] );
	}
	mysqli_free_result( $result );
	$result =
		$dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'bqchangestatus' AND `log_action` ='submit' AND `log_user` = " .
		                    $userObject2->getUserLinkID() . ";"
		);
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "botsstarted", $res['count'] );
	}
	mysqli_free_result( $result );
	$result =
		$dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'urldata' AND `log_user` = " .
		                    $userObject2->getUserLinkID() . ";"
		);
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "urlschanged", $res['count'] );
	}
	mysqli_free_result( $result );
	$result =
		$dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'domaindata' AND `log_user` = " .
		                    $userObject2->getUserLinkID() . ";"
		);
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "domainschanged", $res['count'] );
	}
	mysqli_free_result( $result );
	$result =
		$dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'fpreport' AND `log_action` = 'report' AND `log_user` = " .
		                    $userObject2->getUserLinkID() . ";"
		);
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "fpreported", $res['count'] );
	}
	mysqli_free_result( $result );
	$form = "<hr>\n";
	if( $userObject->validatePermission( "blockuser" ) === true || $userObject->validatePermission( "unblockuser" ) ) {
		$form .= "<form class=\"form-inline\" id=\"blockform\" name=\"blockform\" method=\"post\" action=\"index.php?page=user&action=toggleblock&id={$loadedArguments['id']}\">
  <div class=\"form-group\">
    <input type=\"text\" class=\"form-control\" name=\"reason\" id=\"reason\" placeholder=\"{{{blockreasonplaceholder}}}\"";
		if( $userObject2->isBlocked() === true &&
		    $userObject2->getBlockSource() == "wiki"
		) $form .= "\" disabled=\"disabled";
		elseif( $userObject2->isBlocked() === true && $userObject2->getUserID() == $userObject->getUserID() &&
		        $userObject->validatePermission( "unblockme" ) === false
		) $form .= "\" disabled=\"disabled\"";
		$form .= ">
  </div>
  <button type=\"submit\" class=\"btn btn-";
		if( $userObject2->isBlocked() === true ) $form .= "success";
		else $form .= "danger";
		if( $userObject2->isBlocked() === true &&
		    $userObject2->getBlockSource() == "wiki"
		) $form .= "\" disabled=\"disabled";
		elseif( $userObject2->isBlocked() === true && $userObject2->getUserID() == $userObject->getUserID() &&
		        $userObject->validatePermission( "unblockme" ) === false
		) $form .= "\" disabled=\"disabled\"";
		$form .= "\">";
		if( $userObject2->isBlocked() === true ) $form .= "{{{unblock}}}";
		else $form .= "{{{block}}}";
		$form .= "</button>";
		$form .= "<input type=\"hidden\" value=\"{{csrftoken}}\" name=\"token\">\n";
		$form .= "<input type=\"hidden\" value=\"{{checksum}}\" name=\"checksum\">\n";
		$form .= "</form>";
		$bodyHTML->assignElement( "blockusercontrol", $form );
	}
	if( $userObject->validatePermission( "changepermissions" ) !== true ) {
		$bodyHTML->assignElement( "permissionscontrol", "{{{permissionscontrolnopermission}}}" );
	} else {
		$form =
			"<form id=\"userrightsform\" name=\"userrightsform\" method=\"post\" action=\"index.php?page=user&action=changepermissions&id={$loadedArguments['id']}\">\n";
		$form .= "<label class=\"checkbox-inline\"><h4>{{{groups}}}: </h4></label>";
		foreach( $userGroups as $group => $junk ) {
			$disabledChange = ( $userObject2->validateGroup( $group ) &&
			                    ( !$userObject2->validateGroup( $group, true ) ||
			                      !in_array( $group, $userObject->getRemovableGroups() ) ) ) ||
			                  !in_array( $group, $userObject->getAddableGroups() );
			$checked = $userObject2->validateGroup( $group );
			$global = $userObject2->validateGroup( $group, true, true );
			$form .= " <label class=\"checkbox-inline\">\n";
			if( $checked === true ) {
				$form .= "   <input type=\"hidden\" id=\"$group\" name=\"$group\" value=\"";
				if( $checked === true && $disabledChange === true ) $form .= "on";
				else $form .= "off";
				$form .= "\"/>";
			}
			$form .= "   ";
			if( $global === true ) $form .= "<span class=\"label label-success\">";
			$form .= "<input aria-label='$group";
			if( $global === true ) $form .= ", {{{ariaglobal}}}";
			$form .= "' type=\"checkbox\" id=\"$group\" name=\"$group\"";
			if( $checked === true ) $form .= " checked=\"checked\"";
			if( $disabledChange === true ) $form .= " disabled=\"disabled\"";
			$form .= ">\n";
			$form .= "   <span aria-hidden=\"true\">$group</span>";
			if( $global === true ) $form .= "</span>";
			$form .= "\n";
			$form .= " </label>\n";
		}
		$form .= "<hr>";
		$form .= "<label class=\"checkbox-inline\"><h4>{{{flags}}}: </h4></label>";
		foreach( $userObject->getAllFlags() as $flag ) {
			$disabledChange = ( $userObject2->validatePermission( $flag ) &&
			                    ( !$userObject2->validatePermission( $flag, true ) ||
			                      !in_array( $flag, $userObject->getRemovableFlags() ) ) ) ||
			                  !in_array( $flag, $userObject->getAddableFlags() );
			$checked = $userObject2->validatePermission( $flag );
			$global = $userObject2->validatePermission( $flag, true, true );
			$form .= " <label class=\"checkbox-inline\">\n";
			if( $checked === true ) {
				$form .= "   <input type=\"hidden\" id=\"$flag\" name=\"$flag\" value=\"";
				if( $checked === true && $disabledChange === true ) $form .= "on";
				else $form .= "off";
				$form .= "\"/>";
			}
			$form .= "   ";
			if( $global === true ) $form .= "<span class=\"label label-success\">";
			$form .= "<input aria-label='$flag";
			if( $global === true ) $form .= ", {{{ariaglobal}}}";
			$form .= "' type=\"checkbox\" id=\"$flag\" name=\"$flag\"";
			if( $checked === true ) $form .= " checked=\"checked\"";
			if( $disabledChange === true ) $form .= " disabled=\"disabled\"";
			$form .= ">\n";
			$form .= "   <span aria-hidden=\"true\">$flag</span>";
			if( $global === true ) $form .= "</span>";
			$form .= "\n";
			$form .= " </label>\n";
		}
		$form .= "  <div class=\"form-group\">\n";
		$form .= "    <input type=\"text\" class=\"form-control\" name=\"reason\" id=\"reason\" placeholder=\"{{{reasonplaceholder}}}\">\n";
		$form .= "  </div>\n";
		$form .= "<button type=\"submit\" class=\"btn btn-primary\">Submit</button> <label class=\"checkbox-inline\"><input type=\"checkbox\" id=\"assignglobally\" name=\"assignglobally\"\n";
		$disabledChange = !$userObject->validatePermission( "changeglobalpermissions" );
		if( $disabledChange === true ) $form .= " disabled=\"disabled\"";
		$form .= ">{{{applyglobally}}}</input></label>\n";
		$form .= "<input type=\"hidden\" value=\"{{csrftoken}}\" name=\"token\">\n";
		$form .= "<input type=\"hidden\" value=\"{{checksum}}\" name=\"checksum\">\n";
		$form .= "</form>";
		$bodyHTML->assignElement( "permissionscontrol", $form );
	}
	$result = $dbObject->queryDB( "SELECT * FROM externallinks_userlog WHERE (`wiki` = '" . WIKIPEDIA .
	                              "' OR `wiki` = 'global') AND `log_user` = '" . $userObject2->getUserLinkID() .
	                              "' ORDER BY `log_timestamp` DESC LIMIT 0,100;"
	);
	$text = "<ol>";
	if( $res = mysqli_fetch_all( $result, MYSQLI_ASSOC ) ) {
		loadLogUsers( $res );
		foreach( $res as $entry ) {
			$text .= "<li>" . getLogText( $entry ) . "</li>\n";
		}
	}
	mysqli_free_result( $result );
	$text .= "</ol>";
	$bodyHTML->assignElement( "last100userlogs", $text );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{userheader}}}" );
	if( $returnLoader === false ) $mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	else return $bodyHTML;
}

function loadBotQueue( &$jsonOutAPI = false ) {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $oauthObject, $locales;
	if( !validatePermission( "viewbotqueue", false ) ) {
		loadPermissionError( "viewbotqueue", $jsonOutAPI );

		return;
	}
	if( $jsonOutAPI === false ) $bodyHTML = new HTMLLoader( "botqueue", $userObject->getLanguage() );
	$res = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_status` = 0;" );
	$result = mysqli_fetch_assoc( $res );
	if( $jsonOutAPI === false ) $bodyHTML->assignElement( "reportedbqqueued", $result['count'] );
	else $jsonOutAPI['queued'] = $result['count'];
	$res = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_status` = 1;" );
	$result = mysqli_fetch_assoc( $res );
	if( $jsonOutAPI === false ) $bodyHTML->assignElement( "reportedbqrunning", $result['count'] );
	else $jsonOutAPI['running'] = $result['count'];
	$sql =
		"SELECT queue_id, externallinks_botqueue.wiki as wiki, user_id, user_name, queue_timestamp, status_timestamp, queue_status, run_stats, worker_finished, worker_target FROM externallinks_botqueue LEFT JOIN externallinks_user ON externallinks_botqueue.queue_user = externallinks_user.user_link_id AND externallinks_botqueue.wiki = externallinks_user.wiki WHERE `queue_status` IN (";
	$inArray = [];
	if( !isset( $loadedArguments['displayqueued'] ) && !isset( $loadedArguments['displayrunning'] ) &&
	    !isset( $loadedArguments['displayfinished'] ) && !isset( $loadedArguments['displaykilled'] ) &&
	    !isset( $loadedArguments['displaysuspended'] )
	) $loadedArguments['displayrunning'] = "on";
	if( isset( $loadedArguments['displayqueued'] ) ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "bqdisplayqueuedchecked", "checked=\"checked\"" );
		$inArray[] = 0;
	}
	if( isset( $loadedArguments['displayrunning'] ) ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "bqdisplayrunningchecked", "checked=\"checked\"" );
		$inArray[] = 1;
	}
	if( isset( $loadedArguments['displayfinished'] ) ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "bqdisplayfinishedchecked", "checked=\"checked\"" );
		$inArray[] = 2;
	}
	if( isset( $loadedArguments['displaykilled'] ) ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "bqdisplaykilledchecked", "checked=\"checked\"" );
		$inArray[] = 3;
	}
	if( isset( $loadedArguments['displaysuspended'] ) ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "bqdisplaysuspendedchecked", "checked=\"checked\"" );
		$inArray[] = 4;
	}
	$sql .= implode( ", ", $inArray );
	$sql .= ")";
	if( isset( $loadedArguments['offset'] ) &&
	    is_numeric( $loadedArguments['offset'] )
	) $sql .= " AND `queue_id` > {$loadedArguments['offset']}";
	$sql .= " ORDER BY `queue_id` ASC LIMIT 1001;";
	$res = $dbObject->queryDB( $sql );
	$table = "";
	$urlbuilder = $loadedArguments;
	unset( $urlbuilder['action'], $urlbuilder['token'], $urlbuilder['checksum'], $urlbuilder['id'] );
	$counter = 0;
	$jsonOut = [];
	if( $jsonOutAPI !== false ) $jsonOutAPI['botqueue'] = [];
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$counter++;
		if( $counter > 1000 ) {
			$nextPage = $result['queue_id'];
			continue;
		}
		if( $jsonOutAPI === false ) {
			$jsonOut[$result['queue_id']] = [];
			$table .= "<tr id=\"row" . $result['queue_id'] . "\"";
			if( $result['queue_status'] == 2 ) {
				$table .= " class=\"success\"";
				$jsonOut[$result['queue_id']]['class'] = "success";
			} elseif( $result['queue_status'] == 3 ) {
				$table .= " class=\"danger\"";
				$jsonOut[$result['queue_id']]['class'] = "danger";
			} elseif( $result['queue_status'] == 4 ) {
				$table .= " class=\"warning\"";
				$jsonOut[$result['queue_id']]['class'] = "warning";
			} elseif( $result['queue_status'] == 1 ) {
				$table .= " class=\"info\"";
				$jsonOut[$result['queue_id']]['class'] = "info";
			}
			$table .= ">\n";
			$table .= "<td>" . $result['queue_id'] . "</td>\n";
			$table .= "<td>" . $result['wiki'] . "</td>\n";
			$table .= "<td><a href=\"index.php?page=user&id=" . $result['user_id'] . "\">" . $result['user_name'] .
			          "</a></td>\n";
			$table .= "<td>" . $result['queue_timestamp'] . "</td>\n";
			$table .= "<td id=\"status" . $result['queue_id'] . "\">";
			$statusHTML = "";
			if( $result['queue_status'] == 0 ) {
				$statusHTML .= "{{{queued}}}";
			} elseif( $result['queue_status'] == 1 ||
			          ( $result['queue_status'] == 4 && !empty( $result['assigned_worker'] ) )
			) {
				$statusHTML .= "<div class=\"progress\">
        <div id=\"progressbar" . $result['queue_id'] . "\" ";
				$statusHTML .= "class=\"progress-bar progress-bar-";
				if( $result['queue_status'] == 4 ) {
					$jsonOut[$result['queue_id']]['classProg'] = "progress-bar-warning";
					$statusHTML .= "warning";
				} elseif( time() - strtotime( $result['status_timestamp'] ) > 300 ) {
					$jsonOut[$result['queue_id']]['classProg'] = "progress-bar-danger";
					$statusHTML .= "danger";
				} else {
					$jsonOut[$result['queue_id']]['classProg'] = "progress-bar-info";
					$statusHTML .= "info";
				}
				$statusHTML .= "\" role=\"progressbar\" aria-valuenow=\"";
				$locale = setlocale( LC_ALL, "0" );
				setlocale( LC_ALL, $locales['en'] );
				$statusHTML .= $result['worker_finished'] / $result['worker_target'] * 100;
				$statusHTML .= "\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: ";
				$statusHTML .= $percentage = $result['worker_finished'] / $result['worker_target'] * 100;
				setlocale( LC_ALL, $locale );
				$statusHTML .= "%\"><span id=\"progressbartext" . $result['queue_id'] .
				               "\">{$result['worker_finished']}/{$result['worker_target']} (" .
				               round( $percentage, 2 ) . "%)</span></div>
      </div>";
				setlocale( LC_ALL, $locales['en'] );
				$jsonOut[$result['queue_id']]['style'] = "width: $percentage%";
				$jsonOut[$result['queue_id']]['aria-valuenow'] = $percentage;
				setlocale( LC_ALL, $locale );
				$jsonOut[$result['queue_id']]['progresstext'] =
					"{$result['worker_finished']}/{$result['worker_target']} (" .
					round( $percentage, 2 ) . "%)";

			} elseif( $result['queue_status'] == 4 ) {
				$statusHTML .= "{{{suspended}}}";
			} elseif( $result['queue_status'] == 2 ) {
				$statusHTML .= "{{{finished}}}: " . $result['status_timestamp'];
			} else {
				$statusHTML .= "{{{killed}}}: " . $result['status_timestamp'];
			}
			$statusHTML = new HTMLLoader( $statusHTML, $userObject->getLanguage() );
			$statusHTML->finalize();
			$jsonOut[$result['queue_id']]['statushtml'] = $statusHTML->getLoadedTemplate();
			$table .= $statusHTML->getLoadedTemplate() . "</td>\n";
			$table .= "<td class=\"buttons" . $result['queue_id'] . "\">";
			$buttonHTML = "<";
			if( $result['queue_status'] == 2 || $result['queue_status'] == 3 ) $buttonHTML .= "button";
			else $buttonHTML .= "a";
			$buttonHTML .= " href=\"index.php?";
			if( !empty( $urlbuilder ) ) $buttonHTML .= http_build_query( $urlbuilder ) . "&";
			$buttonHTML .= "page=metabotqueue&action=togglebqstatus&token=" . $oauthObject->getCSRFToken() .
			               "&checksum=" .
			               $oauthObject->getChecksumToken() . "&id=" . $result['queue_id'] . "\" class=\"btn btn-";
			if( $result['queue_status'] == 0 ||
			    $result['queue_status'] == 1
			) $buttonHTML .= "warning\">{{{bqsuspend}}}";
			elseif( $result['queue_status'] == 4 ) $buttonHTML .= "success\">{{{bqunsuspend}}}";
			else $buttonHTML .= "success\" disabled=\"disabled\">{{{bqunsuspend}}}";
			$buttonHTML .= "</";
			if( $result['queue_status'] == 2 || $result['queue_status'] == 3 ) $buttonHTML .= "button";
			else $buttonHTML .= "a";
			$buttonHTML .= ">";
			if( $result['queue_status'] != 2 && $result['queue_status'] != 3 ) {
				$buttonHTML .= "<a href=\"index.php?";
				if( !empty( $urlbuilder ) ) $buttonHTML .= http_build_query( $urlbuilder ) . "&";
				$buttonHTML .= "page=metabotqueue&action=killjob&token=" . $oauthObject->getCSRFToken() . "&checksum=" .
				               $oauthObject->getChecksumToken() . "&id=" . $result['queue_id'] .
				               "\" class=\"btn btn-danger\">{{{bqkill}}}</a>";
			}
			$buttonHTML = new HTMLLoader( $buttonHTML, $userObject->getLanguage() );
			$buttonHTML->finalize();
			$jsonOut[$result['queue_id']]['buttonhtml'] = $buttonHTML->getLoadedTemplate();
			$table .= $buttonHTML->getLoadedTemplate() . "</td>\n";
		} else {
			switch( $result['queue_status'] ) {
				case 0:
					$status = "queued";
					break;
				case 1:
					$status = "running";
					break;
				case 2:
					$status = "complete";
					break;
				case 3:
					$status = "killed";
					break;
				case 4:
					$status = "suspended";
					break;
				default:
					$status = "unknown";
					break;
			}
			$jsonOutAPI['botqueue'][$result['queue_id']] = [
				'id'             => $result['queue_id'], 'status' => $status, 'requestedby' => $result['user_name'],
				'targetwiki'     => $result['wiki'], 'queued' => $result['queue_timestamp'],
				'lastupdate'     => $result['status_timestamp'], 'totalpages' => $result['worker_target'],
				'completedpages' => $result['worker_finished'], 'runstats' => unserialize( $result['run_stats'] )
			];
		}
	}
	mysqli_free_result( $res );
	if( !isset( $loadedArguments['offset'] ) || $loadedArguments['offset'] <= 1 ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "prevbuttonora", "button" );
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "prevpagedisabled", "disabled=\"disable\"" );
	} else {
		if( $jsonOutAPI === false ) {
			$bodyHTML->assignElement( "prevbuttonora", "a" );
			$url = "index.php?";
			unset( $urlbuilder['offset'] );
			if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder ) . "&";
			$url .= "offset=" . ( $loadedArguments['offset'] - 1000 );
			$bodyHTML->assignElement( "prevpageurl", $url );
		}
	}
	if( $counter <= 1000 ) {
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "nextbuttonora", "button" );
		if( $jsonOutAPI === false ) $bodyHTML->assignElement( "nextpagedisabled", "disabled=\"disable\"" );
	} else {
		if( $jsonOutAPI === false ) {
			$bodyHTML->assignElement( "nextbuttonora", "a" );
			$url = "index.php?";
			unset( $urlbuilder['offset'] );
			if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder ) . "&";
			$url .= "offset=$nextPage";
			$bodyHTML->assignElement( "nextpageurl", $url );
		} else {
			if( !isset( $loadedArguments['offset'] ) ) $jsonOutAPI['continue'] = $nextPage;
		}
	}
	if( $jsonOutAPI === false ) {
		$bodyHTML->assignElement( "bqtable", $table );
		$bodyHTML->finalize();
		$mainHTML->assignElement( "tooltitle", "{{{bqreviewpage}}}" );
		$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
		unset( $loadedArguments['action'], $loadedArguments['token'], $loadedArguments['checksum'] );
		$mainHTML->assignElement( "onloadfunction", "loadBotQueue( '" . http_build_query( $loadedArguments ) . "' )" );
		if( isset( $loadedArguments['format'] ) && $loadedArguments['format'] == "json" ) {
			die( json_encode( $jsonOut ) );
		}
	}
}

function loadPermissionError( $permission, &$jsonOut = false ) {
	global $mainHTML, $userObject, $userGroups;
	header( "HTTP/1.1 403 Forbidden", true, 403 );
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
	if( $jsonOut === false ) {
		$bodyHTML = new HTMLLoader( "permissionerror", $userObject->getLanguage() );
		$bodyHTML->assignAfterElement( "userflag", $permission );
		$bodyHTML->assignAfterElement( "grouplist", implode( ", ", $groupList ) );
		$bodyHTML->finalize();
		$mainHTML->assignElement( "tooltitle", "{{{permissionerror}}}" );
		$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	} else {
		$jsonOut['missingpermission'] = $permission;
		$jsonOut['accessibletogroups'] = $groupList;
	}
}

function loadFPReportMeta( &$jsonOut = false ) {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $oauthObject;
	if( !validatePermission( "viewfpreviewpage", false ) ) {
		loadPermissionError( "viewfpreviewpage", $jsonOut );

		return;
	}
	if( $jsonOut === false ) $bodyHTML = new HTMLLoader( "fpinterface", $userObject->getLanguage() );
	$res = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_fpreports WHERE `report_status` = 0;" );
	$result = mysqli_fetch_assoc( $res );
	if( $jsonOut === false ) $bodyHTML->assignElement( "activefptotal", $result['count'] );
	else $jsonOut['openreports'] = $result['count'];
	$sql =
		"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id LEFT JOIN externallinks_user ON externallinks_fpreports.report_user_id = externallinks_user.user_link_id AND externallinks_fpreports.wiki = externallinks_user.wiki WHERE `report_status` IN (";
	$inArray = [];
	if( !isset( $loadedArguments['displayopen'] ) && !isset( $loadedArguments['displayfixed'] ) &&
	    !isset( $loadedArguments['displaydeclined'] )
	) $loadedArguments['displayopen'] = "on";
	if( isset( $loadedArguments['displayopen'] ) ) {
		if( $jsonOut === false ) $bodyHTML->assignElement( "fpdisplayopenchecked", "checked=\"checked\"" );
		$inArray[] = 0;
	}
	if( isset( $loadedArguments['displayfixed'] ) ) {
		if( $jsonOut === false ) $bodyHTML->assignElement( "fpdisplayfixedchecked", "checked=\"checked\"" );
		$inArray[] = 1;
	}
	if( isset( $loadedArguments['displaydeclined'] ) ) {
		if( $jsonOut === false ) $bodyHTML->assignElement( "fpdisplaydeclinedchecked", "checked=\"checked\"" );
		$inArray[] = 2;
	}
	$sql .= implode( ", ", $inArray );
	$sql .= ")";
	if( isset( $loadedArguments['offset'] ) &&
	    is_numeric( $loadedArguments['offset'] )
	) $sql .= " AND `report_id` > {$loadedArguments['offset']}";
	$sql .= " ORDER BY `report_id` ASC LIMIT 1001;";
	$res = $dbObject->queryDB( $sql );
	$table = "";
	$urlbuilder = $loadedArguments;
	unset( $urlbuilder['action'], $urlbuilder['token'], $urlbuilder['checksum'], $urlbuilder['id'] );
	$counter = 0;
	if( $jsonOut !== false ) $jsonOut['fpreports'] = [];
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$counter++;
		if( $counter > 1000 ) {
			$nextPage = $result['report_id'];
			continue;
		}
		if( $jsonOut === false ) {
			$table .= "<tr";
			if( $result['report_status'] == 1 ) {
				$table .= " class=\"success\"";
			} elseif( $result['report_status'] == 2 ) $table .= " class=\"danger\"";
			else $table .= " class=\"warning\"";
			$table .= ">\n";
			$table .= "<td><a href=\"" . htmlspecialchars( $result['url'] ) . "\">" .
			          htmlspecialchars( $result['url'] ) .
			          "</a></td>\n";
			$table .= "<td>" . $result['report_error'] . "</td>\n";
			$table .= "<td><a href=\"index.php?page=user&id=" . $result['user_id'] . "&wiki=" . $result['wiki'] .
			          "\">" . $result['user_name'] .
			          "</a></td>\n";
			$table .= "<td>" . $result['report_timestamp'] . "</td>\n";
			$table .= "<td>" . $result['report_version'] . "</td>\n";
			$table .= "<td><a href=\"index.php?";
			if( !empty( $urlbuilder ) ) $table .= http_build_query( $urlbuilder ) . "&";
			$table .= "page=metafpreview&action=togglefpstatus&token=" . $oauthObject->getCSRFToken() . "&checksum=" .
			          $oauthObject->getChecksumToken() . "&id=" . $result['report_id'] . "\" class=\"btn btn-";
			if( $result['report_status'] != 0 ) {
				$table .= "default\">{{{fpreopen}}}";
			} else $table .= "danger\">{{{fpdecline}}}";
			$table .= "</a></td>\n";
		} else {
			switch( $result['report_status'] ) {
				case 0:
					$status = "open";
					break;
				case 1:
					$status = "fixed";
					break;
				case 2:
					$status = "declined";
					break;
				default:
					$status = "unknown";
					break;
			}
			$jsonOut['fpreports'][] = [
				'status'          => $status, 'id' => $result['report_id'], 'url' => $result['url'],
				'reporterror'     => $result['report_error'], 'reportedon' => $result['report_timestamp'],
				'affectedversion' => $result['report_version'], 'reportedby' => $result['user_name'],
				'report_location' => $result['wiki']
			];
		}
	}

	if( !isset( $loadedArguments['offset'] ) || $loadedArguments['offset'] <= 1 ) {
		if( $jsonOut === false ) $bodyHTML->assignElement( "prevbuttonora", "button" );
		if( $jsonOut === false ) $bodyHTML->assignElement( "prevpagedisabled", "disabled=\"disable\"" );
	} else {
		if( $jsonOut === false ) {
			$bodyHTML->assignElement( "prevbuttonora", "a" );
			$url = "index.php?";
			unset( $urlbuilder['offset'] );
			if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder ) . "&";
			$url .= "offset=" . ( $loadedArguments['offset'] - 1000 );
			$bodyHTML->assignElement( "prevpageurl", $url );
		}
	}
	if( $counter <= 1000 ) {
		if( $jsonOut === false ) $bodyHTML->assignElement( "nextbuttonora", "button" );
		if( $jsonOut === false ) $bodyHTML->assignElement( "nextpagedisabled", "disabled=\"disable\"" );
	} else {
		if( $jsonOut === false ) {
			$bodyHTML->assignElement( "nextbuttonora", "a" );
			$url = "index.php?";
			unset( $urlbuilder['offset'] );
			if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder ) . "&";
			$url .= "offset=$nextPage";
			$bodyHTML->assignElement( "nextpageurl", $url );
		} else {
			if( !isset( $loadedArguments['offset'] ) ) $jsonOut['continue'] = $nextPage;
		}
	}
	if( $jsonOut === false ) {
		$bodyHTML->assignElement( "fptable", $table );
		$bodyHTML->finalize();
		$mainHTML->assignElement( "tooltitle", "{{{fpreviewpage}}}" );
		$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	}
}

function loadUserSearch() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments;
	$bodyHTML = new HTMLLoader( "usersearch", $userObject->getLanguage() );
	if( isset( $loadedArguments['username'] ) ) {
		$bodyHTML->assignElement( "usernamevalueelement",
		                          " value=\"" . htmlspecialchars( $loadedArguments['username'] ) . "\""
		);
		$sql = "SELECT * FROM externallinks_user WHERE `wiki` = '" . WIKIPEDIA . "' AND `user_name` = '" .
		       $dbObject->sanitize( $loadedArguments['username'] ) . "';";
		$res = $dbObject->queryDB( $sql );
		$result = mysqli_fetch_assoc( $res );
		if( $result ) {
			$loadedArguments['id'] = $result['user_id'];
			$accountHTML = loadUserPage( true );
			$bodyHTML->assignElement( "body", $accountHTML->getLoadedTemplate() );
		} else {
			$mainHTML->setMessageBox( "danger", "{{{404User}}}", "{{{404Usermessage}}}" );
		}
	}

	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{usersearch}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadInterfaceInfo() {
	global $mainHTML, $userObject, $userGroups;

	$tableRows = "";
	foreach( $userGroups as $group => $data ) {
		$groupData = User::getGroupFlags( $group );
		$tableRows .= "<tr class=\"{$data['labelclass']}\">\n";
		$tableRows .= "<td><span class=\"label label-{$data['labelclass']}\">$group</span></td>";
		$tableRows .= "<td>" . implode( ", ", $groupData['hasflags'] ) . "</td>";
		$autoacquireText = "";
		if( $data['autoacquire']['registered'] != 0 && ( time() - $data['autoacquire']['registered'] ) > 60 ) {
			$autoacquireText .= "<b>{{{registeredlatest}}}:</b>&nbsp;" .
			                    Parser::strftime( '%k:%M&nbsp;%-e&nbsp;%B&nbsp;%Y&nbsp;(UTC)',
			                                      $data['autoacquire']['registered']
			                    ) . "<br>\n";
		}
		if( $data['autoacquire']['registered'] != 0 && $data['autoacquire']['editcount'] != 0 ) {
			$autoacquireText .= "and<br>\n";
		}
		if( $data['autoacquire']['editcount'] != 0 ) {
			$autoacquireText .= "<b>{{{lowesteditcount}}}:</b>&nbsp;" . $data['autoacquire']['editcount'] . "<br>\n";
		}
		if( ( $data['autoacquire']['registered'] != 0 && ( time() - $data['autoacquire']['registered'] ) > 60 ) ||
		    $data['autoacquire']['editcount'] != 0 || count( $data['autoacquire']['withwikigroup'] ) > 0 ||
		    count( $data['autoacquire']['withwikiright'] ) > 0
		) {
			if( ( ( $data['autoacquire']['registered'] != 0 && ( time() - $data['autoacquire']['registered'] ) > 60 ) ||
			      $data['autoacquire']['editcount'] != 0 ) && ( count( $data['autoacquire']['withwikigroup'] ) > 0 ||
			                                                    count( $data['autoacquire']['withwikiright'] ) > 0 )
			) $autoacquireText .= "and<br>\n";
		} else {
			$autoacquireText = "&mdash;";
		}
		if( count( $data['autoacquire']['withwikigroup'] ) > 0 ) {
			$autoacquireText .= "<b>{{{withgroups}}}:</b> ";
			$autoacquireText .= implode( ", ", $data['autoacquire']['withwikigroup'] );
			$autoacquireText .= "<br>\n";
		}
		if( count( $data['autoacquire']['withwikigroup'] ) > 0 && count( $data['autoacquire']['withwikiright'] ) > 0 ) {
			$autoacquireText . "or<br>\n";
		}
		if( count( $data['autoacquire']['withwikiright'] ) > 0 ) {
			$autoacquireText .= "<b>{{{withrights}}}:</b> ";
			$autoacquireText .= implode( ", ", $data['autoacquire']['withwikiright'] );
			$autoacquireText .= "\n";
		}
		$tableRows .= "<td>$autoacquireText</td>";
		$tableRows .= "<td>";
		foreach( $userGroups as $tgroup => $junk ) {
			if( in_array( $tgroup, $groupData['addgroups'] ) ) {
				$tableRows .= "<span class=\"label label-{$junk['labelclass']}\">$tgroup</span> ";
			}
		}
		$tableRows .= "<hr>" . implode( ", ", $groupData['addflags'] ) . "</td>";
		$tableRows .= "<td>";
		foreach( $userGroups as $tgroup => $junk ) {
			if( in_array( $tgroup, $groupData['removegroups'] ) ) {
				$tableRows .= "<span class=\"label label-{$junk['labelclass']}\">$tgroup</span> ";
			}
		}
		$tableRows .= "<hr>" . implode( ", ", $groupData['removeflags'] ) . "</td>";
		$tableRows .= "</tr>\n";
	}

	$listBody = "";
	foreach( $userObject->getAllFlags() as $flag ) {
		$listBody .= "<li><b>$flag:</b> - {{{{$flag}}}}</li>\n";
	}
	$bodyHTML = new HTMLLoader( "metainfo", $userObject->getLanguage() );
	$bodyHTML->assignElement( "groupinforows", $tableRows );
	$bodyHTML->assignElement( "permissionsexplanationbody", $listBody );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{metainfo}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadBugReporter() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $oauthObject;
	$bodyHTML = new HTMLLoader( "bugreport", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{reportbug}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadFPReporter() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $oauthObject, $checkIfDead;
	if( !validatePermission( "reportfp", false ) ) {
		loadPermissionError( "reportfp" );

		return;
	}
	$bodyHTML = new HTMLLoader( "fpreporter", $userObject->getLanguage() );
	if( ( isset( $loadedArguments['pagenumber'] ) && $loadedArguments['pagenumber'] == 1 ) ||
	    !isset( $loadedArguments['fplist'] )
	) {
		$bodyHTML->assignElement( "page1displaytoggle", "all" );
		$bodyHTML->assignElement( "page2displaytoggle", "none" );
	} else {
		$bodyHTML->assignElement( "page1displaytoggle", "none" );
		$bodyHTML->assignElement( "page2displaytoggle", "all" );
	}
	$schemelessURLRegex =
		'(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';
	if( isset( $loadedArguments['fplist'] ) ) {
		$urls = explode( "\n", $loadedArguments['fplist'] );
		foreach( $urls as $id => $url ) {
			if( !preg_match( '/' . $schemelessURLRegex . '/i', $url, $garbage ) ) {
				unset( $urls[$id] );
			} else {
				$urls[$id] = $checkIfDead->sanitizeURL( $garbage[0], true );
			}
		}
		$loadedArguments['fplist'] = implode( "\n", $urls );
		$bodyHTML->assignElement( "fplistvalue", $loadedArguments['fplist'] );
	}
	if( isset( $loadedArguments['fplist'] ) &&
	    ( !isset( $loadedArguments['pagenumber'] ) || $loadedArguments['pagenumber'] != 1 )
	) {
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
			if( $result['paywall_status'] == 3 ) {
				$notDead[] = $result['url'];
			} elseif( $result['live_state'] != 0 && $result['live_state'] != 6 ) {
				$notDead[] = $result['url'];
			}
			unset( $notfound[$result['url']] );
		}
		$notfound = array_flip( $notfound );
		$urlList = "";
		foreach( $notfound as $url ) {
			$urlList .= "<li><a href=\"" . htmlspecialchars( $url ) . "\">" . htmlspecialchars( $url ) . "</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet4", ( empty( $urlList ) ? "&mdash;" : $urlList ) );
		if( empty( $urlList ) ) $bodyHTML->assignElement( "notfounddisplay", "none" );
		$urlList = "";
		foreach( $notDead as $url ) {
			$urlList .= "<li><a href=\"" . htmlspecialchars( $url ) . "\">" . htmlspecialchars( $url ) . "</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet5", ( empty( $urlList ) ? "&mdash;" : $urlList ) );
		if( empty( $urlList ) ) $bodyHTML->assignElement( "notdeaddisplay", "none" );
		$sql =
			"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id WHERE `url` IN ( '" .
			implode( "', '", $escapedURLs ) . "' ) AND `report_status` = 0;";
		$res = $dbObject->queryDB( $sql );
		while( $result = mysqli_fetch_assoc( $res ) ) {
			$alreadyReported[$result['url']] = $result['report_error'];
		}
		$urlList = "";
		$index = 0;
		foreach( $alreadyReported as $url => $error ) {
			$urlList .= "<li><a href=\"" . htmlspecialchars( $url ) . "\">" . htmlspecialchars( $url ) . "</a> (" .
			            htmlspecialchars( $error ) . ")</li>\n";
			$alreadyReported[$url] = $index;
			$index++;
		}
		$alreadyReported = array_flip( $alreadyReported );
		$bodyHTML->assignElement( "fplistbullet3", ( empty( $urlList ) ? "&mdash;" : $urlList ) );
		if( empty( $urlList ) ) $bodyHTML->assignElement( "reporteddisplay", "none" );
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
				if( empty( $toValidate ) ) break;
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
		$urlList = "";
		foreach( array_merge( $toReset, $toWhitelist ) as $url ) {
			$urlList .= "<li><a href=\"" . htmlspecialchars( $url ) . "\">" . htmlspecialchars( $url ) . "</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet2", ( empty( $urlList ) ? "&mdash;" : $urlList ) );
		if( empty( $urlList ) ) $bodyHTML->assignElement( "toresetdisplay", "none" );
		$urlList = "";
		foreach( $toReport as $url ) {
			$urlList .= "<li><a href=\"" . htmlspecialchars( $url ) . "\">" . htmlspecialchars( $url ) . "</a> (" .
			            ( isset( $errors[$url] ) ? $errors[$url] : "{{{unknownerror}}}" ) . ")</li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet1", ( empty( $urlList ) ? "&mdash;" : $urlList ) );
		if( empty( $urlList ) ) $bodyHTML->assignElement( "toreportdisplay", "none" );
		if( empty( $toReport ) && empty( $toReset ) && empty( $toWhitelist ) ) {
			$bodyHTML->assignElement( "submitdisable", " disabled=\"disabled\"" );
		}

		$_SESSION['precheckedfplistsrorted']['toreport'] = $toReport;
		$_SESSION['precheckedfplistsrorted']['toreporterrors'] = $errors;
		$_SESSION['precheckedfplistsrorted']['toreset'] = $toReset;
		$_SESSION['precheckedfplistsrorted']['towhitelist'] = $toWhitelist;
		$_SESSION['precheckedfplistsrorted']['alreadyreported'] = $alreadyReported;
		$_SESSION['precheckedfplistsrorted']['notfound'] = $notfound;
		$_SESSION['precheckedfplistsrorted']['notdead'] = $notDead;
		$_SESSION['precheckedfplistsrorted']['toreporthash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $toReport ) );
		$_SESSION['precheckedfplistsrorted']['toreporterrorshash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $errors ) );
		$_SESSION['precheckedfplistsrorted']['toresethash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $toReset ) );
		$_SESSION['precheckedfplistsrorted']['towhitelisthash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $toWhitelist ) );
		$_SESSION['precheckedfplistsrorted']['alreadyreportedhash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $alreadyReported ) );
		$_SESSION['precheckedfplistsrorted']['notfoundhash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $notfound ) );
		$_SESSION['precheckedfplistsrorted']['notdeadhash'] =
			md5( CONSUMERSECRET . ACCESSSECRET . implode( ":", $notDead ) );
		$_SESSION['precheckedfplistsrorted']['finalhash'] = md5( $_SESSION['precheckedfplistsrorted']['toreporthash'] .
		                                                         $_SESSION['precheckedfplistsrorted']['toreporterrorshash'] .
		                                                         $_SESSION['precheckedfplistsrorted']['toresethash'] .
		                                                         $_SESSION['precheckedfplistsrorted']['towhitelisthash'] .
		                                                         $_SESSION['precheckedfplistsrorted']['alreadyreportedhash'] .
		                                                         $_SESSION['precheckedfplistsrorted']['notfoundhash'] .
		                                                         $_SESSION['precheckedfplistsrorted']['notdeadhash'] .
		                                                         $oauthObject->getChecksumToken()
		);
	}
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{fpreporter}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadURLData( &$jsonOut ) {
	global $dbObject, $loadedArguments;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	$jsonOut['arguments'] = $loadedArguments;
	if( empty( $loadedArguments['offset'] ) || !is_numeric( substr( $loadedArguments['offset'], 1 ) ) )
		$loadedArguments['offset'] = "A0";

	if( empty( $loadedArguments['urls'] ) && empty( $loadedArguments['urlids'] ) &&
	    !isset( $loadedArguments['hasarchive'] ) &&
	    empty( $loadedArguments['livestate'] ) && empty( $loadedArguments['isarchived'] ) &&
	    !isset( $loadedArguments['reviewed'] )
	) {
		$jsonOut['missingvalue'] = "urls|urlids";
		$jsonOut['errormessage'] =
			"The parameter \"urls\" or \"urlids\" is a newline seperated list of URLs or URL IDs to lookup that is required for this request, or the search is narrowed with the filtering parameters.";

		return false;
	} elseif( empty( $loadedArguments['urls'] ) && empty( $loadedArguments['urlids'] ) ) {
		$pfetchSQL = $fetchSQL =
			"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE";
	} else {
		if( !empty( $loadedArguments['urlids'] ) ) {
			$urls = explode( "\n", $loadedArguments['urlids'] );
			if( !is_array( $urls ) ) {
				$jsonOut['missingvalue'] = "urlids";
				$jsonOut['errormessage'] = "The parameter \"urlids\" has bad data that can't be processed.";

				return false;
			}

			$pfetchSQL = $fetchSQL =
				"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url_id` IN ( '";
			$pfetchSQL = $fetchSQL .= implode( "', '", $urls ) . "' )";
		} else {
			$urls = explode( "\n", $loadedArguments['urls'] );
			if( !is_array( $urls ) ) {
				$jsonOut['missingvalue'] = "urls";
				$jsonOut['errormessage'] = "The parameter \"urls\" has bad data that can't be processed.";

				return false;
			}

			$pfetchSQL = $fetchSQL =
				"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url` IN ( '";
			$normalizedurls = [];
			foreach( $urls as $i => $url ) {
				if( !empty( $url ) ) $normalizedurls[$i] =
					$dbObject->sanitize( $checkIfDead->sanitizeURL( $url, true ) );
			}

			$fetchSQL .= implode( "', '", $normalizedurls ) . "' )";
		}
	}
	if( isset( $loadedArguments['hasarchive'] ) ) {
		if( strlen( substr( $fetchSQL, strpos( $fetchSQL, "WHERE" ) ) ) > 5 ) {
			$pfetchSQL = $fetchSQL .= " AND";
		}
		$pfetchSQL = $fetchSQL .= ' `has_archive` = ' . (int) (bool) (int) $loadedArguments['hasarchive'];
	}
	if( !empty( $loadedArguments['livestate'] ) ) {
		$liveStates = explode( "|", $loadedArguments['livestate'] );
		$global = [];
		$paywall = [];
		foreach( $liveStates as $state ) {
			switch( $state ) {
				case "dead":
					$global[] = 0;
					break;
				case "dying":
					$global[] = 1;
					$global[] = 2;
					break;
				case "alive":
					$global[] = 3;
					break;
				case "paywall":
					$global[] = 5;
					break;
				case "whitelisted":
					$global[] = 7;
					$paywall[] = 3;
					break;
				case "blacklisted":
					$global[] = 6;
					$paywall[] = 2;
					break;
				case "unknown":
					$global[] = 4;
					break;
				default:
					break;
			}
		}
		if( !empty( $paywall ) ) $paywallSQL =
			"SELECT paywall_id FROM externallinks_paywall WHERE `paywall_status` IN (" . implode( ", ", $paywall ) .
			")";
		$filter = "(";
		if( isset( $global ) ) $filter .= " `live_state` IN (" . implode( ", ", $global ) . ")";
		if( isset( $paywallSQL ) ) {
			if( isset( $global ) ) $filter .= " AND";
			$filter .= " externallinks_global.paywall_id NOT IN ($paywallSQL)";
		}
		$filter .= " )";
		if( strlen( substr( $fetchSQL, strpos( $fetchSQL, "WHERE" ) ) ) > 5 ) {
			$pfetchSQL = $fetchSQL .= " AND";
		}
		$fetchSQL .= " $filter";
		if( isset( $paywallSQL ) ) {
			$pfetchSQL .= " externallinks_global.paywall_id IN ($paywallSQL)";
		}
	}
	if( !empty( $loadedArguments['isarchived'] ) ) {
		switch( $loadedArguments['isarchived'] ) {
			case "yes":
				$states = [ 1 ];
				break;
			case "no":
				$states = [ 0 ];
				break;
			case "unknown":
				$states = [ 2 ];
				break;
			case "missing":
				$states = [ 2, 0 ];
				break;
		}
		if( strlen( substr( $fetchSQL, strpos( $fetchSQL, "WHERE" ) ) ) > 5 ) {
			$fetchSQL .= " AND";
			$pfetchSQL .= " AND";
		}
		$fetchSQL .= " `archived` IN ( " . implode( ", ", $states ) . " )";
		$pfetchSQL .= " `archived` IN ( " . implode( ", ", $states ) . " )";
	}
	if( isset( $loadedArguments['reviewed'] ) ) {
		if( strlen( substr( $fetchSQL, strpos( $fetchSQL, "WHERE" ) ) ) > 5 ) {
			$fetchSQL .= " AND";
			$pfetchSQL .= " AND";
		}
		$fetchSQL .= ' `reviewed` = ' . (int) (bool) (int) $loadedArguments['reviewed'];
		$pfetchSQL .= ' `reviewed` = ' . (int) (bool) (int) $loadedArguments['reviewed'];
	}

	if( !empty( $loadedArguments['offset'] ) && is_numeric( substr( $loadedArguments['offset'], 1 ) ) ) {
		if( strlen( substr( $fetchSQL, strpos( $fetchSQL, "WHERE" ) ) ) > 5 ) {
			$fetchSQL .= " AND";
			$pfetchSQL .= " AND";
		}

		if( isset( $paywallSQL ) && substr( $loadedArguments['offset'], 0, 1 ) != "B" ) $pfetchSQL .= ' `url_id` >= ' .
		                                                                                              substr( $loadedArguments['offset'],
		                                                                                                      1
		                                                                                              );
		else $fetchSQL .= ' `url_id` >= ' . substr( $loadedArguments['offset'], 1 );
	}

	if( !isset( $paywallSQL ) ||
	    substr( $loadedArguments['offset'], 0, 1 ) == "B"
	) $fetchSQL .= " ORDER BY `url_id` ASC LIMIT 1001;";
	else $pfetchSQL .= " ORDER BY `url_id` ASC LIMIT 1001;";

	if( isset( $paywallSQL ) && !empty( $loadedArguments['offset'] ) &&
	    substr( $loadedArguments['offset'], 0, 1 ) != "B"
	) {
		$res = $dbObject->queryDB( $pfetchSQL );
		$usedPaywall = true;
	} else {
		$res = $dbObject->queryDB( $fetchSQL );
		$usedPaywall = false;
	}

	if( $res ) {
		$jsonOut['urls'] = [];
		$counter = 0;
		$reviewedList = [];
		resultcycler:
		while( $result = mysqli_fetch_assoc( $res ) ) {
			$counter++;
			if( isset( $normalizedurls ) ) $i = array_search( $result['url'], $normalizedurls );
			if( $counter > 1000 ) {
				$nextPage = $result['url_id'];
				continue;
			}
			if( $result['has_archive'] == 1 ) {
				$tArray['archive'] = $result['archive_url'];
				$tArray['snapshottime'] = $result['archive_time'];
			} else {
				$tArray = [];
			}

			$state = "unknown";
			$level = "url";
			switch( $result['live_state'] ) {
				case 0:
					$state = "dead";
					break;
				case 1:
				case 2:
					$state = "dying";
					break;
				case 3:
					$state = "alive";
					break;
				case 5:
					$state = "paywalled";
					break;
				case 6:
					$state = "blacklisted";
					break;
				case 7:
					$state = "whitelisted";
					break;
			}
			switch( $result['paywall_status'] ) {
				case 1:
					if( $result['live_state'] == 5 ) {
						$state = "paywalled";
						$level = "domain";
					}
					break;
				case 2:
					$state = "blacklisted";
					$level = "domain";
					break;
				case 3:
					$state = "whitelisted";
					$level = "domain";
					break;
			}
			switch( $result['archived'] ) {
				case 0:
					$archived = false;
					break;
				case 1:
					$archived = true;
					break;
				case 2:
				default:
					$archived = null;
					break;
			}
			$jsonOut['urls'][$result['url_id']] = [
				'id'            => $result['url_id'], 'url' => isset( $normalizedurls ) ? $urls[$i] : $result['url'],
				'normalizedurl' => $result['url'], 'accesstime' => $result['access_time'],
				'hasarchive'    => (bool) $result['has_archive'], 'live_state' => $state, 'state_level' => $level,
				'lastheartbeat' => $result['last_deadCheck'], 'assumedarchivable' => (bool) $result['archivable'],
				'archived'      => $archived, 'attemptedarchivingerror' => $result['archive_failure'],
				'reviewed'      => (bool) $result['reviewed']
			];
			$jsonOut['urls'][$result['url_id']] = array_merge( $jsonOut['urls'][$result['url_id']], $tArray );
			if( (bool) $result['reviewed'] === true ) $reviewedList[] = $result['url_id'];
		}

		if( $counter < 1000 && isset( $paywallSQL ) && $usedPaywall !== false ) {
			$fetchSQL .= ' `url_id` >= 0';
			$fetchSQL .= " ORDER BY `url_id` ASC LIMIT 1001;";
			$res = $dbObject->queryDB( $fetchSQL );
			$usedPaywall = false;
			goto resultcycler;
		}

		if( !empty( $reviewedList ) ) {
			$logSQL =
				"SELECT `user_name`, `log_timestamp`, `log_object` FROM externallinks_userlog LEFT JOIN externallinks_user ON externallinks_userlog.log_user=externallinks_user.user_link_id WHERE `log_object` IN (" .
				implode( ", ", $reviewedList ) . ") AND `log_action` = 'changearchive' ORDER BY `log_timestamp` DESC;";
			$done = [];
			if( $res2 = $dbObject->queryDB( $logSQL ) ) {
				while( $result2 = mysqli_fetch_assoc( $res2 ) ) {
					if( !in_array( $result2['log_object'], $done ) ) {
						$done[] = $result2['log_object'];
						$jsonOut['urls'][$result2['log_object']]['reviewedby'] = $result2['user_name'];
						$jsonOut['urls'][$result2['log_object']]['reviewedon'] = $result2['log_timestamp'];
					}
				}
			}
		}

		if( $counter == 0 ) {
			$jsonOut['requesterror'] = "404";
			$jsonOut['errormessage'] =
				"The requested query didn't yield any results.  They're may be an issue with the DB or the requested parameters don't yield any values.";
			unset( $jsonOut['urls'] );
		}

		if( $counter > 1000 ) {
			if( isset( $paywallSQL ) && $usedPaywall === false ) $jsonOut['continue'] = "B$nextPage";
			else $jsonOut['continue'] = "A$nextPage";
		}
	} else {
		$jsonOut['requesterror'] = "404";
		$jsonOut['errormessage'] =
			"The requested query didn't yield any results.  They're may be an issue with the DB or the requested parameters don't yield any values.";
	}

	return;
}

function loadURLsfromPages( &$jsonOut ) {
	global $dbObject, $loadedArguments;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	$jsonOut['arguments'] = $loadedArguments;

	if( empty( $loadedArguments['offset'] ) || !is_numeric( $loadedArguments['offset'] ) )
		$loadedArguments['offset'] = "0";

	if( !empty( $loadedArguments['pageids'] ) ) {
		if( !empty( $loadedArguments['pageids'] ) ) {
			$pageIDs = explode( "|", $loadedArguments['pageids'] );
		} else {
			$pageIDs = [];
			if( USEWIKIDB === true && !empty( PAGETABLE ) &&
			    ( $db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT ) )
			) {
				$pagetitles = explode( "|", $loadedArguments['pagetitles'] );
				$wikiSQL =
					"SELECT * FROM page WHERE `page_title` IN ('" . implode( "', '", $pagetitles ) .
					"');";
				$res = mysqli_query( $db, $wikiSQL );
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$pageIDs[] = $result['page_id'];
				}
				mysqli_close( $db );
			} else {
				if( USEWIKIDB === true && !empty( PAGETABLE ) ) {
					$jsonOut['requesterror'] = "dberror";
					$jsonOut['errormessage'] =
						"The DB to the Wikipedia tables is not accessible.  Page titles and automatic bot submissions are not available for this request.";
				}
			}
		}

		$fetchSQL = "SELECT * FROM externallinks_" . WIKIPEDIA . " LEFT JOIN externallinks_global ON externallinks_" .
		            WIKIPEDIA .
		            ".url_id = externallinks_global.url_id LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id = externallinks_paywall.paywall_id WHERE `pageid` IN (" .
		            implode( ", ", $pageIDs ) . ") AND ";

		$fetchSQL .= ' externallinks_global.`url_id` >= ' . $loadedArguments['offset'];
		$fetchSQL .= " ORDER BY externallinks_global.`url_id` ASC LIMIT 1001;";

		$res = $dbObject->queryDB( $fetchSQL );

		if( $res ) {
			$jsonOut['urls'] = [];
			$counter = 0;
			$reviewedList = [];
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$counter++;
				if( $counter > 1000 ) {
					$nextPage = $result['url_id'];
					continue;
				}
				if( $result['has_archive'] == 1 ) {
					$tArray['archive'] = $result['archive_url'];
					$tArray['snapshottime'] = $result['archive_time'];
				} else {
					$tArray = [];
				}

				$state = "unknown";
				$level = "url";
				switch( $result['live_state'] ) {
					case 0:
						$state = "dead";
						break;
					case 1:
					case 2:
						$state = "dying";
						break;
					case 3:
						$state = "alive";
						break;
					case 5:
						$state = "paywalled";
						break;
					case 6:
						$state = "blacklisted";
						break;
					case 7:
						$state = "whitelisted";
						break;
				}
				switch( $result['paywall_status'] ) {
					case 1:
						if( $result['live_state'] == 5 ) {
							$state = "paywalled";
							$level = "domain";
						}
						break;
					case 2:
						$state = "blacklisted";
						$level = "domain";
						break;
					case 3:
						$state = "whitelisted";
						$level = "domain";
						break;
				}
				switch( $result['archived'] ) {
					case 0:
						$archived = false;
						break;
					case 1:
						$archived = true;
						break;
					case 2:
					default:
						$archived = null;
						break;
				}
				$jsonOut['urls'][$result['url_id']] = [
					'id'            => $result['url_id'], 'url' => $result['url'],
					'normalizedurl' => $result['url'], 'accesstime' => $result['access_time'],
					'hasarchive'    => (bool) $result['has_archive'], 'live_state' => $state, 'state_level' => $level,
					'lastheartbeat' => $result['last_deadCheck'], 'assumedarchivable' => (bool) $result['archivable'],
					'archived'      => $archived, 'attemptedarchivingerror' => $result['archive_failure'],
					'reviewed'      => (bool) $result['reviewed']
				];
				$jsonOut['urls'][$result['url_id']] = array_merge( $jsonOut['urls'][$result['url_id']], $tArray );
				if( (bool) $result['reviewed'] === true ) $reviewedList[] = $result['url_id'];
			}

			if( !empty( $reviewedList ) ) {
				$logSQL =
					"SELECT `user_name`, `log_timestamp`, `log_object` FROM externallinks_userlog LEFT JOIN externallinks_user ON externallinks_userlog.log_user=externallinks_user.user_link_id WHERE `log_object` IN (" .
					implode( ", ", $reviewedList ) .
					") AND `log_action` = 'changearchive' ORDER BY `log_timestamp` DESC;";
				$done = [];
				if( $res2 = $dbObject->queryDB( $logSQL ) ) {
					while( $result2 = mysqli_fetch_assoc( $res2 ) ) {
						if( !in_array( $result2['log_object'], $done ) ) {
							$done[] = $result2['log_object'];
							$jsonOut['urls'][$result2['log_object']]['reviewedby'] = $result2['user_name'];
							$jsonOut['urls'][$result2['log_object']]['reviewedon'] = $result2['log_timestamp'];
						}
					}
				}
			}

			if( $counter == 0 ) {
				$jsonOut['requesterror'] = "404";
				$jsonOut['errormessage'] =
					"The requested query didn't yield any results.  They're may be an issue with the DB or the requested parameters don't yield any values.";
				unset( $jsonOut['urls'] );
			}

			if( $counter > 1000 ) {
				$jsonOut['continue'] = $nextPage;
			}
		} else {
			$jsonOut['requesterror'] = "404";
			$jsonOut['errormessage'] =
				"The requested query didn't yield any results.  They're may be an issue with the DB or the requested parameters don't yield any values.";
		}
	} else {
		$jsonOut['missingvalue'] = "pageids";
		$jsonOut['errormessage'] =
			"The \"pageids\" is a pipe separated list of page IDs that must be supplied to complete this request.";
	}
}

function loadPagesFromURL( &$jsonOut ) {
	global $dbObject, $loadedArguments;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	$jsonOut['arguments'] = $loadedArguments;

	if( empty( $loadedArguments['offset'] ) || !is_numeric( $loadedArguments['offset'] ) )
		$loadedArguments['offset'] = "0";

	if( !empty( $loadedArguments['url'] ) || !empty( $loadedArguments['urlid'] ) ) {
		if( !empty( $loadedArguments['urlid'] ) ) {
			$sqlPages = "SELECT pageid FROM externallinks_" . WIKIPEDIA . " WHERE `url_id` = " .
			            $dbObject->sanitize( $loadedArguments['urlid'] );
		} else {
			$loadedArguments['url'] = $checkIfDead->sanitizeURL( $loadedArguments['url'], true );
			$sqlPages =
				"SELECT pageid FROM externallinks_" . WIKIPEDIA . " LEFT JOIN externallinks_global ON externallinks_" .
				WIKIPEDIA . ".url_id = externallinks_global.url_id WHERE `url` = '" .
				$dbObject->sanitize( $loadedArguments['url'] ) . "'";
		}

		$sqlPages .= ' `pageid` >= ' . $loadedArguments['offset'];
		$sqlPages .= " ORDER BY `pageid` ASC LIMIT 1001;";

		if( $res = $dbObject->queryDB( $sqlPages ) ) {
			$toFetch = [];
			$pages = [];
			$counter = 0;
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$counter++;
				if( $counter > 1000 ) {
					$nextPage = $result['pageid'];
					continue;
				}
				$toFetch[] = $result['pageid'];
			}
			$_SESSION['urlpagelist'] = [];
			if( USEWIKIDB === true && !empty( PAGETABLE ) &&
			    ( $db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT ) )
			) {
				$wikiSQL = "SELECT * FROM page WHERE `page_id` IN (" . implode( ",", $toFetch ) . ");";
				$res = mysqli_query( $db, $wikiSQL );
				$jsonOut['pages'] = [];
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$jsonOut['pages'][$result['page_id']] = $result;
					$_SESSION['urlpagelist'][] = str_replace( "_", " ", $result['page_title'] );
				}
				mysqli_close( $db );
			} else {
				if( USEWIKIDB === true && !empty( PAGETABLE ) ) {
					$jsonOut['requesterror'] = "dberror";
					$jsonOut['errormessage'] =
						"The DB to the Wikipedia tables is not accessible.  Page titles and automatic bot submissions are not available for this request.";
				}
			}

			if( $counter > 1000 ) {
				$jsonOut['continue'] = $nextPage;
			}
		}
	} else {
		$jsonOut['missingvalue'] = "url OR urlid";
		$jsonOut['errormessage'] =
			"Either the \"url\" or the \"urlid\" must be supplied to complete this request.  URL ID is recommended.";
	}

	return;
}

function loadURLInterface() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $accessibleWikis;
	$checkIfDead = new \Wikimedia\DeadlinkChecker\CheckIfDead();
	if( !validatePermission( "changeurldata", false ) ) {
		loadPermissionError( "changeurldata" );

		return;
	}
	$bodyHTML = new HTMLLoader( "urlinterface", $userObject->getLanguage() );
	if( !empty( $loadedArguments['url'] ) ) {
		$loadedArguments['url'] = $checkIfDead->sanitizeURL( $loadedArguments['url'], true );
		$sqlURL =
			"SELECT * FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id=externallinks_paywall.paywall_id WHERE `url` = '" .
			$dbObject->sanitize( $loadedArguments['url'] ) . "';";
		$bodyHTML->assignElement( "urlencodedurl", urlencode( $loadedArguments['url'] ) );
		$bodyHTML->assignAfterElement( "url", htmlspecialchars( $loadedArguments['url'] ) );
		$bodyHTML->assignElement( "urlvalueelement", " value={{url}}" );
		if( ( $res = $dbObject->queryDB( $sqlURL ) ) && ( $result = mysqli_fetch_assoc( $res ) ) ) {
			mysqli_free_result( $res );
			$bodyHTML->assignElement( "urlid", $result['url_id'] );
			$bodyHTML->assignElement( "urlformdisplaycontrol", "block" );
			$bodyHTML->assignAfterElement( "accesstime",
				( strtotime( $result['access_time'] ) > 0 ?
					Parser::strftime( '%H:%M %-e %B %Y', strtotime( $result['access_time'] ) ) :
					"" )
			);
			if( !validatePermission( "alteraccesstime", false ) ) {
				$bodyHTML->assignElement( "accesstimedisabled", " disabled=\"disabled\"" );
			}
			$bodyHTML->assignElement( "deadchecktime", ( strtotime( $result['last_deadCheck'] ) > 0 ?
				Parser::strftime( '%H:%M %-e %B %Y', strtotime( $result['last_deadCheck'] ) ) : "{{{none}}}" )
			);
			if( $result['archived'] == 2 ) {
				$bodyHTML->assignElement( "archived", "{{{unknown}}}" );
				$bodyHTML->assignElement( "archivedhasstatus", "default" );
				$bodyHTML->assignElement( "archivedglyphicon", "question" );
			} elseif( $result['archived'] == 1 ) {
				$bodyHTML->assignElement( "archived", "{{{yes}}}" );
				$bodyHTML->assignElement( "archivedhasstatus", "success" );
				$bodyHTML->assignElement( "archivedglyphicon", "ok" );
			} elseif( $result['archived'] == 0 ) {
				$bodyHTML->assignElement( "archived", "{{{no}}}" );
				$bodyHTML->assignElement( "archivedhasstatus", "error" );
				$bodyHTML->assignElement( "archivedglyphicon", "remove" );
			}
			$selector = "<select id=\"livestateselect\" name=\"livestateselect\" class=\"form-control\">\n";
			$selector .= "<option value=\"0\" {{{{0selected}}}} {{{{0disabled}}}}>{{{dead}}}</option>\n";
			$selector .= "{{{{dyingselector}}}}\n";
			$selector .= "<option value=\"3\" {{{{3selected}}}} {{{{3disabled}}}}>{{{alive}}}</option>\n";
			$selector .= "{{{{unknownselector}}}}\n";
			$selector .= "<option value=\"5\" {{{{5selected}}}} {{{{5disabled}}}}>{{{paywall}}}</option>\n";
			$selector .= "<option value=\"6\" {{{{6selected}}}} {{{{6disabled}}}}>{{{blacklisted}}}</option>\n";
			$selector .= "<option value=\"7\" {{{{7selected}}}} {{{{7disabled}}}}>{{{whitelisted}}}</option>\n";
			$selector .= "</select>";
			$selectorHTML = new HTMLLoader( $selector, $userObject->getLanguage() );
			$lockSelector = false;

			switch( $result['paywall_status'] ) {
				case 1:
					if( $result['live_state'] == 5 ) {
						$bodyHTML->assignElement( "livestatehasstatus", "warning" );
						$bodyHTML->assignElement( "livestateglyphicon", "lock" );
						$bodyHTML->assignElement( "livestate", "{{{paywall}}}" );
						$lockSelector = true;
					}
					break;
				case 2:
					$bodyHTML->assignElement( "livestatehasstatus", "error" );
					$bodyHTML->assignElement( "livestateglyphicon", "thumbs-down" );
					$bodyHTML->assignElement( "livestate", "{{{blacklisted}}}" );
					$lockSelector = true;
					break;
				case 3:
					$bodyHTML->assignElement( "livestatehasstatus", "success" );
					$bodyHTML->assignElement( "livestateglyphicon", "thumbs-up" );
					$bodyHTML->assignElement( "livestate", "{{{whitelisted}}}" );
					$lockSelector = true;
					break;
			}
			switch( $result['live_state'] ) {
				case 0:
					$bodyHTML->assignElement( "livestatehasstatus", "error" );
					$bodyHTML->assignElement( "livestateglyphicon", "remove-sign" );
					$bodyHTML->assignElement( "livestate", "{{{dead}}}" );
					$selectorHTML->assignElement( "0selected", "selected" );
					break;
				case 1:
				case 2:
					$bodyHTML->assignElement( "livestatehasstatus", "warning" );
					$bodyHTML->assignElement( "livestateglyphicon", "warning-sign" );
					$bodyHTML->assignElement( "livestate", "{{{dying}}}" );
					$selectorHTML->assignElement( "dyingselector",
					                              "<option value=\"{$result['live_state']}\" disabled=\"disabled\" selected>{{{dying}}}</option>"
					);
					break;
				case 3:
					$bodyHTML->assignElement( "livestatehasstatus", "success" );
					$bodyHTML->assignElement( "livestateglyphicon", "ok-sign" );
					$bodyHTML->assignElement( "livestate", "{{{alive}}}" );
					$selectorHTML->assignElement( "3selected", "selected" );
					break;
				case 4:
					$bodyHTML->assignElement( "livestatehasstatus", "default" );
					$bodyHTML->assignElement( "livestateglyphicon", "question-sign" );
					$selectorHTML->assignElement( "unknownselector",
					                              "<option value=\"{$result['live_state']}\" disabled=\"disabled\" selected>{{{unknown}}}</option>"
					);
					$bodyHTML->assignElement( "livestate", "{{{unknown}}}" );
					break;
				case 5:
					$bodyHTML->assignElement( "livestatehasstatus", "warning" );
					$bodyHTML->assignElement( "livestateglyphicon", "lock" );
					$bodyHTML->assignElement( "livestate", "{{{paywall}}}" );
					$selectorHTML->assignElement( "5selected", "selected" );
					if( $result['paywall_status'] == 1 ) $lockSelector = true;
					break;
				case 6:
					$bodyHTML->assignElement( "livestatehasstatus", "error" );
					$bodyHTML->assignElement( "livestateglyphicon", "thumbs-down" );
					$bodyHTML->assignElement( "livestate", "{{{blacklisted}}}" );
					$selectorHTML->assignElement( "6selected", "selected" );
					if( !validatePermission( "deblacklisturls", false ) ) {
						$selectorHTML->assignElement( "0disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "3disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "5disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "7disabled", "disabled=\"disabled\"" );
					}
					break;
				case 7:
					$bodyHTML->assignElement( "livestatehasstatus", "success" );
					$bodyHTML->assignElement( "livestateglyphicon", "thumbs-up" );
					$bodyHTML->assignElement( "livestate", "{{{whitelisted}}}" );
					$selectorHTML->assignElement( "7selected", "selected" );
					if( !validatePermission( "dewhitelisturls", false ) ) {
						$selectorHTML->assignElement( "0disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "3disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "5disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "6disabled", "disabled=\"disabled\"" );
					}
					break;
				default:
					$bodyHTML->assignElement( "livestatehasstatus", "default" );
					$bodyHTML->assignElement( "livestateglyphicon", "question-sign" );
					$bodyHTML->assignElement( "livestate", "{{{unknown}}}" );
					$selectorHTML->assignElement( "unknownselector",
					                              "<option value=\"{$result['live_state']}\" disabled=\"disabled\" selected>{{{unknown}}}</option>"
					);
					break;
			}
			if( !validatePermission( "blacklisturls", false ) ) {
				$selectorHTML->assignElement( "6disabled", "disabled=\"disabled\"" );
			}
			if( !validatePermission( "whitelisturls", false ) ) {
				$selectorHTML->assignElement( "7disabled", "disabled=\"disabled\"" );
			}
			$selectorHTML->finalize();
			if( $lockSelector === false ) $bodyHTML->assignElement( "livestateselect",
			                                                        $selectorHTML->getLoadedTemplate()
			);

			if( !is_null( $result['archive_url'] ) ) {
				$bodyHTML->assignElement( "archiveurlvalue", " value=\"{$result['archive_url']}\"" );
				$bodyHTML->assignElement( "snapshottime",
				                          Parser::strftime( '%H:%M %-e %B %Y', strtotime( $result['archive_time'] ) )
				);
			} else {
				$bodyHTML->assignElement( "snapshottime", "&mdash;" );
			}
			if( !validatePermission( "alterarchiveurl", false ) ) {
				$bodyHTML->assignElement( "archiveurldisabled", " disabled=\"disabled\"" );
			}
			if( !validatePermission( "overridearchivevalidation", false ) ) {
				$bodyHTML->assignElement( "overridearchivevalidationsuppression",
				                          "style=\"display:none\" disabled=\"disabled\""
				);
			}

			$sqlPages = "SELECT * FROM externallinks_" . WIKIPEDIA . " WHERE `url_id` = " . $result['url_id'];
			$logURL = "SELECT * FROM externallinks_userlog WHERE (`log_type` = 'urldata' AND `log_object` = '" .
			          $result['url_id'] . "') OR (`log_type` = 'domaindata' AND `log_object` = '" .
			          $result['paywall_id'] . "') ORDER BY `log_timestamp` ASC;";
			if( $res = $dbObject->queryDB( $sqlPages ) ) {
				$toFetch = [];
				$pages = [];
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$toFetch[] = $result['pageid'];
				}
				$_SESSION['urlpagelist'] = [];
				if( USEWIKIDB === true && !empty( PAGETABLE ) &&
				    ( $db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT ) )
				) {
					$wikiSQL = "SELECT * FROM page WHERE `page_id` IN (" . implode( ",", $toFetch ) . ");";
					$res = mysqli_query( $db, $wikiSQL );
					if( $res ) while( $result = mysqli_fetch_assoc( $res ) ) {
						$pages[] = str_replace( "_", " ", $result['page_title'] );
						$_SESSION['urlpagelist'][] = str_replace( "_", " ", $result['page_title'] );
					}
					mysqli_close( $db );
				} else {
					if( USEWIKIDB === true && !empty( PAGETABLE ) ) {
						$mainHTML->setMessageBox( "warning", "{{{dberror}}}", "{{{wikidbconnectfailed}}}" );
					}
					do {
						$url = API;
						$post = [];
						$post['format'] = "json";
						$post['action'] = "query";
						$post['pageids'] = implode( "|", $toFetch );
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
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, [ API::generateOAuthHeader( 'POST', $url ) ] );
						curl_setopt( $ch, CURLOPT_HTTPGET, 0 );
						curl_setopt( $ch, CURLOPT_POST, 1 );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
						$data = curl_exec( $ch );
						curl_close( $ch );
						$data = json_decode( $data, true );
						if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $pageID => $page ) {
							if( !isset( $page['missing'] ) ) {
								$pages[] = $page['title'];
							}
						}
						$toFetch = array_slice( $toFetch, 50 );
					} while( !empty( $toFetch ) );
				}
				$pagesElement = "";
				foreach( $pages as $page ) {
					$pagesElement .= "<li><a href=\"" . $accessibleWikis[WIKIPEDIA]['rooturl'] . "wiki/" .
					                 htmlspecialchars( rawurlencode( $page ) ) . "\">" . htmlspecialchars( $page ) .
					                 "</a></li>\n";
					$_SESSION['urlpagelist'][] = $page;
				}
				$bodyHTML->assignElement( "foundonarticles", $pagesElement );
			}
			$logElement = "";
			if( $res = $dbObject->queryDB( $logURL ) ) {
				$result = mysqli_fetch_all( $res, MYSQLI_ASSOC );
				loadLogUsers( $result );
				foreach( $result as $entry ) {
					$logElement .= "<li>" . getLogText( $entry ) . "</li>\n";
				}
				if( empty( $result ) ) {
					$bodyHTML->assignElement( "logurldata", "{{{none}}}" );
				} else {
					$bodyHTML->assignElement( "logurldata", $logElement );
				}
			}

		} else {
			$mainHTML->setMessageBox( "danger", "{{{404url}}}", "{{{404urlmessage}}}" );
			$bodyHTML->assignElement( "urlformdisplaycontrol", "none" );
		}
	} else {
		$bodyHTML->assignElement( "urlformdisplaycontrol", "none" );
	}
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{urlinterface}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadDomainInterface() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $accessibleWikis;
	$bodyHTML = new HTMLLoader( "domaininterface", $userObject->getLanguage() );
	if( !validatePermission( "changedomaindata", false ) ) {
		loadPermissionError( "changedomaindata" );

		return;
	}
	if( isset( $loadedArguments['domainsearch'] ) && !empty( $loadedArguments['domainsearch'] ) ) {
		$loadedArguments['domainsearch'] = strtolower( $loadedArguments['domainsearch'] );
		$loadedArguments['domainsearch'] =
			preg_replace( '/(?:[a-z0-9\+\-\.]*:)?\/\//i', "", $loadedArguments['domainsearch'], 1 );
		if( isset( $loadedArguments['exactmatch'] ) && $loadedArguments['exactmatch'] == "on" ) {
			$searchSQL = "SELECT * FROM externallinks_paywall WHERE `domain` = '" .
			             $dbObject->sanitize( $loadedArguments['domainsearch'] ) . "';";
		} elseif( strlen( $loadedArguments['domainsearch'] ) > 4 ) {
			$searchSQL = "SELECT * FROM externallinks_paywall WHERE `domain` LIKE '%" .
			             $dbObject->sanitize( $loadedArguments['domainsearch'] ) . "%';";
		} else {
			$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{domainsearchruleviolation}}}" );
			$bodyHTML->assignElement( "domainselectordisplaycontrol", "none" );
			$bodyHTML->assignElement( "urlformdisplaycontrol", "none" );
			goto domainfinish;
		}
		$bodyHTML->assignElement( "domainsearchvalue", htmlspecialchars( $loadedArguments['domainsearch'] ) );
		$bodyHTML->assignElement( "domainvalueelement",
		                          "value=\"" . htmlspecialchars( $loadedArguments['domainsearch'] ) . "\""
		);
		$domainCheckboxes = "";
		if( ( !isset( $loadedArguments['pageaction'] ) || $loadedArguments['pageaction'] != "submitpaywalls" ) &&
		    ( !isset( $loadedArguments['action'] ) || $loadedArguments['action'] != "submitdomaindata" )
		) {
			$res = $dbObject->queryDB( $searchSQL );
			$column = 1;
			if( mysqli_num_rows( $res ) >= 1 ) while( $result = mysqli_fetch_assoc( $res ) ) {
				if( $column === 1 ) $domainCheckboxes .= "<div class=\"row\">\n";
				$domainCheckboxes .= "<div class=\"col-md-4\">\n";
				$domainCheckboxes .= "<label class=\"checkbox-inline\">\n";
				$domainCheckboxes .= "<input aria-label=\"" . htmlspecialchars( $result['domain'] ) .
				                     "\" type=\"checkbox\" id=\"{$result['paywall_id']}\" name=\"{$result['paywall_id']}\">\n";
				$domainCheckboxes .= "<span aria-hidden=\"true\">" . htmlspecialchars( $result['domain'] ) .
				                     "</span>\n";
				$domainCheckboxes .= "</label>\n";
				$domainCheckboxes .= "</div>";
				if( $column === 3 ) {
					$domainCheckboxes .= "</div>\n";
					$column = 1;
				} else {
					$column++;
				}
				$bodyHTML->assignElement( "domainsearcherdisplaycontrol", "none" );
			} else {
				mysqli_free_result( $res );
				$mainHTML->setMessageBox( "danger", "{{{domaindataerror}}}", "{{{domainsearchempty}}}" );
				$bodyHTML->assignElement( "domainselectordisplaycontrol", "none" );
				$bodyHTML->assignElement( "urlformdisplaycontrol", "none" );
			}
			if( $column > 1 ) {
				$domainCheckboxes .= "</div>\n";
			}
			$bodyHTML->assignElement( "domainselectionoption", $domainCheckboxes );
			$bodyHTML->assignElement( "urlformdisplaycontrol", "none" );
		} else {
			$bodyHTML->assignElement( "domainselectordisplaycontrol", "none" );
			if( isset( $loadedArguments['paywallids'] ) && !empty( $loadedArguments['paywallids'] ) ) {
				$paywallIDs = explode( "|", $loadedArguments['paywallids'] );
			} else {
				$paywallIDs = [];
				foreach( $loadedArguments as $id => $value ) {
					if( is_numeric( $id ) ) {
						if( $value == "on" ) {
							$paywallIDs[] = $id;
						}
					}
				}
			}
			$bodyHTML->assignElement( "pipeseperatepaywallids", implode( "|", $paywallIDs ) );
			$paywallSQL =
				"SELECT * FROM externallinks_paywall WHERE `paywall_id` IN (" . implode( ",", $paywallIDs ) . ");";
			$urlsSQL =
				"SELECT * FROM externallinks_global WHERE `paywall_id` IN (" . implode( ",", $paywallIDs ) . ");";
			$res = $dbObject->queryDB( $paywallSQL );
			$domainList = "";
			$paywallStatus = -2;
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$domainList .= "<li>" . htmlspecialchars( $result['domain'] ) . "</li>\n";
				if( $paywallStatus == -2 ) $paywallStatus = $result['paywall_status'];
				elseif( $paywallStatus != $result['paywall_status'] ) $paywallStatus = -1;
			}
			$res = $dbObject->queryDB( $urlsSQL );
			$urlIDs = [];
			$urlList = "";
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$urlIDs[] = $result['url_id'];
				$urlList .= "<li><a href=\"" . htmlspecialchars( $result['url'] ) . "\">" .
				            htmlspecialchars( $result['url'] ) . "</a></li>\n";
			}
			$pageIDs = [];
			$pageList = "";
			$pageSQL =
				"SELECT * FROM externallinks_" . WIKIPEDIA . " WHERE `url_id` IN (" . implode( ",", $urlIDs ) . ")";
			$res = $dbObject->queryDB( $pageSQL );
			while( $result = mysqli_fetch_assoc( $res ) ) {
				if( !in_array( $result['pageid'], $pageIDs ) ) {
					$pageIDs[] = $result['pageid'];
				}
			}
			$_SESSION['domainpagelist'] = [];
			if( USEWIKIDB === true && !empty( PAGETABLE ) &&
			    ( $db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT ) )
			) {
				$wikiSQL = "SELECT * FROM page WHERE `page_id` IN (" . implode( ",", $pageIDs ) . ");";
				$res = mysqli_query( $db, $wikiSQL );
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$pageList .= "<li><a href=\"" . $accessibleWikis[WIKIPEDIA]['rooturl'] . "wiki/" .
					             htmlspecialchars( rawurlencode( $result['page_title'] ) ) . "\">" .
					             htmlspecialchars( str_replace( "_", " ", $result['page_title'] ) ) . "</a></li>\n";
					$_SESSION['domainpagelist'][] = str_replace( "_", " ", $result['page_title'] );
				}
				mysqli_close( $db );
			} else {
				if( USEWIKIDB === true && !empty( PAGETABLE ) ) {
					$mainHTML->setMessageBox( "warning", "{{{dberror}}}", "{{{wikidbconnectfailed}}}" );
				}
				do {
					$url = API;
					$post = [];
					$post['format'] = "json";
					$post['action'] = "query";
					$post['pageids'] = implode( "|", $pageIDs );
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
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, [ API::generateOAuthHeader( 'POST', $url ) ] );
					curl_setopt( $ch, CURLOPT_HTTPGET, 0 );
					curl_setopt( $ch, CURLOPT_POST, 1 );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
					$data = curl_exec( $ch );
					curl_close( $ch );
					$data = json_decode( $data, true );
					if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $pageID => $page ) {
						if( !isset( $page['missing'] ) ) {
							$pageList .= "<li><a href=\"" . $accessibleWikis[WIKIPEDIA]['rooturl'] . "wiki/" .
							             htmlspecialchars( rawurlencode( $page['title'] ) ) . "\">" .
							             htmlspecialchars( $page['title'] ) . "</a></li>\n";
							$_SESSION['domainpagelist'][] = $page['title'];
						}
					}
					$pageIDs = array_slice( $pageIDs, 50 );
				} while( !empty( $pageIDs ) );
			}
			$bodyHTML->assignElement( "targetdomains", $domainList );
			$bodyHTML->assignElement( "affectedurls", $urlList );
			$bodyHTML->assignElement( "foundonarticles", $pageList );
			$selector = "<select id=\"livestateselect\" name=\"livestateselect\" class=\"form-control\">\n";
			$selector .= "{{{{unknownselector}}}}\n";
			$selector .= "{{{{mixedselector}}}}\n";
			$selector .= "<option value=\"0\" {{{{0selected}}}} {{{{0disabled}}}}>{{{none}}}</option>\n";
			$selector .= "<option value=\"4\" {{{{4selected}}}} {{{{4disabled}}}}>{{{alive}}}</option>\n";
			$selector .= "<option value=\"5\" {{{{5selected}}}} {{{{5disabled}}}}>{{{dead}}}</option>\n";
			$selector .= "<option value=\"1\" {{{{1selected}}}} {{{{1disabled}}}}>{{{paywall}}}</option>\n";
			$selector .= "<option value=\"2\" {{{{2selected}}}} {{{{2disabled}}}}>{{{blacklisted}}}</option>\n";
			$selector .= "<option value=\"3\" {{{{3selected}}}} {{{{3disabled}}}}>{{{whitelisted}}}</option>\n";
			$selector .= "</select>";
			$selectorHTML = new HTMLLoader( $selector, $userObject->getLanguage() );

			switch( $paywallStatus ) {
				case -2:
					$bodyHTML->assignElement( "livestatehasstatus", "default" );
					$bodyHTML->assignElement( "livestateglyphicon", "question-sign" );
					$bodyHTML->assignElement( "livestate", "{{{unknown}}}" );
					$selectorHTML->assignElement( "unknownselector",
					                              "<option value=\"{$paywallStatus}\" disabled=\"disabled\" selected>{{{unknown}}}</option>"
					);
					break;
				case -1:
					$bodyHTML->assignElement( "livestatehasstatus", "warning" );
					$bodyHTML->assignElement( "livestateglyphicon", "random" );
					$bodyHTML->assignElement( "livestate", "{{{mixed}}}" );
					$selectorHTML->assignElement( "mixedselector",
					                              "<option value=\"{$paywallStatus}\" disabled=\"disabled\" selected>{{{mixed}}}</option>"
					);
					break;
				case 0:
					$bodyHTML->assignElement( "livestatehasstatus", "default" );
					$bodyHTML->assignElement( "livestateglyphicon", "minus" );
					$bodyHTML->assignElement( "livestate", "{{{none}}}" );
					break;
				case 1:
					$bodyHTML->assignElement( "livestatehasstatus", "warning" );
					$bodyHTML->assignElement( "livestateglyphicon", "lock" );
					$bodyHTML->assignElement( "livestate", "{{{paywall}}}" );
					break;
				case 2:
					$bodyHTML->assignElement( "livestatehasstatus", "error" );
					$bodyHTML->assignElement( "livestateglyphicon", "thumbs-down" );
					$bodyHTML->assignElement( "livestate", "{{{blacklisted}}}" );
					if( !validatePermission( "deblacklistdomains", false ) ) {
						$selectorHTML->assignElement( "0disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "1disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "3disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "4disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "5disabled", "disabled=\"disabled\"" );
					}
					break;
				case 3:
					$bodyHTML->assignElement( "livestatehasstatus", "success" );
					$bodyHTML->assignElement( "livestateglyphicon", "thumbs-up" );
					$bodyHTML->assignElement( "livestate", "{{{whitelisted}}}" );
					if( !validatePermission( "dewhitelistdomains", false ) ) {
						$selectorHTML->assignElement( "0disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "1disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "2disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "4disabled", "disabled=\"disabled\"" );
						$selectorHTML->assignElement( "5disabled", "disabled=\"disabled\"" );
					}
					break;
			}

			$selectorHTML->assignElement( "{$paywallStatus}selected", "selected" );
			if( !validatePermission( "blacklistdomains", false ) ) {
				$selectorHTML->assignElement( "2disabled", "disabled=\"disabled\"" );
			}
			if( !validatePermission( "whitelistdomains", false ) ) {
				$selectorHTML->assignElement( "3disabled", "disabled=\"disabled\"" );
			}
			$selectorHTML->finalize();
			$bodyHTML->assignElement( "livestateselect", $selectorHTML->getLoadedTemplate() );
		}
	} else {
		$bodyHTML->assignElement( "domainselectordisplaycontrol", "none" );
		$bodyHTML->assignElement( "urlformdisplaycontrol", "none" );
	}
	domainfinish:
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{domaininterface}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadPageAnalyser() {
	global $mainHTML, $userObject, $runStats, $modifiedLinks, $loadedArguments;
	$bodyHTML = new HTMLLoader( "pageanalysis", $userObject->getLanguage() );
	$pageURL = "";
	if( !validatePermission( "analyzepage", false ) ) {
		loadPermissionError( "analyzepage" );

		return;
	}

	if( isset( $loadedArguments['pagesearch'] ) ) {
		$bodyHTML->assignElement( "pagevalueelement",
		                          "value=\"" . htmlspecialchars( $loadedArguments['pagesearch'] ) . "\""
		);
		$bodyHTML->assignAfterElement( "pagetitle", htmlspecialchars( $loadedArguments['pagesearch'] ) );
		$pageURL = "{{wikiroot}}wiki/{{pagetitle}}";
	}

	if( isset( $loadedArguments['archiveall'] ) && $loadedArguments['archiveall'] == "on" ) {
		$bodyHTML->assignElement( "archiveall", "checked" );
	}
	if( isset( $loadedArguments['restrictref'] ) && $loadedArguments['restrictref'] == "on" ) {
		$bodyHTML->assignElement( "restrictref", "checked" );
	}

	if( !is_null( $runStats ) ) {
		$bodyHTML->assignElement( "timerequired", $runStats['runtime'] . " {{{seconds}}}" );
		$bodyHTML->assignElement( "pagemodified", ( $runStats['pagemodified'] === true ? "{{{yes}}}" : "{{{no}}}" ) );
		$bodyHTML->assignElement( "linksanalyzed", $runStats['linksanalyzed'] );
		$bodyHTML->assignElement( "linksarchived", $runStats['linksarchived'] );
		$bodyHTML->assignElement( "linksrescued", $runStats['linksrescued'] );
		$bodyHTML->assignElement( "linkstagged", $runStats['linkstagged'] );
		if( $runStats['revid'] !== false ) {
			$pageURL = str_replace( "api.php", "index.php", API ) . "?diff=prev&oldid={$runStats['revid']}";
		}
		$bodyHTML->assignElement( "pageurl", $pageURL );

		$modifiedLinkString = "";
		foreach( $modifiedLinks as $link ) {
			$tout = new HTMLLoader( "{{{ml{$link['type']}}}}", $userObject->getLanguage() );
			if( isset( $link['link'] ) ) $tout->assignAfterElement( "link",
			                                                        "<a href=\"" . htmlspecialchars( $link['link'] ) .
			                                                        "\">" . htmlspecialchars( $link['link'] ) . "</a>"
			);
			if( isset( $link['oldarchive'] ) ) $tout->assignAfterElement( "oldarchive", "<a href=\"" .
			                                                                            htmlspecialchars( $link['oldarchive']
			                                                                            ) . "\">" .
			                                                                            htmlspecialchars( $link['oldarchive']
			                                                                            ) . "</a>"
			);
			if( isset( $link['newarchive'] ) ) $tout->assignAfterElement( "newarchive", "<a href=\"" .
			                                                                            htmlspecialchars( $link['newarchive']
			                                                                            ) . "\">" .
			                                                                            htmlspecialchars( $link['newarchive']
			                                                                            ) . "</a>"
			);
			$tout->finalize();

			$modifiedLinkString .= "<li>" . $tout->getLoadedTemplate() . "</li>\n";
		}

		$bodyHTML->assignElement( "modificationsmade", $modifiedLinkString );
	} else {
		$bodyHTML->assignElement( "rundisplaycontrol", "none" );
	}

	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{pageanalysis}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadBotQueuer() {
	global $mainHTML, $userObject, $loadedArguments, $dbObject;
	$meSQL =
		"SELECT `user_id` FROM externallinks_user WHERE `user_name` = '" . TASKNAME . "' AND `wiki` = '" . WIKIPEDIA .
		"';";
	$res = $dbObject->queryDB( $meSQL );
	if( !$res || mysqli_num_rows( $res ) < 1 ) {
		$bodyHTML = new HTMLLoader( "botqueuesubmitterdisabled", $userObject->getLanguage() );
		$bodyHTML->finalize();
		$mainHTML->assignElement( "tooltitle", "{{{botsubmitdisabled}}}" );
		$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );

		return;
	}
	$bodyHTML = new HTMLLoader( "botqueuesubmitter", $userObject->getLanguage() );
	if( !validatePermission( "submitbotjobs", false ) ) {
		loadPermissionError( "submitbotjobs" );

		return;
	}

	if( validatePermission( "botsubmitlimitnolimit", false ) ) {
		$bodyHTML->assignAfterElement( "submitlimit", "∞" );
	} elseif( validatePermission( "botsubmitlimit50000", false ) ) {
		$bodyHTML->assignAfterElement( "submitlimit", "50000" );
	} elseif( validatePermission( "botsubmitlimit5000", false ) ) {
		$bodyHTML->assignAfterElement( "submitlimit", "5000" );
	} else {
		$bodyHTML->assignAfterElement( "submitlimit", "500" );
	}

	if( isset( $loadedArguments['loadfrom'] ) ) {
		if( isset( $_SESSION[$loadedArguments['loadfrom'] . 'pagelist'] ) ) {
			$bodyHTML->assignElement( "pagelistvalue",
			                          htmlspecialchars( implode( "\n",
			                                                     $_SESSION[$loadedArguments['loadfrom'] . 'pagelist']
			                                            )
			                          )
			);
		} else {
			$mainHTML->setMessageBox( "danger", "{{{pagelisterror}}}", "{{{missingpagelistsource}}}" );
		}
	}

	if( isset( $loadedArguments['pagelist'] ) && !empty( $loadedArguments['pagelist'] ) ) {
		$bodyHTML->assignElement( "pagelistvalue", $loadedArguments['pagelist'] );
	}

	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{botsubmit}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadJobViewer( &$jsonOutAPI = false ) {
	global $mainHTML, $userObject, $loadedArguments, $dbObject, $locales;
	if( $jsonOutAPI === false ) {
		$bodyHTML = new HTMLLoader( "jobview", $userObject->getLanguage() );
		$bodyHTML->loadWikisi18n();
		$jsonOut = [];
	}
	if( $jsonOutAPI === false && $loadedArguments['page'] != "viewjob" ) $loadedArguments['page'] = "viewjob";
	if( !empty( $loadedArguments['id'] ) ) {
		if( $jsonOutAPI === false ) {
			$bodyHTML->assignElement( "jobvalueelement", " value={{id}}" );
			$bodyHTML->assignAfterElement( "id", htmlspecialchars( $loadedArguments['id'] ) );
		}

		$sql =
			"SELECT * FROM externallinks_botqueue LEFT JOIN externallinks_user ON externallinks_botqueue.queue_user = externallinks_user.user_link_id AND externallinks_botqueue.wiki = externallinks_user.wiki WHERE `queue_id` = '" .
			$dbObject->sanitize( $loadedArguments['id'] ) . "';";
		if( $res = $dbObject->queryDB( $sql ) ) {
			if( mysqli_num_rows( $res ) > 0 ) {
				$result = mysqli_fetch_assoc( $res );
				mysqli_free_result( $res );
				if( $jsonOutAPI === false ) {
					$bodyHTML->assignElement( "bqjobid", $result['queue_id'] );
					$bodyHTML->assignElement( "bqwiki", "{{{" . $result['wiki'] . "name}}}" );
					$bodyHTML->assignElement( "bquser", htmlspecialchars( $result['user_name'] ) );
					$bodyHTML->assignElement( "bqqueuetimestamp", $result['queue_timestamp'] );
					switch( $result['queue_status'] ) {
						case 0:
							$tempLoader = new HTMLLoader( "{{{queued}}}", $userObject->getLanguage() );
							break;
						case 1:
							$tempLoader = new HTMLLoader( "{{{running}}}", $userObject->getLanguage() );
							break;
						case 2:
							$tempLoader = new HTMLLoader( "{{{finished}}}", $userObject->getLanguage() );
							break;
						case 3:
							$tempLoader = new HTMLLoader( "{{{killed}}}", $userObject->getLanguage() );
							break;
						case 4:
							$tempLoader = new HTMLLoader( "{{{suspended}}}", $userObject->getLanguage() );
							break;
					}
					$tempLoader->finalize();
					$jsonOut['bqstatus'] = $tempLoader->getLoadedTemplate();
					$bodyHTML->assignElement( "bqstatus", $tempLoader->getLoadedTemplate() );
					$result['run_stats'] = unserialize( $result['run_stats'] );
					$result['queue_pages'] = unserialize( $result['queue_pages'] );
					$bodyHTML->assignElement( "pagesmodified",
					                          $jsonOut['pagesmodified'] = $result['run_stats']['pagesModified']
					);
					$bodyHTML->assignElement( "linksanalyzed",
					                          $jsonOut['linksanalyzed'] = $result['run_stats']['linksanalyzed']
					);
					$bodyHTML->assignElement( "linksrescued",
					                          $jsonOut['linksrescued'] = $result['run_stats']['linksrescued']
					);
					$bodyHTML->assignElement( "linkstagged",
					                          $jsonOut['linkstagged'] = $result['run_stats']['linkstagged']
					);
					$bodyHTML->assignElement( "linksarchived",
					                          $jsonOut['linksarchived'] = $result['run_stats']['linksarchived']
					);
					$statusHTML = "<div class=\"progress\">
        <div id=\"progressbar\" ";
					$statusHTML .= "class=\"progress-bar progress-bar-";
					if( $result['queue_status'] == 4 ) {
						$jsonOut['classProg'] = "progress-bar-warning";
						$statusHTML .= "warning";
					} elseif( $result['queue_status'] == 2 ) {
						$jsonOut['classProg'] = "progress-bar-success";
						$statusHTML .= "success";
					} elseif( $result['queue_status'] == 3 ||
					          time() - strtotime( $result['status_timestamp'] ) > 300
					) {
						$jsonOut['classProg'] = "progress-bar-danger";
						$statusHTML .= "danger";
					} else {
						$jsonOut['classProg'] = "progress-bar-info";
						$statusHTML .= "info progress-bar-striped active";
					}
					$statusHTML .= "\" role=\"progressbar\" aria-valuenow=\"";
					$locale = setlocale( LC_ALL, "0" );
					setlocale( LC_ALL, $locales['en'] );
					$statusHTML .= $percentage = $result['worker_finished'] / $result['worker_target'] * 100;
					$statusHTML .= "\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: ";
					$statusHTML .= $percentage;
					setlocale( LC_ALL, $locale );
					$statusHTML .= "%\"><span id=\"progressbartext\">{$result['worker_finished']}/{$result['worker_target']} (" .
					               round( $percentage, 2 ) . "%)</span></div>
      </div>";
					setlocale( LC_ALL, $locales['en'] );
					$jsonOut['style'] = "width: $percentage%";
					$jsonOut['aria-valuenow'] = $percentage;
					setlocale( LC_ALL, $locale );
					$jsonOut['progresstext'] =
						"{$result['worker_finished']}/{$result['worker_target']} (" . round( $percentage, 2 ) . "%)";
					$bodyHTML->assignElement( "bqprogress", $statusHTML );
					if( count( $result['queue_pages'] ) < 10000 ) {
						$listHTML = "";
						foreach( $result['queue_pages'] as $page ) {
							$listHTML .= "<li>";
							if( $page['status'] ==
							    "complete"
							) $listHTML .= "<span class='has-success'><label class='control-label'><span class=\"glyphicon glyphicon-ok-sign\"></span> ";
							elseif( $page['status'] ==
							        "skipped"
							) $listHTML .= "<span class='has-error'><label class='control-label'><span class=\"glyphicon glyphicon-remove-sign\"></span> ";
							$listHTML .= $page['title'];
							if( $page['status'] == "complete" ||
							    $page['status'] == "skipped"
							) $listHTML .= "</label></span>";
							$listHTML .= "</li>";
						}
						$jsonOut['pagelist'] = $listHTML;
						$bodyHTML->assignElement( "pagelist", $listHTML );
					} else {
						$listHTML = new HTMLLoader( "{{{listtoolarge}}}", $userObject->getLanguage() );
						$listHTML->finalize();
						$bodyHTML->assignElement( "pagelist", $listHTML->getLoadedTemplate() );
						$jsonOut['pagelist'] = $listHTML->getLoadedTemplate();
					}

					$viewForm = false;
					$buttonHTML = "";
					if( $userObject->validatePermission( "changebqjob" ) ) {
						$buttonHTML .= "<button type=\"submit\" class=\"btn btn-";
						if( $result['queue_status'] == 0 ||
						    $result['queue_status'] == 1
						) {
							$buttonHTML .= "warning\"name=\"action\" value=\"togglebqstatus\">{{{bqsuspend}}}";
							$viewForm = true;
						} elseif( $result['queue_status'] ==
						          4
						) {
							$buttonHTML .= "success\"name=\"action\" value=\"togglebqstatus\">{{{bqunsuspend}}}";
							$viewForm = true;
						} else $buttonHTML .= "success\" disabled=\"disabled\" name=\"action\" value=\"togglebqstatus\">{{{bqunsuspend}}}";
						$buttonHTML .= "</button>";
					}
					if( $result['queue_user'] == $userObject->getUserLinkID() ||
					    $userObject->validatePermission( "changebqjob" )
					) {
						if( $result['queue_status'] != 2 && $result['queue_status'] != 3 ) {
							$buttonHTML .= "<button type=\"submit\" name=\"action\" value=\"killjob\" class=\"btn btn-danger\">{{{bqkill}}}</button>";
							$viewForm = true;
						}
					}

					if( $viewForm === false ) {
						$bodyHTML->assignElement( "jobcontrolvisibility", "none" );
					}
					$buttonHTML = new HTMLLoader( $buttonHTML, $userObject->getLanguage() );
					$buttonHTML->finalize();
					$bodyHTML->assignElement( "togglebuttonshtml", $buttonHTML->getLoadedTemplate() );
					$jsonOut['buttonhtml'] = $buttonHTML->getLoadedTemplate();
					if( isset( $loadedArguments['format'] ) &&
					    $loadedArguments['format'] = "json"
					) die( json_encode( $jsonOut, true ) );
					unset( $loadedArguments['action'], $loadedArguments['token'], $loadedArguments['checksum'] );
					$mainHTML->assignElement( "onloadfunction",
					                          "loadBotJob( '" . http_build_query( $loadedArguments ) . "' )"
					);

					$logURL =
						"SELECT * FROM externallinks_userlog WHERE (`log_type` = 'bqchangestatus' AND `log_object` = '" .
						$result['queue_id'] .
						"') OR (`log_type` = 'bqmasschange' AND `log_timestamp` >= '{$result['queue_timestamp']}' AND `log_timestamp` <= '{$result['status_timestamp']}') ORDER BY `log_timestamp` ASC;";
					$logElement = "";
					if( $res = $dbObject->queryDB( $logURL ) ) {
						$result = mysqli_fetch_all( $res, MYSQLI_ASSOC );
						loadLogUsers( $result );
						foreach( $result as $entry ) {
							$logElement .= "<li>" . getLogText( $entry ) . "</li>\n";
						}
						if( empty( $result ) ) {
							$bodyHTML->assignElement( "logjobdata", "{{{none}}}" );
						} else {
							$bodyHTML->assignElement( "logjobdata", $logElement );
						}
					}
				} else {
					switch( $result['queue_status'] ) {
						case 0:
							$status = "queued";
							break;
						case 1:
							$status = "running";
							break;
						case 2:
							$status = "complete";
							break;
						case 3:
							$status = "killed";
							break;
						case 4:
							$status = "suspended";
							break;
						default:
							$status = "unknown";
							break;
					}
					$jsonOutAPI = array_merge( $jsonOutAPI, [
						                                      'id'             => $result['queue_id'],
						                                      'status'         => $status,
						                                      'requestedby'    => $result['user_name'],
						                                      'targetwiki'     => $result['wiki'],
						                                      'queued'         => $result['queue_timestamp'],
						                                      'lastupdate'     => $result['status_timestamp'],
						                                      'totalpages'     => $result['worker_target'],
						                                      'completedpages' => $result['worker_finished'],
						                                      'runstats'       => unserialize( $result['run_stats'] )
					                                      ]
					);
				}

			} else {
				if( $jsonOutAPI === false ) {
					$mainHTML->setMessageBox( "danger", "{{{joberror}}}", "{{{job404}}}" );
					$bodyHTML->assignElement( "jobdisplaycontrol", "none" );
				} else {
					$jsonOutAPI['requesterror'] = "404";
					$jsonOutAPI['errormessage'] = "The job being looked up doesn't exist.";
				}
			}
		} else {
			if( $jsonOutAPI === false ) {
				$mainHTML->setMessageBox( "danger", "{{{dberror}}}", "{{{unknownerror}}}" );
				$bodyHTML->assignElement( "jobdisplaycontrol", "none" );
			} else {
				$jsonOutAPI['requesterror'] = "dberror";
				$jsonOutAPI['errormessage'] = "An unknown DB error occured.";
			}
		}
	} elseif( $jsonOutAPI === false ) {
		$bodyHTML->assignElement( "jobdisplaycontrol", "none" );
	} else {
		$jsonOutAPI['missingvalue'] = "id";
		$jsonOutAPI['errormessage'] = "This value is required to identify the job being looked up.";
	}

	if( $jsonOutAPI === false ) {
		$bodyHTML->finalize();
		$mainHTML->assignElement( "tooltitle", "{{{jobview}}}" );
		$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
	}
}

function loadLogViewer() {
	global $mainHTML, $userObject, $loadedArguments, $dbObject, $oauthObject;
	$bodyHTML = new HTMLLoader( "logview", $userObject->getLanguage() );
	if( empty( $loadedArguments['offset'] ) || !is_numeric( $loadedArguments['offset'] ) )
		$loadedArguments['offset'] = "999999999999999999999999999999999";

	$logsqljoin = "";
	if( !isset( $loadedArguments['tos'] ) && !isset( $loadedArguments['admin'] ) &&
	    !isset( $loadedArguments['fpreport'] ) && !isset( $loadedArguments['data'] ) &&
	    !isset( $loadedArguments['bot'] )
	) {
		$logsql = " WHERE";
		if( isset( $loadedArguments['username'] ) && !empty( $loadedArguments['username'] ) ) {
			$logsqljoin =
				" LEFT JOIN externallinks_user ON externallinks_user.user_link_id=externallinks_userlog.log_user ";
			$logsql .= " `user_name` = '" . $dbObject->sanitize( $loadedArguments['username'] ) .
			           "' AND externallinks_user.wiki = '" . $dbObject->sanitize( WIKIPEDIA ) . "' AND";
			$bodyHTML->assignElement( "usernamevalueelement",
			                          "value=\"" . htmlspecialchars( $loadedArguments['username'] ) . "\""
			);
		}
		$logsql .= " (externallinks_userlog.wiki = '" . $dbObject->sanitize( WIKIPEDIA ) .
		           "' OR externallinks_userlog.wiki = 'global') AND";
		$previoussql = $logsql;

		$logsql .= ' `log_id` <= ' . $loadedArguments['offset'];
		$previoussql .= ' `log_id` > ' . $loadedArguments['offset'];
		$logsql .= " ORDER BY `log_id` DESC LIMIT 1001;";
		$previoussql .= " ORDER BY `log_id` ASC LIMIT 999,1;";
	} else {
		$logsql = " WHERE (";
		$needOr = false;
		if( isset( $loadedArguments['tos'] ) ) {
			$logsqlt = "( `log_type` = 'tos' AND `log_action` IN (";
			foreach( $loadedArguments['tos'] as $value ) {
				if( $value == "accept" || $value == "decline" ) {
					$bodyHTML->assignElement( "{$value}1-selected", " selected" );
					$inList[] = $value;
				}
			}
			if( !empty( $inList ) ) {
				$logsqlt .= "'" . implode( "','", $inList ) . "'";
				$logsql .= $logsqlt . ") )";
				$needOr = true;
			}
		}
		if( isset( $loadedArguments['admin'] ) ) {
			foreach( $loadedArguments['admin'] as $value ) {
				if( $value == "permissionchange" || $value == "permissionchangeglobal" ) {
					if( !isset( $logsqlp ) ) $logsqlp = "( `log_type` = 'permissionchange' AND `log_action` IN (";
					$inListP[] = $value;
					$bodyHTML->assignElement( "$value-selected", " selected" );
				}
				if( $value == "block" || $value == "unblock" || $value == "selfunblock" ) {
					if( !isset( $logsqlb ) ) $logsqlb = "( `log_type` = 'block' AND `log_action` IN (";
					$inListB[] = $value;
					$bodyHTML->assignElement( "$value-selected", " selected" );
				}
			}
			if( !empty( $inListP ) ) {
				$logsqlp .= "'" . implode( "','", $inListP ) . "'";
				if( $needOr === true ) $logsql .= " OR ";
				$logsql .= $logsqlp . ") )";
				$needOr = true;
			}
			if( !empty( $inListB ) ) {
				$logsqlb .= "'" . implode( "','", $inListB ) . "'";
				if( $needOr === true ) $logsql .= " OR ";
				$logsql .= $logsqlb . ") )";
				$needOr = true;
			}
		}
		if( isset( $loadedArguments['fpreport'] ) ) {
			$logsqlt = "( `log_type` = 'fpreport' AND `log_action` IN (";
			foreach( $loadedArguments['fpreport'] as $value ) {
				if( $value == "report" || $value == "decline" || $value == "open" || $value == "fix" ) {
					$bodyHTML->assignElement( "$value-selected", " selected" );
					$inList[] = $value;
				}
			}
			if( !empty( $inList ) ) {
				$logsqlt .= "'" . implode( "','", $inList ) . "'";
				if( $needOr === true ) $logsql .= " OR ";
				$logsql .= $logsqlt . ") )";
				$needOr = true;
			}
		}
		if( isset( $loadedArguments['data'] ) ) {
			foreach( $loadedArguments['data'] as $value ) {
				$logsqlt = "( ( `log_type` = 'urldata' OR `log_type` = 'domaindata' ) AND `log_action` IN (";
				if( $value == "changestate" || $value == "changeglobalstate" || $value == "changeaccess" ||
				    $value == "changearchive"
				) {
					$inList[] = $value;
					$bodyHTML->assignElement( "$value-selected", " selected" );
				}
			}
			if( !empty( $inList ) ) {
				$logsqlt .= "'" . implode( "','", $inList ) . "'";
				if( $needOr === true ) $logsql .= " OR ";
				$logsql .= $logsqlt . ") )";
				$needOr = true;
			}
		}
		if( isset( $loadedArguments['bot'] ) ) {
			foreach( $loadedArguments['bot'] as $value ) {
				if( $value == "analyzepage" ) {
					if( !isset( $logsqla ) ) $logsqla = "( `log_type` = 'analyzepage' AND `log_action` IN (";
					$inListA[] = $value;
					$bodyHTML->assignElement( "$value-selected", " selected" );
				}
				if( $value == "submit" || $value == "suspend" || $value == "unsuspend" || $value == "kill" ||
				    $value == "finish"
				) {
					if( !isset( $logsqlq ) ) $logsqlq = "( `log_type` = 'bqchangestatus' AND `log_action` IN (";
					$inListQ[] = $value;
					$bodyHTML->assignElement( "$value-selected", " selected" );
				}
			}
			if( !empty( $inListA ) ) {
				$logsqla .= "'" . implode( "','", $inListA ) . "'";
				if( $needOr === true ) $logsql .= " OR ";
				$logsql .= $logsqla . ") )";
				$needOr = true;
			}
			if( !empty( $inListQ ) ) {
				$logsqlq .= "'" . implode( "','", $inListQ ) . "'";
				if( $needOr === true ) $logsql .= " OR ";
				$logsql .= $logsqlq . ") )";
				$needOr = true;
			}
		}

		$logsql .= ")";

		if( isset( $loadedArguments['username'] ) && !empty( $loadedArguments['username'] ) ) {
			$logsqljoin =
				" LEFT JOIN externallinks_user ON externallinks_user.user_link_id=externallinks_userlog.log_user ";
			$logsql .= " AND `user_name` = '" . $dbObject->sanitize( $loadedArguments['username'] ) .
			           "' AND externallinks_user.wiki = '" . $dbObject->sanitize( WIKIPEDIA ) . "'";
			$bodyHTML->assignElement( "usernamevalueelement",
			                          "value=\"" . htmlspecialchars( $loadedArguments['username'] ) . "\""
			);
		}
		$logsql .= " AND (externallinks_userlog.wiki = '" . $dbObject->sanitize( WIKIPEDIA ) .
		           "' OR externallinks_userlog.wiki = 'global') AND ";

		$previoussql = $logsql;

		$logsql .= ' `log_id` <= ' . $loadedArguments['offset'];
		$previoussql .= ' `log_id` > ' . $loadedArguments['offset'];
		$logsql .= " ORDER BY `log_id` DESC LIMIT 1001;";
		$previoussql .= " ORDER BY `log_id` ASC LIMIT 999,1;";
	}

	$sql = "SELECT * FROM externallinks_userlog" . $logsqljoin . "$logsql";
	$previoussql = "SELECT * FROM externallinks_userlog" . "$logsqljoin" . "$previoussql";
	$logElement = "";
	if( $res = $dbObject->queryDB( $sql ) ) {
		$counter = 0;
		$result = mysqli_fetch_all( $res, MYSQLI_ASSOC );
		loadLogUsers( $result );
		foreach( $result as $entry ) {
			$counter++;
			if( $counter > 1000 ) {
				$nextPage = $entry['log_id'];
				continue;
			}
			$logElement .= "<li>" . getLogText( $entry ) . "</li>\n";
		}
		if( empty( $result ) ) {
			$bodyHTML->assignElement( "log", "{{{none}}}" );
		} else {
			$bodyHTML->assignElement( "log", $logElement );
		}
	}
	if( $res = $dbObject->queryDB( $previoussql ) ) {
		$previousPage = mysqli_fetch_assoc( $res );
		if( !is_null( $previousPage ) ) $previousPage = $previousPage['log_id'];
	}

	$urlbuilder = $loadedArguments;
	unset( $urlbuilder['action'], $urlbuilder['token'], $urlbuilder['checksum'], $urlbuilder['id'] );

	if( !isset( $loadedArguments['offset'] ) || $loadedArguments['offset'] <= 1 || !isset( $previousPage ) ) {
		$bodyHTML->assignElement( "prevbuttonora", "button" );
		$bodyHTML->assignElement( "prevpagedisabled", "disabled=\"disable\"" );
	} else {
		$bodyHTML->assignElement( "prevbuttonora", "a" );
		$url = "index.php?";
		unset( $urlbuilder['offset'] );
		if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder ) . "&";
		$url .= "offset=" . $previousPage;
		$bodyHTML->assignElement( "prevpageurl", $url );
	}
	if( $counter <= 1000 ) {
		$bodyHTML->assignElement( "nextbuttonora", "button" );
		$bodyHTML->assignElement( "nextpagedisabled", "disabled=\"disable\"" );
	} else {
		$bodyHTML->assignElement( "nextbuttonora", "a" );
		$url = "index.php?";
		unset( $urlbuilder['offset'] );
		if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder ) . "&";
		$url .= "offset=$nextPage";
		$bodyHTML->assignElement( "nextpageurl", $url );
	}

	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{logview}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}