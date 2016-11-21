<?php

/*
	Copyright (c) 2016, Maximilian Doerr

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

	public function __construct( $template, $langCode, $templatePath = false, $i18nPath = false ) {
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

		$this->i18n = json_decode( $this->i18n, true );
	}

	public function assignElement( $element, $value ) {
		$this->template = str_replace( "{{{{" . $element . "}}}}", $value, $this->template );
	}

	public function assignAfterElement( $element, $value ) {
		$this->afterLoadedElements[$element] = $value;
	}

	public function setUserMenuElement( $user = false, $id = false ) {
		$elementText = "";
		if( $user === false ) {
			$elementText = "<li class=\"dropdown\">
						<a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\" role=\"button\"
						   aria-haspopup=\"true\"
						   aria-expanded=\"false\"><strong>{{{notloggedin}}}</strong> <span class=\"caret\"></span></a>
						<ul class=\"dropdown-menu\">
							<li><a href=\"oauthcallback.php?action=login&wiki=" . WIKIPEDIA . "&returnto=https://" .
			               $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
			               "\">{{{loginbutton}}}</a></li>
						</ul>";
		} else {
			$elementText = "<li class=\"dropdown\">
						<a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\" role=\"button\"
						   aria-haspopup=\"true\"
						   aria-expanded=\"false\"><strong>$user</strong> <span class=\"caret\"></span></a>
						<ul class=\"dropdown-menu\">
							<li><a href=\"index.php?page=user&id=".$id."\">{{{usermebutton}}}</a></li>
							<li><a href=\"oauthcallback.php?action=logout&returnto=https://" . $_SERVER['HTTP_HOST'] .
			               $_SERVER['REQUEST_URI'] . "\">{{{logoutbutton}}}</a></li>
						</ul>
					</li>";
		}

		$this->template = str_replace( "{{{{usermenuitem}}}}", $elementText, $this->template );
	}

	public function setMessageBox( $boxType = "info", $headline = "", $text = "" ) {
		$elementText = "<div class=\"alert alert-$boxType\" role=\"alert\">
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

		foreach( $this->afterLoadedElements as $element=>$content ) {
			$this->template = str_replace( "{{" . $element . "}}", $content, $this->template );
		}
	}

	public function getLoadedTemplate() {
		return $this->template;
	}
}