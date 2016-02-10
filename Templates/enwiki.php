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
* enwikiTemplatePointer object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* enwikiTemplatePointer class
* Extends the master TemplatePointer class
* Specifically for en.wikipedia.org
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class enwikiTemplatePointer extends TemplatePointer {
    
    /**
    * Resolves all Allmusic templates to 
    * http://www.allmusic.com/{class|artist}/{id}(/{tab})?
    * 
    * @param array $parameters Template parameters
    * @access protected
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return mixed URL string, else false on failure
    */
    protected function allmusic( $parameters ) {
        if( isset( $parameters['pure_url'] ) && $parameters['pure_url'] == "yes" ) {
            $url = "http://www.allmusic.com/";
            if( isset( $parameters['class'] ) ) {
                $url .= $parameters['class'];
            } else {
                $url .= "artist";
            }
            if( !isset( $parameters['id'] ) ) return false;
            $url .= "/".$parameters['id'];
            if( isset( $parameters['tab'] ) ) {
                $url .= "/".$parameters['tab'];
            }
            return $url;
        } else return false;
    }
}