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
 * nlwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * nlwikiParser class
 * Extension of the master parser class specifically for nl.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class nlwikiParser extends Parser {

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
		if( !is_bool( $default ) &&
		        preg_match( '/\d\d? (?:January|januari|February|februari|March|maart|April|april|May|mei|June|juni|July|juli|August|augustus|September|september|October|oktober|November|november|December|december) \d{4}/i',
		                    $default
		        )
		) return '%-e %B %Y';
		elseif( !is_bool( $default ) &&
		        preg_match( '/(?:January|januari|February|februari|March|maart|April|april|May|mei|June|juni|July|juli|August|augustus|September|september|October|oktober|November|november|December|december) \d\d?\, \d{4}/i',
		                    $default
		        )
		) return '%B %-e, %Y';
		else return '%Y-%m-%d';
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
		return false;
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
			$link['newdata']['link_template']['parameters']['deadurl'] = "yes";
		} else {
			$link['newdata']['tag_type'] = "template";
			$link['newdata']['tag_template']['name'] = "dode link";
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
	 * Generate a string to replace the old string
	 *
	 * @param array $link Details about the new link including newdata being injected.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string New source string
	 */
	public function generateString( $link ) {
		$out = "";
		$multiline = false;
		if( $link['link_type'] != "reference" ) {
			if( strpos( $link[$link['link_type']]['link_string'], "\n" ) !== false ) $multiline = true;
			$mArray = Parser::mergeNewData( $link[$link['link_type']] );
			$tArray =
				array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
				             $this->commObject->config['ignore_tags']
				);
			$regex = $this->fetchTemplateRegex( $tArray );
			//Clear the existing archive, dead, and ignore tags from the remainder.
			//Why ignore?  It gives a visible indication that there's a bug in IABot.
			$remainder = preg_replace( $regex, "", $mArray['remainder'] );
			if( isset( $mArray['archive_string'] ) ) {
				$remainder =
					str_replace( $mArray['archive_string'], "", $remainder );
			}
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
			$offsetAdd = 0;
			foreach( $link['reference'] as $tid => $tlink ) {
				if( strpos( $tlink['link_string'], "\n" ) !== false ) $multiline = true;
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
					//For other archives that don't have archive templates or there is no suitable template, replace directly.
					if( $tlink['is_archive'] === false && $mArray['is_archive'] === true ) {
						$ttout = str_replace( $mArray['url'], $mArray['archive_url'], $ttout );
					}
				} //If handling a cite template...
				elseif( $mArray['link_type'] == "template" ) {
					//Build a clean cite template with the set parameters.
					$ttout .= "{{" . $mArray['link_template']['name'];
					foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
						if( $multiline === true ) $ttout .= "\n";
						$ttout .= "| $parameter = $value";
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
						foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
							$ttout .= "| $parameter = $value";
						}
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
							foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
								$tttout .= "| $parameter = $value";
							}
							$tttout .= "}}";
							if( isset( $mArray['archive_string'] ) ) {
								$ttout = str_replace( $mArray['archive_string'], trim( $tttout ), $ttout );
							} else {
								$ttout .= $tttout;
							}
						}
					}
					if( isset( $mArray['archive_string'] ) && $mArray['archive_type'] != "link" ) {
						$ttout =
							str_replace( $mArray['archive_string'], "", $ttout );
					}
				}
				//Search for source's entire string content, and replace it with the new string from the sub-sub-output buffer, and save it into the sub-output buffer.
				$tout =
					self::str_replace( $tlink['string'], $ttout, $tout, $count, 1, $tlink['offset'] + $offsetAdd );
				$offsetAdd += strlen( $ttout ) - strlen( $tlink['string'] );
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
			if( $link['link_type'] == "template" ) {
				$out .= "{{" . $link['template']['name'];
			} elseif( $link['link_type'] == "stray" ) $out .= "{{" . $mArray['link_template']['name'];
			foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
				if( $multiline === true ) $out .= "\n";
				$out .= "| $parameter = $value";
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
				if( isset( $mArray['old_archive'] ) ) {
					$out =
						str_replace( $mArray['old_archive'], $mArray['archive_url'], $out );
				} else $out = str_replace( $mArray['url'], $mArray['archive_url'], $out );
			} elseif( $mArray['archive_type'] == "template" ) {
				$out .= " {{" . $mArray['archive_template']['name'];
				foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
					$out .= "| $parameter = $value";
				}
				$out .= "}}";
			}
		}

		return $out;
	}
}