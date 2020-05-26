<?php
/*
	Copyright (c) 2015-2018, Maximilian Doerr

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
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @file
 * Parser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * ISBN class
 * Allows for converting ISBNs and processing them
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class ISBN {
	protected $isbnCoreRegex = '(?:\d{1,5}[\s\-]\d{1,7}[\s\-]\d{1,6}|\d{1,5}[\s\-]\d{4,8}|\d{3,8}[\s\-]\d{1,6}|\d{1,9})';

	protected $isbn13Regex = '/(?:ISBN(?:[\s\-]?(?:13)?\:?\s?)?)?(97(?:8|9)[\s\-]{core}[\s\-]\d|\d{13})/i';

	protected $isbn10Regex = '/(?:ISBN(?:[\s\-]?(?:10)?\:?\s?)?)?({core}[\s\-](?:\d|[X])|\d{9}[0-9x])/i';

	public function getISBN( $string ) {
		if( $res = $this->isISBN13( $string ) ) return $res;
		elseif( $res = $this->isISBN10( $string ) ) return $res;
		else return false;
	}

	public function isISBN13( $string ) {
		if( preg_match( str_replace( "{core}", $this->isbnCoreRegex, $this->isbn13Regex ), $string, $matches ) ) {
			return $this->sanitize( $matches[1] );
		}
	}

	public function sanitize( $isbn ) {
		return strtoupper( preg_replace( '/[\s\-]/', "", $isbn ) );
	}

	public function isISBN10( $string ) {
		if( preg_match( str_replace( "{core}", $this->isbnCoreRegex, $this->isbn10Regex ), $string, $matches ) ) {
			return $this->$this->sanitize( $matches[1] );
		}
	}

	public function normalizeISBN( $isbn ) {
		$isbn = $this->sanitize( $isbn );

		switch( strlen( $isbn ) ) {
			case 10:
				return $this->toISBN13( $isbn );
			case 13:
				if( $this->validateISBN13( $isbn ) ) return $isbn;
				else return false;
			default:
				return false;
		}
	}

	public function toISBN13( $isbn ) {
		if( !$this->validateISBN10( $isbn ) ) return false;

		$isbn = substr( $this->sanitize( $isbn ), 0, 9 );
		$isbn = "978$isbn";

		$check = $this->calccheck13( $isbn );

		$isbn .= (string) $check;

		if( !$this->validateISBN13( $isbn ) ) return false;

		return $isbn;
	}

	public function validateISBN10( $isbn ) {
		if( substr( $this->sanitize( $isbn ), 0, 9 ) == "000000000" ) return false;

		$check1 = substr( $this->sanitize( $isbn ), 9, 1 );

		$check2 = $this->calccheck10( $isbn );

		return $check1 == $check2;
	}

	public function calccheck10( $isbn ) {
		$isbn = substr( $this->sanitize( $isbn ), 0, 9 );

		$check = 0;
		$multiplier = 10;
		for( $i = 0; $i < 9; $i++ ) {
			$char = (int) $isbn[$i];
			$check += $multiplier * $char;
			$multiplier--;

			if( $multiplier < 1 ) return false;
		}

		$check = $check % 11;

		if( $check !== 0 ) {
			$check = 11 - $check;
		}

		if( $check === 10 ) $check = "X";

		return (string) $check;
	}

	public function calccheck13( $isbn ) {
		$isbn = substr( $this->sanitize( $isbn ), 0, 12 );

		$check = 0;
		$multiplier = 1;
		for( $i = 0; $i < 12; $i++ ) {
			$char = (int) $isbn[$i];
			$check += $multiplier * $char;
			if( $multiplier === 1 ) $multiplier = 3;
			else $multiplier = 1;
		}

		$check = $check % 10;

		if( $check !== 0 ) {
			$check = 10 - $check;
		}

		return $check;
	}

	public function validateISBN13( $isbn ) {
		if( substr( $this->sanitize( $isbn ), 0, 12 ) == "978000000000" ) return false;

		$check1 = substr( $this->sanitize( $isbn ), 12, 1 );

		$check2 = (string) $this->calccheck13( $isbn );

		return $check1 == $check2;
	}
}




