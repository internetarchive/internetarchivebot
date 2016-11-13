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

/**
 * @file
 * enwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2016, Maximilian Doerr
 */

/**
 * enwikiParser class
 * Extension of the master parser class specifically for en.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2016, Maximilian Doerr
 */
class svwikiParser extends Parser {

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
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return string Format to be fed in time()
	 */
	protected function retrieveDateFormat( $default = false ) {
		if( !is_bool( $default ) &&
		    preg_match( '/\d\d? (?:Januari|Februari|Mars|April|Maj|Juni|Juli|Augusti|September|Oktober|November|December) \d{4}/i',
		                $default
		    )
		) return 'j F Y';
		elseif( !is_bool( $default ) &&
		        preg_match( '/(?:Januari|Februari|Mars|April|Maj|Juni|Juli|Augusti|September|Oktober|November|December) \d\d?\, \d{4}/i',
		                    $default
		        )
		) return 'F j, Y';
		else return 'o-m-d';
	}

	/**
	 * Rescue a link
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links that were modified
	 * @param array $temp Cached result value from archive retrieval function
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return void
	 */
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
		//The initial assumption is that we are adding an archive to a URL.
		$modifiedLinks["$tid:$id"]['type'] = "addarchive";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

		//The newdata index is all the data being injected into the link array.  This allows for the preservation of the old data for easier manipulation and maintenance.
		$link['newdata']['has_archive'] = true;
		$link['newdata']['archive_url'] = $temp['archive_url'];
		if( isset( $link['fragment'] ) || !is_null( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" .
		                                                                                                     $link['fragment'];
		$link['newdata']['archive_time'] = $temp['archive_time'];
		//If we are dealing with an external link, or a stray archive template, then...
		if( $link['link_type'] == "link" || $link['link_type'] == "stray" ) {
			//If it is plain URL with no embedded text if it's in brackets, or is a stray archive template, then convert it to a citation template.
			//Else attach an archive template to it.
			if( trim( $link['link_string'], " []" ) == $link['url'] || $link['link_type'] == "stray" ) {
				$link['newdata']['archive_type'] = "parameter";
				$link['newdata']['link_template']['name'] = "Webbref";
				//Correct mixed encoding in URLs by fulling decoding everything, and then encoding the query segment.
				$link['newdata']['link_template']['parameters']['url'] =
					str_replace( parse_url( $link['url'], PHP_URL_QUERY ),
					             urlencode( urldecode( parse_url( $link['url'], PHP_URL_QUERY ) ) ), $link['url']
					);
				//If we are dealing with a stray archive template, try and copy the contents of its title parameter to the new citation template.
				if( $link['link_type'] == "stray" && ( isset( $link['archive_template']['parameters']['titel'] ) ||
				                                       isset( $link['archive_template']['parameters']['title'] ) )
				) {
					if( isset( $link['archive_template']['parameters']['titel'] ) )
						$link['newdata']['link_template']['parameters']['titel'] =
							$link['archive_template']['parameters']['titel'];
					else $link['newdata']['link_template']['parameters']['titel'] =
						$link['archive_template']['parameters']['title'];
				} else $link['newdata']['link_template']['parameters']['titel'] = "Archived copy";
				//We need to define the access date.
				$link['newdata']['link_template']['parameters']['hämtdatum'] =
					date( $this->retrieveDateFormat( true ), $link['access_time'] );
				//Let this function handle the rest.
				$this->generateNewCitationTemplate( $link, $temp );

				//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
				if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
					if( !isset( $link['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] =
						$link['url'];
					else $link['newdata']['link_template']['parameters']['url'] = $link['template_url'];
					$modifiedLinks["$tid:$id"]['type'] = "fix";
				}
				//Force change the link type to a template.  This part is not within the scope of the array merger, as it's too high level.
				$link['link_type'] = "template";
			} else {
				if( $this->generateNewArchiveTemplate( $link, $temp ) ) {
					$link['newdata']['archive_type'] = "template";
					$link['newdata']['tagged_dead'] = false;
				}
			}
		} elseif( $link['link_type'] == "template" ) {
			//Since we already have a template, let this function make the needed modifications.
			$this->generateNewCitationTemplate( $link, $temp );

			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
			) {
				if( !isset( $link['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] =
					$link['url'];
				else $link['newdata']['link_template']['parameters']['url'] = $link['template_url'];
				$modifiedLinks["$tid:$id"]['type'] = "fix";
			}
		}
		//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
		if( isset( $link['convert_archive_url'] ) ||
		    ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
		    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
		) {
			$modifiedLinks["$tid:$id"]['type'] = "fix";
		}
		//If we ended up changing the archive URL despite invalid flags, we should mention that change instead.
		if( $link['has_archive'] === true && $link['archive_url'] != $temp['archive_url'] &&
		    !isset( $link['convert_archive_url'] )
		) {
			$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
			$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
		}
		unset( $temp );
	}

	/**
	 * Generates an appropriate citation template without altering existing parameters.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewCitationTemplate( &$link, &$temp ) {
		$link['newdata']['archive_type'] = "parameter";
		//Set the archive URL
		if( isset( $link['link_template']['parameters']['accessdate'] ) )
			$link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
		else $link['newdata']['link_template']['parameters']['arkivurl'] = $temp['archive_url'];

		//Set the archive date
		if( !isset( $link['link_template']['parameters']['arkivdatum'] ) &&
		    !isset( $link['link_template']['parameters']['archivedate'] )
		) {
			if( isset( $link['link_template']['parameters']['hämtdatum'] ) )
				$link['newdata']['link_template']['parameters']['arkivdatum'] =
					date( $this->retrieveDateFormat( $link['link_template']['parameters']['hämtdatum'] ),
					      $temp['archive_time']
					);
			elseif( isset( $link['link_template']['parameters']['accessdate'] ) )
				$link['newdata']['link_template']['parameters']['archivedate'] =
					date( $this->retrieveDateFormat( $link['link_template']['parameters']['accessdate'] ),
					      $temp['archive_time']
					);
			else $link['newdata']['link_template']['parameters']['arkivdatum'] =
				date( $this->retrieveDateFormat(), $temp['archive_time'] );
		} else {
			if( isset( $link['newdata']['link_template']['parameters']['arkivdatum'] ) )
				$link['newdata']['link_template']['parameters']['arkivdatum'] =
					date( $this->retrieveDateFormat( $link['newdata']['link_template']['parameters']['arkivdatum'] ),
					      $temp['archive_time']
					);
			else $link['newdata']['link_template']['parameters']['archivedate'] =
				date( $this->retrieveDateFormat( $link['newdata']['link_template']['parameters']['archivedate'] ),
				      $temp['archive_time']
				);
		}
	}

	/**
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
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
				$link['newdata']['archive_template']['name'] = "Wayback";
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['date'] =
					date( 'YmdHis', $temp['archive_time'] );
				else $link['newdata']['archive_template']['parameters']['date'] = "*";
				break;
			case "webcite":
				$link['newdata']['archive_template']['name'] = "WebCite";
				$link['newdata']['archive_template']['parameters']['url'] = $temp['archive_url'];
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['date'] =
					date( 'YmdHis', $temp['archive_time'] );
				switch( $this->retrieveDateFormat() ) {
					case 'F j Y':
						$link['newdata']['archive_template']['parameters']['dateformat'] = "mdy";
						break;
					case 'j F Y':
						$link['newdata']['archive_template']['parameters']['dateformat'] = "dmy";
						break;
					default:
						$link['newdata']['archive_template']['parameters']['dateformat'] = "iso";
						break;
				}
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
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return void
	 */
	protected function noRescueLink( &$link, &$modifiedLinks, $tid, $id ) {
		$modifiedLinks["$tid:$id"]['type'] = "tagged";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		$link['newdata']['tag_type'] = "template";
		$link['newdata']['tag_template']['name'] = "död länk";
		$link['newdata']['tag_template']['parameters']['datum'] = date( 'Y-m' );
		$link['newdata']['tag_template']['parameters']['bot'] = USERNAME;
	}

	/**
	 * Analyze the citation template
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $params Citation template regex match breakdown
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeCitation( &$returnArray, &$params ) {
		$returnArray['tagged_dead'] = false;
		$returnArray['link_type'] = "template";
		$returnArray['link_template'] = [];
		$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
		$returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
		$returnArray['link_template']['string'] = $params[0];
		//If we can't get a URL, then this is useless.  Discontinue analysis and move on.
		if( isset( $returnArray['link_template']['parameters']['url'] ) &&
		    !empty( $returnArray['link_template']['parameters']['url'] )
		) $returnArray['url'] = $this->filterText( $returnArray['link_template']['parameters']['url'] );
		if( isset( $returnArray['link_template']['parameters']['website'] ) &&
		    !empty( $returnArray['link_template']['parameters']['website'] )
		) $returnArray['url'] = $this->filterText( $returnArray['link_template']['parameters']['website'] );
		else return false;
		//Fetch the access date.  Use the wikitext resolver in case a date template is being used.
		if( isset( $returnArray['link_template']['parameters']['accessdate'] ) &&
		    !empty( $returnArray['link_template']['parameters']['accessdate'] )
		) {
			$time = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
			if( $time === false ) $time =
				strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['accessdate'] ) );
			if( $time === false ) $time = "x";
			$returnArray['access_time'] = $time;
		} elseif( isset( $returnArray['link_template']['parameters']['hämtdatum'] ) &&
		          !empty( $returnArray['link_template']['parameters']['hämtdatum'] )
		) {
			$time = strtotime( $returnArray['link_template']['parameters']['hämtdatum'] );
			if( $time === false ) $time =
				strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['hämtdatum'] ) );
			if( $time === false ) $time = "x";
			$returnArray['access_time'] = $time;
		} else $returnArray['access_time'] = "x";
		//Check for the presence of an archive URL
		if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) &&
		    !empty( $returnArray['link_template']['parameters']['archiveurl'] )
		) $returnArray['archive_url'] = $this->filterText( $returnArray['link_template']['parameters']['archiveurl'] );
		if( isset( $returnArray['link_template']['parameters']['arkivdatum'] ) &&
		    !empty( $returnArray['link_template']['parameters']['arkivurl'] )
		) $returnArray['archive_url'] = $this->filterText( $returnArray['link_template']['parameters']['arkivurl'] );
		if( ( isset( $returnArray['link_template']['parameters']['archiveurl'] ) &&
		      !empty( $returnArray['link_template']['parameters']['archiveurl'] ) ) ||
		    ( isset( $returnArray['link_template']['parameters']['arkivurl'] ) &&
		      !empty( $returnArray['link_template']['parameters']['arkivurl'] ) )
		) {
			$returnArray['archive_type'] = "parameter";
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$this->commObject->isArchive( $returnArray['archive_url'], $returnArray );
		}
		//Check for the presence of an archive date, resolving date templates as needed.
		if( isset( $returnArray['link_template']['parameters']['archivedate'] ) &&
		    !empty( $returnArray['link_template']['parameters']['archivedate'] )
		) {
			$time = strtotime( $returnArray['link_template']['parameters']['archivedate'] );
			if( $time === false ) $time =
				strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['archivedate'] ) );
			if( $time === false ) $time = "x";
			$returnArray['archive_time'] = $time;
		}
		if( isset( $returnArray['link_template']['parameters']['arkivdatum'] ) &&
		    !empty( $returnArray['link_template']['parameters']['arkivdatum'] )
		) {
			$time = strtotime( $returnArray['link_template']['parameters']['arkivdatum'] );
			if( $time === false ) $time =
				strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['arkivdatum'] ) );
			if( $time === false ) $time = "x";
			$returnArray['archive_time'] = $time;
		}
		//Using an archive URL in the url field is not correct.  Flag as invalid usage if the URL is an archive.
		if( $this->commObject->isArchive( $returnArray['url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "invalid";

			if( !isset( $returnArray['link_template']['parameters']['accessdate'] ) &&
			    !isset( $returnArray['link_template']['parameters']['hämtdatum'] ) && $returnArray['access_time'] != "x"
			) $returnArray['access_time'] = $returnArray['archive_time'];
			else {
				if( isset( $returnArray['link_template']['parameters']['accessdate'] ) &&
				    !empty( $returnArray['link_template']['parameters']['accessdate'] ) &&
				    $returnArray['access_time'] != "x"
				) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
				elseif( isset( $returnArray['link_template']['parameters']['hämtdatum'] ) &&
				        !empty( $returnArray['link_template']['parameters']['hämtdatum'] ) &&
				        $returnArray['access_time'] != "x"
				) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['hämtdatum'] );
				else $returnArray['access_time'] = "x";
			}
		}

		//Check if this URL is lingering behind a paywall.
		if( isset( $returnArray['link_template']['parameters']['registration'] ) ||
		    isset( $returnArray['link_template']['parameters']['subscription'] )
		) {
			$returnArray['tagged_paywall'] = true;
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
	 * @copyright Copyright (c) 2016, Maximilian Doerr
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
			if( $returnArray['has_archive'] === true ) {
				$returnArray['archive_type'] = "invalid";
			} else {
				$returnArray['has_archive'] = true;
				$returnArray['is_archive'] = false;
			}
			//Same here.
			if( $returnArray['link_type'] == "template" ) {
				$returnArray['archive_type'] = "invalid";
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "implied";
			}

			//If there is a wayback tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['wayback_tags'] ), $remainder, $params2
			) ) {
				$returnArray['archive_host'] = "wayback";

				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$url = $returnArray['archive_template']['parameters']['url'];
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$url = $returnArray['archive_template']['parameters'][1];
				} elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
					$url = $returnArray['archive_template']['parameters']['site'];
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for archive timestamp.  If there isn't any, then it's not pointing a snapshot, which makes it harder for the reader and other editors.
				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
					$returnArray['archive_url'] =
						"https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/$url";
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
						if( $params3[2] != "*" ) $returnArray['archive_time'] = strtotime( $params3[2] );
						else $returnArray['archive_time'] = "x";
						$returnArray['archive_url'] = "https://web." . $params3[0];
					} else {
						$returnArray['archive_type'] = "invalid";
					}
				}
			}

			//If there is a webcite tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['webcite_tags'] ), $remainder, $params2
			) ) {
				$returnArray['archive_host'] = "webcite";
				//Look for the URL.  If there isn't any found, the template is being used wrong.
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$returnArray['archive_url'] = $returnArray['archive_template']['parameters']['url'];
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$returnArray['archive_url'] = $returnArray['archive_template']['parameters'][1];
				} else {
					$returnArray['archive_url'] = "x";
					$returnArray['archive_type'] = "invalid";
				}

				//Look for the archive timestamp.  Since the Webcite archives use a unique URL for each snapshot, a missing date stamp does not mean invalid usage.
				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
				} else {
					$returnArray['archive_time'] = "x";
				}

				//If the original URL isn't present, then we are dealing with a stray archive template.
				if( !isset( $returnArray['url'] ) ) {
					//resolve the archive to the original URL
					$this->commObject->isArchive( $returnArray['archive_url'], $returnArray );
					$returnArray['archive_type'] = "invalid";
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
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
			// isset( $returnArray['tag_template']['parameters']['fix-attempted'] ) ) $returnArray['permanent_dead'] = true;
			$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
			$returnArray['tag_template']['string'] = $params2[0];
		}
	}

	/**
	 * Generate a string to replace the old string
	 *
	 * @param array $link Details about the new link including newdata being injected.
	 *
	 * @access public
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return string New source string
	 */
	public function generateString( $link ) {
		$out = "";
		$multiline = false;
		if( strpos( $link['string'], "\n" ) !== false ) $multiline = true;
		if( $link['link_type'] != "reference" ) {
			$mArray = Parser::mergeNewData( $link[$link['link_type']] );
			$tArray =
				array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
				             $this->commObject->config['ignore_tags']
				);
			$regex = $this->fetchTemplateRegex( $tArray );
			//Clear the existing archive, dead, and ignore tags from the remainder.
			//Why ignore?  It gives a visible indication that there's a bug in IABot.
			$remainder = preg_replace( $regex, "", $mArray['remainder'] );
			if( isset( $mArray['archive_string'] ) ) $remainder =
				str_replace( $mArray['archive_string'], "", $remainder );
		}
		//Beginning of the string
		//For references...
		if( $link['link_type'] == "reference" ) {
			//Build the opening reference tag with parameters, when dealing with references.
			$out .= "<ref";
			if( isset( $link['reference']['parameters'] ) ) {
				foreach( $link['reference']['parameters'] as $parameter => $value ) {
					$out .= " $parameter=$value";
				}
				unset( $link['reference']['parameters'] );
			}
			$out .= ">";
			//Store the original link string in sub output buffer.
			$tout = $link['reference']['link_string'];
			//Delete it, to avoid confusion when processing the array.
			unset( $link['reference']['link_string'] );
			//Process each individual source in the reference
			foreach( $link['reference'] as $tid => $tlink ) {
				//Create an sub-sub-output buffer.
				$ttout = "";
				//If the ignore tag is set on this specific source, move on to the next.
				if( isset( $tlink['ignore'] ) && $tlink['ignore'] === true ) continue;
				if( !is_int( $tid ) ) continue;
				//Merge the newdata index with the link array.
				$mArray = Parser::mergeNewData( $tlink );
				$tArray =
					array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
					             $this->commObject->config['ignore_tags']
					);
				$regex = $this->fetchTemplateRegex( $tArray );
				//Clear the existing archive, dead, and ignore tags from the remainder.
				//Why ignore?  It gives a visible indication that there's a bug in IABot.
				$remainder = preg_replace( $regex, "", $mArray['remainder'] );
				//If handling a plain link, or a plain archive link...
				if( $mArray['link_type'] == "link" ||
				    ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" )
				) {
					//Store source link string into sub-sub-output buffer.
					$ttout .= $mArray['link_string'];
				} //If handling a cite template...
				elseif( $mArray['link_type'] == "template" ) {
					//Build a clean cite template with the set parameters.
					$ttout .= "{{" . $mArray['link_template']['name'];
					foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
						if( $multiline === true ) $ttout .= "\n ";
						$ttout .= "|$parameter=$value ";
					}
					//Use multiline if detected in the original string.
					if( $multiline === true ) $ttout .= "\n";
					$ttout .= "}}";
				}
				//If the detected archive is invalid, replace with the original URL.
				if( $mArray['is_archive'] === true && isset( $mArray['invalid_archive'] ) ) {
					$ttout = str_replace( $mArray['iarchive_url'], $mArray['url'], $ttout );
				}
				//If tagged dead, and set as a template, add tag.
				if( $mArray['tagged_dead'] === true ) {
					if( $mArray['tag_type'] == "template" ) {
						$ttout .= "{{" . $mArray['tag_template']['name'];
						foreach( $mArray['tag_template']['parameters'] as $parameter => $value )
							$ttout .= "|$parameter=$value ";
						$ttout .= "}}";
					}
				}
				//Attach the cleaned remainder.
				$ttout .= $remainder;
				//Attach archives as needed
				if( $mArray['has_archive'] === true ) {
					//For archive templates.
					if( $mArray['archive_type'] == "template" ) {
						if( $tlink['has_archive'] === true && $tlink['archive_type'] == "link" ) {
							$ttout = str_replace( $mArray['old_archive'], $mArray['archive_url'], $ttout );
						} else {
							$tttout = " {{" . $mArray['archive_template']['name'];
							foreach( $mArray['archive_template']['parameters'] as $parameter => $value )
								$tttout .= "|$parameter=$value ";
							$tttout .= "}}";
							if( isset( $mArray['archive_string'] ) ) {
								$ttout = str_replace( $mArray['archive_string'], trim( $tttout ), $ttout );
							} else {
								$ttout .= $tttout;
							}
						}
					}
					if( isset( $mArray['archive_string'] ) && $mArray['archive_type'] != "link" ) $ttout =
						str_replace( $mArray['archive_string'], "", $ttout );
				}
				//Search for source's entire string content, and replace it with the new string from the sub-sub-output buffer, and save it into the sub-output buffer.
				$tout = str_replace( $tlink['string'], $ttout, $tout );
			}

			//Attach contents of sub-output buffer, to main output buffer.
			$out .= $tout;
			//Close reference.
			$out .= "</ref>";

			return $out;

		} elseif( $link['link_type'] == "externallink" ) {
			//Attach the external link string to the output buffer.
			$out .= $link['externallink']['link_string'];
		} elseif( $link['link_type'] == "template" || $link['link_type'] == "stray" ) {
			//Create a clean cite template
			if( $link['link_type'] == "template" ) $out .= "{{" . $link['template']['name'];
			elseif( $link['link_type'] == "stray" ) $out .= "{{" . $mArray['link_template']['name'];
			foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
				if( $multiline === true ) $out .= "\n ";
				$out .= "|$parameter=$value ";
			}
			if( $multiline === true ) $out .= "\n";
			$out .= "}}";
		}
		//Add dead link tag if needed.
		if( $mArray['tagged_dead'] === true ) {
			if( $mArray['tag_type'] == "template" ) {
				$out .= "{{" . $mArray['tag_template']['name'];
				foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
				$out .= "}}";
			}
		}
		//Add remainder
		$out .= $remainder;
		//Add the archive if needed.
		if( $mArray['has_archive'] === true ) {
			if( $link['link_type'] == "externallink" ) {
				if( isset( $mArray['old_archive'] ) ) $out =
					str_replace( $mArray['old_archive'], $mArray['archive_url'], $out );
				else $out = str_replace( $mArray['url'], $mArray['archive_url'], $out );
			} elseif( $mArray['archive_type'] == "template" ) {
				$out .= " {{" . $mArray['archive_template']['name'];
				foreach( $mArray['archive_template']['parameters'] as $parameter => $value )
					$out .= "|$parameter=$value ";
				$out .= "}}";
			}
		}

		return $out;
	}

}