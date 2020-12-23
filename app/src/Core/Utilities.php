<?php

/*
	Copyright (c) 2020 Maximilian Doerr, James Hare, Internet Archive

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

/**
 * @file
 * Utilities object
 * @author Maximilian Doerr, James Hare
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2020, Maximilian Doerr, James Hare, Internet Archive
 */

/**
 * Utilities class
 * Miscellaneous utility functions
 * @author Maximilian Doerr, James Hare
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2020, Maximilian Doerr, Internet Archive
 */
class Utilities {
	/**
	 * Convert any base number, up to 62, to base 10.  Only does whole numbers.
	 *
	 * @access public
	 * @static
	 *
	 * @param $num Based number to convert
	 * @param int $b Base to convert from
	 *
	 * @return string New base 10 number
	 */
	public static function to10( $num, $b = 62 )
	{
		$base = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$limit = strlen( $num );
		$res = strpos( $base, $num[0] );
		for( $i = 1; $i < $limit; $i++ ) {
			$res = $b * $res + strpos( $base, $num[$i] );
		}

		return $res;
	}

	/**
	 * Convert a base 10 number to any base up to 62.  Only does whole numbers.
	 *
	 * @access public
	 * @static
	 *
	 * @param $num Decimal to convert
	 * @param int $b Base to convert to
	 *
	 * @return string New base number
	 */
	public static function toBase( $num, $b = 62 )
	{
		$base = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$r = $num % $b;
		$res = $base[$r];
		$q = floor( $num / $b );
		while( $q ) {
			$r = $q % $b;
			$q = floor( $q / $b );
			$res = $base[$r] . $res;
		}

		return $res;
	}
}
