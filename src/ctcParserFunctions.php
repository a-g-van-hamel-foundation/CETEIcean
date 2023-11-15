<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;
use MediaWiki\PPFrame;
/* Required: */
use MediaWiki\Revision\RevisionRecord;
use Ctc\Core\ctcXmlProc;

class ctcParserFunctions {

	/* Run #cetei parser function */
	public static function runCeteiPF( $parser, $frame, $args ) {

		$xmlStr = self::getDocXmlStr( $parser, $frame, $args );
			//$xml = simplexml_load_string( $xmlStr, 'SimpleXMLElement' );
			//$xml->registerXPathNamespace("def", "http://www.tei-c.org/ns/1.0");
		$randomNo1 = rand(1000, 9999);
		$randomNo2 = rand(1000, 9999);
		$output = \Html::rawElement( 'div', [
			'id' => 'cetei-' . $randomNo1 . '-' . $randomNo2,
			'class' => 'cetei-instance cetei-rendering',
			'noparse' => true,
			'isHTML' => true
			], $xmlStr
		);

		return [ $output, 'noparse' => true, 'isHTML' => true ];

	}

	public static function runCeteiAlignPF( $parser, $frame, $params ) {
		// $xmlStr = self::getDocXmlStr( $parser, $frame, $args );
		$resourceStr = $alignCsvStr = "";
		$resourceSep = "^^";
		$valSep = ";";
		$selectors = "//ctc:xml:id[@n='***']";

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
				case 'valsep': $valSep = $value;
				break;
			}
		}

		$xmlStr = ctcAlign::align( $resourceStr, $resourceSep, $selectors, $alignCsvStr, $valSep );
		$ctcXmlProc = new ctcXmlProc();
		$xmlTransformed = $ctcXmlProc->transformXMLwithXSL( $xmlStr, null );

		$output = \Html::rawElement( 'div', [
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
	 */
	public static function runCeteiAcePF( $parser, $frame, $params ) {
		// maybe add check if CodeEditor is installed
		$out = $parser->getOutput();
		$out->addModuleStyles( [ "ext.ctc.ace.styles" ] );
		$out->addModules( [ "ext.ctc.ace" ] );
	}

	/* Get XML string as object */
	protected static function getDocXmlStr( $parser, $frame, $params ) {
		// Defaults
		$paramDoc = $paramUrl = $text = "";
		$paramSel = $paramBreak1 = $paramBreak2 = $outputDefault = null;
		// $outputDefault = '<div class="cetei-no-document-found"><i>' . wfMessage( 'cetei-no-document-found' )->parse() . '</i></div>';
		if ( $params == null || $params == 'undefined' ) {
			return $outputDefault;
		}

		foreach ( $params as $i => $param ) {
			$paramExpanded = $frame->expand($param);
			$keyValPair = explode( '=', $paramExpanded, 2 );
			if ( count( $keyValPair ) > 1 ) {
				$paramName = trim( $keyValPair[0] );
				$value = trim( $keyValPair[1] );
			} elseif ( $i == 1
					&& count( $keyValPair ) == 1
					&& trim($keyValPair[0]) !== 'doc' ) {
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
			}
		}    

		// cf. ctcUtils::getContentFromPageTitleOrUrl()
		if ( $paramDoc !== '' ) {
			$text = ctcUtils::getContentfromTitleStr( $paramDoc, "" );
		} else if ( $paramUrl !== '' ) {
			$allowUrl = \RequestContext::getMain()->getConfig()->get( 'CeteiAllowUrl' );
			$defaultNoUrl = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><p>URLs not allowed.</p></text></TEI>';
			$text = ( $allowUrl == true ) ? ctcUtils::getContentfromUrl( $paramUrl, "" ) : $defaultNoUrl ;
		} else {
			return;
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

		$output = str_replace(array("\r\n", "\r", "\n", "  " ), " ", trim($output) );
		return $output;
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
	 * Extract part of MS using XPath expression
	 */
	public static function getExcerptDocXmlStr( $paramDoc, $paramSel, $text ) {
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
		} else {
			//do nothing?
		}
		$teiOpen = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text type="excerpts">';
		$teiClose = '</text></TEI>';
		$text = $teiOpen . $excerptStr . $teiClose;
		// weird we already did this
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

	static private function getDtdContent () {
		// @todo Might be useful
	}

}

