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
 * ckbwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * ckbwikiParser class
 * Extension of the master parser class specifically for ckb.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class ckbwikiParser extends Parser {

	/**
	 * Get page date formatting standard
	 *
	 * @param bool|string $default Return default format, or return supplied date format of timestamp, provided a page
	 *     tag doesn't override it.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string Format to be fed in time()
	 */
	protected function retrieveDateFormat( $default = false ) {
		return '%eی %Bی %Y';
	}

	/**
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewArchiveTemplate( &$link, &$temp ) {
		//We need the archive host, to pick the right template.
		if( !isset( $link['newdata']['archive_host'] ) ) $link['newdata']['archive_host'] =
			$this->getArchiveHost( $temp['archive_url'] );
		//If the archive template is being used improperly, delete the parameters, and start fresh.
		if( $link['has_archive'] === true &&
		    $link['archive_type'] == "invalid"
		) unset( $link['archive_template']['parameters'] );
		switch( $link['newdata']['archive_host'] ) {
			default:
				$link['newdata']['archive_template']['name'] = "webarchive";
				$link['newdata']['archive_template']['parameters']['url'] = $temp['archive_url'];
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['date'] =
					self::strftime( $this->retrieveDateFormat( $link['string'] ), $temp['archive_time'] );
				break;
		}

		return true;
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
		if( $link['link_type'] == "template" && $link['has_archive'] === true ) {
			if( $this->getCiteDefaultKey( "deadurl", $link['link_template']['language'] ) !== false ) {
				$link['newdata']['tag_type'] = "parameter";
				if( $this->getCiteDefaultKey( "deadurlyes", $link['link_template']['language'] ) === false ) {
					$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "deadurl", $link['link_template']['language'],
					                                                                         $link['link_template'],
					                                                                         true
					)] = "yes";
				} else {
					$link['newdata']['link_template']['parameters'][$this->getCiteActiveKey( "deadurl",
					                                                                         $link['link_template']['language'],
					                                                                         $link['link_template'],
					                                                                         true
					)] = $this->getCiteDefaultKey( "deadurlyes", $link['link_template']['language'] );
				}
			}
		} else {
			$link['newdata']['tag_type'] = "template";
			$link['newdata']['tag_template']['name'] = "dead link";
			$link['newdata']['tag_template']['parameters']['date'] = self::strftime( '%B %Y' );
			$link['newdata']['tag_template']['parameters']['bot'] = USERNAME;
			$link['newdata']['tag_template']['parameters']['fix-attempted'] = 'yes';
		}
	}

	/**
	 * Analyze the remainder string
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $remainder Remainder string
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeRemainder( &$returnArray, &$remainder ) {
		//If there's an archive tag, then...
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive_tags'] ), $remainder, $params2
		) ) {
			$returnArray['archive_type'] = "template";
			$returnArray['archive_template'] = [];
			$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
			$returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
			$returnArray['archive_template']['string'] = $params2[0];

			//If there already is an archive in this source, it's means there's an archive template attached to a citation template.  That's needless confusion when sourcing.
			if( $returnArray['link_type'] == "template" && $returnArray['has_archive'] === false ) {
				$returnArray['archive_type'] = "invalid";
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "implied";
			}

			$returnArray['has_archive'] = true;

			//If there is a webcite tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive1_tags'] ), $remainder,
			                    $params2
			) ) {
				$returnArray['archive_host'] = "webcite";
				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$returnArray['archive_url'] =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'],
						                                            true
						)
						);
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$returnArray['archive_url'] =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters'][1],
						                                            true
						)
						);
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for the archive timestamp.  Since the Webcite archives use a unique URL for each snapshot, a missing date stamp does not mean invalid usage.
				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] =
						self::strtotime( $this->filterText( $returnArray['archive_template']['parameters']['date'], true
						)
						);
				} else {
					$returnArray['archive_time'] = "x";
				}

				//If the original URL isn't present, then we are dealing with a stray archive template.
				if( !isset( $returnArray['url'] ) ) {
					//resolve the archive to the original URL
					API::isArchive( $returnArray['archive_url'], $returnArray );
					$returnArray['archive_type'] = "invalid";
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
				}
			} //If there is a webarchive tag present, process it
			elseif( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive4_tags'] ), $remainder,
			                    $params2
			) ) {
				//If the original URL isn't present, then we are dealing with a stray archive template.
				if( !isset( $returnArray['url'] ) ) {
					$returnArray['archive_type'] = "invalid";
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
				}

				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					if( !API::isArchive( htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'],
					                                                                 true
					)
					                     ), $returnArray
					)
					) {
						$returnArray['archive_url'] = "x";
						$returnArray['archive_type'] = "invalid";
					}
				}
			}

			//If we have multiple archives, we can't handle these correctly, so remove any force markers that may force the editing of the citations.
			if( $returnArray['link_type'] == "template" && $returnArray['has_archive'] === true &&
			    $returnArray['archive_type'] == "template"
			) {
				unset( $returnArray['convert_archive_url'] );
				unset( $returnArray['force_when_dead'] );
				unset( $returnArray['force'] );
				unset( $returnArray['force_when_alive'] );
			}
		}

		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['deadlink_tags'] ), $remainder, $params2
		) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "template";
			if( isset( $params2[2] ) ) $returnArray['tag_template']['parameters'] =
				$this->getTemplateParameters( $params2[2] );
			else $returnArray['tag_template']['parameters'] = [];
			//Flag those that can't be fixed.
			if( isset( $returnArray['tag_template']['parameters']['fix-attempted'] ) &&
			    ( !isset( $returnArray['tag_template']['paramaters']['bot'] ) ||
			      $returnArray['tag_template']['paramaters']['bot'] != USERNAME ) ) $returnArray['permanent_dead'] =
				true;
			$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
			$returnArray['tag_template']['string'] = $params2[0];
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
		$string = str_replace( "،", " ", $string );
		$string = str_replace( "ی", " ", $string );
		$string = str_replace( "٠", "0", $string );
		$string = str_replace( "١", "1", $string );
		$string = str_replace( "٢", "2", $string );
		$string = str_replace( "٣", "3", $string );
		$string = str_replace( "٤", "4", $string );
		$string = str_replace( "٥", "5", $string );
		$string = str_replace( "٦", "6", $string );
		$string = str_replace( "٧", "7", $string );
		$string = str_replace( "٨", "8", $string );
		$string = str_replace( "٩", "9", $string );
		$string = preg_replace( '/(?:ئازار)/i', "March", $string );
		$string = preg_replace( '/(?:نیسان)/i', "April", $string );
		$string = preg_replace( '/(?:ئایار)/i', "May", $string );
		$string = preg_replace( '/(?:حوزەیران)/i', "June", $string );
		$string = preg_replace( '/(?:تەممووز)/i', "July", $string );
		$string = preg_replace( '/(?:ئاب)/i', "August", $string );
		$string = preg_replace( '/(?:ئەیلوول)/i', "September", $string );
		$string = preg_replace( '/(?:تشرینی یەکەم)/i', "October", $string );
		$string = preg_replace( '/(?:تشرینی دووەم)/i', "November", $string );
		$string = preg_replace( '/(?:کانوونی یەکەم)/i', "December", $string );
		$string = preg_replace( '/(?:کانوونی دووەم)/i', "January", $string );
		$string = preg_replace( '/(?:شوبات)/i', "February", $string );

		return strtotime( $string );
	}

	/**
	 * A workaround function for when Locale information isn't available
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
	public static function localizeTimestamp( $string ) {
		$string = str_ireplace( "January", "کانوونی دووەم", $string );
		$string = str_ireplace( "February", "شوبات", $string );
		$string = str_ireplace( "March", "ئازار", $string );
		$string = str_ireplace( "April", "نیسان", $string );
		$string = str_ireplace( "May", "ئایار", $string );
		$string = str_ireplace( "June", "حوزەیران", $string );
		$string = str_ireplace( "July", "تەممووز", $string );
		$string = str_ireplace( "August", "ئاب", $string );
		$string = str_ireplace( "September", "ئەیلوول", $string );
		$string = str_ireplace( "October", "تشرینی یەکەم", $string );
		$string = str_ireplace( "November", "تشرینی دووەم", $string );
		$string = str_ireplace( "December", "کانوونی یەکەم", $string );
		$string = str_replace( "0", "٠", $string );
		$string = str_replace( "1", "١", $string );
		$string = str_replace( "2", "٢", $string );
		$string = str_replace( "3", "٣", $string );
		$string = str_replace( "4", "٤", $string );
		$string = str_replace( "5", "٥", $string );
		$string = str_replace( "6", "٦", $string );
		$string = str_replace( "7", "٧", $string );
		$string = str_replace( "8", "٨", $string );
		$string = str_replace( "9", "٩", $string );

		return $string;
	}
}