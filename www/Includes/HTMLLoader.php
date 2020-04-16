<?php

/*
	Copyright (c) 2015-2018, Maximilian Doerr

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

	public static $incompleteLanguage = false;
	protected $template;
	protected $i18n;
	protected $defaulti18n;
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
				$this->i18n = file_get_contents( "i18n/en.json" );
				$this->loadLangErrorBox( $langCode );
			}
			$this->defaulti18n = file_get_contents( "i18n/en.json" );
		} else {
			if( file_exists( $i18nPath . $langCode . ".json" ) ) {
				$this->i18n = file_get_contents( $i18nPath . $langCode . ".json" );
			} else {
				echo "i18n file $langCode.json cannot be found.  Tried to load \"$i18nPath$langCode.json\"";
				exit( 50 );
			}
			$this->defaulti18n = file_get_contents( $i18nPath . "en.json" );
		}

		$this->langCode = $langCode;

		$this->i18n = json_decode( $this->i18n, true );

		$this->defaulti18n = json_decode( $this->defaulti18n, true );

		$this->assignElement( "languagecode", $langCode );
		if( defined( 'VERSION' ) ) $this->assignAfterElement( "botversion", VERSION );
		if( defined( 'CHECKIFDEADVERSION' ) ) $this->assignAfterElement( "cidversion", CHECKIFDEADVERSION );
		$this->assignAfterElement( "rooturl", ROOTURL );
		if( defined( 'WIKIPEDIA' ) ) $this->assignAfterElement( "wikiroot", $accessibleWikis[WIKIPEDIA]['rooturl'] );
	}

	public function loadDebugWarning( $langcode ) {
		$elementText = "<div class=\"alert alert-warning\" role=\"alert\" aria-live=\"assertive\">
        <strong>{{{debugwarningheader}}}:</strong> {{{debugwarningmessage}}}
      </div>";
		$this->template = str_replace( "{{{{debugmessage}}}}", $elementText, $this->template );
	}

	public function loadLangErrorBox( $langcode, $incomplete = false ) {
		if( $incomplete === false ) $elementText = "<div class=\"alert alert-warning\" role=\"alert\" aria-live=\"assertive\">
        <strong>{{{languageunavailableheader}}}:</strong> {{{languageunavailablemessage}}}
      </div>";
		else $elementText = "<div class=\"alert alert-warning\" role=\"alert\" aria-live=\"assertive\">
        <strong>{{{incompletetranslationheader}}}:</strong> {{{incompletetranslationmessage}}}
      </div>";
		$this->template = str_replace( "{{languagemessage}}", $elementText, $this->template );
	}

	public function assignElement( $element, $value ) {
		$this->template = str_replace( "{{{{" . $element . "}}}}", $value, $this->template );
	}

	public function assignAfterElement( $element, $value ) {
		$this->afterLoadedElements[$element] = $value;
	}

	public function setUserMenuElement( $lang, $user = false, $id = false ) {
		global $accessibleWikis, $loadedArguments, $languages;
		$elementText = "";
		if( $user === false ) {
			$elementText = "<li class=\"dropdown\" id=\"usermenudropdown\" onclick=\"openUserMenu()\" onmouseover=\"openUserMenu()\" onmouseout=\"closeUserMenu()\">
						<a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
						   aria-haspopup=\"true\"
						   aria-expanded=\"false\"
						   id=\"usermenudropdowna\"><strong>{{{notloggedin}}}</strong> <span class=\"caret\"></span></a>
						<ul class=\"dropdown-menu\">
							<li><a href=\"oauthcallback.php?action=login&wiki=" . WIKIPEDIA . "&returnto=https://" .
			               $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			if( defined( 'GUIFULLAUTH' ) ) $elementText .= "&fullauth=1";
			$elementText .= "\"><span class=\"glyphicon glyphicon-log-in\"></span> {{{loginbutton}}}</a></li>
						</ul>";
		} else {
			$elementText = "<li class=\"dropdown\" id=\"usermenudropdown\" onclick=\"openUserMenu()\" onmouseover=\"openUserMenu()\" onmouseout=\"closeUserMenu()\">
						<a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
						   aria-haspopup=\"true\"
						   aria-expanded=\"false\"
						   id=\"usermenudropdowna\"><span class=\"glyphicon glyphicon-user\"></span> <strong>$user</strong> <span class=\"caret\"></span></a>
						<ul class=\"dropdown-menu\">
							<li><a href=\"index.php?page=user&id=" . $id . "&wiki=" . WIKIPEDIA . "\"><span class=\"glyphicon glyphicon-list-alt\"></span> {{{usermebutton}}}</a></li>
							<li><a href=\"index.php?page=userpreferences\"><span class=\"glyphicon glyphicon-wrench\"></span> {{{userpreferences}}}</a></li>
							<li><a href=\"oauthcallback.php?action=logout&returnto=https://" . $_SERVER['HTTP_HOST'] .
			               $_SERVER['REQUEST_URI'] . "\"><span class=\"glyphicon glyphicon-log-out\"></span> {{{logoutbutton}}}</a></li>
							<li role=\"separator\" class=\"divider\"></li>
	                            <li class=\"dropdown-header\"><span class=\"glyphicon glyphicon-globe\" aria-hidden=\"true\"></span> {{{selectwiki}}}</li>
								<li class=\"dropdown\" id=\"userwikidropdown\" onclick=\"toggleWikiMenu()\"><a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
								   aria-haspopup=\"true\"
								   aria-expanded=\"false\"
								   id=\"userwikidropdowna\">{{{".$accessibleWikis[WIKIPEDIA]['i18nsourcename'].WIKIPEDIA."name}}} <span class=\"caret\"></a>
	                                <ul class=\"dropdown-menu scrollable-menu\">\n";
			unset( $accessibleWikis[WIKIPEDIA] );
			foreach( $accessibleWikis as $wiki => $info ) {
				$urlbuilder = $loadedArguments;
				unset( $urlbuilder['action'], $urlbuilder['token'], $urlbuilder['checksum'] );
				$urlbuilder['wiki'] = $wiki;
				$elementText .= "<li><a href=\"index.php?" . http_build_query( $urlbuilder ) . "\">{{{".$accessibleWikis[$wiki]['i18nsourcename'].$wiki."name}}}</a></li>\n";
			}
			$elementText .= "                                </ul>
							</li>
					<li role=\"separator\" class=\"divider\"></li>
	                            <li class=\"dropdown-header\"><span class=\"glyphicon glyphicon-globe\" aria-hidden=\"true\"></span> {{{selectlang}}}</li>
								<li class=\"dropdown\" id=\"userlangdropdown\" onclick=\"toggleLangMenu()\"><a href=\"#\" class=\"dropdown-toggle\" role=\"button\"
								   aria-haspopup=\"true\"
								   aria-expanded=\"false\"
								   id=\"userlangdropdowna\">" . $languages[$lang] . " <span class=\"caret\"></a>
	                                <ul class=\"dropdown-menu scrollable-menu\">\n";
			$tmp = $languages;
			unset( $tmp[$lang] );
			foreach( $tmp as $langCode => $langName ) {
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

	public function setMaintenanceMessage( $maintenance = false ) {
		if( $maintenance === true ) {
			$elementText = "		<div class=\"alert alert-info\" role=\"alert\" aria-live=\"assertive\">
			<p style=\"text-align: center;\">
				{{{maintenancesentence1}}}
				<br>
				<div class=\"progress\">
					<div id=\"progressbarmaintenance\" class=\"progress-bar progress-bar-striped progress-bar-success active\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: 0%\">
						<span id=\"progressbarmaintenancetext\">--/-- (--%)</span>
					</div>
				</div>
				{{{eta}}}:
				<span id=\"maintenanceeta\">
					---
				</span>
			</p>
		</div>";
		} else {
			$elementText = "		<div class=\"alert alert-warning\" role=\"alert\" aria-live=\"assertive\">
			 <strong>{{{interacedisabled}}}:</strong> {{{interacedisableddescription}}}
      </div>";
		}
		$this->template = str_replace( "{{{{maintenancefield}}}}", $elementText, $this->template );
		$this->template = str_replace( "{{{{onloadfunction}}}}", "loadInterface()", $this->template );
	}

	public function disableLockOutOverride() {
		$this->template = str_replace( "{{{{accessoverride}}}}", "none", $this->template );
	}

	public function setMessageBox( $boxType = "info", $headline = "", $text = "" ) {
		$elementText = "<div class=\"alert alert-$boxType\" role=\"alert\" aria-live=\"assertive\">
        <strong>$headline:</strong> $text
      </div>";
		$this->template = str_replace( "{{{{messages}}}}", $elementText, $this->template );
	}

	public function finalize() {
		$this->template = preg_replace( '/\{\{\{\{.*?\}\}\}\}/i', "", $this->template );

        if( self::$incompleteLanguage === true ) $this->loadLangErrorBox( $this->langCode, true );
        else $this->template = str_replace( "{{languagemessage}}", "", $this->template );

		preg_match_all( '/\{\{\{(.*?)\}\}\}/i', $this->template, $i18nElements );

		foreach( $i18nElements[1] as $element ) {
			if( isset( $this->i18n[$element] ) ) {
				$this->template = str_replace( "{{{" . $element . "}}}",
				                               $this->i18n[$element],
				                               $this->template
				);
			} elseif( isset( $this->defaulti18n[$element] ) ) {
				$this->template = str_replace( "{{{" . $element . "}}}",
				                               $this->defaulti18n[$element],
				                               $this->template
				);
				self::$incompleteLanguage = true;
			} else $this->template =
				str_replace( "{{{" . $element . "}}}", "MISSING i18n ELEMENT ($element)", $this->template );
		}



		foreach( $this->afterLoadedElements as $element => $content ) {
			$this->template = str_replace( "{{" . $element . "}}", $content, $this->template );
		}
	}

	public function getLoadedTemplate() {
		return $this->template;
	}

	public function loadLanguages() {
		global $accessibleWikis, $oauthObject, $languages;

		$reloadData = false;

		if( $this->langCode != "en" ) $englishLanguage = DB::getConfiguration( "global", "languages", "en" );
		else $englishLanguage = [];

		$intList = [];

		$intList[$this->langCode] = "{$this->langCode} - {{#language:{$this->langCode}|{$this->langCode}}}";
		if( !isset( $languages[$this->langCode] ) ) $reloadData = true;

		foreach( $accessibleWikis as $wiki => $data ) {
			$intList[$data['language']] = "{$data['language']} - {{#language:{$data['language']}|{$this->langCode}}}";
			if( !isset( $languages[$data['language']] ) ) $reloadData = true;
			elseif( isset( $englishLanguage[$data['language']] ) && $englishLanguage[$data['language']] == $languages[$data['language']] ) $reloadData = true;
		}

		$dir = __DIR__ . '/../i18n/';

		// Open a directory, and read its contents
		if( is_dir( $dir ) ) {
			if( $dh = opendir( $dir ) ) {
				while( ( $file = readdir( $dh ) ) !== false ) {
					if( strpos( $file, ".json" ) === false ) continue;
					if( $file == "qqq.json" ) continue;
					$lang = str_replace( ".json", "", $file );
					$intList[$lang] = "$lang - {{#language:$lang|{$this->langCode}}}";
					if( !isset( $languages[$lang] ) ) $reloadData = true;
					elseif( isset( $englishLanguage[$lang] ) && $englishLanguage[$lang] == $languages[$lang] ) $reloadData = true;
				}
				closedir( $dh );
			}
		}

		if( $reloadData === true ) {
			asort( $intList );

			$toParse = implode( "\n", $intList );
			$post = [
				"action"             => "parse",
				"format"             => "json",
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
			if( $oauthObject->isLoggedOn() ) curl_setopt( $ch, CURLOPT_HTTPHEADER,
			                                              [ API::generateOAuthHeader( 'POST', $url ) ]
			);
			curl_setopt( $ch, CURLOPT_HTTPGET, 0 );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
			$data = curl_exec( $ch );
			curl_close( $ch );
			$data = json_decode( $data, true );
			$writeConfiguration = true;
			if( isset( $data['parse']['text']['*'] ) ) {
				$data = $data['parse']['text']['*'];
				preg_match( '/\<p\>(.*?)\<\/p\>/si', $data, $data );
				$data = $data[1];
				$data = trim( $data );
				$data = explode( "\n", $data );
				$counter = 0;
				foreach( $intList as $language => $junk ) {
					if( $data[$counter] == "$language - $language" ) $writeConfiguration = false;
					$languages[$language] = $data[$counter];
					$counter++;
				}
			} else {
				return false;
			}

			if( $writeConfiguration === true ) DB::setConfiguration( "global", "languages", $this->langCode, $languages );
		}

		return true;
	}

	public function loadWikisi18n() {
		global $accessibleWikis, $oauthObject;

		$reloadData = false;

		$wikis = DB::getConfiguration( "global", "wiki-languages", $this->langCode );
		if( $this->langCode != "en" ) $englishLanguage = DB::getConfiguration( "global", "wiki-languages", "en" );
		else $englishLanguage = [];

		$intList = [];
		$intListAPI = [];
		foreach( $accessibleWikis as $wiki => $data ) {
			$intList[$data['i18nsourcename']][$wiki] = "$wiki - {{int:Project-localized-name-$wiki}}";
			$intListAPI[$data['i18nsourcename']] = $data['i18nsource'];
			if( !isset( $wikis[$data['i18nsourcename'].$wiki."name"] ) ) $reloadData = true;
			elseif( isset( $englishLanguage[$data['i18nsourcename'].$wiki."name"] ) && $englishLanguage[$data['i18nsourcename'].$wiki."name"] == $wikis[$data['i18nsourcename'].$wiki."name"] ) $reloadData = true;
		}

		if( $reloadData === true ) {
			foreach( $intListAPI as $name => $url ) {
				$toParse = implode( "\n", $intList[$name] );
				$post = [
					"action"             => "parse",
					"format"             => "json",
					"text"               => $toParse,
					"prop"               => "text",
					"disablelimitreport" => 1,
					"disableeditsection" => 1,
					"disabletidy"        => 1,
					"disabletoc"         => 1,
					"contentformat"      => "text/x-wiki",
					"contentmodel"       => "wikitext"
				];

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
				curl_setopt( $ch, CURLOPT_HTTPGET, 0 );
				curl_setopt( $ch, CURLOPT_POST, 1 );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
				$data = curl_exec( $ch );
				curl_close( $ch );
				$data = json_decode( $data, true );
				if( isset( $data['parse']['text']['*'] ) ) {
					$data = $data['parse']['text']['*'];
					preg_match( '/\<p\>(.*?)\<\/p\>/si', $data, $data );
					$data = $data[1];
					$data = trim( $data );
					$data = explode( "\n", $data );
					$counter = 0;
					foreach( $intList[$name] as $wiki => $stuff ) {
						$wikis[$name.$wiki . 'name'] = $data[$counter];
						if( $wikis[$name.$wiki.'name'] == "$wiki - ⧼Project-localized-name-{$wiki}⧽" ) $wikis[$name.$wiki.'name'] = $wiki;
						$counter++;
					}
				} else {
					return false;
				}
			}

			DB::setConfiguration( "global", "wiki-languages", $this->langCode, $wikis );
		}

		if( !is_null( $wikis) && !is_null( $this->i18n ) ) $this->i18n = $wikis + $this->i18n;

		return true;
	}
}