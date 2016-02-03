<?php
//Multithread engine

//This thread class allows for asyncronous function calls.  This is useful for the functions that consume time and can run in the background.
//Caution must be excercised to ensure that the functions are thread safe.
class AsyncFunctionCall extends Thread {
    
    protected $method;
    protected $params;
    public $result;
    
    public function __construct( $method, $params ) {
        $this->method = $method;
        $this->params = $params;
        $this->result = null; 
    }
    
    public function run() {
        if (($this->result=call_user_func_array($this->method, $this->params))) {
            return true;
        } else return false;
    }
    
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

// Analyze multiple pages simultaneously and edit them.
class ThreadedBot extends Collectable {
    
    protected $id, $page, $pageid, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN;
    
    public $result;
    
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
    
    public function run() {
    	$commObject = new API( $this->page, $this->pageid, $this->ARCHIVE_ALIVE, $this->TAG_OVERRIDE, $this->ARCHIVE_BY_ACCESSDATE, $this->TOUCH_ARCHIVE, $this->DEAD_ONLY, $this->NOTIFY_ERROR_ON_TALK, $this->NOTIFY_ON_TALK, $this->TALK_MESSAGE_HEADER, $this->TALK_MESSAGE, $this->TALK_ERROR_MESSAGE_HEADER, $this->TALK_ERROR_MESSAGE, $this->DEADLINK_TAGS, $this->CITATION_TAGS, $this->IGNORE_TAGS, $this->ARCHIVE_TAGS, $this->VERIFY_DEAD, $this->LINK_SCAN );
        $this->result = analyzePage( $commObject );
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
        $commObject = null;
        unset( $this->page, $this->pageid, $this->alreadyArchived, $this->ARCHIVE_ALIVE, $this->TAG_OVERRIDE, $this->ARCHIVE_BY_ACCESSDATE, $this->TOUCH_ARCHIVE, $this->DEAD_ONLY, $this->NOTIFY_ERROR_ON_TALK, $this->NOTIFY_ON_TALK, $this->TALK_MESSAGE_HEADER, $this->TALK_MESSAGE, $this->TALK_ERROR_MESSAGE_HEADER, $this->TALK_ERROR_MESSAGE, $this->DEADLINK_TAGS, $this->CITATION_TAGS, $this->IGNORE_TAGS, $this->ARCHIVE_TAGS, $this->VERIFY_DEAD, $this->LINK_SCAN, $commObject );
        $commObject = null;
    }
}
