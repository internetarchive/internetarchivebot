<?php
/*
	Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive

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

class PHPMailer implements EmailDriver {
	protected $mailer;

	protected $config;

	protected $recipientList = [];
	protected $ccList = [];
	protected $bccList = [];
	protected $body;
	protected $subject;
	protected $headers = [];
	protected $sender = [];
	protected $exceptOnFail;
	protected $dummy;
	protected $usingDummy = false;

	public function __construct( array $config = [] ) {
		$this->recipients = '';
		$this->CC = '';
		$this->BCC = '';
		$this->subject = '';
		$this->body = '';
		$this->sender = '';
		$this->headers = [];

		$this->config = $config;

		$this->mailer = new PHPMailer\PHPMailer\PHPMailer( true );

		if( class_exists( "Dummy" ) ) {
			$this->dummy = new Dummy();
		}
	}

	public function initialize( $exceptOnFail = false ): bool {
		$this->exceptOnFail = $exceptOnFail;

		try {
			$this->mailer->isSMTP();
			$this->mailer->Host = $this->config['host'];
			if( !empty( $this->config['port'] ) ) $this->mailer->Port = $this->config['port'];
			if( !empty( $this->config['auth_required'] ) ) $this->mailer->SMTPAuth = true;
			else $this->mailer->SMTPAuth = false;
			if( !empty( $this->config['username'] ) ) $this->mailer->Username = $this->config['username'];
			if( !empty( $this->config['password'] ) ) $this->mailer->Password = $this->config['password'];
			if( !empty( $this->config['encryption'] ) ) $this->mailer->SMTPSecure = $this->config['encryption'];
			if( IAVERBOSE ) {
				$this->mailer->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_LOWLEVEL;
			}

			return true;
		} catch( Exception $e ) {
			if( !$exceptOnFail ) {
				if( $this->dummy instanceof EmailDriver ) {
					$this->usingDummy = true;

					return false;
				} else throw $e;
			} else throw $e;
		}
	}

	public function setRecipient( array $toList ): EmailDriver {
		$this->recipientList = $toList;

		return $this;
	}

	public function setCC( array $CCList ): EmailDriver {
		$this->ccList = $CCList;

		return $this;
	}

	public function setBCC( array $BCCList ): EmailDriver {
		$this->bccList = $BCCList;

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
		$this->sender = [ 'name' => $name, 'email' => $email ];

		return $this;
	}

	protected function addRecipients( $recipients, $method ) {
		foreach( $recipients as $name => $email ) {
			if( is_numeric( $name ) ) {
				$this->mailer->$method( $email );
			} else {
				$this->mailer->$method( $email, $name );
			}
		}
	}

	public function send(): bool {
		if( $this->usingDummy ) return $this->dummy->send();

		try {
			$this->addRecipients( $this->recipientList, 'addAddress' );
			$this->addRecipients( $this->ccList, 'addCC' );
			$this->addRecipients( $this->bccList, 'addBCC' );

			$this->mailer->Subject = $this->subject;
			$this->mailer->Body = $this->body;

			foreach( $this->headers as $header => $value ) {
				if( is_int( $header ) ) [ $header, $value ] = array_map( 'trim', explode( ':', $value, 2 ) );
				$this->mailer->addCustomHeader( $header, $value );
			}

			if( IAVERBOSE ) echo "Sending email\n";
			echo "ping\n";

			return $this->mailer->send();
		} catch( Exception $e ) {
			if( $this->exceptOnFail ) {
				throw $e;
			} else {
				return false;
			}
		}
	}

}