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

namespace Core;
use MetricsDriver, EmailDriver;

class Dummy implements MetricsDriver, EmailDriver {

	public function initialize( $exceptOnFail = false ): bool {
		return true;
	}

	public function flushEntries(): bool {
		return true;
	}

	public function purgeEntries(): bool {
		return true;
	}

	public function setFlushInterval( $seconds = 300, $entryLimit = 1000 ) {
		return true;
	}

	public function createEntry( float $microtime, $attributesArray ): bool {
		return true;
	}

	public function __construct( $config = [] ) {
		return;
	}

	public function readyToFlush(): bool {
		return false;
	}

	public function setRecipient( array $toList ): EmailDriver {
		return $this;
	}

	public function setCC( array $CCList ): EmailDriver {
		return $this;
	}

	public function setBCC( array $BCCList ): EmailDriver {
		return $this;
	}

	public function setBody( string $body ): EmailDriver {
		return $this;
	}

	public function setSubject( string $subject ): EmailDriver {
		return $this;
	}

	public function send(): bool {
		return true;
	}

	public function setSender( $name, $email ): EmailDriver {
		return $this;
	}

	public function setHeaders( array $headers ): EmailDriver {
		return $this;
	}
}