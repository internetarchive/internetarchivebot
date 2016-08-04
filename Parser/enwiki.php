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
class enwikiParser extends Parser {

	/**
	* Get page date formatting standard
	*
	* @param bool $default Return default format.
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string Format to be fed in time()
	*/
	protected function retrieveDateFormat( $default = false ) {
		if( !$default && preg_match( '/\{\{(use)?\s?dmy\s?(dates)?/i', $this->commObject->content ) ) return 'j F Y';
		elseif( !$default && preg_match( '/\{\{(use)?\s?mdy\s?(dates)?/i', $this->commObject->content ) ) return 'F j, Y';
		else return 'o-m-d';
	}

	/**
	* Rescue a link
	*
	* @param array $link Link being analyzed
	* @param array $modifiedLinks Links that were modified
	* @param array $temp Cached result value from archive retrieval function
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
		$modifiedLinks["$tid:$id"]['type'] = "addarchive";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];
		if( $link['has_archive'] === true ) {
			$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
			$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
		}
		$link['newdata']['has_archive'] = true;
		$link['newdata']['archive_url'] = $temp['archive_url'];
		if( isset( $link['fragment'] ) || !is_null( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#".$link['fragment'];
		$link['newdata']['archive_time'] = $temp['archive_time'];
		if( $link['link_type'] == "link" || $link['link_type'] == "stray" ) {
			if( trim( $link['link_string'], " []" ) == $link['url'] || $link['link_type'] == "stray" ) {
				$link['newdata']['archive_type'] = "parameter";
				$link['newdata']['link_template']['name'] = "cite web";
				$link['newdata']['link_template']['parameters']['url'] = str_replace( parse_url($link['url'], PHP_URL_QUERY), urlencode( urldecode( parse_url($link['url'], PHP_URL_QUERY) ) ), $link['url'] ) ;
                if( $link['link_type'] == "stray" && isset( $link['archive_template']['parameters']['title'] ) ) $link['newdata']['link_template']['parameters']['title'] = $link['archive_template']['parameters']['title'];
                else $link['newdata']['link_template']['parameters']['title'] = "Archived copy";
                $link['newdata']['link_template']['parameters']['accessdate'] = date( $this->retrieveDateFormat( true ), $link['access_time'] );
				if( !isset( $link['link_template']['parameters']['df'] ) ) {
					switch( $this->retrieveDateFormat() ) {
						case 'j F Y':
							$link['newdata']['link_template']['parameters']['df'] = "dmy";
							break;
						case 'F j, Y':
							$link['newdata']['link_template']['parameters']['df'] = "mdy";
							break;
						default:
							$link['newdata']['link_template']['parameters']['df'] = "iso";
							break;
					}
				}
				if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
				else $link['newdata']['tagged_dead'] = false;
				$link['newdata']['tag_type'] = "parameter";
				if( $link['link_type'] == "stray" ) {
					if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "bot: unknown";
					else $link['newdata']['link_template']['parameters']['dead-url'] = "bot: unknown";
				} elseif( $link['tagged_dead'] === true || $link['is_dead'] === true ) {
					if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
					else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
				} else {
					if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
					else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
				}
				if( !isset( $link['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
				else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];

				if( !isset( $link['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( $this->retrieveDateFormat( true ), $temp['archive_time'] );
				else $link['newdata']['link_template']['parameters']['archive-date'] = date( $this->retrieveDateFormat( true ), $temp['archive_time'] );

				if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
					if( !isset( $link['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] = $link['url'];
					else $link['newdata']['link_template']['parameters']['url'] = $link['template_url'];
					$modifiedLinks["$tid:$id"]['type'] = "fix";
				}
				$link['link_type'] = "template";
			} else {
				if( $this->generateNewArchiveTemplate( $link, $temp ) ) {
					$link['newdata']['archive_type'] = "template";
					$link['newdata']['tagged_dead'] = false;
				}
			}
		} elseif( $link['link_type'] == "template" ) {
			$link['newdata']['archive_type'] = "parameter";
			if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
			else $link['newdata']['tagged_dead'] = false;
			$link['newdata']['tag_type'] = "parameter";
			if( ($link['tagged_dead'] === true || $link['is_dead'] === true) &&
				( $link['has_archive'] === false || $link['archive_type'] != "invalid" || ( $link['has_archive'] === true && $link['archive_type'] === "invalid" && isset($link['archive_template']) ) ) ) {
				if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
				else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
			} elseif( ($link['tagged_dead'] === true || $link['is_dead'] === true) && ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ) {
				if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "bot: unknown";
				else $link['newdata']['link_template']['parameters']['dead-url'] = "bot: unknown";
			} else {
				if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
				else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
			}
			if( !isset( $link['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
			else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];

			if( !isset( $link['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( $this->retrieveDateFormat( true ), $temp['archive_time'] );
			else $link['newdata']['link_template']['parameters']['archive-date'] = date( $this->retrieveDateFormat( true ), $temp['archive_time'] );

			if( !isset( $link['link_template']['parameters']['df'] ) ) {
				switch( $this->retrieveDateFormat() ) {
					case 'j F Y':
						$link['newdata']['link_template']['parameters']['df'] = "dmy";
						break;
					case 'F j, Y':
						$link['newdata']['link_template']['parameters']['df'] = "mdy";
						break;
					default:
						$link['newdata']['link_template']['parameters']['df'] = "iso";
						break;
				}
			}

			if( ($link['has_archive'] === true && $link['archive_type'] == "invalid") || ($link['tagged_dead'] === true && $link['tag_type'] == "invalid") ) {
				if( !isset( $link['template_url'] ) ) $link['newdata']['link_template']['parameters']['url'] = $link['url'];
				else $link['newdata']['link_template']['parameters']['url'] = $link['template_url'];
				$modifiedLinks["$tid:$id"]['type'] = "fix";
			}
		}
		if( ($link['has_archive'] === true && $link['archive_type'] == "invalid") || ($link['tagged_dead'] === true && $link['tag_type'] == "invalid") ) {
			$modifiedLinks["$tid:$id"]['type'] = "fix";
		}
		unset( $temp );
	}

	/**
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 * @return bool If successful or not
	 */
	protected function generateNewArchiveTemplate( &$link, &$temp ) {
		if( !isset( $link['newdata']['archive_host'] ) ) $link['newdata']['archive_host'] = $this->getArchiveHost( $temp['archive_url'] );
		if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) unset( $link['archive_template']['parameters'] );
		switch( $link['newdata']['archive_host'] ) {
			case "memento":
			case "wayback":
				$link['newdata']['archive_template']['name'] = $link['newdata']['archive_host'];
				$link['newdata']['archive_template']['parameters']['url'] = $link['url'];
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
				else $link['newdata']['archive_template']['parameters']['date'] = "*";
				if( $this->retrieveDateFormat() == 'j F Y' ) $link['newdata']['archive_template']['parameters']['df'] = "y";
				break;
			case "webcite":
				$link['newdata']['archive_template']['name'] = "webcite";
				$link['newdata']['archive_template']['parameters']['url'] = $temp['archive_url'];
				if( $temp['archive_time'] != 0 ) $link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
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
		if( $link['link_type'] == "link" ) {
			$link['newdata']['tag_type'] = "template";
			$link['newdata']['tag_template']['name'] = "dead link";
			$link['newdata']['tag_template']['parameters']['date'] = date( 'F Y' );
			$link['newdata']['tag_template']['parameters']['bot'] = USERNAME;
			$link['newdata']['tag_template']['parameters']['fix-attempted'] = 'yes';
		} elseif( $link['link_type'] == "template" ) {
			$link['newdata']['tag_type'] = "parameter";
			if( !isset( $link['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
			else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
		}
	}

	/**
	* Analyze the citation template
	*
	* @param array $returnArray Array being generated in master function
	* @param string $params Citation template regex match breakdown
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
		$returnArray['link_template'] = array();
		$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
		$returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
		$returnArray['link_template']['string'] = $params[0];
		if( isset( $returnArray['link_template']['parameters']['url'] ) && !empty( $returnArray['link_template']['parameters']['url'] ) ) $returnArray['url'] = $returnArray['link_template']['parameters']['url'];
		else return false;
		if( isset( $returnArray['link_template']['parameters']['accessdate']) && !empty( $returnArray['link_template']['parameters']['accessdate'] ) ) {
			$time = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
			if( $time === false ) $time = strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['accessdate'] ) );
			if( $time === false ) $time = "x";
			$returnArray['access_time'] = $time;
		}
		elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) && !empty( $returnArray['link_template']['parameters']['access-date'] ) ) {
			$time = strtotime( $returnArray['link_template']['parameters']['access-date'] );
			if( $time === false ) $time = strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['access-date'] ) );
			if( $time === false ) $time = "x";
			$returnArray['access_time'] = $time;
		}
		else $returnArray['access_time'] = "x";
		if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) && !empty( $returnArray['link_template']['parameters']['archiveurl'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archiveurl'];
		if( isset( $returnArray['link_template']['parameters']['archive-url'] ) && !empty( $returnArray['link_template']['parameters']['archive-url'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archive-url'];
		if( (isset( $returnArray['link_template']['parameters']['archiveurl'] ) && !empty( $returnArray['link_template']['parameters']['archiveurl'] )) || (isset( $returnArray['link_template']['parameters']['archive-url'] ) && !empty( $returnArray['link_template']['parameters']['archive-url'] )) ) {
			$returnArray['archive_type'] = "parameter";
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_host'] = $this->getArchiveHost( $returnArray['archive_url'] );
		}
		if( isset( $returnArray['link_template']['parameters']['archivedate'] ) && !empty( $returnArray['link_template']['parameters']['archivedate'] ) ) {
			$time = strtotime( $returnArray['link_template']['parameters']['archivedate'] );
			if( $time === false ) $time = strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['archivedate'] ) );
			if( $time === false ) $time = "x";
			$returnArray['archive_time'] = $time;
		}
		if( isset( $returnArray['link_template']['parameters']['archive-date'] ) && !empty( $returnArray['link_template']['parameters']['archive-date'] ) ) {
			$time = strtotime( $returnArray['link_template']['parameters']['archive-date'] );
			if( $time === false ) $time = strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['archive-date'] ) );
			if( $time === false ) $time = "x";
			$returnArray['archive_time'] = $time;
		}
		if( ( isset( $returnArray['link_template']['parameters']['deadurl'] ) && $returnArray['link_template']['parameters']['deadurl'] == "yes" ) || ( ( isset( $returnArray['link_template']['parameters']['dead-url'] ) && $returnArray['link_template']['parameters']['dead-url'] == "yes" ) ) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "parameter";
		}
		if( $this->isArchive( $returnArray['url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "invalid";

			if( !isset( $returnArray['link_template']['parameters']['accessdate'] ) && !isset( $returnArray['link_template']['parameters']['access-date'] ) && $returnArray['access_time'] != "x" ) $returnArray['access_time'] = $returnArray['archive_time'];
			else {
				if( isset( $returnArray['link_template']['parameters']['accessdate'] ) && !empty( $returnArray['link_template']['parameters']['accessdate'] ) && $returnArray['access_time'] != "x" ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
				elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) && !empty( $returnArray['link_template']['parameters']['access-date'] ) && $returnArray['access_time'] != "x" ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
				else $returnArray['access_time'] = "x";
			}
		}

		if( isset( $returnArray['link_template']['parameters']['registration'] ) || isset( $returnArray['link_template']['parameters']['subscription'] ) ) {
			$returnArray['tagged_paywall'] = true;
		}
	}

	/**
	* Analyze the remainder string
	*
	* @param array $returnArray Array being generated in master function
	* @param string $remainder Remainder string
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected function analyzeRemainder( &$returnArray, &$remainder ) {
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive_tags'] ), $remainder, $params2 ) ) {
			$returnArray['archive_type'] = "template";
			$returnArray['archive_template'] = array();
			$returnArray['archive_template']['parameters'] = $this->getTemplateParameters($params2[2]);
			$returnArray['archive_template']['name'] = str_replace("{{", "", $params2[1]);
			$returnArray['archive_template']['string'] = $params2[0];
			if( $returnArray['has_archive'] === true ) {
				$returnArray['archive_type'] = "invalid";
			} else {
				$returnArray['has_archive'] = true;
				$returnArray['is_archive'] = false;
			}
			if( $returnArray['link_type'] == "template" ) {
			    $returnArray['archive_type'] = "invalid";
                $returnArray['tagged_dead'] = true;
                $returnArray['tag_type'] = "implied";
            }

			//If there is a wayback tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['wayback_tags'] ), $remainder, $params2 ) ) {
				$returnArray['archive_host'] = "wayback";

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

				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
					$returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/$url";
				} else {
					$returnArray['archive_time'] = "x";
					$returnArray['archive_url'] = "https://web.archive.org/web/*/$url";
					$returnArray['archive_type'] = "invalid";
				}

				if( !isset( $returnArray['url'] ) ) {
					$returnArray['archive_type'] = "invalid";
					$returnArray['url'] = $url;
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
				}

				//Check for a malformation or template misuse.
				if( $returnArray['archive_url'] == "x" || strpos( $url, "archive.org" ) !== false ) {
					if( preg_match( '/archive\.org\/(web\/)?(\d*?|\*)\/(\S*)\s?/i', $url, $params3 ) ) {
						$returnArray['archive_type'] = "invalid";
						if( $params3[2] != "*" ) $returnArray['archive_time'] = strtotime( $params3[2] );
						else $returnArray['archive_time'] = "x";
						$returnArray['archive_url'] = "https://web.".$params3[0];
					} else {
						$returnArray['archive_type'] = "invalid";
					}
				}
			}

			//If there is a webcite tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['webcite_tags'] ), $remainder, $params2 ) ) {
				$returnArray['archive_host'] = "webcite";
				if( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
					$returnArray['archive_url'] = $returnArray['archive_template']['parameters']['url'];
				} elseif( isset( $returnArray['archive_template']['parameters'][1] ) ) {
					$returnArray['archive_url'] = $returnArray['archive_template']['parameters'][1];
				}

				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
				} else {
					$returnArray['archive_time'] = "x";
				}

