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
 * jawikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * jawikiParser class
 * Extension of the master parser class specifically for ja.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class jawikiParser extends Parser {

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
		return '%Y年%-m月%-e日';
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
			$link['newdata']['tag_type'] = "parameter";
			$link['newdata']['link_template']['parameters']['deadlinkdate'] = self::strftime( '%Y年%m月' );
		} else {
			$link['newdata']['tag_type'] = "template";
			$link['newdata']['tag_template']['name'] = "リンク切れ";
			$link['newdata']['tag_template']['parameters']['date'] = self::strftime( '%Y年%m月' );
			$link['newdata']['tag_template']['parameters']['bot'] = USERNAME;
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

			//If there is a wayback tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive1_tags'] ), $remainder,
			                $params2
			) ) {
				$returnArray['archive_host'] = "wayback";

				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$url = htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'], true ) );
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$url = htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters'][1], true ) );
				} elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
					$url = htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['site'], true ) );
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for archive timestamp.  If there isn't any, then it's not pointing a snapshot, which makes it harder for the reader and other editors.
				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] =
						self::strtotime( $timestamp = $this->filterText( $returnArray['archive_template']['parameters']['date'], true ) );
					$returnArray['archive_url'] =
						"https://web.archive.org/web/$timestamp/$url";
				} else {
					$returnArray['archive_time'] = "x";
					$returnArray['archive_url'] = "https://web.archive.org/web/*/$url";
					$returnArray['archive_type'] = "invalid";
				}

				//If the original URL isn't present, then we are dealing with a stray archive template.
				if( !isset( $returnArray['url'] ) ) {
					$returnArray['archive_type'] = "invalid";
					$returnArray['url'] = $url;
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
				}

				//Check for a malformation or template misuse.  The URL field needs the original URL, not the archive URL.
				if( $returnArray['archive_url'] == "x" || strpos( $url, "archive.org" ) !== false ) {
					if( preg_match( '/archive\.org\/(web\/)?(\d*?|\*)\/(\S*)\s?/i', $url, $params3 ) ) {
						$returnArray['archive_type'] = "invalid";
						if( $params3[2] != "*" ) $returnArray['archive_time'] = self::strtotime( $params3[2] );
						else $returnArray['archive_time'] = "x";
						$returnArray['archive_url'] = "https://web." . $this->filterText( $params3[0], true );
					} else {
						$returnArray['archive_type'] = "invalid";
					}
				}
			} //If there is a webcite tag present, process it
			elseif( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive2_tags'] ), $remainder,
			                    $params2
			) ) {
				$returnArray['archive_host'] = "webcite";
				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$returnArray['archive_url'] =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'], true ) );
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$returnArray['archive_url'] =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters'][1], true ) );
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for the archive timestamp.  Since the Webcite archives use a unique URL for each snapshot, a missing date stamp does not mean invalid usage.
				if( isset( $returnArray['archive_template']['parameters']['fecha'] ) ) {
					$returnArray['archive_time'] =
						self::strtotime( $this->filterText( $returnArray['archive_template']['parameters']['date'], true ) );
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
			elseif( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive3_tags'] ), $remainder,
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
					if( !API::isArchive( htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'], true )
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
		$string = preg_replace( '/(?:三月|遊行|游行|3月)/i', "March", $string );
		$string = preg_replace( '/(?:四月|四月|4月)/i', "April", $string );
		$string = preg_replace( '/(?:五月|可能|可能|5月)/i', "May", $string );
		$string = preg_replace( '/(?:六月|六月|6月)/i', "June", $string );
		$string = preg_replace( '/(?:七月|七月|7月)/i', "July", $string );
		$string = preg_replace( '/(?:八月|八月|8月)/i', "August", $string );
		$string = preg_replace( '/(?:九月|九月|9月)/i', "September", $string );
		$string = preg_replace( '/(?:十月|十月|10月)/i', "October", $string );
		$string = preg_replace( '/(?:十一月|十一月|11月)/i', "November", $string );
		$string = preg_replace( '/(?:十二月|十二月|12月)/i', "December", $string );
		$string = preg_replace( '/(?:一月|1月)/i', "January", $string );
		$string = preg_replace( '/(?:二月|2月)/i', "February", $string );
		$string = str_replace( "年", "-", $string );
		$string = str_replace( "月", "-", $string );
		$string = str_replace( "日", "-", $string );

		return strtotime( $string );
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
	 * @param $lang Default language to use
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewCitationTemplate( &$link, $lang = "en" ) {
		parent::generateNewCitationTemplate( $link, $lang );

		if( $link['newdata']['tagged_dead'] === true ) {
			$link['newdata']['link_template']['parameters']['deadurldate'] = self::strftime( '%Y年%m月' );
		}
	}

	/**
	 * Analyze the citation template
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $params Citation template regex match breakdown
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeCitation( &$returnArray, &$params ) {
		parent::analyzeCitation( $returnArray, $params );

		if( !empty( $returnArray['link_template']['parameters']['deadurldate'] ) || !empty( $returnArray['link_template']['parameters']['deadurl'] ) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "parameter";
		}
	}
}