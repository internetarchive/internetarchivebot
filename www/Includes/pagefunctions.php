<?php

function getLogText( $logEntry ) {
	global $userObject, $userCache;
	$logText = new HTMLLoader( date( 'G\:i\, j F Y', strtotime($logEntry['log_timestamp']) )." <a href=\"index.php?page=user&id=".$logEntry['log_user']."\">".$userCache[$logEntry['log_user']]['user_name']."</a> {{{".$logEntry['log_type'].$logEntry['log_action']."}}}", $userObject->getLanguage() );
	if( $logEntry['log_type'] == "permissionchange" || $logEntry['log_type'] == "block" ) {
		$logText->assignAfterElement( "targetusername", $userCache[$logEntry['log_object']]['user_name'] );
		$logText->assignAfterElement( "targetuserid", $logEntry['log_object'] );
	}
	if( $logEntry['log_type'] == "permissionchange" ) {
		$added = array_diff( unserialize( $logEntry['log_to'] ), unserialize( $logEntry['log_from'] ) );
		$removed = array_diff( unserialize( $logEntry['log_from'] ), unserialize( $logEntry['log_to'] ) );
		$logText->assignAfterElement( "logfrom", implode( ", ", $added ) );
		$logText->assignAfterElement( "logto", implode( ", ", $removed ) );
	} else {
		$logText->assignAfterElement( "logfrom", $logEntry['log_from'] );
		$logText->assignAfterElement( "logto", $logEntry['log_to'] );
	}
	$logText->assignAfterElement( "logobject", $logEntry['log_object'] );
	$logText->assignAfterElement( "logobjecttext", $logEntry['log_object_text'] );
	$logText->assignAfterElement( "logreason", htmlspecialchars( $logEntry['log_reason'] ) );
	$logText->finalize();
	return $logText->getLoadedTemplate();
}

