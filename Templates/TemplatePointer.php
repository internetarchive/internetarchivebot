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
    along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
* @file
* TemplatePointer object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* TemplatePointer class
* Resolves known templates that generate URLs
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
abstract class TemplatePointer {
    
    /**
    * Searches for the appropriate function to resolve the URL and activates it
    * 
    * @param string $name Template name, in all lowercase
    * @param array $parameters Template parameters
    * @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return mixed Resolved URL, else false on failure
    */
    public function getURL( $name, $parameters ) {
        if( !method_exists( WIKIPEDIA."TemplatePointer", $name ) ) return false;
        return $this->$name( $parameters );
    }
    
}