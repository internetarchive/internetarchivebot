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
			$link['newdata']['link_template']['parameters']['deadlinkdate'] = self::strftime( '%Y年%-m月' );
		} else {
			$link['newdata']['tag_type'] = "template";
			$link['newdata']['tag_template']['name'] = "リンク切れ";
			$link['newdata']['tag_template']['parameters']['date'] = self::strftime( '%Y年%-m月' );
			$link['newdata']['tag_template']['parameters']['bot'] = USERNAME;
		}
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
			$link['newdata']['link_template']['parameters']['deadlinkdate'] = self::strftime( '%Y年%-m月' );
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

		if( !empty( $returnArray['link_template']['parameters']['deadlinkdate'] ) ||
		    !empty( $returnArray['link_template']['parameters']['deadurl'] ) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "parameter";
		} else {
			$returnArray['tagged_dead'] = false;
		}
	}
}