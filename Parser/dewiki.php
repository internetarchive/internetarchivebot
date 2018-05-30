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

		if( !isset( $link['newdata']['link_template']['name'] ) ) {
			if( strpos($link['link_template']['name'],"nternetquelle" ) !== false ) {
				$link['newdata']['link_template']['parameters']['archiv-bot'] = date( 'Y-m-d H\:i\:s' ) . " " . TASKNAME;
			} else {
				$link['newdata']['link_template']['parameters']['archivebot'] = date( 'Y-m-d H\:i\:s' ) . " " . TASKNAME;
			}
		} else {
			if( strpos($link['newdata']['link_template']['name'],"nternetquelle" ) !== false ) {
				$link['newdata']['link_template']['parameters']['archiv-bot'] = date( 'Y-m-d H\:i\:s' ) . " " . TASKNAME;
			} else {
				$link['newdata']['link_template']['parameters']['archivebot'] = date( 'Y-m-d H\:i\:s' ) . " " . TASKNAME;
			}
		}
	}
}