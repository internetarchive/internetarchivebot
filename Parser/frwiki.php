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

/**
 * @file
 * frwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * frwikiParser class
 * Extension of the master parser class specifically for fr.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class frwikiParser extends Parser {

	//FIXME: The master no longer considers wikiwix invalid.  Remove child class after merging
	/**
	 * Parses a given refernce/external link string and returns details about it.
	 *
	 * @param string $linkString Primary reference string
	 * @param string $remainder Left over stuff that may apply
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array    Details about the link
	 */
	public function getLinkDetails( $linkString, $remainder ) {
		$returnArray = parent::getLinkDetails( $linkString, $remainder );

		if( isset( $returnArray['invalid_archive'] ) && $returnArray['archive_host'] == "wikiwix" ) {
			$returnArray['ignore_iarchive_flag'] = true;
		}

		return $returnArray;
	}

	/**
	 * Rescue a link
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links that were modified
	 * @param array $temp Cached result value from archive retrieval function
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
		$notExists = !API::WikiwixExists( $link['original_url'] );
		if( $link['link_type'] == "template" && $notExists === true ) {
			$link['newdata']['archive_url'] = $temp['archive_url'];
			$link['newdata']['archive_time'] = $temp['archive_time'];
			if( !empty( $link['archive_fragment'] ) ) $link['newdata']['archive_url'] .= "#" .
			                                                                             $link['archive_fragment'];
			elseif( !empty( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['fragment'];

			//The initial assumption is that we are adding an archive to a URL.
			$modifiedLinks["$tid:$id"]['type'] = "addarchive";
			$modifiedLinks["$tid:$id"]['link'] = $link['url'];
			$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

			//Since we already have a template, let this function make the needed modifications.
			$this->generateNewCitationTemplate( $link, $link['link_template']['language'] );

			$temporaryTemplateData = array_merge( $link['link_template'], $link['newdata']['link_template'] );

			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
			) {
				if( !isset( $link['template_url'] ) )
					$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "url",
					                                                                         $link['link_template']['language'],
					                                                                         $temporaryTemplateData,
					                                                                         true
					)] =
						$link['url'];
				else $link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "url",
				                                                                              $link['link_template']['language'],
				                                                                              $temporaryTemplateData,
				                                                                              true
				)] = $link['template_url'];
				$modifiedLinks["$tid:$id"]['type'] = "fix";
			}
			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( isset( $link['convert_archive_url'] ) ||
			    ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
			) {
				$modifiedLinks["$tid:$id"]['type'] = "fix";
				if( isset( $link['convert_archive_url'] ) ) $link['newdata']['converted_archive'] = true;
			}
			//If we ended up changing the archive URL despite invalid flags, we should mention that change instead.
			if( $link['has_archive'] === true && $link['archive_url'] != $temp['archive_url'] &&
			    !isset( $link['convert_archive_url'] )
			) {
				$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
				$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
			}

			return true;
		} elseif( $link['is_archive'] === false && $notExists === true &&
		          $this->generateNewArchiveTemplate( $link, $temp ) ) {
			$link['newdata']['archive_url'] = $temp['archive_url'];
			$link['newdata']['archive_time'] = $temp['archive_time'];
			if( !empty( $link['archive_fragment'] ) ) $link['newdata']['archive_url'] .= "#" .
			                                                                             $link['archive_fragment'];
			elseif( !empty( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['fragment'];

			//The initial assumption is that we are adding an archive to a URL.
			$modifiedLinks["$tid:$id"]['type'] = "addarchive";
			$modifiedLinks["$tid:$id"]['link'] = $link['url'];
			$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

			//The newdata index is all the data being injected into the link array.  This allows for the preservation of the old data for easier manipulation and maintenance.
			$link['newdata']['has_archive'] = true;
			$link['newdata']['archive_time'] = $temp['archive_time'];
			$link['newdata']['archive_type'] = "template";
			$link['newdata']['tagged_dead'] = false;
			$link['newdata']['is_archive'] = false;
			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( isset( $link['convert_archive_url'] ) ||
			    ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
			) {
				$modifiedLinks["$tid:$id"]['type'] = "fix";
				if( isset( $link['convert_archive_url'] ) ) $link['newdata']['converted_archive'] = true;
			}
			//If we ended up changing the archive URL despite invalid flags, we should mention that change instead.
			if( $link['has_archive'] === true && $link['archive_url'] != $temp['archive_url'] &&
			    !isset( $link['convert_archive_url'] )
			) {
				$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
				$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
			}

			return true;
		} elseif( $link['is_archive'] === false && $notExists === true ) {
			$this->noRescueLink( $link, $modifiedLinks, $tid, $id );
		}

		return false;
	}

	/**
	 * Generates an appropriate citation template without altering existing parameters.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewCitationTemplate( &$link, $lang = "en" ) {
		parent::generateNewCitationTemplate( $link, $lang );

		if( $this->getCiteDefaultKey( "deadurl", $lang ) !== false ) {
			if( ( $link['tagged_dead'] === true || $link['is_dead'] === true ) ) {
				$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "deadurl", $lang,
				                                                                         $link['link_template'],
				                                                                         true
				)] = self::strtotime( '%-e %B %Y' );
			}
		}

	}

	/**
	 * Return a unix timestamp allowing for international support through abstract functions.
	 *
	 * @param $string A timestamp
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
	 */
	public static function strtotime( $string ) {
		$string = preg_replace( '/(?:janvier)/i', "January", $string );
		$string = preg_replace( '/(?:février)/i', "February", $string );
		$string = preg_replace( '/(?:mars)/i', "March", $string );
		$string = preg_replace( '/(?:avril)/i', "April", $string );
		$string = preg_replace( '/(?:mai)/i', "May", $string );
		$string = preg_replace( '/(?:juin)/i', "June", $string );
		$string = preg_replace( '/(?:juillet)/i', "July", $string );
		$string = preg_replace( '/(?:août)/i', "August", $string );
		$string = preg_replace( '/(?:septembre)/i', "September", $string );
		$string = preg_replace( '/(?:octobre)/i', "October", $string );
		$string = preg_replace( '/(?:novembre)/i', "November", $string );
		$string = preg_replace( '/(?:décembre)/i', "December", $string );

		return strtotime( $string );
	}

	/**
	 * Modify link that can't be rescued
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links modified array
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function noRescueLink( &$link, &$modifiedLinks, $tid, $id ) {
		$modifiedLinks["$tid:$id"]['type'] = "tagged";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		if( $link['link_type'] == "template" ) {
			$link['newdata']['tag_type'] = "parameter";
			$link['newdata']['link_template']['parameters']['brisé le'] = self::strtotime( '%-e %B %Y' );
		} else {
			$title = trim( str_replace( $link['original_url'] .
			                            ( empty( $link['fragment'] ) === false ? "#" . $link['fragment'] : "" ), "",
			                            $link['link_string']
			               ), " []"
			);
			$link['newdata']['tag_type'] = "template-swallow";
			$link['newdata']['tag_template']['name'] = "lien brisé";
			$link['newdata']['tag_template']['parameters']['url'] = $link['url'];
			$link['newdata']['tag_template']['parameters']['titre'] = $title;
		}
	}
}