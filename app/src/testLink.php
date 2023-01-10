<?php

/*
	Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

use Wikimedia\DeadlinkChecker\CheckIfDead;

if( empty( $argv[1] ) ) {
	echo "Link required to test.\n";
	exit( 1 );
}
if( !empty( $argv[2] ) ) {
	echo "UA set to {$argv[2]}\n";
	define( 'UA', $argv[2] );
} else {
	define( 'UA', false );
}

require_once( 'Core/init.php' );

$checkIfDead = new CheckIfDead( 30, 60, UA, true, true );

$isDead = $checkIfDead->isLinkDead( $argv[1] );

echo "IS DEAD: ";
var_dump( $isDead );

var_dump( $checkIfDead->getRequestDetails(), $checkIfDead->getErrors() );