<?php

/**
 * Class for the #cetei and #cetei-ace parser functions
 */

namespace Ctc\ParserFunctions;

use Parser;
use PPFrame;
use RequestContext;
use MediaWiki\MediaWikiServices;
//use MediaWiki\MainConfigNames;
//use OutputPage;
//use ParserOutput;
use Html;
/* Required? */
//use MediaWiki\Revision\RevisionRecord;
use Ctc\Core\ctcUtils;
use Ctc\Content\ctcSearchableContentUtils;
use Ctc\Process\ctcXmlProc;
use Ctc\Process\ctcXmlExtract;
use Ctc\ParserFunctions\ctcAlign;
use Ctc\ParserFunctions\ctcParserFunctionUtils;
use Ctc\ParserFunctions\ctcParserFunctionsInfo;
use Ctc\SMW\ctcSMWStore;

class ctcParserFunctions {

	/**
	 * Run #cetei parser function
	 */
	public static function runCeteiPF( Parser $parser, PPFrame $frame, $args ) {
		$xmlStr = self::getDocXmlStr( $parser, $frame, $args );

		if ( $xmlStr == null ) {
			// @todo 
			$xmlStr = "";
		}
			//$xml = simplexml_load_string( $xmlStr, 'SimpleXMLElement' );
			//$xml->registerXPathNamespace("def", "http://www.tei-c.org/ns/1.0");
		$randomNo1 = rand(1000, 9999);
		$randomNo2 = rand(1000, 9999);
		$html = Html::rawElement( "div",
			[
				'id' => 'cetei-' . $randomNo1 . '-' . $randomNo2,
				'class' => 'cetei-instance cetei-rendering',
				'noparse' => true,
				'isHTML' => true
			],
			$xmlStr
		);

		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * {{#cetei-ace:}}
	 * Load Ace js - 
	 * @todo maybe add check if CodeEditor is installed?
	 */
	public static function runCeteiAcePF( $parser, $frame, $params ) {
		$out = $parser->getOutput();
		$out->addModuleStyles( [ "ext.ctc.ace.styles" ] );
		$out->addModules( [ "ext.ctc.ace" ] );
	}

	/**
	 * Get XML string.
	 * Null if params are wanting or an error was returned
	 * @return string|null
	 **/
	protected static function getDocXmlStr( Parser $parser, PPFrame $frame, $params ): string|null {
		if ( $params == null || $params == 'undefined' ) {
			return null;
		}

		// @todo
		$paramsAllowed = [
			"doc" => "",
			"url" => "",
			"sel" => null,
			"break1" => null,
			"break2" => null,
			"action" => "normal",
			"text" => "",
			// @todo
			"property" => "Searchstring",
			"term" => null
		];
		[ $paramDoc, $paramUrl, $paramSel, $paramBreak1, $paramBreak2, $action, $text, $propertyName, $searchTerm ] = array_values( ctcParserFunctionUtils::extractParams( $frame, $params, $paramsAllowed ) );

		// $outputDefault = '<div class="cetei-no-document-found"><i>' . wfMessage( 'cetei-no-document-found' )->parse() . '</i></div>';

		if ( $action === "info" ) {
			$info = ctcParserFunctionsInfo::getParserFunctionInfo( "cetei" );
			return $parser->recursiveTagParse( $info );
		}

		// cf. ctcUtils::getContentFromPageTitleOrUrl()
		if ( $paramDoc !== "" ) {
			$text = ctcUtils::getContentfromTitleStr( $paramDoc, "" );
		} elseif ( $paramUrl !== "" ) {
			$allowUrl = RequestContext::getMain()->getConfig()->get( "CeteiAllowUrl" );
			$defaultNoUrl = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><p>URLs not allowed.</p></text></TEI>';
			$text = ( $allowUrl == true ) ? ctcUtils::getContentfromUrl( $paramUrl, "" ) : $defaultNoUrl ;
		} else {
			return null;
		}

		if( $action === "semantifychunks" ) {
			// @todo Experimental, work in progress
			self::handleSemantifyChunksAction( $text, $propertyName, $parser );
			// No visible output required
			$output = null;
		} elseif( $action === "semantifyexcerpts" ) {
			// @todo Work in progress - store XPath selections
			$ctcSMWStore = new ctcSMWStore();
			$title = $parser->getPage();
			$ctcSMWStore->createExcerptsAndSemantify( $title, $text, $paramSel, $propertyName );
			// No visible output
			$output = null;
		} elseif ( $paramBreak1 !== null && $paramBreak2 !== null ) {
			// Experimentally extract and repair
			// @todo Consider Tidy repair methods instead
			$output = self::getFragmentXmlStr ( $text, $paramBreak1, $paramBreak2, $paramDoc );
		} elseif( $paramSel !== null ) {
			// XPath selection
			$output = self::getExcerptDocXmlStr( $paramDoc, $paramSel, $text );
		} else {
			// Full document
			if( $action === "flatten" || $action === "highlight" ) {
				// If any of these actions will be run later,
				// it is more efficient to take the original xml string
				$output = $text;
			} else {
				$output = self::getFullDocXmlStr( $paramDoc, $text );
			}
		}

		// Optional actions
		if( $action === "highlight" ) {
			return self::handleHighlightAction( $output, $searchTerm );
		} elseif( $action === "flatten" ) {
			// @todo Should this be an official feature? Currently useful for testing.
			$ctcSearchableContentUtils = new ctcSearchableContentUtils();
			$text = $ctcSearchableContentUtils->transformXMLForSearch( $output, true );
			return $text;
		}

		$res = null;
		if ( $output !== null && $output !== false ) {
			$res = str_replace(array( "\r\n", "\r", "\n", "  " ), " ", trim($output) );
		}
		return $res;
	}

	/**
	 * Get full TEI XML document
	 * @param 
	 * @param string text
	 */
	public static function getFullDocXmlStr( $paramDoc, $text ) {
		$ctcXmlProc = new ctcXmlProc();
		$text = ctcXmlProc::addDocType( $text );
		$output = $ctcXmlProc->transformXMLwithXSL( $text, null );
		return $output;
	}

	/**
	 * Extract part of MS using XPath expression.
	 * Return empty document if no excerpt was created.
	 */
	public static function getExcerptDocXmlStr(
		$paramDoc,
		string $paramSel,
		string $text
	): mixed {
		$ctcXmlProc = new ctcXmlProc();
		$text = ctcXmlProc::addDocType( $text );
		$excerpts = [];
		//$seltest = "//ctc:p[@xml:id='p1']";
		$excerpts = ctcXmlProc::getExcerpts( $text, $paramSel );
		$excerptStr = '';
		if ( $excerpts !== false ) {
			foreach ( $excerpts as $excerpt ) {
				$excerptStr .= '<div type="excerpt">'. $excerpt->asXml() . '</div>';
			}
		}

		// Prepare and transform excerpt
		// involves treating it as a new document
		$text = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text type="excerpts">' . $excerptStr . '</text></TEI>';
		$text = ctcXmlProc::addDocType( $text );
		$output = $ctcXmlProc->transformXMLwithXSL( $text, null );

		return $output;
	}

	/**
	 * Experimental attempt to extract fragment between self-closing tags,
	 * repair the XML and return result
	 * 
	 * @param string text
	 * @param string break1
	 * @param string break2
	 * @param string paramDoc // not used
	 */
	public static function getFragmentXmlStr ( $text, $break1, $break2, $paramDoc ) {
		$extract = ctcXmlExtract::extractFragmentFromXml( $text, $break1, $break2 );
		if ( gettype ( $extract ) !== 'string' ) {
			return;
		}
		$missingTags = ctcXmlExtract::repairXml( $extract );

		$teiOpen = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><teiHeader></teiHeader><text type="fragment">';
		$teiClose = '</text></TEI>';
		$restoredXml = $teiOpen . $missingTags[0] . $extract . $missingTags[1] . $teiClose;

		$ctcXmlProc = new ctcXmlProc();
		$text = ctcXmlProc::addDocType( $restoredXml );

		$output = $ctcXmlProc->transformXMLwithXSL( $text, null );
		return $output;
	}

	/**
	 * See also ctcHighlight
	 * @param string|null $text - xml string
	 * @param mixed $searchTerm
	 * @return string
	 */
	private static function handleHighlightAction( string $text, mixed $searchTerm ) {
		if ( $searchTerm === null || $searchTerm === "" ) {
			return "";
		}
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		$text = $ctcSearchableContentUtils->transformXMLForSearch( $text, true );
		$extracts = $ctcSearchableContentUtils->highlightTerm( $text, $searchTerm, "extracts" );
		$res = "";
		foreach( $extracts as $extract ) {
			$res .= "<div class='cetei-search-extract'>$extract</div>";
		}
		return $res;
	}

	private static function handleSemantifyChunksAction( $text, $propertyName, $parser ) {
		// Transform full document using XSL designed for search
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		$text = $ctcSearchableContentUtils->transformXMLForSearch( $text, true );

		// Create chunks and add them as values to property
		$ctcSMWStore = new ctcSMWStore();
		$title = $parser->getPage();
		$ctcSMWStore->createChunksAndSemantify( $parser, $text, $title, $propertyName );
	}

}
