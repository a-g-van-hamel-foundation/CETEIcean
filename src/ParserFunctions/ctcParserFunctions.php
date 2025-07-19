<?php

namespace Ctc\ParserFunctions;

use Parser;
use PPFrame;
use RequestContext;
//use MediaWiki\MediaWikiServices;
//use MediaWiki\MainConfigNames;
//use OutputPage;
//use ParserOutput;
use Html;
/* Required? */
//use MediaWiki\Revision\RevisionRecord;
use Ctc\Core\ctcUtils;
use Ctc\Process\ctcXmlProc;
use Ctc\Process\ctcXmlExtract;
use Ctc\ParserFunctions\ctcAlign;
use Ctc\ParserFunctions\ctcParserFunctionsInfo;

class ctcParserFunctions {

	/* Run #cetei parser function */
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
		$html = Html::rawElement( 'div', [
			'id' => 'cetei-' . $randomNo1 . '-' . $randomNo2,
			'class' => 'cetei-instance cetei-rendering',
			'noparse' => true,
			'isHTML' => true
			], $xmlStr
		);

		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

	public static function runCeteiAlignPF( Parser $parser, PPFrame $frame, $params ) {
		// $xmlStr = self::getDocXmlStr( $parser, $frame, $args );
		$resourceStr = $alignCsvStr = "";
		$resourceSep = "^^";
		$valSep = ";";
		$selectors = "//ctc:xml:id[@n='***']";
		$rangeSep = null;
		$action = "normal";

		foreach ( $params as $i => $param ) {
			$paramExpanded = $frame->expand($param);
			$keyValPair = explode( '=', $paramExpanded, 2 );
			if ( count( $keyValPair ) > 1 ) {
				$paramName = trim( $keyValPair[0] );
				$value = trim( $keyValPair[1] );
			} else {
				$paramName = null;
				$value = trim( $paramExpanded );
			}
			/* */
			switch ( $paramName ) {
				case 'resources': $resourceStr = $value;
				break;
				case 'resourcesep': $resourceSep = $value;
				break;
				case 'selectors' : $selectors = $value;
				break;
				case 'align': $alignCsvStr = $value;
				break;
				case 'map': $alignCsvStr = $value;
				break;
				case 'valsep': $valSep = $value;
				break;
				case 'rangesep': $rangeSep = $value;
				break;
				case 'action': $action = $value;
				break;
			}
		}

		if ( $action == 'info' ) {
			$info = ctcParserFunctionsInfo::getParserFunctionInfo( 'cetei-align' );
			return $parser->recursiveTagParse( $info );
		}

		$xmlStr = ctcAlign::align( $resourceStr, $resourceSep, $selectors, $alignCsvStr, $valSep, $rangeSep );
		$ctcXmlProc = new ctcXmlProc();
		$xmlTransformed = $ctcXmlProc->transformXMLwithXSL( $xmlStr, null );

		$output = Html::rawElement( 'div', [
			//'id' => 'cetei-' . $randomNo1 . '-' . $randomNo2,
			'class' => 'cetei-instance cetei-rendering',
			'noparse' => true,
			'isHTML' => true
			], $xmlTransformed
		);
		return [ $output, 'noparse' => true, 'isHTML' => true ];

	}

	/**
	 * Load js - {{#cetei-ace:}}
	 * @todo maybe add check if CodeEditor is installed.
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
		// Defaults
		$paramDoc = $paramUrl = $text = "";
		$paramSel = $paramBreak1 = $paramBreak2 = null;
		$action = "normal";
		// $outputDefault = '<div class="cetei-no-document-found"><i>' . wfMessage( 'cetei-no-document-found' )->parse() . '</i></div>';
		if ( $params == null || $params == 'undefined' ) {
			return null;
		}

		foreach ( $params as $i => $param ) {
			$paramExpanded = $frame->expand( $param );
			$keyValPair = explode( '=', $paramExpanded, 2 );
			if ( count( $keyValPair ) > 1 ) {
				$paramName = trim( $keyValPair[0] );
				$value = trim( $keyValPair[1] );
			} elseif ( $i == 1
					&& count( $keyValPair ) == 1
					&& trim( $keyValPair[0] ) !== 'doc' ) {
				$paramName = 'doc'; // for shorthand {{#cetei:<doc>}}
				$value = trim( $keyValPair[0] );
			} else {
				$paramName = null;
				$value = trim( $paramExpanded );
			}
			/* */
			switch ( $paramName ) {
				case 'doc': $paramDoc = $value;
				break;
				case 'url': $paramUrl = $value;
				break;
				case 'sel': $paramSel = $value;
				break;
				case 'break1': $paramBreak1 = $value;
				break;
				case 'break2': $paramBreak2 = $value;
				break;
				case 'action': $action = $value;
				break;
			}
		}

		if ( $action == 'info' ) {
			$info = ctcParserFunctionsInfo::getParserFunctionInfo( 'cetei' );
			return $parser->recursiveTagParse( $info );
		}

		// cf. ctcUtils::getContentFromPageTitleOrUrl()
		if ( $paramDoc !== '' ) {
			$text = ctcUtils::getContentfromTitleStr( $paramDoc, "" );
		} else if ( $paramUrl !== '' ) {
			$allowUrl = RequestContext::getMain()->getConfig()->get( 'CeteiAllowUrl' );
			$defaultNoUrl = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><p>URLs not allowed.</p></text></TEI>';
			$text = ( $allowUrl == true ) ? ctcUtils::getContentfromUrl( $paramUrl, "" ) : $defaultNoUrl ;
		} else {
			return null;
		}

		if ( $paramBreak1 !== null && $paramBreak2 !== null ) {
			// Experimentally extract and repair
			$output = self::getFragmentXmlStr ( $text, $paramBreak1, $paramBreak2, $paramDoc );
		} else if ( $paramSel == null ) {
			// Full document
			$output = self::getFullDocXmlStr( $paramDoc, $text );
		} else {
			// XPath selection
			$output = self::getExcerptDocXmlStr ( $paramDoc, $paramSel, $text );
		}

		$res = null;
		if ( $output !== null && $output !== false ) {
			$res = str_replace(array("\r\n", "\r", "\n", "  " ), " ", trim($output) );
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
	public static function getExcerptDocXmlStr( $paramDoc, $paramSel, $text ): mixed {
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

}
