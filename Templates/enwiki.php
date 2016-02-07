<?php
/**
* @file
* enwikiTemplatePointer object
* @author Maximilian Doerr (Cyberpower678)
* @license http://www.gnu.org/licenses/gpl-3.0.html
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* enwikiTemplatePointer class
* Extends the master TemplatePointer class
* Specifically for en.wikipedia.org
* @author Maximilian Doerr (Cyberpower678)
* @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
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