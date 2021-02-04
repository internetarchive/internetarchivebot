<?php

/*
	Copyright (c) 2021 Maximilian Doerr, James Hare, Internet Archive

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
 * UrlResolver object
 * @author Maximilian Doerr, James Hare
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
 */

use Wikimedia\DeadlinkChecker\CheckIfDead;

/**
 * UrlResolver class
 * Resolves URLs and provides relevant metadata
 * @author Maximilian Doerr, James Hare
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */
class UrlResolver {
	/**
	 * Stores the global curl handle for the bot.
	 *
	 * @var resource
	 * @access protected
	 * @static
	 * @staticvar
	 */
	protected static $globalCurl_handle = null;

	/**
	 * Evaluates the provided path pattern against the provided match
	 *
	 * @access private
	 * @static
	 *
	 * @param $pathPattern string Formatted string where $n is a stand-in for $match[n].
	 * @param $match array Match results against URL regular expression
	 *
	 * @return string Part of URL that comes after the URL stem
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2021 James Hare, Internet Archive
	 * @author James Hare
	 */
	private static function evalPathPattern( $pathPattern, $match )
	{
		$patternParts = explode( '/', $pathPattern );
		$returnStr = '';
		for ( $i = 0; $i < count($patternParts); $i++ ) {
			if ( preg_match( '/\$(\d)/', $patternParts[$i], $submatch ) ) {
				$returnStr .= str_replace( $patternParts[$i], $match[intval($submatch[1])], $submatch[0] ) . '/';
			} else $returnStr .= $patternParts[$i] . '/';
		}

		// Return without trailing slash
		return substr($returnStr, 0, -1);

	}

