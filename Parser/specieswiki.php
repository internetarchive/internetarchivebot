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
     * @copyright Copyright (c) 2015-2017, Maximilian Doerr
     * @return void
     */
    protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
        //The initial assumption is that we are adding an archive to a URL.
        $modifiedLinks["$tid:$id"]['type'] = "addarchive";
        $modifiedLinks["$tid:$id"]['link'] = $link['url'];
        $modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

        //The newdata index is all the data being injected into the link array.  This allows for the preservation of the old data for easier manipulation and maintenance.
        $link['newdata']['has_archive'] = true;
        $link['newdata']['is_archive'] = true;
        $link['newdata']['archive_url'] = $temp['archive_url'];
        if( isset( $link['fragment'] ) && !is_null( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" .
            $link['fragment'];
        elseif( isset( $link['archive_fragment'] ) &&
            !is_null( $link['archive_fragment'] )
        ) $link['newdata']['archive_url'] .= "#" .
            $link['archive_fragment'];
        $link['newdata']['archive_time'] = $temp['archive_time'];
        $link['newdata']['archive_type'] = "link";
        if( $link['link_type'] == "template" ) {
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
     * Generates an appropriate citation template without altering existing parameters.
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
    protected function generateNewCitationTemplate( &$link, &$temp ) {
        $link['newdata']['archive_type'] = "parameter";
        //We need to flag it as dead so the string generator knows how to behave, when assigning the deadurl parameter.
        if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
        else $link['newdata']['tagged_dead'] = false;
        $link['newdata']['tag_type'] = "parameter";
        //Set the archive URL
        $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];

        //Set the archive date

            $link['newdata']['link_template']['parameters']['archivedate'] =
                self::strftime( $this->retrieveDateFormat( $link['string'] ), $temp['archive_time'] );
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
     * @copyright Copyright (c) 2015-2017, Maximilian Doerr
     * @return void
     */
    protected function noRescueLink( &$link, &$modifiedLinks, $tid, $id ) {
        return;
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
     * @copyright Copyright (c) 2015-2017, Maximilian Doerr
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
        ) $returnArray['original_url'] = $returnArray['url'] =
            $this->filterText( htmlspecialchars_decode( $returnArray['link_template']['parameters']['url'] ) );
        else return true;
        //Fetch the access date.  Use the wikitext resolver in case a date template is being used.
        if( isset( $returnArray['link_template']['parameters']['accessdate'] ) &&
            !empty( $returnArray['link_template']['parameters']['accessdate'] )
        ) {
            $time = self::strtotime( $returnArray['link_template']['parameters']['accessdate'] );
            if( $time === false ) $time =
                self::strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['accessdate'] ) );
            if( $time === false ) $time = "x";
            $returnArray['access_time'] = $time;
        } elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) &&
            !empty( $returnArray['link_template']['parameters']['access-date'] )
        ) {
            $time = self::strtotime( $returnArray['link_template']['parameters']['access-date'] );
            if( $time === false ) $time =
                self::strtotime( API::resolveWikitext( $returnArray['link_template']['parameters']['access-date'] ) );
            if( $time === false ) $time = "x";
            $returnArray['access_time'] = $time;
        } else $returnArray['access_time'] = "x";
        //Check for the presence of an archive URL
        if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) &&
            !empty( $returnArray['link_template']['parameters']['archiveurl'] )
        ) $returnArray['archive_url'] =
            $this->filterText( htmlspecialchars_decode( $returnArray['link_template']['parameters']['archiveurl'] ) );
        if( isset( $returnArray['link_template']['parameters']['archive-url'] ) &&
            !empty( $returnArray['link_template']['parameters']['archive-url'] )
        ) $returnArray['archive_url'] =
            $this->filterText( htmlspecialchars_decode( $returnArray['link_template']['parameters']['archive-url'] ) );
        if( ( ( isset( $returnArray['link_template']['parameters']['archiveurl'] ) &&
                    !empty( $returnArray['link_template']['parameters']['archiveurl'] ) ) ||
                ( isset( $returnArray['link_template']['parameters']['archive-url'] ) &&
                    !empty( $returnArray['link_template']['parameters']['archive-url'] ) ) ) &&
            API::isArchive( $returnArray['archive_url'], $returnArray )
        ) {
            $returnArray['archive_type'] = "parameter";
            $returnArray['has_archive'] = true;
            $returnArray['is_archive'] = false;
        }
            $returnArray['tagged_dead'] = true;
            $returnArray['tag_type'] = "implied";
        //Using an archive URL in the url field is not correct.  Flag as invalid usage if the URL is an archive.
        if( API::isArchive( $returnArray['original_url'], $returnArray ) ) {
            $returnArray['has_archive'] = true;
            $returnArray['is_archive'] = true;
            $returnArray['archive_type'] = "invalid";

            if( !isset( $returnArray['link_template']['parameters']['accessdate'] ) &&
                !isset( $returnArray['link_template']['parameters']['access-date'] ) &&
                $returnArray['access_time'] != "x"
            ) $returnArray['access_time'] = $returnArray['archive_time'];
            else {
                if( isset( $returnArray['link_template']['parameters']['accessdate'] ) &&
                    !empty( $returnArray['link_template']['parameters']['accessdate'] ) &&
                    $returnArray['access_time'] != "x"
                ) $returnArray['access_time'] =
                    self::strtotime( $returnArray['link_template']['parameters']['accessdate'] );
                elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) &&
                    !empty( $returnArray['link_template']['parameters']['access-date'] ) &&
                    $returnArray['access_time'] != "x"
                ) $returnArray['access_time'] =
                    self::strtotime( $returnArray['link_template']['parameters']['access-date'] );
                else $returnArray['access_time'] = "x";
            }
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
        return;
    }

}