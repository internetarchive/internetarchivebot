<?php

/**
 * Acceptance tests for InternetArchiveBot
 *
 * These work by logging in as a bot (not necessarily IABot), writing acceptance-source.txt
 *   to a test page on wiki, then running IABot against it and comparing the results
 *   with the acceptance-result.txt
 *
 * HOW TO USE:
 * 1) Configure the acceptance test bot username/password in config.php ( see config.sample.php )
 * 2) In deadlink.config.local.inc.php, set $debugStyle to 'test', and $debugPage to:
 *      array( 'title'=>"User:MyBot/acceptance-source", 'pageid'=>0 );
 *    where 'MyBot' is the bot configured in config.php
 * 3) Add your test cases to ./test_cases/acceptance-source.txt, and
 *      and what the result should be to ./test_cases/acceptance-result.txt
 * 4) run `phpunit acceptanceTests.php`
 */

require_once dirname( __FILE__ ) . '/config.php';
require_once dirname( __FILE__ ) . '/botclasses.php';

class acceptanceTests extends PHPUnit_Framework_TestCase {
	// Text files representing the test cases. Filenames should end in .txt
	//   and be located in the ./test_cases directory
	const TEST_CASE_SOURCE = 'acceptance-source';
	const TEST_CASE_RESULT = 'acceptance-result';

	/**
	 * Initialize botclasses and login Community_Tech_bot
	 */
	public function setUp() {
		$this->api = new wikipedia( 'https://test.wikipedia.org/w/api.php' );
		$this->api->login( ACCEPTANCE_USERNAME, ACCEPTANCE_PASSWORD );
		$this->createTestCases();
	}

	/**
	 * Creates the test case page on the configured wiki, located within Community_Tech_bot's userspace
	 */
	private function createTestCases() {
		$page = $this->getTestPageName( self::TEST_CASE_SOURCE );
		$text = $this->getTestCaseContents( self::TEST_CASE_SOURCE );
		$this->api->edit( $page, $text );
	}

	/**
	 * Get the wiki path for the given test page
	 *
	 * @param  string $page Page name
	 *
	 * @return string Given page name within Community_Tech_bot's ( or ACCEPTANCE_USERNAME ) userspace
	 */
	private function getTestPageName( $page ) {
		return 'User:' . ACCEPTANCE_USERNAME . "/$page";
	}

	/**
	 * Get the contents of the given test case from the file system
	 * $file should be located in ./test_cases and have a .txt extension
	 *
	 * @param  string $file File name without extension
	 *
	 * @return string Contents of test case
	 */
	private function getTestCaseContents( $file ) {
		return file_get_contents( "./test_cases/$file.txt" );
	}

	/**
	 * Run the bot on the configured page in deadlink.config.local.inc.php
	 * The configured page should be the same as $this->getTestPageName( self::TEST_CASE_SOURCE )
	 */
	public function testPages() {
		// Require deadlink.php to start the bot
		require dirname( __FILE__ ) . '/../../deadlink.php';

		// get the page the bot edited
		$testPageName = $this->getTestPageName( self::TEST_CASE_SOURCE );
		$testPageContents = $this->api->getPage( $testPageName );
		$testCaseContents = $this->getTestCaseContents( self::TEST_CASE_RESULT );

		// remove archivedates and wayback dates as they may differ on every run
		$testPageContents =
			preg_replace( '/archivedate\s*=\s*\d{4}-\d{2}-\d{2}|date\s*=\s*\d{14}/', 'PLACEHOLDER', $testPageContents );
		$testCaseContents =
			preg_replace( '/archivedate\s*=\s*\d{4}-\d{2}-\d{2}|date\s*=\s*\d{14}/', 'PLACEHOLDER', $testCaseContents );

		// remove trivial whitespace that may be the end of the lines (text editors may strip them off, and the bot does not)
		$testPageContents = preg_replace( '/\s+\n/', "\n", $testPageContents );
		$testCaseContents = preg_replace( '/\s+\n/', "\n", $testCaseContents );

		// compare what is expected with the new version of the page
		$this->assertEquals( $testCaseContents, $testPageContents );
	}
}