	/**
	 * Resolves a URL and provides metadata by comparing it against a regex and parsing
	 * parts of it.
	 *
	 * @access private
	 * @static
	 *
	 * @param $url string URL to check
	 * @param $regex string Regular expression to compare URL against
	 * @param $urlStem string Base of the URL being checked
	 * @param $archiveHost string String identifying the archive
	 * @param $pathPattern string A shorthand describing the directory structure of a URL where e.g. $n corresponds to $match[$n]
	 * @param $urlMatchIndex int The corresponding index in the $match array with the archived URL being extracted
	 * @param $timeMatchIndex int The corresponding index in the $match array with the time the URL was archived
	 * @param $force bool Whether the return array should set the "force" parameter to true
	 *
	 * @return array Metadata regarding $url
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	private static function resolve(
		$url,
		$regex,
		$urlStem,
		$archiveHost,
		$pathPattern='$1/$2',
		$urlMatchIndex=2,
		$timeMatchIndex=1,
		$force=false
	) {
	
		$checkIfDead = new CheckIfDead();
		$returnArray = [];

		if ( preg_match( $regex, $url, $match ) ) {
			$returnArray['archive_url']  = $urlStem . self::evalPathPattern($pathPattern, $match);
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[$urlMatchIndex], true );
			$returnArray['archive_time'] = strtotime( $match[$timeMatchIndex] );
			$returnArray['archive_host'] = $archiveHost;
			$returnArray['force']        = $force;
			if ( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;

	}

	/**
	 * Retrieves URL information given a Catalonian Archive URL
	 *
	 * @access public
	 *
	 * @param string $url A Catalonian Archive URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveCatalonianArchive( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:www\.)?padi.cat(?:\:8080)?\/wayback\/(\d*?)\/(\S*)/i',
			"http://padi.cat:8080/wayback/",
			'catalonianarchive'
		);
	}

	/**
	 * Retrieves URL information given a Webarchive UK URL
	 *
	 * @access public
	 *
	 * @param string $url A Webarchive UK URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveWebarchiveUK( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:webarchive\.org\.uk)\/wayback\/archive\/(\d*)(?:mp_)?\/(\S*)/i',
			"https://www.webarchive.org/wayback/archive/",
			'webarchiveuk'
		);
	}

	/**
	 * Retrieves URL information given a Europarchive URL
	 *
	 * @access public
	 *
	 * @param string $url A Europarchive URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveEuropa( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:collection\.europarchive\.org|collections\.internetmemory\.org)\/nli\/(\d*)\/(\S*)/i',
			"https://wayback.archive-it.org/10702/",
			'archiveit',
			$force=true
		);
	}

	/**
	 * Retrieves URL information given a UK Web Archive URL
	 *
	 * @access public
	 *
	 * @param string $url A UK Web Archive URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveUKWebArchive( $url )
	{
		return self::resolve(
			$url,
			'/www\.webarchive\.org\.uk\/wayback\/archive\/([^\s\/]*)(?:\/(\S*))?/i',
			"https://www.webarchive.org.uk/wayback/archive/",
			'ukwebarchive'
		);
	}

	/**
	 * Retrieves URL information given a memento URL
	 *
	 * @access public
	 *
	 * @param string $url A memento URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveMemento( $url )
	{
		return self::resolve(
			$url,
			'/\/\/timetravel\.mementoweb\.org\/(?:memento|api\/json)\/(\d*?)\/(\S*)/i',
			"https://timetravel.mementoweb.org/memento/",
			"memento"
		);
	}

	/**
	 * Retrieves URL information given a York University URL
	 *
	 * @access public
	 *
	 * @param string $url A York University URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveYorkU( $url )
	{
		return self::resolve(
			$url,
			'/\/\/digital\.library\.yorku\.ca\/wayback\/(\d*)\/(\S*)/i',
			"https://digital.library.yorku.ca/wayback/",
			"yorku"
		);
	}

	/**
	 * Retrieves URL information given a Archive It URL
	 *
	 * @access public
	 *
	 * @param string $url An Archive It URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveArchiveIt( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:wayback\.)?archive-it\.org\/(\d*|all)\/(\d*?)\/(\S*)/i',
			"https://wayback.archive-it.org/",
			"archiveit",
			$pathPattern="$1/$2/$3",
			$urlMatchIndex=3,
			$timeMatchIndex=2
		);
	}

	/**
	 * Retrieves URL information given an Arquivo URL
	 *
	 * @access public
	 *
	 * @param string $url A Arquivo URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveArquivo( $url )
	{
		return self::resolve(
			$url,
			'/\/\/arquivo.pt\/wayback\/(?:wayback\/)?(\d*?)\/(\S*)/i',
			"http://arquivo.pt/wayback/",
			"arquivo"
		);
	}

	/**
	 * Retrieves URL information given a LOC URL
	 *
	 * @access public
	 *
	 * @param string $url A LOC URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveLoc( $url )
	{
		return self::resolve(
			$url,
			'/\/\/webarchive.loc.gov\/(?:all\/|lcwa\d{4}\/)(\d*?)\/(\S*)/i',
			"http://webarchive.loc.gov/all/",
			"loc"
		);
	}

	/**
	 * Retrieves URL information given a Webharvest URL
	 *
	 * @access public
	 *
	 * @param string $url A Webharvest URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveWebharvest( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:www.)?webharvest.gov\/(.*?)\/(\d*?)\/(\S*)/i',
			"https://www.webharvest.gov/",
			"warbharvest",
			$pathPattern="$1/$2/$3",
			$urlMatchIndex=3,
			$timeMatchIndex=2
		);
	}

	/**
	 * Retrieves URL information given a Bibalex URL
	 *
	 * @access public
	 *
	 * @param string $url A Bibalex URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveBibalex( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:web\.)?(?:archive|petabox)\.bibalex\.org(?:\:80)?(?:\/web)?\/(\d*?)\/(\S*)/i',
			"http://web.archive.bibalex.org/web/",
			"bibalex"
		);
	}

	/**
	 * Retrieves URL information given a Collections Canada URL
	 *
	 * @access public
	 *
	 * @param string $url A Collections Canada URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveCollectionsCanada( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:www\.)?collectionscanada(?:\.gc)?\.ca\/(?:archivesweb|webarchives)\/(\d*?)\/(\S*)/i',
			"http://webarchive.bac-lac.gc.ca:8080/wayback/",
			"lacarchive",
			$force=true
		);
	}

	/**
	 * Retrieves URL information given a Veebiarhiiv URL
	 *
	 * @access public
	 *
	 * @param string $url A Veebiarhiiv URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveVeebiarhiiv( $url )
	{
		return self::resolve(
			$url,
			'/\/\/veebiarhiiv\.digar\.ee\/a\/(\d*?)\/(\S*)/i',
			"http://veebiarhiiv.digar.ee/a/",
			"veebiarhiiv"
		);
	}

	/**
	 * Retrieves URL information given a Vefsafn URL
	 *
	 * @access public
	 *
	 * @param string $url A Vefsafn URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveVefsafn( $url )
	{
		return self::resolve(
			$url,
			'/\/\/wayback\.vefsafn\.is\/wayback\/(\d*?)\/(\S*)/i',
			"http://wayback.vefsafn.is/wayback/",
			"vefsafn"
		);
	}

	/**
	 * Retrieves URL information given a Proni URL
	 *
	 * @access public
	 *
	 * @param string $url A Proni URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveProni( $url )
	{
		return self::resolve(
			$url,
			'/\/\/webarchive\.proni\.gov\.uk\/(\d*?)\/(\S*)/i',
			"https://wayback.archive-it.org/11112/",
			"archiveit",
			$force=true
		);
	}

	/**
	 * Retrieves URL information given a Spletni URL
	 *
	 * @access public
	 *
	 * @param string $url A Spletni URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveSpletni( $url )
	{
		return self::resolve(
			$url,
			'/\/\/nukrobi2\.nuk\.uni-lj\.si:8080\/wayback\/(\d*?)\/(\S*)/i',
			"http://nukrobi2.nuk.uni-lj.si:8080/wayback/",
			"spletni"
		);
	}

	/**
	 * Retrieves URL information given a Stanford URL
	 *
	 * @access public
	 *
	 * @param string $url A Stanford URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveStanford( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:sul-)?swap(?:\-prod)?\.stanford\.edu\/(\d*?)\/(\S*)/i',
			"https://swap.stanford.edu/",
			"stanford"
		);
	}

	/**
	 * Retrieves URL information given a National Archives (UK) URL
	 *
	 * @access public
	 *
	 * @param string $url A National Archives URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveNationalArchives( $url )
	{
		return self::resolve(
			$url,
			'/\/\/(?:yourarchives|webarchive)\.nationalarchives\.gov\.uk\/(\d*?)\/(\S*)/i',
			"http://webarchive.nationalarchives.gov.uk/",
			"nationalarchives"
		);
	}

	/**
	 * Retrieves URL information given a Parliament UK URL
	 *
	 * @access public
	 *
	 * @param string $url A Parliament UK URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveParliamentUK( $url )
	{
		return self::resolve(
			$url,
			'/\/\/webarchive\.parliament\.uk\/(\d*?)\/(\S*)/i',
			"http://webarchive.parliament.uk/",
			"parliamentuk"
		);
	}

	/**
	 * Retrieves URL information given a WAS URL
	 *
	 * @access public
	 *
	 * @param string $url A WAS URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James hare
	 */
	public static function resolveWAS( $url )
	{
		return self::resolve(
			$url,
			'/\/\/eresources\.nlb\.gov\.sg\/webarchives\/wayback\/(\d*?)\/(\S*)/i',
			"http://eresources.nlb.gov.sg/webarchives/wayback/",
			"was"
		);
	}