				if( !isset( $returnArray['url'] ) ) {
					//resolve the archive to the original URL
					$this->isArchive( $returnArray['archive_url'], $returnArray );
					$returnArray['archive_type'] = "invalid";
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
				}
			}

			//If there is a wayback tag present, process it
			if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['memento_tags'] ), $remainder, $params2 ) ) {
				$returnArray['archive_host'] = "memento";

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

				if( isset( $returnArray['archive_template']['parameters']['date'] ) ) {
					$returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
					$returnArray['archive_url'] = "https://timetravel.mementoweb.org/memento/{$returnArray['archive_template']['parameters']['date']}/$url";
				} else {
					$returnArray['archive_time'] = "x";
					$returnArray['archive_url'] = "https://timetravel.mementoweb.org/memento/*/$url";
					$returnArray['archive_type'] = "invalid";
				}

				if( !isset( $returnArray['url'] ) ) {
					$returnArray['archive_type'] = "invalid";
					$returnArray['url'] = $url;
					$returnArray['link_type'] = "stray";
					$returnArray['is_archive'] = true;
				}

				//Check for a malformation or template misuse.
				if( $returnArray['archive_url'] == "x" || strpos( $url, "mementoweb.org" ) !== false ) {
					if( preg_match( '/mementoweb\.org\/(memento|api\/json)\/(\d*?|\*)\/(\S*)\s?/i', $url, $params3 ) ) {
						$returnArray['archive_type'] = "invalid";
						if( $params3[2] != "*" ) $returnArray['archive_time'] = strtotime( $params3[2] );
						else $returnArray['archive_time'] = "x";
						$returnArray['archive_url'] = "https://timetravel.".$params3[0];
					} else {
						$returnArray['archive_type'] = "invalid";
					}
				}
			}
		}

		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['deadlink_tags'] ), $remainder, $params2 ) ) {
			if( $returnArray['tagged_dead'] === true ) {
				$returnArray['tag_type'] = "invalid";
			} else {
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "template";
				$returnArray['tag_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
				//Flag those that can't be fixed.
				if( isset( $returnArray['tag_template']['parameters']['fix-attempted'] ) ) $returnArray['permanent_dead'] = true;
				$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
				$returnArray['tag_template']['string'] = $params2[0];
			}
		}
	}

	/**
	* Generate a string to replace the old string
	*
	* @param array $link Details about the new link including newdata being injected.
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
			$tArray = array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'], $this->commObject->config['ignore_tags'] );
			$regex = $this->fetchTemplateRegex( $tArray );
			$remainder = preg_replace( $regex, "", $mArray['remainder'] );
		}
		//Beginning of the string
		if( $link['link_type'] == "reference" ) {
			$out .= "<ref";
			if( isset( $link['reference']['parameters'] ) ) {
				foreach( $link['reference']['parameters'] as $parameter => $value ) {
					$out .= " $parameter=$value";
				}
				unset( $link['reference']['parameters'] );
			}
			$out .= ">";
			$tout = $link['reference']['link_string'];
			unset( $link['reference']['link_string'] );
			foreach( $link['reference'] as $tid=>$tlink ) {
				$ttout = "";
				if( isset( $tlink['ignore'] ) && $tlink['ignore'] === true ) continue;
				$mArray = Parser::mergeNewData( $tlink );
				$tArray = array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'], $this->commObject->config['ignore_tags'] );
				$regex = $this->fetchTemplateRegex( $tArray );
				$remainder = preg_replace( $regex, "", $mArray['remainder'] );
				if( $mArray['link_type'] == "link" || ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" ) ) $ttout .= $mArray['link_string'];
				elseif( $mArray['link_type'] == "template" ) {
					$ttout .= "{{".$mArray['link_template']['name'];
					foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
						if ($multiline === true) $ttout .= "\n ";
						$ttout .= "|$parameter=$value ";
					}
					if( $multiline === true ) $ttout .= "\n";
					$ttout .= "}}";
				}
				if( $mArray['tagged_dead'] === true ) {
					if( $mArray['tag_type'] == "template" ) {
						$ttout .= "{{".$mArray['tag_template']['name'];
						foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $ttout .= "|$parameter=$value ";
						$ttout .= "}}";
					}
				}
				$ttout .= $remainder;
				if( $mArray['has_archive'] === true ) {
					if( $mArray['archive_type'] == "template" ) {
						$tttout = " {{".$mArray['archive_template']['name'];
						foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) $tttout .= "|$parameter=$value ";
						$tttout .= "}}";
						if( isset( $mArray['archive_string'] ) ) {
							$ttout = str_replace( $mArray['archive_string'], trim($tttout), $ttout );
						} else {
							$ttout .= $tttout;
						}
					}
					if( isset( $mArray['archive_string'] ) && $mArray['archive_type'] != "link" ) $ttout = str_replace( $mArray['archive_string'], "", $ttout );
				}
				$tout = str_replace( $tlink['string'], $ttout, $tout );
			}

			$out .= $tout;
			$out .= "</ref>";

			return $out;

		} elseif( $link['link_type'] == "externallink" ) {
			$out .= str_replace( $link['externallink']['remainder'], "", $link['string'] );
		} elseif( $link['link_type'] == "template" || $link['link_type'] == "stray" ) {
			if( $link['link_type'] == "template" ) $out .= "{{".$link['template']['name'];
            elseif( $link['link_type'] == "stray" ) $out .= "{{".$mArray['link_template']['name'];
			foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
				if ($multiline === true) $out .= "\n ";
				$out .= "|$parameter=$value ";
			}
			if( $multiline === true ) $out .= "\n";
			$out .= "}}";
		}
		if( $mArray['tagged_dead'] === true ) {
			if( $mArray['tag_type'] == "template" ) {
				$out .= "{{".$mArray['tag_template']['name'];
				foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
				$out .= "}}";
			}
		}
		$out .= $remainder;
		if( $mArray['has_archive'] === true ) {
			if( $link['link_type'] == "externallink" ) {
				$out = str_replace( $mArray['url'], $mArray['archive_url'], $out );
			} elseif( $mArray['archive_type'] == "template" ) {
				$out .= " {{".$mArray['archive_template']['name'];
				foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
				$out .= "}}";
			}
		}
		return $out;
	}

}