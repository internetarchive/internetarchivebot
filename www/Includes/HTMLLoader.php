<?php

/*
	Copyright (c) 2015-2017, Maximilian Doerr

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	IABot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

class HTMLLoader {

	protected $template;

	protected $i18n;

	protected $afterLoadedElements = [];

	protected $langCode = "";

	public function __construct( $template, $langCode, $templatePath = false, $i18nPath = false ) {
		global $accessibleWikis;

		if( $templatePath === false ) {
			if( file_exists( "Templates/" . $template . ".html" ) ) {
				$this->template = file_get_contents( "Templates/" . $template . ".html" );
			} else {
				$this->template = $template;
			}
		} else {
			if( file_exists( $templatePath . $template . ".html" ) ) {
				$this->template = file_get_contents( $templatePath . $template . ".html" );
			} else {
				echo "Template file $template.html cannot be found.  Tried to load \"$templatePath$template.html\"";
				exit( 50 );
			}
		}
		if( $i18nPath === false ) {
			if( file_exists( "i18n/" . $langCode . ".json" ) ) {
				$this->i18n = file_get_contents( "i18n/" . $langCode . ".json" );
			} else {
				echo "i18n file $langCode.json cannot be found.";
				exit( 50 );
			}
		} else {
			if( file_exists( $i18nPath . $langCode . ".json" ) ) {
				$this->i18n = file_get_contents( $i18nPath . $langCode . ".json" );
			} else {
				echo "i18n file $langCode.json cannot be found.  Tried to load \"$i18nPath$langCode.json\"";
				exit( 50 );
			}
		}

		$this->langCode = $langCode;

		$this->i18n = json_decode( $this->i18n, true );

		$this->assignElement( "languagecode", $langCode );
		$this->assignAfterElement( "consoleversion", INTERFACEVERSION );
		$this->assignAfterElement( "botversion", VERSION );
		$this->assignAfterElement( "cidversion", CHECKIFDEADVERSION );
		$this->assignAfterElement( "rooturl", ROOTURL );
		$this->assignAfterElement( "wikiroot", $accessibleWikis[WIKIPEDIA]['rooturl'] );
	}

	public function assignElement( $element, $value ) {
		$this->template = str_replace( "{{{{" . $element . "}}}}", $value, $this->template );
	}

	public function assignAfterElement( $element, $value ) {
		$this->afterLoadedElements[$element] = $value;
	}

	public function setUserMenuElement( $lang, $user = false, $id = false ) {
		global $accessibleWikis, $loadedArguments, $interfaceLanguages;
		$elementText = "";
		if( $user === false ) {
			$elementText = "<li class=\"dropdown\" id=\"usermenudropdown\" onclick=\"openUserMenu()\" onmouseover=\"openUserMenu()\" onmouseout=\"closeUserMenu()\">
						<a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
						   aria-haspopup=\"true\"
						   aria-expanded=\"false\"
						   id=\"usermenudropdowna\"><strong>{{{notloggedin}}}</strong> <span class=\"caret\"></span></a>
						<ul class=\"dropdown-menu\">
							<li><a href=\"oauthcallback.php?action=login&wiki=" . WIKIPEDIA . "&returnto=https://" .
			               $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
			               "\"><span class=\"glyphicon glyphicon-log-in\"></span> {{{loginbutton}}}</a></li>
						</ul>";
		} else {
			$elementText = "<li class=\"dropdown\" id=\"usermenudropdown\" onclick=\"openUserMenu()\" onmouseover=\"openUserMenu()\" onmouseout=\"closeUserMenu()\">
						<a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
						   aria-haspopup=\"true\"
						   aria-expanded=\"false\"
						   id=\"usermenudropdowna\"><span class=\"glyphicon glyphicon-user\"></span> <strong>$user</strong> <span class=\"caret\"></span></a>
						<ul class=\"dropdown-menu\">
							<li><a href=\"index.php?page=user&id=" . $id . "\"><span class=\"glyphicon glyphicon-list-alt\"></span> {{{usermebutton}}}</a></li>
							<li><a href=\"index.php?page=userpreferences\"><span class=\"glyphicon glyphicon-wrench\"></span> {{{userpreferences}}}</a></li>
							<li><a href=\"oauthcallback.php?action=logout&returnto=https://" . $_SERVER['HTTP_HOST'] .
			               $_SERVER['REQUEST_URI'] . "\"><span class=\"glyphicon glyphicon-log-out\"></span> {{{logoutbutton}}}</a></li>
							<li role=\"separator\" class=\"divider\"></li>
	                            <li class=\"dropdown-header\"><span class=\"glyphicon glyphicon-globe\" aria-hidden=\"true\"></span> {{{selectwiki}}}</li>
								<li class=\"dropdown\" id=\"userwikidropdown\" onclick=\"toggleWikiMenu()\"><a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
								   aria-haspopup=\"true\"
								   aria-expanded=\"false\"
								   id=\"userwikidropdowna\">" . $accessibleWikis[WIKIPEDIA]['name'] . " <span class=\"caret\"></a>
	                                <ul class=\"dropdown-menu scrollable-menu\">\n";
			unset( $accessibleWikis[WIKIPEDIA] );
			foreach( $accessibleWikis as $wiki => $info ) {
				$urlbuilder = $loadedArguments;
				unset( $urlbuilder['action'], $urlbuilder['token'], $urlbuilder['checksum'] );
				$urlbuilder['wiki'] = $wiki;
				$elementText .= "<li><a href=\"index.php?" . http_build_query( $urlbuilder ) . "\">" .
				                $accessibleWikis[$wiki]['name'] . "</a></li>\n";
			}
			$elementText .= "                                </ul>
							</li>
					<li role=\"separator\" class=\"divider\"></li>
	                            <li class=\"dropdown-header\"><span class=\"glyphicon glyphicon-globe\" aria-hidden=\"true\"></span> {{{selectlang}}}</li>
								<li class=\"dropdown\" id=\"userlangdropdown\" onclick=\"toggleLangMenu()\"><a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
								   aria-haspopup=\"true\"
								   aria-expanded=\"false\"
								   id=\"userlangdropdowna\">" . $interfaceLanguages[$lang] . " <span class=\"caret\"></a>
	                                <ul class=\"dropdown-menu scrollable-menu\">\n";
			unset( $interfaceLanguages[$lang] );
			foreach( $interfaceLanguages as $langCode => $langName ) {
				$urlbuilder = $loadedArguments;
				unset( $urlbuilder['action'], $urlbuilder['token'], $urlbuilder['checksum'] );
				$urlbuilder['lang'] = $langCode;
				$elementText .= "<li><a href=\"index.php?" . http_build_query( $urlbuilder ) . "\">" .
				                $langName . "</a></li>\n";
			}
			$elementText .= "                                </ul>
							</li>
				</ul>";
		}

		$this->template = str_replace( "{{{{usermenuitem}}}}", $elementText, $this->template );
	}

	public function setMessageBox( $boxType = "info", $headline = "", $text = "" ) {
		$elementText = "<div class=\"alert alert-$boxType\" role=\"alert\" aria-live=\"assertive\">
        <strong>$headline:</strong> $text
      </div>";
		$this->template = str_replace( "{{{{messages}}}}", $elementText, $this->template );
	}

	public function finalize() {
		$this->template = preg_replace( '/\{\{\{\{.*?\}\}\}\}/i', "", $this->template );
		preg_match_all( '/\{\{\{(.*?)\}\}\}/i', $this->template, $i18nElements );

		foreach( $i18nElements[1] as $element ) {
			if( isset( $this->i18n[$element] ) ) {
				$this->template = str_replace( "{{{" . $element . "}}}",
				                               $this->i18n[$element],
				                               $this->template
				);
			} else $this->template = str_replace( "{{{" . $element . "}}}", "MISSING i18n ELEMENT", $this->template );
		}

		foreach( $this->afterLoadedElements as $element => $content ) {
			$this->template = str_replace( "{{" . $element . "}}", $content, $this->template );
		}
	}

	public function getLoadedTemplate() {
		return $this->template;
	}

	public function loadWikisi18n() {
		global $accessibleWikis, $oauthObject;

		if( isset( $_SESSION['intWikis'] ) && $_SESSION['intWikis']['lang'] == $this->langCode ) {
			$this->i18n = array_merge( $this->i18n, $_SESSION['intWikis']['wikinames'] );

			return true;
		}
		$intList = [];
		foreach( $accessibleWikis as $wiki => $data ) {
			$intList[] = "{{int:Project-localized-name-$wiki}}";
		}

		$toParse = implode( "\n", $intList );
		$post = [
			"action"             => "parse",
			"format"             => "php",
			"text"               => $toParse,
			"prop"               => "text",
			"disablelimitreport" => 1,
			"disableeditsection" => 1,
			"disabletidy"        => 1,
			"disabletoc"         => 1,
			"contentformat"      => "text/x-wiki",
			"contentmodel"       => "wikitext"
		];

		$url = API;
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
		if( $oauthObject->isLoggedOn() ) curl_setopt( $ch, CURLOPT_HTTPHEADER, [ API::generateOAuthHeader( 'POST', $url ) ] );
		curl_setopt( $ch, CURLOPT_HTTPGET, 0 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
		$data = curl_exec( $ch );
		curl_close( $ch );
		$data = unserialize( $data );
		if( isset( $data['parse']['text']['*'] ) ) {
			$data = $data['parse']['text']['*'];
			$data = substr( $data, 3, strlen( $data ) - 7 );
			$data = trim( $data );
			$data = explode( "\n", $data );
			$_SESSION['intWikis']['lang'] = $this->langCode;
			$counter = 0;
			foreach( $accessibleWikis as $wiki => $stuff ) {
				$_SESSION['intWikis']['wikinames'][$wiki . 'name'] = $data[$counter];
				$counter++;
			}
		} else {
			return false;
		}
		$this->i18n = array_merge( $this->i18n, $_SESSION['intWikis']['wikinames'] );

		return true;
	}
}