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
 * frwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * frwikiParser class
 * Extension of the master parser class specifically for fr.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class frwikiParser extends Parser {

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
		    preg_match( '/\d\d? (?:January|janvier|February|février|March|mars|April|avril|May|mai|June|juin|July|juillet|August|août|September|septembre|October|octobre|November|novembre|December|décembre) \d{4}/i',
		                $default
		    )
		) return '%-e %B %Y';
		elseif( !is_bool( $default ) &&
		        preg_match( '/(?:January|janvier|February|février|March|mars|April|avril|May|mai|June|juin|July|juillet|August|août|September|septembre|October|octobre|November|novembre|December|décembre) \d\d?\, \d{4}/i',
		                    $default
		        )
		) return '%B %-e, %Y';
		else return '%-e %B %Y';
	}

	/**
	 * Parses a given refernce/external link string and returns details about it.
	 *
	 * @param string $linkString Primary reference string
	 * @param string $remainder Left over stuff that may apply
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array    Details about the link
	 */
	public function getLinkDetails( $linkString, $remainder ) {
		$returnArray = parent::getLinkDetails($linkString, $remainder );

		if( isset( $returnArray['invalid_archive'] ) && $returnArray['archive_host'] == "wikiwix" ) {
			$returnArray['ignore_iarchive_flag'] = true;
		}

		return $returnArray;
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
			case "wayback":
				$link['newdata']['archive_template']['name'] = "Lien archive";
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['date'] =
					date( 'YmdHis', $temp['archive_time'] );
				else $link['newdata']['archive_template']['parameters']['date'] = "*";
				$link['newdata']['archive_template']['parameters']['titre'] = "Copie archivée";
				break;
			default:
				return false;
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
			$link['newdata']['tag_type'] = "parameter";
			$link['newdata']['link_template']['parameters']['brisé le'] = self::strtotime( '%-e %B %Y' );
		} else {
			$title = trim( str_replace( $link['original_url'] . (empty( $link['fragment'] ) === false ? "#" . $link['fragment'] : ""), "",
			                       $link['link_string']
			          ), " []"
			    );
			$link['newdata']['tag_type'] = "template-swallow";
			$link['newdata']['tag_template']['name'] = "lien brisé";
			$link['newdata']['tag_template']['parameters']['url'] = $link['url'];
			$link['newdata']['tag_template']['parameters']['titre'] = $title;
		}
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
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return void
	 */
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
		$notExists = !API::WikiwixExists( $link['original_url'] );
		if( $link['link_type'] == "template" && $notExists === true ) {
			$link['newdata']['archive_url'] = $temp['archive_url'];
			$link['newdata']['archive_time'] = $temp['archive_time'];
			if( !empty( $link['archive_fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['archive_fragment'];
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
			if( !empty( $link['archive_fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['archive_fragment'];
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

		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive_tags'] ), $remainder, $params2
		) ) {
			$returnArray['archive_type'] = "template";
			$returnArray['archive_template'] = [];
			$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
			$returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
			$returnArray['archive_template']['string'] = $params2[0];

			$returnArray['has_archive'] = true;

			//If there is a wayback tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive1_tags'] ), $remainder,
			                $params2
			) ) {
				$returnArray['archive_host'] = "wayback";

				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$url =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['url'],
						                                            true
						)
						);
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$url =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters'][1],
						                                            true
						)
						);
				} elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
					$url =
						htmlspecialchars_decode( $this->filterText( $returnArray['archive_template']['parameters']['site'],
						                                            true
						)
						);
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for archive timestamp.  If there isn't any, then it's not pointing a snapshot, which makes it harder for the reader and other editors.
				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] =
						self::strtotime( $timestamp =
							                 $this->filterText( $returnArray['archive_template']['parameters']['date'],
							                                    true
							                 )
						);
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
}