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
* thread object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* AsyncFunctionCall class
* Allows for asyncronous function calls
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class AsyncFunctionCall extends Thread {
   
    /**
    * Function being called
    * 
    * @var string
    * @access protected
    */
    protected $method;
    
    /**
    * Function parameters being passed
    * 
    * @var array
    * @access protected
    */
    protected $params;
    
    /**
    * Returned function values
    * 
    * @var mixed
    * @access public
    */
    public $result;
    
    /**
    * Contstructs the class
    * 
    * @param string $method Name of function being called
    * @param array $params array of parameters being passed into the function
    * @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
    */
    public function __construct( $method, $params ) {
        $this->method = $method;
        $this->params = $params;
        $this->result = null; 
    }
    
    /**
    * Call the function in the seperate thread
    * 
    * @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return bool True on success
    */
    public function run() {
        if (($this->result=call_user_func_array($this->method, $this->params))) {
            return true;
        } else return false;
    }
    
    /**
    * Call the thread class to execute to execute an
    * asyncronous function call
    * 
    * @param string $method Function name
    * @param array $params Function parameters
    * @return AsyncFunctionCall on success, false on failure
    * @access public
    * @static
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    */
    public static function call($method, $params){
        $thread = new AsyncFunctionCall($method, $params);
        if($thread->start()){
            return $thread;
        } else {
            echo "Unable to initiate background function $method!\n";
            return false;
        }
    }
}

/**
* ThreadedBot class
* Allows the bot to analyze multiple pages simultaneously
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class ThreadedBot extends Collectable {
    
    /**
    * Container variables to be passed in the thread
    * 
    * @var mixed
    * @access protected
    */
    protected $id, $page, $pageid, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN;
    
    /**
    * Page analysis statistic
    * 
    * @var array
    * @access public
    */
    public $result;
    
    /**
    * Constructor class of the thread engine
    * 
    * @param string $page
    * @param int $pageid
    * @param int $ARCHIVE_ALIVE
    * @param int $TAG_OVERRIDE
    * @param int $ARCHIVE_BY_ACCESSDATE
    * @param int $TOUCH_ARCHIVE
    * @param int $DEAD_ONLY
    * @param int $NOTIFY_ERROR_ON_TALK
    * @param int $NOTIFY_ON_TALK
    * @param string $TALK_MESSAGE_HEADER
    * @param string $TALK_MESSAGE
    * @param string $TALK_ERROR_MESSAGE_HEADER
    * @param string $TALK_ERROR_MESSAGE
    * @param array $DEADLINK_TAGS
    * @param array $CITATION_TAGS
    * @param array $IGNORE_TAGS
    * @param array $ARCHIVE_TAGS
    * @param int $VERIFY_DEAD
    * @param int $LINK_SCAN
    * @param mixed $i
    * @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
    */
    public function __construct($page, $pageid, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN, $i) {
        $this->page = $page;
        $this->pageid = $pageid;
        $this->ARCHIVE_ALIVE = $ARCHIVE_ALIVE;
        $this->TAG_OVERRIDE = $TAG_OVERRIDE;
        $this->ARCHIVE_BY_ACCESSDATE = $ARCHIVE_BY_ACCESSDATE;
        $this->TOUCH_ARCHIVE = $TOUCH_ARCHIVE;
        $this->DEAD_ONLY = $DEAD_ONLY;
        $this->NOTIFY_ERROR_ON_TALK = $NOTIFY_ERROR_ON_TALK;
        $this->NOTIFY_ON_TALK = $NOTIFY_ON_TALK;
        $this->TALK_MESSAGE_HEADER = $TALK_MESSAGE_HEADER;
        $this->TALK_MESSAGE = $TALK_MESSAGE;
        $this->TALK_ERROR_MESSAGE_HEADER = $TALK_ERROR_MESSAGE_HEADER;
        $this->TALK_ERROR_MESSAGE = $TALK_ERROR_MESSAGE;
        $this->DEADLINK_TAGS = $DEADLINK_TAGS;
        $this->CITATION_TAGS = $CITATION_TAGS;
        $this->IGNORE_TAGS = $IGNORE_TAGS;
        $this->ARCHIVE_TAGS = $ARCHIVE_TAGS;
        $this->VERIFY_DEAD = $VERIFY_DEAD;
        $this->LINK_SCAN = $LINK_SCAN; 
        $this->id = $i;   
    }
    
    /**
    * Code to run in the thread
    * 
    * @access public
    * @author Maximilian Doerr (Cyberpower678)
    * @license https://www.gnu.org/licenses/gpl.txt
    * @copyright Copyright (c) 2016, Maximilian Doerr
    * @return void
    */
    public function run() {
    	$commObject = new API( $this->page, $this->pageid, $this->ARCHIVE_ALIVE, $this->TAG_OVERRIDE, $this->ARCHIVE_BY_ACCESSDATE, $this->TOUCH_ARCHIVE, $this->DEAD_ONLY, $this->NOTIFY_ERROR_ON_TALK, $this->NOTIFY_ON_TALK, $this->TALK_MESSAGE_HEADER, $this->TALK_MESSAGE, $this->TALK_ERROR_MESSAGE_HEADER, $this->TALK_ERROR_MESSAGE, $this->DEADLINK_TAGS, $this->CITATION_TAGS, $this->IGNORE_TAGS, $this->ARCHIVE_TAGS, $this->VERIFY_DEAD, $this->LINK_SCAN );
        $tmp = PARSERCLASS;
        $parser = new $tmp( $commObject );
        $this->result = $parser->analyzePage( $commObject );
        if( !file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) ) mkdir( IAPROGRESS.WIKIPEDIA."workers", 0777 );
        file_put_contents( IAPROGRESS.WIKIPEDIA."workers/worker{$this->id}", serialize( $this->result ) );
        $this->setGarbage();
        $this->page = null;
        $this->pageid = null;
        $this->ARCHIVE_ALIVE = null;
        $this->TAG_OVERRIDE = null;
        $this->ARCHIVE_BY_ACCESSDATE = null;
        $this->TOUCH_ARCHIVE = null;
        $this->DEAD_ONLY = null;
        $this->NOTIFY_ERROR_ON_TALK = null;
        $this->NOTIFY_ON_TALK = null;
        $this->TALK_MESSAGE_HEADER = null;
        $this->TALK_MESSAGE = null;
        $this->TALK_ERROR_MESSAGE_HEADER = null;
        $this->TALK_ERROR_MESSAGE = null;
        $this->DEADLINK_TAGS = null;
        $this->CITATION_TAGS = null;
        $this->IGNORE_TAGS = null;
        $this->ARCHIVE_TAGS = null;
        $this->VERIFY_DEAD = null;
        $this->LINK_SCAN = null;
        $commObject->closeResources();
        $parser = $commObject = null;
        unset( $this->page, $this->pageid, $this->alreadyArchived, $this->ARCHIVE_ALIVE, $this->TAG_OVERRIDE, $this->ARCHIVE_BY_ACCESSDATE, $this->TOUCH_ARCHIVE, $this->DEAD_ONLY, $this->NOTIFY_ERROR_ON_TALK, $this->NOTIFY_ON_TALK, $this->TALK_MESSAGE_HEADER, $this->TALK_MESSAGE, $this->TALK_ERROR_MESSAGE_HEADER, $this->TALK_ERROR_MESSAGE, $this->DEADLINK_TAGS, $this->CITATION_TAGS, $this->IGNORE_TAGS, $this->ARCHIVE_TAGS, $this->VERIFY_DEAD, $this->LINK_SCAN, $commObject );
    }
}