	/**
	 * Retrieves URL information given a Library and Archives Canada (LAC) URL
	 *
	 * @access public
	 *
	 * @param string $url A Library and Archives Canada (LAC) URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveLAC( $url )
	{
		return self::resolve(
			$url,
			'/\/\/webarchive\.bac\-lac\.gc\.ca\:8080\/wayback\/(\d*)\/(\S*)/i',
			"http://webarchive.bac-lac.gc.ca:8080/wayback/",
			"lacarchive"
		);
	}

	/**
	 * Retrieves URL information given a Web Recorder URL
	 *
	 * @access public
	 *
	 * @param string $url A Web Recorder URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, James Hare, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678), James Hare
	 */
	public static function resolveWebRecorder( $url )
	{
		return self::resolve(
			$url,
			'/\/\/webrecorder\.io\/(.*?)\/(.*?)\/(\d*).*?\/(\S*)/i',
			"https://webrecorder.io/",
			"webrecorder",
			$pathPattern="$1/$2/$3/$4",
			$urlMatchIndex=4,
			$timeMatchIndex=3
		);
	}

	/**
	 * Retrieves URL information given a Wayback URL
	 *
	 * @access public
	 *
	 * @param string $url A Wayback URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWayback( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		if( preg_match( '/\/\/(?:www\.|(?:www\.|classic\-|replay\.?)?(?:web)?(?:\-beta|\.wayback)?\.|wayback\.|liveweb\.)?(?:archive|waybackmachine)\.org(?:\/web)?(?:\/(\d*?)(?:\-)?(?:id_|re_)?)?(?:\/_embed)?\/(\S*)/i',
		                $url,
		                $match
		) ) {
			if( empty( $match[1] ) ) {
				$nocodeAURL = "https://web.archive.org/web/" . $match[2];
				if( !preg_match( '/(?:http|ftp|www\.)/i', $match[2] ) ) return $returnArray;
				$returnArray['archive_url']  =
					"https://web.archive.org/web/" . $checkIfDead->sanitizeURL( $match[2], false, true );
				$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2], true );
				$returnArray['archive_time'] = "x";
			} else {
				$nocodeAURL = "https://web.archive.org/web/" . $match[1] . "/" . $match[2];
				$returnArray['archive_url'] =
					"https://web.archive.org/web/" . $match[1] . "/" .
					$checkIfDead->sanitizeURL( $match[2], false, true );
				$returnArray['url'] = $checkIfDead->sanitizeURL( $match[2], true );
				if( strlen( $match[1] ) >= 4 ) $match[1] = str_pad( $match[1], 14, "0", STR_PAD_RIGHT );
				else return [];
				$returnArray['archive_time'] = strtotime( $match[1] );
			}
			$returnArray['archive_host'] = "wayback";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
			if( $url == $nocodeAURL && $nocodeAURL != $returnArray['archive_url'] )
				$returnArray['converted_encoding_only'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given an archive.is URL
	 *
	 * @access public
	 *
	 * @param string $url An archive.is URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveArchiveIs( $url )
	{
		$checkIfDead = new CheckIfDead();

		$returnArray = [];
		archiveisrestart:
		if( preg_match( '/\/\/((?:www\.)?archive.(?:is|today|fo|li|vn|ph|md))\/(\S*?)\/(\S+)/i', $url, $match ) ) {
			if( ( $timestamp = strtotime( $match[2] ) ) === false ) $timestamp =
				strtotime( $match[2] = ( is_numeric( preg_replace( '/[\.\-\s]/i', "", $match[2] ) ) ?
					preg_replace( '/[\.\-\s]/i', "", $match[2] ) : $match[2] )
				);
			$oldurl                      = $match[3];
			$returnArray['archive_time'] = $timestamp;
			$returnArray['url']          = $checkIfDead->sanitizeURL( $oldurl, true );
			$returnArray['archive_url']  = "https://" . $match[1] . "/" . $match[2] . "/" . $match[3];
			$returnArray['archive_host'] = "archiveis";
			if( $returnArray['archive_url'] != $url ) $returnArray['convert_archive_url'] = true;
			if( isset( $originalURL ) ) DB::accessArchiveCache( $originalURL, $returnArray['archive_url'] );

			return $returnArray;
		}

		if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
			$url = $newURL;
			goto archiveisrestart;
		}
		if( is_null( self::$globalCurl_handle ) ) API::initGlobalCurlHandle();
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
		curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 1 );
		if( IAVERBOSE ) echo "Making query: $url\n";
		$data = curl_exec( self::$globalCurl_handle );
		if( preg_match( '/\<input id\=\"SHARE_LONGLINK\".*?value\=\"(.*?)\"\/\>/i', $data, $match ) ) {
			$originalURL = $url;
			$url = htmlspecialchars_decode( $match[1] );
			goto archiveisrestart;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a webcite URL
	 *
	 * @access public
	 *
	 * @param string $url A webcite URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWebCite( $url )
	{
		$checkIfDead = new CheckIfDead();

		$returnArray = [];
		webcitebegin:
		//Try and decode the information from the URL first
		if( preg_match( '/\/\/(?:www\.)?webcitation.org\/(query|\S*?)\?(\S+)/i', $url, $match ) ) {
			if( $match[1] != "query" ) {
				$args['url'] = rawurldecode( preg_replace( "/url\=/i", "", $match[2], 1 ) );
				if( strlen( $match[1] ) === 9 ) $timestamp = substr( (string) Utilities::to10( $match[1], 62 ), 0, 10 );
				else $timestamp = substr( $match[1], 0, 10 );
			} else {
				$args = explode( '&', $match[2] );
				foreach( $args as $arg ) {
					$arg = explode( '=', $arg, 2 );
					$temp[urldecode( $arg[0] )] = urldecode( $arg[1] );
				}
				$args = $temp;
				if( isset( $args['id'] ) ) {
					if( strlen( $args['id'] ) === 9 ) $timestamp =
						substr( (string) Utilities::to10( $args['id'], 62 ), 0, 10 );
					else $timestamp = substr( $args['id'], 0, 10 );
				} elseif( isset( $args['date'] ) ) $timestamp = strtotime( $args['date'] );
			}
			if( isset( $args['url'] ) ) {
				$oldurl = $checkIfDead->sanitizeURL( $args['url'], true );
				$oldurl = str_replace( "[", "%5b", $oldurl );
				$oldurl = str_replace( "]", "%5d", $oldurl );
			}
			if( isset( $oldurl ) && isset( $timestamp ) && $timestamp !== false ) {
				$returnArray['archive_time'] = $timestamp;
				$returnArray['url'] = $oldurl;
				if( $match[1] == "query" ) {
					$returnArray['archive_url'] = "https:" . $match[0];
				} else {
					$returnArray['archive_url'] = "https://www.webcitation.org/{$match[1]}?url=$oldurl";
				}
				$returnArray['archive_host'] = "webcite";
				if( $returnArray['archive_url'] != $url ) $returnArray['convert_archive_url'] = true;

				return $returnArray;
			}
		}

		if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false && !empty( $newURL ) ) {
			$url = $newURL;
			goto webcitebegin;
		}
		if( preg_match( '/\/\/(?:www\.)?webcitation.org\/query\?(\S*)/i', $url, $match ) ) {
			$query = "https:" . $match[0] . "&returnxml=true";
		} elseif( preg_match( '/\/\/(?:www\.)?webcitation.org\/(\S*)/i', $url, $match ) ) {
			$query = "https://www.webcitation.org/query?returnxml=true&id=" . $match[1];
		} else return $returnArray;
		if( is_null( self::$globalCurl_handle ) ) API::initGlobalCurlHandle();
		curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
		curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $query );
		curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 1 );
		if( IAVERBOSE ) echo "Making query: $query\n";
		$data = curl_exec( self::$globalCurl_handle );
		$data = preg_replace( '/\<br\s\/\>\n\<b\>.*? on line \<b\>\d*\<\/b\>\<br\s\/\>/i', "", $data );
		$data = trim( $data );
		$xml_parser = xml_parser_create();
		xml_parse_into_struct( $xml_parser, $data, $vals );
		xml_parser_free( $xml_parser );
		$webciteID = false;
		$webciteURL = false;
		foreach( $vals as $val ) {
			if( $val['tag'] == "TIMESTAMP" && isset( $val['value'] ) ) $returnArray['archive_time'] =
				strtotime( $val['value'] );
			if( $val['tag'] == "ORIGINAL_URL" && isset( $val['value'] ) ) $returnArray['url'] = $val['value'];
			if( $val['tag'] == "REDIRECTED_TO_URL" && isset( $val['value'] ) ) $returnArray['url'] =
				$checkIfDead->sanitizeURL( $val['value'], true );
			if( $val['tag'] == "WEBCITE_ID" && isset( $val['value'] ) ) $webciteID = $val['value'];
			if( $val['tag'] == "WEBCITE_URL" && isset( $val['value'] ) ) $webciteURL = $val['value'];
			if( $val['tag'] == "RESULT" && $val['type'] == "close" ) break;
		}
		if( $webciteURL !== false ) $returnArray['archive_url'] =
			$webciteURL . "?url=" . $checkIfDead->sanitizeURL( $returnArray['url'], true );
		elseif( $webciteID !== false ) $returnArray['archive_url'] =
			"https://www.webcitation.org/" . Utilities::toBase( $webciteID, 62 ) . "?url=" . $returnArray['url'];
		$returnArray['archive_host'] = "webcite";
		$returnArray['convert_archive_url'] = true;

		DB::accessArchiveCache( $url, $returnArray['archive_url'] );

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Perma CC URL
	 *
	 * @access public
	 *
	 * @param string $url A Perma CC URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolvePermaCC( $url )
	{
		$checkIfDead = new CheckIfDead();

		permaccurlbegin:
		$returnArray                         = [];
		if( preg_match( '/\/\/perma(?:-archives\.org|\.cc)(?:\/warc)?\/([^\s\/]*)(\/\S*)?/i', $url, $match ) ) {

			if( !is_numeric( $match[1] ) ) {
				if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
					$url = $newURL;
					goto permaccurlbegin;
				}
				$queryURL = "https://api.perma.cc/v1/public/archives/" . $match[1] . "/";
				if( is_null( self::$globalCurl_handle ) ) API::initGlobalCurlHandle();
				curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
				curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
				curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $queryURL );
				curl_setopt( self::$globalCurl_handle, CURLOPT_FOLLOWLOCATION, 1 );
				if( IAVERBOSE ) echo "Making query: $queryURL\n";
				$data = curl_exec( self::$globalCurl_handle );
				$data = json_decode( $data, true );
				if( is_null( $data ) ) return $returnArray;
				if( ( $returnArray['archive_time'] =
						strtotime( $data['capture_time'] ) ) === false ) $returnArray['archive_time'] =
					strtotime( $data['creation_timestamp'] );

				$returnArray['url'] = $checkIfDead->sanitizeURL( $data['url'], true );
				$returnArray['archive_host'] = "permacc";
				$returnArray['archive_url'] =
					"https://perma-archives.org/warc/" . date( 'YmdHms', $returnArray['archive_time'] ) . "/" .
					$returnArray['url'];
				if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
				DB::accessArchiveCache( $url, $returnArray['archive_url'] );
			} else {
				$returnArray['archive_url'] = "https://perma-archives.org/warc/" . $match[1] . $match[2];
				$returnArray['url'] = $checkIfDead->sanitizeURL( $match[2], true );
				$returnArray['archive_time'] = strtotime( $match[1] );
				$returnArray['archive_host'] = "permacc";
				if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
			}
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a google web cache URL
	 *
	 * @access public
	 *
	 * @param string $url A google web cache URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveGoogle( $url )
	{
		$returnArray = [];
		$checkIfDead = new CheckIfDead();
		if( preg_match( '/(?:https?\:)?\/\/(?:webcache\.)?google(?:usercontent)?\.com\/.*?\:(?:(?:.*?\:(.*?)\+.*?)|(.*))/i',
		                $url,
		                $match
		) ) {
			$returnArray['archive_url'] = $url;
			if( !empty( $match[1] ) ) {
				$returnArray['url'] = $checkIfDead->sanitizeURL( "http://" . $match[1], true );
			} elseif( !empty( $match[2] ) ) {
				$returnArray['url'] = $checkIfDead->sanitizeURL( $match[2], true );
			}
			$returnArray['archive_time'] = "x";
			$returnArray['archive_host'] = "google";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a NLA Australia URL
	 *
	 * @access public
	 *
	 * @param string $url A NLA Australia URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveNLA( $url )
	{
		$returnArray = [];
		$checkIfDead = new CheckIfDead();
		if( preg_match( '/\/\/((?:pandora|(?:content\.)?webarchive|trove)\.)?nla\.gov\.au\/(pan\/\d{4,7}\/|nph\-wb\/|nph-arch\/\d{4}\/|gov\/(?:wayback\/)?)([a-z])?(\d{4}\-(?:[a-z]{3,9}|\d{1,2})\-\d{1,2}|\d{8}\-\d{4}|\d{4,14})\/((?:(?:https?\:)?\/\/|www\.)\S*)/i',
		                $url,
		                $match
		) ) {
			$returnArray['archive_url'] =
				"http://" . $match[1] . "nla.gov.au/" . $match[2] . ( isset( $match[3] ) ? $match[3] : "" ) .
				$match[4] . "/" . $match[5];
			//Hack.  Strtotime fails with certain date stamps
			$match[4] = preg_replace( '/jan(uary)?/i', "01", $match[4] );
			$match[4] = preg_replace( '/feb(ruary)?/i', "02", $match[4] );
			$match[4] = preg_replace( '/mar(ch)?/i', "03", $match[4] );
			$match[4] = preg_replace( '/apr(il)?/i', "04", $match[4] );
			$match[4] = preg_replace( '/may/i', "05", $match[4] );
			$match[4] = preg_replace( '/jun(e)?/i', "06", $match[4] );
			$match[4] = preg_replace( '/jul(y)?/i', "07", $match[4] );
			$match[4] = preg_replace( '/aug(ust)?/i', "08", $match[4] );
			$match[4] = preg_replace( '/sep(tember)?/i', "09", $match[4] );
			$match[4] = preg_replace( '/oct(ober)?/i', "10", $match[4] );
			$match[4] = preg_replace( '/nov(ember)?/i', "11", $match[4] );
			$match[4] = preg_replace( '/dec(ember)?/i', "12", $match[4] );
			$match[4] = strtotime( $match[4] );
			$returnArray['url'] = $checkIfDead->sanitizeURL( $match[5], true );
			$returnArray['archive_time'] = $match[4];
			$returnArray['archive_host'] = "nla";
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a Wikiwix URL
	 *
	 * @access public
	 *
	 * @param string $url A Wikiwix URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveWikiwix( $url )
	{
		$checkIfDead = new CheckIfDead();
		$returnArray = [];
		wikiwixbegin:
		if( preg_match( '/archive\.wikiwix\.com\/cache\/(\d{14})\/(.*)/i', $url, $match ) ) {
			$returnArray['archive_url']  = $url;
			$returnArray['url']          = $checkIfDead->sanitizeURL( $match[2] );
			$returnArray['archive_time'] = strtotime( $match[1] );
			$returnArray['archive_host'] = "wikiwix";
		} elseif( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
			$url = $newURL;
			goto wikiwixbegin;
		} elseif( preg_match( '/\/\/(?:www\.|archive\.)?wikiwix\.com\/cache\/(?:(?:display|index)\.php(?:.*?)?)?\?url\=(.*)/i',
		                      $url, $match
		) ) {
			$returnArray['archive_url'] =
				"http://archive.wikiwix.com/cache/?url=" . urldecode( $match[1] ) . "&apiresponse=1";
			if( is_null( self::$globalCurl_handle ) ) API::initGlobalCurlHandle();
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $returnArray['archive_url'] );
			if( IAVERBOSE ) echo "Making query: {$returnArray['archive_url']}\n";
			$data = curl_exec( self::$globalCurl_handle );
			if( $data == "can't connect db" ) return [];
			$data = json_decode( $data, true );

			if( $data['status'] >= 400 ) return [];

			$returnArray['url'] = $checkIfDead->sanitizeURL( $match[1], true );
			$returnArray['archive_time'] = $data['timestamp'];
			$returnArray['archive_url'] = $data['longformurl'];
			$returnArray['archive_host'] = "wikiwix";

			DB::accessArchiveCache( $url, $returnArray['archive_url'] );
			if( $url != $returnArray['archive_url'] ) $returnArray['convert_archive_url'] = true;
		}

		return $returnArray;
	}

	/**
	 * Retrieves URL information given a freezepage URL
	 *
	 * @access public
	 *
	 * @param string $url A freezepage URL that goes to an archive.
	 *
	 * @return array Details about the archive.
	 * @license https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
	 * @author Maximilian Doerr (Cyberpower678)
	 */
	public static function resolveFreezepage( $url )
	{
		$checkIfDead = new CheckIfDead();
		if( ( $newURL = DB::accessArchiveCache( $url ) ) !== false ) {
			return unserialize( $newURL );
		}

		$returnArray = [];
		//Try and decode the information from the URL first
		if( preg_match( '/(?:www\.)?freezepage.com\/\S*/i', $url, $match ) ) {
			if( is_null( self::$globalCurl_handle ) ) API::initGlobalCurlHandle();
			curl_setopt( self::$globalCurl_handle, CURLOPT_HTTPGET, 1 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_POST, 0 );
			curl_setopt( self::$globalCurl_handle, CURLOPT_URL, $url );
			if( IAVERBOSE ) echo "Making query: $url\n";
			$data = curl_exec( self::$globalCurl_handle );
			if( preg_match( '/\<a.*?\>((?:ftp|http).*?)\<\/a\> as of (.*?) \<a/i', $data, $match ) ) {
				$returnArray['archive_url'] = $url;
				$returnArray['url'] = $checkIfDead->sanitizeURL( htmlspecialchars_decode( $match[1] ), true );
				$returnArray['archive_time'] = strtotime( $match[2] );
				$returnArray['archive_host'] = "freezepage";
			}
			DB::accessArchiveCache( $url, serialize( $returnArray ) );
		}

		return $returnArray;
	}
}
