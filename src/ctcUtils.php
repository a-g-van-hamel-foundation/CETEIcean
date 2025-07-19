<?php

/**
 * Collection of utility methods
 */

namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
//use MediaWiki\MainConfigNames;
//use MediaWiki\OutputPage;
//use MediaWiki\ParserOutput;
use MediaWiki\Revision\RevisionRecord;
use OutputPage;
use RequestContext;
use Title;
use MediaWiki\Revision\SlotRecord;
use WikiPage;
use TitleValue;
use ContentHandler;
use Html;
use ExtensionRegistry;
use Ctc\Process\ctcXmlProc;

class ctcUtils {

	/**
	 * Check if present user is in the 'user' group
	 * Originally part of ctcRender class
	 * @todo: change hardcoded 'user' to language-independent value?
	 * @todo: check whether user has editing rights = preferred action
	 * @param RequestContext|null $context
	 * @return bool
	 */
	public static function isUser( $context = null ) {
		if( $context === null ) {
			$context = RequestContext::getMain();
		}
		$presentUser = $context->getUser();
		$presentUserGroups = [];
		$presentUserGroups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $presentUser );
		return in_array( "user", $presentUserGroups ) ? true : false;
	}

	/**
	 * Check if TEI page ($title) has an associated /doc subpage 
	 * @param string|bool $title
	 * @param OutputPage|null $outputPage
	 */
	public static function hasDocPage(
		mixed $title = false,
		mixed $outputPage = null
	) {
		// page name of /doc page
		if ( $title ) {
			$docPageStr = $title . "/doc";
		} elseif( $outputPage !== null ) {
			$docPageStr = $outputPage->getTitle() . "/doc";
		} else {
			$outputPage = RequestContext::getMain()->getOutput();
			if ( $outputPage === null || $outputPage === false ) {
				return false;
			}
			$docPageStr = $outputPage->getTitle() . "/doc";
		}
		// Check if it exists. If Page ID=0, page does not exist
		$docPageObj = Title::newFromText( $docPageStr );
		$docPageID = $docPageObj->getArticleID();
		return ( $docPageID !== 0 ) ? true : false;
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
			$allowUrl = RequestContext::getMain()->getConfig()->get( 'CeteiAllowUrl' );
			$defaultNoUrl = `<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><p>URLs not allowed.</p></text></TEI>`;
			$text = ( $allowUrl == true ) ? self::getContentfromUrl( $doc, $default ) : $defaultNoUrl ;
	   } else {
			return $default;
		}
		return $text;
	}

	/** 
	 * Utility - accept title (string) and return full-text content
	 * Intended for XML content
	 */
	public static function getContentfromTitleStr(
		string $titleStr,
		string $default = ""
	): string {
		// maybe resolve redirects first?
		$titleObj = Title::newFromText( $titleStr );
		if ( $titleObj == null ) {
			//self::printRawText( 'Could not find page...' );
			return $default;
		}
		$wikiObj = WikiPage::factory( $titleObj );
		// https://www.mediawiki.org/wiki/Manual:WikiPage.php
		$wikiContent = $wikiObj->getContent( RevisionRecord::RAW );
		$text = '';
		$text = ContentHandler::getContentText( $wikiContent );

		/* Important */
		//$ctcXmlProc = new ctcXmlProc();
		$output = ctcXmlProc::removeDocType( $text );

		return $output;
	}

	/**
	 * Summary of getRawContentFromTitleObj
	 * @param Title $titleObj
	 * @param string $slotName
	 * @return string|bool
	 */
	public static function getRawContentFromTitleObj(
		Title $titleObj,
		string $slotName = "main"
	) {
		$article = new \Article( $titleObj );
		//$title = $article->getTitle();
		$revRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
            ->getRevisionByTitle( $titleObj, $article->getRevIdFetched() );
		if ( !$revRecord ) {
			return false;
		}

		$webRequest = $article->getContext()->getRequest();
		if ( $slotName === "main" || $slotName === "" ) {
			// Get normalised name of main slot
			$slot = $webRequest->getText( "slot", SlotRecord::MAIN );
		} else {
			$slot = $slotName;
		}

		// Ensure we get latest revision
		$lastmod = wfTimestamp( TS_RFC2822, $revRecord->getTimestamp() );
		$webRequest->response()->header( "Last-modified: $lastmod" );
		
		$content = $revRecord->hasSlot( $slot )
			? $revRecord->getContent( $slot )
			: null;
		return $content !== null
			? $content->getText()
			: "";
	}

	/**
	 * Accept URL and return fulltext content.
	 */
	public static function getContentfromUrl(
		string $url,
		string $default = ""
	): string {
		//$uri = urlencode( $url );
		$text = file_get_contents( $url );
		/* Important */
		if ( $text == false ) {
			$output = $default;
		} else {
			//$ctcXmlProc = new ctcXmlProc();
			$output = ctcXmlProc::removeDocType( $text );
		}
		return $output;
	}

	/**
	 * Unused
	 */
	public static function renderLink( Title $target, $nsprefix, string $pageName, string $label
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

	/**
	 * Checks if either the SyntaxHighlight extension or 
	 * highlight.js integration is installed.
	 * @return bool
	 */
	public static function isSyntaxHighlightAvailable(): bool {
		$registry = ExtensionRegistry::getInstance();
		if ( $registry->isLoaded( "SyntaxHighlight" ) == true || $registry->isLoaded( "highlight.js integration" ) == true ) {
			return true;
		}
		return false;
	}

	/**
	 * Converts array to JSON string to be displayed on the wiki.
	 */
	public static function showArrayAsJsonInWikiText( array $arr ): string {
		$registry = ExtensionRegistry::getInstance();
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
		return Html::element( "span", [ "class" => "spinner-dual-ring" ], "" );
	}

	/**
	 * Convenience methods for testing in development only.
	 * @internal
	 */
	public static function printArray( $arr ) {
		print_r( "<pre>" );
		print_r( $arr );
		print_r( "</pre>" );
	}
	/**
	 * @internal
	 * @param mixed $str
	 * @return void
	 */
	public static function printRawText( $str ) {
		print_r( "<pre>" );
		print_r( htmlspecialchars($str) );
		print_r( "</pre>" );
	}

}
