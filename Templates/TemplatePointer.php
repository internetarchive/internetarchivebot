<?php
/**
* @file
* TemplatePointer object
* @author Maximilian Doerr (Cyberpower678)
* @license http://www.gnu.org/licenses/gpl-3.0.html
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* TemplatePointer class
* Resolves known templates that generate URLs
* @author Maximilian Doerr (Cyberpower678)
* @license http://www.gnu.org/licenses/gpl-3.0.html
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
    * @license http://www.gnu.org/licenses/gpl-3.0.html
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return mixed Resolved URL, else false on failure
    */
    public function getURL( $name, $parameters ) {
        if( !method_exists( WIKIPEDIA."TemplatePointer", $name ) ) return false;
        return $this->$name( $parameters );
    }
    
}