<?php
/**
 * Require a space after "//" on every line of a comment block.
 *
 * MediaWiki's SpaceBeforeSingleLineComment sniff only checks the first line of
 * consecutive "//" comments. This checks the lines after it. Extra spaces are
 * still allowed, so comment text can be indented.
 */

namespace IABot\Sniffs;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class CommentBlockSpacingSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_COMMENT ];
	}

	/**
	 * @inheritDoc
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$content = $tokens[$stackPtr]['content'];

		// Only "//" comments; "///" and "//" with no text are left alone
		if ( strncmp( $content, '//', 2 ) !== 0 || strncmp( $content, '///', 3 ) === 0 ) {
			return;
		}
		$text = rtrim( substr( $content, 2 ) );
		if ( $text === '' || $text[0] === ' ' || $text[0] === "\t" ) {
			return;
		}

		// The first line of a block is checked by MediaWiki's own sniff
		$prev = $phpcsFile->findPrevious( T_WHITESPACE, $stackPtr - 1, null, true );
		if ( $prev === false || $tokens[$prev]['code'] !== T_COMMENT ) {
			return;
		}

		$fix = $phpcsFile->addFixableWarning(
			'Single space expected between "//" and comment',
			$stackPtr,
			'NoSpace'
		);
		if ( $fix ) {
			$phpcsFile->fixer->replaceToken( $stackPtr, '// ' . substr( $content, 2 ) );
		}
	}
}
