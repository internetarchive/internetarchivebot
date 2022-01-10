<?php

class SPNException extends Exception {

	public function __construct( $message, $code, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}

class AvailabilityException extends Exception {

	public function __construct( $message, $code, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}