function loadLogUsers( $logEntries ) {
	global $userCache, $dbObject;
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
	$res = $dbObject->queryDB( "SELECT * FROM `externallinks_user` WHERE `user_id` IN (".implode( ", ", $toFetch ).") AND `wiki` = '".WIKIPEDIA."';" );
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$userCache[$result['user_id']] = $result;
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

function loadLoginNeededPage() {
	global $mainHTML, $userObject;
	$bodyHTML = new HTMLLoader( "loginneeded", $userObject->getLanguage() );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{loginrequired}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadToSPage() {
	global $mainHTML, $userObject, $oauthObject, $loadedArguments, $dbObject;
	if( isset( $loadedArguments['tosaccept'] ) ) {
		if( isset( $loadedArguments['token'] ) ) {
			if( $loadedArguments['token'] == $oauthObject->getCSRFToken() ) {
				if( $loadedArguments['tosaccept'] == "yes" ) {
					$dbObject->insertLogEntry( WIKIPEDIA, "tos", "accept", 0, "", $userObject->getUserID() );
					$userObject->setLastAction( time() );
					return true;
				} else {
					$dbObject->insertLogEntry( WIKIPEDIA, "tos", "decline", 0, "", $userObject->getUserID() );
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
	if( $userObject2->getLastAction() > 0 ) $bodyHTML->assignElement( "lastactivitytimestamp", date( 'G\:i j F Y \(\U\T\C\)', $userObject2->getLastAction() ) );
	if( $userObject2->getAuthTimeEpoch() > 0 )$bodyHTML->assignElement( "lastlogontimestamp", date( 'G\:i j F Y \(\U\T\C\)', $userObject2->getAuthTimeEpoch() ) );
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
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'pagerescue';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "pagesrescued", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'botqueue' AND `log_action` ='queue';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "botsstarted", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'urldata';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "urlschanged", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'domaindata';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "domainschanged", $res['count'] );
	}
	mysqli_free_result( $result );
	$result = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_userlog WHERE `log_type` = 'fpreport' AND `log_action` = 'report';" );
	while( $res = mysqli_fetch_assoc( $result ) ) {
		$bodyHTML->assignElement( "fpreported", $res['count'] );
	}
	mysqli_free_result( $result );
	$form = "<hr>\n";
	if( $userObject->validatePermission( "blockuser" ) === true || $userObject->validatePermission( "unblockuser" ) ) {
		$form .= "<form class=\"form-inline\" id=\"blockform\" name=\"blockform\" method=\"post\" action=\"index.php?page=user&action=toggleblock&id={$loadedArguments['id']}\">
  <div class=\"form-group\">
    <input type=\"text\" class=\"form-control\" name=\"reason\" id=\"reason\" placeholder=\"{{{blockreasonplaceholder}}}\"";
		if( $userObject2->isBlocked() === true && $userObject2->getBlockSource() == "wiki" ) $form .= "\" disabled=\"disabled";
		elseif( $userObject2->isBlocked() === true && $userObject2->getUserID() == $userObject->getUserID() && $userObject->validatePermission( "unblockme" ) === false ) $form .= "\" disabled=\"disabled\"";
		$form .= ">
  </div>
  <button type=\"submit\" class=\"btn btn-";
		if( $userObject2->isBlocked() === true ) $form.="success";
		else $form.="danger";
		if( $userObject2->isBlocked() === true && $userObject2->getBlockSource() == "wiki" ) $form .= "\" disabled=\"disabled";
		elseif( $userObject2->isBlocked() === true && $userObject2->getUserID() == $userObject->getUserID() && $userObject->validatePermission( "unblockme" ) === false ) $form .= "\" disabled=\"disabled\"";
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
		$form = "<form id=\"userrightsform\" name=\"userrightsform\" method=\"post\" action=\"index.php?page=user&action=changepermissions&id={$loadedArguments['id']}\">\n";
		$form .= "<label class=\"checkbox-inline\"><h4>Groups: </h4></label>";
		foreach( $userGroups as $group=>$junk ) {
			$disabledChange = ($userObject2->validateGroup( $group ) && (!$userObject2->validateGroup( $group, true ) || !in_array( $group, $userObject->getRemovableGroups() ))) || !in_array( $group, $userObject->getAddableGroups());
			$checked = $userObject2->validateGroup( $group );
			$form .= " <label class=\"checkbox-inline\">\n";
			if( $checked === true ) {
				$form .= "   <input type=\"hidden\" id=\"$group\" name=\"$group\" value=\"";
				if( $checked === true && $disabledChange === true ) $form .= "on";
				else $form .= "off";
				$form .= "\"/>";
			}
			$form .= "   <input type=\"checkbox\" id=\"$group\" name=\"$group\"";
			if( $checked === true ) $form .= " checked=\"checked\"";
			if( $disabledChange === true ) $form .= " disabled=\"disabled\"";
			$form .= ">\n";
			$form .= "   $group\n";
			$form .= " </label>\n";
		}
		$form .= "<hr>";
		$form .= "<label class=\"checkbox-inline\"><h4>Flags: </h4></label>";
		foreach( $userObject->getAllFlags() as $flag ) {
			$disabledChange = ($userObject2->validatePermission( $flag ) && (!$userObject2->validatePermission( $flag, true ) || !in_array( $flag, $userObject->getRemovableFlags() ))) || !in_array( $flag, $userObject->getAddableFlags());
			$checked = $userObject2->validatePermission( $flag );
			$form .= " <label class=\"checkbox-inline\">\n";
			if( $checked === true ) {
				$form .= "   <input type=\"hidden\" id=\"$flag\" name=\"$flag\" value=\"";
				if( $checked === true && $disabledChange === true ) $form .= "on";
				else $form .= "off";
				$form .= "\"/>";
			}
			$form .= "   <input type=\"checkbox\" id=\"$flag\" name=\"$flag\"";
			if( $checked === true ) $form .= " checked=\"checked\"";
			if( $disabledChange === true ) $form .= " disabled=\"disabled\"";
			$form .= ">\n";
			$form .= "   $flag\n";
			$form .= " </label>\n";
		}
		$form .= "  <div class=\"form-group\">\n";
		$form .= "    <input type=\"text\" class=\"form-control\" name=\"reason\" id=\"reason\" placeholder=\"{{{reasonplaceholder}}}\">\n";
		$form .= "  </div>\n";
		$form .= "<button type=\"submit\" class=\"btn btn-primary\">Submit</button>\n";
		$form .= "<input type=\"hidden\" value=\"{{csrftoken}}\" name=\"token\">\n";
		$form .= "<input type=\"hidden\" value=\"{{checksum}}\" name=\"checksum\">\n";
		$form .= "</form>";
		$bodyHTML->assignElement( "permissionscontrol", $form );
	}
	$result = $dbObject->queryDB( "SELECT * FROM externallinks_userlog WHERE `wiki` = '".WIKIPEDIA."' AND `log_user` = '".$dbObject->sanitize( $loadedArguments['id'] )."' ORDER BY `log_timestamp` DESC LIMIT 0,100;" );
	$text = "<ol>";
	if( $res = mysqli_fetch_all( $result, MYSQLI_ASSOC ) ) {
		loadLogUsers( $res );
		foreach( $res as $entry ) {
			$text .= "<li>".getLogText( $entry )."</li>\n";
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

function loadBotQueue() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $oauthObject;
	if( !validatePermission( "viewbotqueue", false ) ) {
		loadPermissionError( "viewbotqueue" );
		return;
	}
	$bodyHTML = new HTMLLoader( "botqueue", $userObject->getLanguage() );
	$res = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_status` = 0;" );
	$result = mysqli_fetch_assoc( $res );
	$bodyHTML->assignElement( "reportedbqqueued", $result['count'] );
	$res = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_botqueue WHERE `queue_status` = 1;" );
	$result = mysqli_fetch_assoc( $res );
	$bodyHTML->assignElement( "reportedbqrunning", $result['count'] );
	$sql = "SELECT * FROM externallinks_botqueue LEFT JOIN externallinks_user ON externallinks_botqueue.queue_user = externallinks_user.user_id AND externallinks_botqueue.wiki = externallinks_user.wiki WHERE `queue_status` IN (";
	$inArray = [];
	if( !isset( $loadedArguments['displayqueued'] ) && !isset( $loadedArguments['displayrunning'] ) && !isset( $loadedArguments['displayfinished'] ) && !isset( $loadedArguments['displaykilled'] ) && !isset( $loadedArguments['displaysuspended'] ) ) $loadedArguments['displayrunning'] = "on";
	if( isset( $loadedArguments['displayqueued'] ) ) {
		$bodyHTML->assignElement( "bqdisplayqueuedchecked", "checked=\"checked\"");
		$inArray[] = 0;
	}
	if( isset( $loadedArguments['displayrunning'] ) ) {
		$bodyHTML->assignElement( "bqdisplayrunningchecked", "checked=\"checked\"");
		$inArray[] = 1;
	}
	if( isset( $loadedArguments['displayfinished'] ) ) {
		$bodyHTML->assignElement( "bqdisplayfinishedchecked", "checked=\"checked\"");
		$inArray[] = 2;
	}
	if( isset( $loadedArguments['displaykilled'] ) ) {
		$bodyHTML->assignElement( "bqdisplaykilledchecked", "checked=\"checked\"");
		$inArray[] = 3;
	}
	if( isset( $loadedArguments['displaysuspended'] ) ) {
		$bodyHTML->assignElement( "bqdisplaysuspendedchecked", "checked=\"checked\"");
		$inArray[] = 4;
	}
	$sql .= implode( ", ", $inArray );
	$sql .= ") LIMIT ";
	if( isset( $loadedArguments['pagenumber'] ) && is_int( $loadedArguments['pagenumber'] ) ) $sql .= ($loadedArguments['pagenumber'] -1)*1000;
	else $sql .= 0;
	$sql .= ",1001;";
	$res = $dbObject->queryDB( $sql );
	$table = "";
	$urlbuilder = $loadedArguments;
	unset( $urlbuilder['action'] , $urlbuilder['token'], $urlbuilder['checksum'], $urlbuilder['id'] );
	$counter = 0;
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$counter++;
		if( $counter > 1000 ) continue;
		$table .= "<tr";
		if( $result['queue_status'] == 2 ) $table .= " class=\"success\"";
		elseif( $result['queue_status'] == 3 ) $table .= " class=\"danger\"";
		elseif( $result['queue_status'] == 4 ) $table .= " class=\"warning\"";
		elseif( $result['queue_status'] == 1 ) $table .= " class=\"info\"";
		$table .= ">\n";
		$table .= "<td>".$result['queue_id']."</td>\n";
		$table .= "<td>".$result['wiki']."</td>\n";
		$table .= "<td><a href=\"index.php?page=user&id=".$result['user_id']."\">".$result['user_name']."</a></td>\n";
		$table .= "<td>".$result['queue_timestamp']."</td>\n";
		$table .= "<td>";
		if( $result['queue_status'] == 0 ) {
			$table .= "{{{queued}}}";
		} elseif( $result['queue_status'] == 1 || ($result['queue_status'] == 4 && !empty( $result['assigned_worker'] ) ) ) {
			$table .= "<div class=\"progress\">
        <div id=\"progressbar".$result['queue_id']."\" ";
			$table .= "class=\"progress-bar progress-bar-";
			if( $result['queue_status'] == 4 ) $table .= "warning";
			elseif ( time() - strtotime( $result['status_timestamp'] ) > 300 ) $table .= "danger";
			else $table .= "info";
			$table .= "\" role=\"progressbar\" aria-valuenow=\"";
			$table .= $result['worker_finished']/$result['worker_target']*100;
			$table .= "\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: ";
			$table .= $result['worker_finished']/$result['worker_target']*100;
			$table .= "%\"><span id=\"progressbartext".$result['queue_id']."\">{$result['worker_finished']}/{$result['worker_target']} (".round( $result['worker_finished']/$result['worker_target']*100, 2 )."%)</span></div>
      </div>";
		} elseif( $result['queue_status'] == 4 ) {
			$table.= "{{{suspended}}}";
		} elseif( $result['queue_status'] == 2 ) {
			$table.="{{{finished}}}: ".$result['status_timestamp'];
		} else {
			$table.="{{{killed}}}: ".$result['status_timestamp'];
		}
		$table .= "</td>\n";
		$table .= "<td><";
		if( $result['queue_status'] == 2 || $result['queue_status'] == 3 ) $table .= "button";
		else $table .= "a";
		$table .= " href=\"index.php?";
		if( !empty( $urlbuilder ) ) $table .= http_build_query( $urlbuilder )."&";
		$table .= "page=metabotqueue&action=togglebqstatus&token=".$oauthObject->getCSRFToken()."&checksum=".$oauthObject->getChecksumToken()."&id=".$result['queue_id']."\" class=\"btn btn-";
		if( $result['queue_status'] == 0 || $result['queue_status'] == 1 ) $table .= "warning\">{{{bqsuspend}}}";
		elseif( $result['queue_status'] == 4 ) $table .= "success\">{{{bqunsuspend}}}";
		else $table .= "success\" disabled=\"disabled\">{{{bqunsuspend}}}";
		$table .= "</";
		if( $result['queue_status'] == 2 || $result['queue_status'] == 3 ) $table .= "button";
		else $table .= "a";
		$table .= ">";
		if( $result['queue_status'] != 2 && $result['queue_status'] != 3 ) {
			$table .= "<a href=\"index.php?";
			if( !empty( $urlbuilder ) ) $table .= http_build_query( $urlbuilder )."&";
			$table .= "page=metabotqueue&action=killjob&token=".$oauthObject->getCSRFToken()."&checksum=".$oauthObject->getChecksumToken()."&id=".$result['queue_id']."\" class=\"btn btn-danger\">{{{bqkill}}}</a>";
		}
		$table .= "</td>\n";
	}
	mysqli_free_result( $res );
	if( !isset( $loadedArguments['pagenumber'] ) || $loadedArguments['pagenumber'] <= 1 ) {
		$bodyHTML->assignElement( "prevbuttonora", "button" );
		$bodyHTML->assignElement( "prevpagedisabled", "disabled=\"disable\"" );
	} else {
		$bodyHTML->assignElement( "prevbuttonora", "a" );
		$url = "index.php?";
		unset( $urlbuilder['pagenumber'] );
		if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder )."&";
		$url .= "pagenumber=".($loadedArguments['pagenumber'] - 1);
		$bodyHTML->assignElement( "prevpageurl", $url );
	}
	if( $counter <= 1000 ) {
		$bodyHTML->assignElement( "nextbuttonora", "button" );
		$bodyHTML->assignElement( "nextpagedisabled", "disabled=\"disable\"" );
	} else {
		$bodyHTML->assignElement( "nextbuttonora", "a" );
		$url = "index.php?";
		unset( $urlbuilder['pagenumber'] );
		if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder )."&";
		if( !isset( $loadedArguments['pagenumber'] ) ) $url .= "pagenumber=2";
		else $url .= "pagenumber=".$loadedArguments['pagenumber'] - 1;
		$bodyHTML->assignElement( "nextpageurl", $url );
	}
	$bodyHTML->assignElement( "bqtable", $table );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{bqreviewpage}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadPermissionError( $permission ) {
	global $mainHTML, $userObject;
	header( "HTTP/1.1 403 Forbidden", true, 403 );
	$bodyHTML = new HTMLLoader( "permissionerror", $userObject->getLanguage() );
	$bodyHTML->assignAfterElement( "userflag", $permission );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{permissionerror}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadFPReportMeta() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments, $oauthObject;
	if( !validatePermission( "viewfpreviewpage", false ) ) {
		loadPermissionError( "viewfpreviewpage" );
		return;
	}
	$bodyHTML = new HTMLLoader( "fpinterface", $userObject->getLanguage() );
	$res = $dbObject->queryDB( "SELECT COUNT(*) AS count FROM externallinks_fpreports WHERE `report_status` = 0;" );
	$result = mysqli_fetch_assoc( $res );
	$bodyHTML->assignElement( "activefptotal", $result['count'] );
	$sql = "SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id LEFT JOIN externallinks_user ON externallinks_fpreports.report_user_id = externallinks_user.user_id AND externallinks_fpreports.wiki = externallinks_user.wiki WHERE `report_status` IN (";
	$inArray = [];
	if( !isset( $loadedArguments['displayopen'] ) && !isset( $loadedArguments['displayfixed'] ) && !isset( $loadedArguments['displaydeclined'] ) ) $loadedArguments['displayopen'] = "on";
	if( isset( $loadedArguments['displayopen'] ) ) {
		$bodyHTML->assignElement( "fpdisplayopenchecked", "checked=\"checked\"");
		$inArray[] = 0;
	}
	if( isset( $loadedArguments['displayfixed'] ) ) {
		$bodyHTML->assignElement( "fpdisplayfixedchecked", "checked=\"checked\"");
		$inArray[] = 1;
	}
	if( isset( $loadedArguments['displaydeclined'] ) ) {
		$bodyHTML->assignElement( "fpdisplaydeclinedchecked", "checked=\"checked\"");
		$inArray[] = 2;
	}
	$sql .= implode( ", ", $inArray );
	$sql .= ") LIMIT ";
	if( isset( $loadedArguments['pagenumber'] ) && is_int( $loadedArguments['pagenumber'] ) ) $sql .= ($loadedArguments['pagenumber'] -1)*1000;
	else $sql .= 0;
	$sql .= ",1001;";
	$res = $dbObject->queryDB( $sql );
	$table = "";
	$urlbuilder = $loadedArguments;
	unset( $urlbuilder['action'] , $urlbuilder['token'], $urlbuilder['checksum'], $urlbuilder['id'] );
	$counter = 0;
	while( $result = mysqli_fetch_assoc( $res ) ) {
		$counter++;
		if( $counter > 1000 ) continue;
		$table .= "<tr";
		if( $result['report_status'] == 1 ) $table .= " class=\"success\"";
		elseif( $result['report_status'] == 2 ) $table .= " class=\"danger\"";
		else $table .= " class=\"warning\"";
		$table .= ">\n";
		$table .= "<td><a href=\"".$result['url']."\">".$result['url']."</a></td>\n";
		$table .= "<td><a href=\"index.php?page=user&id=".$result['user_id']."\">".$result['user_name']."</a></td>\n";
		$table .= "<td>".$result['report_timestamp']."</td>\n";
		$table .= "<td>".$result['report_version']."</td>\n";
		$table .= "<td><a href=\"index.php?";
		if( !empty( $urlbuilder ) ) $table .= http_build_query( $urlbuilder )."&";
		$table .= "page=metafpreview&action=togglefpstatus&token=".$oauthObject->getCSRFToken()."&checksum=".$oauthObject->getChecksumToken()."&id=".$result['report_id']."\" class=\"btn btn-";
		if( $result['report_status'] != 0 ) $table .= "default\">{{{fpreopen}}}";
		else $table .= "danger\">{{{fpdecline}}}";
		$table .= "</a></td>\n";
	}
	if( !isset( $loadedArguments['pagenumber'] ) || $loadedArguments['pagenumber'] <= 1 ) {
		$bodyHTML->assignElement( "prevbuttonora", "button" );
		$bodyHTML->assignElement( "prevpagedisabled", "disabled=\"disable\"" );
	} else {
		$bodyHTML->assignElement( "prevbuttonora", "a" );
		$url = "index.php?";
		unset( $urlbuilder['pagenumber'] );
		if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder )."&";
		$url .= "pagenumber=".($loadedArguments['pagenumber'] - 1);
		$bodyHTML->assignElement( "prevpageurl", $url );
	}
	if( $counter <= 1000 ) {
		$bodyHTML->assignElement( "nextbuttonora", "button" );
		$bodyHTML->assignElement( "nextpagedisabled", "disabled=\"disable\"" );
	} else {
		$bodyHTML->assignElement( "nextbuttonora", "a" );
		$url = "index.php?";
		unset( $urlbuilder['pagenumber'] );
		if( !empty( $urlbuilder ) ) $url .= http_build_query( $urlbuilder )."&";
		if( !isset( $loadedArguments['pagenumber'] ) ) $url .= "pagenumber=2";
		else $url .= "pagenumber=".$loadedArguments['pagenumber'] - 1;
		$bodyHTML->assignElement( "nextpageurl", $url );
	}
	$bodyHTML->assignElement( "fptable", $table );
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{fpreviewpage}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}

function loadUserSearch() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments;
	$bodyHTML = new HTMLLoader( "usersearch", $userObject->getLanguage() );
	if( isset( $loadedArguments['username'] ) ) {
		$bodyHTML->assignElement( "usernamevalueelement", " value=\"".htmlspecialchars( $loadedArguments['username'] )."\"" );
		$sql = "SELECT * FROM externallinks_user WHERE `wiki` = '".WIKIPEDIA."' AND `user_name` = '".$dbObject->sanitize( $loadedArguments['username'] )."';";
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

	$tableRows ="";
	foreach( $userGroups as $group=>$data ) {
		$groupData = User::getGroupFlags( $group );
		$tableRows .= "<tr class=\"{$data['labelclass']}\">\n";
		$tableRows .= "<td><span class=\"label label-{$data['labelclass']}\">$group</span></td>";
		$tableRows .= "<td>".implode( ", ", $groupData['hasflags'] )."</td>";
		$autoacquireText = "";
		if( $data['autoacquire']['registered'] != 0 ) {
			$autoacquireText .= "<b>{{{registeredlatest}}}:</b>&nbsp;".date( 'G\:i\&\n\b\s\p\;j\&\n\b\s\p\;F\&\n\b\s\p\;Y\&\n\b\s\p\;\(\U\T\C\)', $data['autoacquire']['registered'])."<br>\n";
		}
		if( $data['autoacquire']['registered'] != 0 && $data['autoacquire']['editcount'] != 0 ) {
			$autoacquireText .= "and<br>\n";
		}
		if( $data['autoacquire']['editcount'] != 0 ) {
			$autoacquireText .= "<b>{{{lowesteditcount}}}:</b>&nbsp;".$data['autoacquire']['editcount']."<br>\n";
		}
		if( $data['autoacquire']['registered'] != 0 || $data['autoacquire']['editcount'] != 0 || count( $data['autoacquire']['withwikigroup'] ) > 0 || count( $data['autoacquire']['withwikiright'] ) > 0 ) {
			if( ($data['autoacquire']['registered'] != 0 || $data['autoacquire']['editcount'] != 0) && (count( $data['autoacquire']['withwikigroup'] ) > 0 || count( $data['autoacquire']['withwikiright'] ) > 0) ) $autoacquireText .= "and<br>\n";
		} else {
			$autoacquireText = "&mdash;";
		}
		if( count( $data['autoacquire']['withwikigroup'] ) > 0 ) {
			$autoacquireText .= "<b>{{{withgroups}}}:</b> ";
			$autoacquireText .= implode( ", ", $data['autoacquire']['withwikigroup'] );
			$autoacquireText .= "<br>\n";
		}
		if( count( $data['autoacquire']['withwikigroup'] ) > 0 && count( $data['autoacquire']['withwikiright'] ) > 0 ) {
			$autoacquireText ."or<br>\n";
		}
		if( count( $data['autoacquire']['withwikiright'] ) > 0 ) {
			$autoacquireText .= "<b>{{{withrights}}}:</b> ";
			$autoacquireText .= implode( ", ", $data['autoacquire']['withwikiright'] );
			$autoacquireText .= "\n";
		}
		$tableRows .= "<td>$autoacquireText</td>";
		$tableRows .= "<td>";
		foreach( $userGroups as $tgroup=>$junk ) {
			if( in_array( $tgroup, $groupData['addgroups'] ) ) {
				$tableRows .= "<span class=\"label label-{$junk['labelclass']}\">$tgroup</span> ";
			}
		}
		$tableRows .= "<hr>".implode( ", ", $groupData['addflags'] )."</td>";
		$tableRows .= "<td>";
		foreach( $userGroups as $tgroup=>$junk ) {
			if( in_array( $tgroup, $groupData['removegroups'] ) ) {
				$tableRows .= "<span class=\"label label-{$junk['labelclass']}\">$tgroup</span> ";
			}
		}
		$tableRows .= "<hr>".implode( ", ", $groupData['removeflags'] )."</td>";
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

function loadFPReporter() {
	global $mainHTML, $userObject, $dbObject, $loadedArguments;
	if( !validatePermission( "reportfp", false ) ) {
		loadPermissionError( "reportfp" );
		return;
	}
	$bodyHTML = new HTMLLoader( "fpreporter", $userObject->getLanguage() );
	if( (isset( $loadedArguments['pagenumber'] ) && $loadedArguments['pagenumber'] == 1) || !isset( $loadedArguments['fplist'] ) ) {
		$bodyHTML->assignElement( "page1displaytoggle", "all" );
		$bodyHTML->assignElement( "page2displaytoggle", "none" );
	} else {
		$bodyHTML->assignElement( "page1displaytoggle", "none" );
		$bodyHTML->assignElement( "page2displaytoggle", "all" );
	}
	$schemelessURLRegex = '(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\/\?\#\[\]]+)*\/?(?:\?[^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';
	if( isset( $loadedArguments['fplist'] ) ) {
		$urls = explode( "\n", $loadedArguments['fplist'] );
		foreach( $urls as $id=>$url ) {
			if( !preg_match( '/'.$schemelessURLRegex.'/i', $url, $garbage ) ) {
				unset( $urls[$id] );
			} else {
				$urls[$id] = $garbage[0];
			}
		}
		$loadedArguments['fplist'] = implode( "\n", $urls );
		$bodyHTML->assignElement( "fplistvalue", $loadedArguments['fplist'] );
	}
	if( isset( $loadedArguments['fplist'] ) && (!isset( $loadedArguments['pagenumber'] ) || $loadedArguments['pagenumber'] != 1) ) {
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
		}
		$notfound = array_flip( $notfound );
		$urlList = "";
		foreach( $notfound as $url ) {
			$urlList .= "<li><a href=\"".$url."\">".htmlspecialchars( $url )."</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet4", $urlList );
		$sql = "SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id WHERE `url` IN ( '".implode( "', '", $escapedURLs )."' ) AND `report_status` = 0;";
		$res = $dbObject->queryDB( $sql );
		while( $result = mysqli_fetch_assoc( $res ) ) {
			$alreadyReported[] = $result['url'];
		}
		$urlList = "";
		foreach( $alreadyReported as $url ) {
			$urlList .= "<li><a href=\"".$url."\">".htmlspecialchars( $url )."</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet3", $urlList );
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
		$urlList = "";
		foreach( $toReset as $url ) {
			$urlList .= "<li><a href=\"".$url."\">".htmlspecialchars( $url )."</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet2", $urlList );
		$urlList = "";
		foreach( $toReport as $url ) {
			$urlList .= "<li><a href=\"".$url."\">".htmlspecialchars( $url )."</a></li>\n";
		}
		$bodyHTML->assignElement( "fplistbullet1", $urlList );

		$_SESSION['precheckedfplistsrorted'] = [];
		$_SESSION['toreport'] = $toReport;
		$_SESSION['toreset'] = $toReset;
		$_SESSION['alreadyreported'] = $alreadyReported;
		$_SESSION['notfound'] = $notfound;
		$_SESSION['toreporthash'] = CONSUMERSECRET.ACCESSSECRET.implode(":", $toReport );

	}
	$bodyHTML->finalize();
	$mainHTML->assignElement( "tooltitle", "{{{fpreporter}}}" );
	$mainHTML->assignElement( "body", $bodyHTML->getLoadedTemplate() );
}