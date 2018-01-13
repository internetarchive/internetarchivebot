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

/**
 * @file
 * itwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * itwikiParser class
 * Extension of the master parser class specifically for it.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class itwikiParser extends Parser {

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
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return string Format to be fed in time()
	 */
	protected function retrieveDateFormat( $default = false ) {
		if( !is_bool( $default ) &&
		    preg_match( '/\d\d? (?:January|gennaio|February|febbraio|March|marzo|April|aprile|May|maggio|June|giugno|July|luglio|August|agosto|September|settembre|October|ottobre|November|novembre|December|dicembre) \d{4}/i',
		                $default
		    )
		) return '%-e %B %Y';
		elseif( !is_bool( $default ) &&
		        preg_match( '/(?:January|gennaio|February|febbraio|March|marzo|April|aprile|May|maggio|June|giugno|July|luglio|August|agosto|September|settembre|October|ottobre|November|novembre|December|dicembre) \d\d?\, \d{4}/i',
		                    $default
		        )
		) return '%B %-e, %Y';
		else return '%-e %B %Y';
	}

	/**
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
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
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['data'] =
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
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return void
	 */
	protected function noRescueLink( &$link, &$modifiedLinks, $tid, $id ) {
		$modifiedLinks["$tid:$id"]['type'] = "tagged";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		if( $link['link_type'] == "template" ) {
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
		} elseif( $link['link_type'] == "link" ) {
				$link['newdata']['tag_type'] = "template-swallow";
				$link['newdata']['tag_template']['parameters'][1] = $link['link_string'];
				$link['newdata']['tag_template']['name'] = "collegamento interrotto";
				$link['newdata']['tag_template']['parameters']['date'] = self::strftime( '%B %Y' );
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
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
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

			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive1_tags'] ), $remainder,
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
		}

		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['deadlink_tags'] ), $remainder, $params2
		) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "template";
			if( isset( $params2[2] ) ) $returnArray['tag_template']['parameters'] =
				$this->getTemplateParameters( $params2[2] );
			else $returnArray['tag_template']['parameters'] = [];
			//Flag those that can't be fixed.
			$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
			$returnArray['tag_template']['string'] = $params2[0];

			if( !empty( $returnArray['tag_template']['parameters'][1] ) ) {
				$returnArray['tag_type'] = "template-swallow";
				$returnArray2 = $this->getLinkDetails( $returnArray['tag_template']['parameters'][1], "" );

				unset( $returnArray2['tagged_dead'], $returnArray2['permanent_dead'], $returnArray2['remainder'] );

				$returnArray = array_replace( $returnArray, $returnArray2 );
				unset( $returnArray2 );
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
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
	 */
	public static function strtotime( $string ) {
		$string = preg_replace( '/(?:marzo)/i', "March", $string );
		$string = preg_replace( '/(?:aprile)/i', "April", $string );
		$string = preg_replace( '/(?:maggio)/i', "May", $string );
		$string = preg_replace( '/(?:giugno)/i', "June", $string );
		$string = preg_replace( '/(?:luglio)/i', "July", $string );
		$string = preg_replace( '/(?:agosto)/i', "August", $string );
		$string = preg_replace( '/(?:settembre)/i', "September", $string );
		$string = preg_replace( '/(?:ottobre)/i', "October", $string );
		$string = preg_replace( '/(?:novembre)/i', "November", $string );
		$string = preg_replace( '/(?:dicembre)/i', "December", $string );
		$string = preg_replace( '/(?:gennaio)/i', "January", $string );
		$string = preg_replace( '/(?:febbraio)/i', "February", $string );

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
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
	 */
	public static function localizeTimestamp( $string ) {
		$string = strtolower( $string );
		$string = preg_replace( '/\b1\b/i', '1º', $string  );

		return $string;
	}
}