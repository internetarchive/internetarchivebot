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
 * specieswikiParser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */

/**
 * specieswikiParser class
 * Extension of the master parser class specifically for species.wikimedia.org
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
 */
class specieswikiParser extends Parser {

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
            preg_match( '/\d\d? (?:January|February|March|April|May|June|July|August|September|October|November|December) \d{4}/i',
                $default
            )
        ) return '%-e %B %Y';
        elseif( !is_bool( $default ) &&
            preg_match( '/(?:January|February|March|April|May|June|July|August|September|October|November|December) \d\d?\, \d{4}/i',
                $default
            )
        ) return '%B %-e, %Y';
        elseif( !is_bool( $default ) &&
            preg_match( '/\d\d? (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4}/i',
                $default
            )
        ) return '%-e %b %Y';
        elseif( !is_bool( $default ) &&
            preg_match( '/(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d\d?\, \d{4}/i',
                $default
            )
        ) return '%b %-e, %Y';
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
        return;
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
        return;
    }

}