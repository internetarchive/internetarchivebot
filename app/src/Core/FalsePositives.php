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
     * FalsePositives object
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     * @copyright Copyright (c) 2015-2018, Maximilian Doerr
     */
    
    /**
     * FalsePositives class
     * Routines that assist with detecting or reporting false positives
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     * @copyright Copyright (c) 2015-2018, Maximilian Doerr
     */
    class FalsePositives {
        
        /**
         * The API class
         *
         * @var API
         * @access public
         */
        public $commObject;
        
        /**
         * The DB2 class
         *
         * @var DB2
         * @access public
         */
        public $dbObject;
        
        public function __construct( $commObject, $dbObject ) {
            $this->commObject = $commObject;
            $this->dbObject = $dbObject;
        }
    /**
     * Determine if the bot was likely reverted
     *
     * @param array $newlink The new link to look at
     * @param array $lastRevLinks The collection of link data from the previous revision to compare with.
     *
     * @access public
     * @return array Details about every link on the page
     * @return bool|int If the edit was likely the bot being reverted, it will return the first bot revid it occurred on.
     * @copyright Copyright (c) 2015-2018, Maximilian Doerr
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     */
    public function isEditReversed( $newlink, $lastRevLinkss ) {
        foreach( $lastRevLinkss as $revisionID => $lastRevLinks ) {
            $lastRevLinks = $lastRevLinks->get( true );
            if( $newlink['link_type'] == "reference" ) {
                foreach( $newlink['reference'] as $tid => $link ) {
                    if( !is_numeric( $tid ) ) continue;
                    if( !isset( $link['newdata'] ) ) continue;
                    
                    $breakout = false;
                    foreach( $lastRevLinks as $revLink ) {
                        if( !is_array( $revLink ) ) continue;
                        if( $revLink['link_type'] == "reference" ) {
                            foreach( $revLink['reference'] as $ttid => $oldLink ) {
                                if( !is_numeric( $ttid ) ) continue;
                                if( isset( $oldLink['ignore'] ) ) continue;
                                
                                if( $oldLink['url'] == $link['url'] ) {
                                    $breakout = true;
                                    break;
                                }
                            }
                        } else {
                            if( isset( $revLink[$revLink['link_type']]['ignore'] ) ) continue;
                            if( $revLink[$revLink['link_type']]['url'] == $link['url'] ) {
                                $oldLink = $revLink[$revLink['link_type']];
                                break;
                            }
                        }
                        if( $breakout === true ) break;
                    }
                    
                    if( is_array( $oldLink ) ) {
                        if( API::isReverted( $oldLink, $link ) ) {
                            return $revisionID;
                        } else continue;
                    } else continue;
                }
            } else {
                $link = $newlink[$newlink['link_type']];
                
                $breakout = false;
                foreach( $lastRevLinks as $revLink ) {
                    if( !is_array( $revLink ) ) continue;
                    if( $revLink['link_type'] == "reference" ) {
                        foreach( $revLink['reference'] as $ttid => $oldLink ) {
                            if( !is_numeric( $ttid ) ) continue;
                            if( isset( $oldLink['ignore'] ) ) continue;
                            
                            if( $oldLink['url'] == $link['url'] ) {
                                $breakout = true;
                                break;
                            }
                        }
                    } else {
                        if( isset( $revLink[$revLink['link_type']]['ignore'] ) ) continue;
                        if( $revLink[$revLink['link_type']]['url'] == $link['url'] ) {
                            $oldLink = $revLink[$revLink['link_type']];
                            break;
                        }
                    }
                    if( $breakout === true ) break;
                }
                
                if( is_array( $oldLink ) ) {
                    if( API::isReverted( $oldLink, $link ) ) {
                        return $revisionID;
                    } else continue;
                } else continue;
            }
        }
        
        return false;
    }
    
    /**
     * Determine if the given link is likely a false positive
     *
     * @param string|int $id array index ID
     * @param array $link Array of link information with details
     *
     * @access public
     * @return array Details about every link on the page
     * @return bool If the link is likely a false positive
     * @copyright Copyright (c) 2015-2018, Maximilian Doerr
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     */
    public function isLikelyFalsePositive( $id, $link, &$makeModification = true ) {
        if( is_null( $makeModification ) ) $makeModification = true;
        if( $this->commObject->db->dbValues[$id]['live_state'] == 0 ) {
            if( $link['has_archive'] === true ) return false;
            if( $link['tagged_dead'] === true ) {
                if( $link['tag_type'] == "parameter" ) {
                    $makeModification = false;
                    
                    return true;
                }
                
                return false;
            }
            
            $sql =
            "SELECT * FROM externallinks_fpreports WHERE `report_status` = 2 AND `report_url_id` = {$this->commObject->db->dbValues[$id]['url_id']};";
            if( $res = $this->dbObject->queryDB( $sql ) ) {
                if( mysqli_num_rows( $res ) > 0 ) {
                    mysqli_free_result( $res );
                    
                    return false;
                }
            }
            
            $makeModification = false;
            
            return true;
        } else {
            if( $link['tagged_dead'] === true ) {
                if( $link['tag_type'] == "parameter" ) $makeModification = false;
                
                return false;
            }
        }
        
        return false;
    }
    }
