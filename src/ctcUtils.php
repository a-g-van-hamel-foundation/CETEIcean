<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;
use MediaWiki\Revision\RevisionRecord;

/**
 * Collection of helper methods
 */

class ctcUtils {

	/**
	 * Check if present user is in the 'user' group
	 * Originally part of ctcRender class
	 * @todo: change hardcoded 'user' to language-independent value?
	 * @todo: check whether user has editing rights = preferred action
	*/
	public static function isUser() {
		$presentUser = \RequestContext::getMain()->getUser();
		$presentUserGroups = [];
		$presentUserGroups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $presentUser );
		if ( in_array( 'user', $presentUserGroups ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if TEI page ($title) has an associated /doc subpage 
	 */
	public static function hasDocPage( $title ) {
		if ( $title ) {
			$docPageStr = $title . '/doc';
		} else {
			$out = \RequestContext::getMain()->getOutput();
			$docPageStr = $out->getTitle() . '/doc';
		}
		$docPageObj = \Title::newFromText( $docPageStr );
		$docPageID = $docPageObj->getArticleID() ;
		// if Page ID equals 0, page does not exist
		if ( $docPageID !== 0 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Determines if reference is to wiki page or URL resource
	 * And retrieves text accordingly
	 */
	public static function getContentFromPageTitleOrUrl( string $doc, string $default = "" ): string {
		$docType = ( strpos( $doc, "http" ) === 0 ) ? "url" : "wikipage";
		$xmlOpeningTag = '<?xml version="1.0" encoding="UTF-8"?>';
		if ( $docType == "wikipage" ) {
			$text = self::getContentfromTitleStr( $doc, $default );
		} else if ( $docType == "url" ) {
			$allowUrl = \RequestContext::getMain()->getConfig()->get( 'CeteiAllowUrl' );
			$defaultNoUrl = `<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><p>URLs not allowed.</p></text></TEI>`;
			$text = ( $allowUrl == true ) ? self::getContentfromUrl( $doc, $default ) : $defaultNoUrl ;
	   } else {
			return $default;
		}
		return $text;
	}

	/** 
	 * Utility - accept title (string) and return fulltext content
	 * Actually belongs to ctcUtils
	 */
	public static function getContentfromTitleStr( string $titleStr, string $default = "" ): string {
		// maybe resolve redirects first?
		$titleObj = \Title::newFromText( $titleStr );
		if ( $titleObj == null ) {
			self::printRawText( 'Could not fimd page...' );
			return $default;
		}
		$wikiObj = \WikiPage::factory( $titleObj );
		// https://www.mediawiki.org/wiki/Manual:WikiPage.php
		$wikiContent = $wikiObj->getContent( RevisionRecord::RAW );
		$text = '';
		$text = \ContentHandler::getContentText( $wikiContent );

		/* Important */
		$ctcXmlProc = new ctcXmlProc();
		$output = ctcXmlProc::removeDocType( $text );

		return $output;
	}

	/* Accept URL and return fulltext content */
	public static function getContentfromUrl( string $url, string $default = "" ): string {
		//$uri = urlencode( $url );
		$text = file_get_contents( $url );
		/* Important */
		if ( $text == false ) {
			$output = $default;
		} else {
			$ctcXmlProc = new ctcXmlProc();
			$output = ctcXmlProc::removeDocType( $text );
		}
		return $output;
	}

	/**
	 * Unused
	 */
	public static function renderLink(
		\Title $target, $nsprefix, string $pageName, string $label
	) {
		//$nsprefix = NS_MAIN;
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$link = $linkRenderer->makeLink( new TitleValue( $nsprefix, $pageName ), $label );
		return $link;
	}

	public static function removeInlineComments( $str ) {
		$str = preg_replace( '/<!--.*?-->/s', '', $str );
		return $str;
	}

	public static function isSyntaxHighlightAvailable(): bool {
		$registry = \ExtensionRegistry::getInstance();
		if ( $registry->isLoaded( 'SyntaxHighlight' ) == true || $registry->isLoaded( 'highlight.js integration' ) == true ) {
			return true;
		}
		return false;
	}

	/**
	 * Converts array to JSON string to be displayed on the wiki.
	 */
	public static function showArrayAsJsonInWikiText( array $arr ): string {
		$registry = \ExtensionRegistry::getInstance();
		if ( $registry->isLoaded( 'SyntaxHighlight' ) == true || $registry->isLoaded( 'highlight.js integration' ) == true )  {
			$str = "<syntaxhighlight lang='json'>" . json_encode( $arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "</syntaxhighlight>";
		} else {
			$str = "<pre lang='json'>" . json_encode( $arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "</pre>";
		}
		return $str;
	}

	/**
	 * @deprecated
	 */
	public static function getTemporarySpinner() {
		$tempSpinner = \Html::element( 'span', [
			'class' => 'spinner-dual-ring'
		], '' );
		return $tempSpinner;
	}

	/**
	 * Convenience methods for testing in development only.
	 */
	public static function printArray( $arr ) {
		print_r( "<pre>" );
		print_r( $arr );
		print_r( "</pre>" );
	}
	public static function printRawText( $str ) {
		print_r( "<pre>" );
		print_r( htmlspecialchars($str) );
		print_r( "</pre>" );
	}

}
