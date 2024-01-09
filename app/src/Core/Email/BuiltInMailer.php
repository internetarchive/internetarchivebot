<?php
/*
	Copyright (c) 2015-2024, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	InternetArchiveBot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with InternetArchiveBot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

class BuiltInMailer implements EmailDriver {

	protected $recipients;

	protected $CC;

	protected $BCC;

	protected $subject;

	protected $body;

	protected $sender;

	protected $headers;

	public function __construct( array $config = [] ) {
		$this->recipients = '';
		$this->CC = '';
		$this->BCC = '';
		$this->subject = '';
		$this->body = '';
		$this->sender = '';
		$this->headers = [];
	}

	public function initialize( $exceptOnFail = false ): bool {
		return true;
	}

	public function setRecipient( array $toList ): EmailDriver {
		$this->recipients = '';
		foreach( $toList as $name => $email ) {
			if( !is_numeric( $name ) ) $this->recipients .= "$name <$email>, ";
			else $this->recipients .= "$email, ";
		}
		rtrim( $this->recipients, ', ' );

		return $this;
	}

	public function setCC( array $CCList ): EmailDriver {
		$this->CC = '';
		foreach( $CCList as $name => $email ) {
			if( !is_numeric( $name ) ) $this->CC .= "$name <$email>, ";
			else $this->CC .= "$email, ";
		}
		rtrim( $this->CC, ', ' );

		return $this;
	}

	public function setBCC( array $BCCList ): EmailDriver {
		$this->BCC = '';
		foreach( $BCCList as $name => $email ) {
			if( !is_numeric( $name ) ) $this->BCC .= "$name <$email>, ";
			else $this->BCC .= "$email, ";
		}
		rtrim( $this->BCC, ', ' );

		return $this;
	}

	public function setBody( string $body ): EmailDriver {
		$this->body = $body;

		return $this;
	}

	public function setSubject( string $subject ): EmailDriver {
		$this->subject = $subject;

		return $this;
	}

	public function setHeaders( array $headers ): EmailDriver {
		$this->headers = $headers;

		return $this;
	}

	public function setSender( $name, $email ): EmailDriver {
		if( empty( $name ) ) $this->sender = $email;
		else $this->sender = "$name <$email>";

		return $this;
	}

	public function send(): bool {
		$headers = [
			"From: {$this->sender}"
		];

		if( !empty( $this->headers ) ) $headers = array_merge( $headers, $this->headers );

		if( !empty( $this->CC ) ) $headers[] = "CC: {$this->CC}";

		if( !empty( $this->BCC ) ) $headers[] = "BCC: {$this->BCC}";

		return mail( $this->recipients, $this->subject, $this->body, $headers );
	}

}