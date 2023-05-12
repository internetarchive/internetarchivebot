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

interface MetricsDriver {
	public function initialize( $exceptOnFail = false ): bool;

	public function flushEntries(): bool;

	public function purgeEntries(): bool;

	public function setFlushInterval( int $seconds = 300, int $entryLimit = 1000 );

	public function createEntry(float $microtime, $attributesArray): bool;

	public function __construct( $config = [] );

	public function readyToFlush(): bool;
}