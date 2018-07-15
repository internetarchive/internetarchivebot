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
	//TODO: Clean up function
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
			if( !$this->generateNewCitationTemplate( $link ) ) return false;

			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
			    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
			) {
				if( !empty( $link['newdata']['link_template']['template_map'] ) ) $map =
					$link['newdata']['link_template']['template_map'];
				elseif( !empty( $link['link_template']['template_map'] ) ) $map =
					$link['link_template']['template_map'];

				if( !empty( $map['services']['@default']['url'] ) )
					foreach( !empty( $map['services']['@default']['url'] ) as $dataIndex ) {
						foreach( $map['data'][$dataIndex]['mapto'] as $paramIndex ) {
							if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ||
							    isset( $link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] ) ) break 2;
						}
					}

				if( !isset( $link['template_url'] ) )
					$link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] = $link['url'];
				else $link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] =
					$link['template_url'];
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
}