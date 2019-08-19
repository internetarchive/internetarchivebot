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
 * ptwikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * ptwikiParser class
 * Extension of the master parser class specifically for pt.wikipedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class ptwikiParser extends Parser {

	/**
	 * Analyze the citation template
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $params Citation template regex match breakdown
	 *
	 * @access protected
	 * @return void
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	protected function analyzeCitation( &$returnArray, &$params ) {
		$returnArray = parent::analyzeCitation( $returnArray, $params );

		if( isset( $returnArray['ignore'] ) ) return $returnArray;

		if( !empty( $returnArray['link_template']['parameters']['wayb'] ) ) {
			if( API::isArchive( "https://web.archive.org/web/" . $returnArray['link_template']['parameters']['wayb'] . "" . $returnArray['url'] ) ) {
				$returnArray['archive_type'] = "parameter";
				$returnArray['has_archive'] = true;
				$returnArray['is_archive'] = false;
			}
		}

		return $returnArray;
	}
}