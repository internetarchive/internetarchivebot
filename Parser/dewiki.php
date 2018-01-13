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
 * dewikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * dewikiParser class
 * Extension of the master parser class specifically for de.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class dewikiParser extends Parser {

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
		    preg_match( '/\d\d? (?:Januar|January|Februar|February|März|March|April|Mai|May|Juni|June|Juli|July|August|September|Oktober|October|November|December|Dezember) \d{4}/i',
		                $default
		    )
		) return '%-e %B %Y';
		elseif( !is_bool( $default ) &&
		        preg_match( '/(?:Januar|January|Februar|February|März|March|April|Mai|May|Juni|June|Juli|July|August|September|Oktober|October|November|December|Dezember) \d\d?\, \d{4}/i',
		                    $default
		        )
		) return '%B %-e, %Y';
		else return '%Y-%m-%d';
	}

	/**
	 * Generates an appropriate citation template without altering existing parameters.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewCitationTemplate( &$link, $lang = "de" ) {
		parent::generateNewCitationTemplate( $link, $lang );

		$link['newdata']['link_template']['parameters']['archivebot'] = USERNAME;
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
		$text = $link['link_string'];
		$text = str_replace( $link['original_url'], "", $text );
		$text = trim( $text, "[] " );
		$text = str_replace( "|", "{{!}}", $text );
		if( empty( $text ) ) $link['newdata']['archive_template']['parameters']['text'] = "Archivlink";
		else $link['newdata']['archive_template']['parameters']['text'] = $text;
		switch( $link['newdata']['archive_host'] ) {
			case "wayback":
				$link['newdata']['archive_type'] = "template";
				$link['newdata']['archive_template']['name'] = "Webarchiv";
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				if( !preg_match( '/\/\/(?:www\.|(?:www\.|classic\-|replay\.?)?(?:web)?(?:\-beta|\.wayback)?\.|wayback\.|liveweb\.)?(?:archive|waybackmachine)\.org(?:\/web)?(?:\/(\d*?)(?:\-)?(?:id_|re_)?)?(?:\/_embed)?\/(\S*)/i',
				                 $temp['archive_url'],
				                 $match
				) ) return false;
				$match[1] = str_pad( $match[1], 14, "0", STR_PAD_RIGHT );
				$link['newdata']['archive_template']['parameters']['wayback'] = $match[1];
				$link['newdata']['archive_template']['parameters']['arkiv-bot'] = USERNAME;
				break;
			case "webcite":
				$link['newdata']['archive_type'] = "template";
				$link['newdata']['archive_template']['name'] = "Webarchiv";
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				if( !preg_match( '/\/\/(?:www\.)?webcitation.org\/(query|\S*?)\?(\S+)/i', $temp['archive_url'], $match
				) ) return false;
				if( $match[1] != "query" ) {
					$timestamp = $match[1];
				} else {
					$args = explode( '&', $match[2] );
					foreach( $args as $arg ) {
						$arg = explode( '=', $arg, 2 );
						$temp[urldecode( $arg[0] )] = urldecode( $arg[1] );
					}
					$args = $temp;
					if( isset( $args['id'] ) ) $timestamp = $args['id'];
					elseif( isset( $args['date'] ) ) $timestamp = $args['date'];
					else return false;
				}
				$link['newdata']['archive_template']['parameters']['webciteID'] = $timestamp;
				$link['newdata']['archive_template']['parameters']['arkiv-bot'] = USERNAME;
				break;
			case "archiveis":
				$link['newdata']['archive_type'] = "template";
				$link['newdata']['archive_template']['name'] = "Webarchiv";
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				if( !preg_match( '/\/\/((?:www\.)?archive.(?:is|today|fo|li))\/(\S*?)\/(\S+)/i', $temp['archive_url'],
				                 $match
				) ) return false;
				if( ( strtotime( $match[2] ) ) === false ) {
					$match[2] = preg_replace( '/[\.\-\s]/i', "", $match[2] );
					if( !is_numeric( $match[2] ) ) return false;
				}
				$timestamp = $match[2];
				$link['newdata']['archive_template']['parameters']['archive-is'] = $timestamp;
				$link['newdata']['archive_template']['parameters']['arkiv-bot'] = USERNAME;
				break;
			default:
				$link['newdata']['archive_type'] = "template";
				$link['newdata']['archive_template']['name'] = "Webarchiv";
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				$link['newdata']['archive_template']['parameters']['archiv-url'] = $temp['archive_url'];
				$link['newdata']['archive_template']['parameters']['archive-datum'] = $temp['archive_time'];
				$link['newdata']['archive_template']['parameters']['arkiv-bot'] = USERNAME;
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
		$link['newdata']['tag_type'] = "template";
		$link['newdata']['tag_template']['name'] = "Toter Link";
		$link['newdata']['tag_template']['parameters']['date'] = date( 'Y-m' );
		$link['newdata']['tag_template']['parameters']['archivebot'] = USERNAME;
		$link['newdata']['tag_template']['parameters']['url'] = $link['url'];
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

			//If there is a wayback tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive1_tags'] ), $remainder,
			                $params2
			) ) {
				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$url =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'],
						                                            true
						)
						);
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for archive timestamp.  If there isn't any, then it's not pointing a snapshot, which makes it harder for the reader and other editors.
				if( isset( $returnArray['archive_template']['parameters']['wayback'] ) ) {
					$returnArray['archive_host'] = "wayback";
					$returnArray['archive_time'] =
						self::strtotime( $timestamp =
							                 $this->filterText( $returnArray['archive_template']['parameters']['wayback'],
							                                    true
							                 )
						);
					$returnArray['archive_url'] =
						"https://web.archive.org/web/$timestamp/$url";
				} elseif( isset( $returnArray['archive_template']['parameters']['webciteID'] ) ) {
					$timestamp = $this->filterText( $returnArray['archive_template']['parameters']['webciteID'], true );
					$returnArray['archive_url'] =
						"https://web.archive.org/web/$timestamp/$url";
					API::isArchive( $returnArray['archive_url'], $returnArray );
				} elseif( isset( $returnArray['archive_template']['parameters']['archive-is'] ) ) {
					$returnArray['archive_host'] = "archiveis";
					$returnArray['archive_time'] =
						self::strtotime( $timestamp =
							                 $this->filterText( $returnArray['archive_template']['parameters']['archive-is'],
							                                    true
							                 )
						);
					$returnArray['archive_url'] = "https://archive.is/$timestamp/$url";
				} elseif( isset( $returnArray['archive_template']['parameters']['archiv-url'] ) ) {
					if( !API::isArchive( $returnArray['archive_template']['parameters']['archiv-url'], $returnArray ) ) {
						$returnArray['archive_type'] = "invalid";
					}
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
						if( $params3[2] != "*" ) $returnArray['archive_time'] = strtotime( $params3[2] );
						else $returnArray['archive_time'] = "x";
						$returnArray['archive_url'] = "https://web." . $this->filterText( $params3[0], true );
					} else {
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
			if( empty( $returnArray['url'] ) ) {
				$returnArray['url'] = $returnArray['tag_template']['parameters']['url'];
				$returnArray['tag_type'] = "stray";
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
		$string = preg_replace( '/Januari/i', "January", $string );
		$string = preg_replace( '/Februari/i', "February", $string );
		$string = preg_replace( '/März/i', "March", $string );
		$string = preg_replace( '/April/i', "April", $string );
		$string = preg_replace( '/Mai/i', "May", $string );
		$string = preg_replace( '/Juni/i', "June", $string );
		$string = preg_replace( '/Juli/i', "July", $string );
		$string = preg_replace( '/August/i', "August", $string );
		$string = preg_replace( '/September/i', "September", $string );
		$string = preg_replace( '/Oktober/i', "October", $string );
		$string = preg_replace( '/November/i', "November", $string );
		$string = preg_replace( '/Dezember/i', "December", $string );

		return strtotime( $string );
	}
}