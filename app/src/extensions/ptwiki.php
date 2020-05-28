<?php


/*
	Copyright (c) 2015-2020, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	InternetArchiveBot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with InternetArchiveBot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
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
		parent::analyzeCitation( $returnArray, $params );

		if( isset( $returnArray['ignore'] ) ) return;

		if( !empty( $returnArray['link_template']['parameters']['wayb'] ) ) {
			if( API::isArchive( "https://web.archive.org/web/" . $returnArray['link_template']['parameters']['wayb'] . "/" . $returnArray['url'], $returnArray ) ) {
				$returnArray['archive_type'] = "parameter";
				$returnArray['has_archive'] = true;
				$returnArray['is_archive'] = false;
			}
		}

		return;
	}
}