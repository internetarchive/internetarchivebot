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

interface EmailDriver {

	public function __construct( array $config = [] );

	public function initialize( $exceptOnFail = false ): bool;

	public function setRecipient( array $toList ): EmailDriver;

	public function setCC( array $CCList ): EmailDriver;

	public function setBCC( array $BCCList ): EmailDriver;

	public function setBody( string $body ): EmailDriver;

	public function setSubject( string $subject ): EmailDriver;

	public function setHeaders( array $headers ): EmailDriver;

	public function setSender( $name, $email ): EmailDriver;

	public function send(): bool;
}