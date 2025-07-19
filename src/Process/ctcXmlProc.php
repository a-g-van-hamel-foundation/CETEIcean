<?php

/**
 * Methods for converting between XML, PHP arrays and HTML output
 * Reads, extracts and modifies XML.
 */

namespace Ctc\Process;

//use MediaWiki\MediaWikiServices;
use RequestContext;

class ctcXmlProc {

	public $errorMsg = "";
	
	/**
	 * @todo print errors to designated area
	 * @todo maybe convert to dynamic function
	 * @return SimpleXMLElement|bool
	 */
	private static function getSimpleXML( $xmlString, $nsprefix = "ctc" ) {
		$useErrors = libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xmlString, 'SimpleXMLElement' );
			// @todo maybe or die("Error: Cannot create object");
		if ( $xml === false ) {
			foreach (libxml_get_errors() as $error) {
				$message = "<div>line " . $error->line . ". " . $error->message . "</div>";
				// this->errorMsg .= $message;
				print_r( $message );
			}
			libxml_clear_errors();
			return false;
		}
		$xml->registerXPathNamespace( $nsprefix, "http://www.tei-c.org/ns/1.0");
		return $xml;
	}

	/**
	 * Get the full SimpleXMLElement
	 * @return SimpleXMLElement|bool
	 */
	public static function getFullXML( $xmlString, $nsprefix = "ctc" ) {
		$xml = self::getSimpleXML( $xmlString, $nsprefix );
		return $xml;
	}

	/**
	 * Fetch the title from the TEI Header.
	 * @todo concat multiple titles if available
	 * @return string|bool
	 */
	public function getHeaderTitle( $xmlString ) {
		$xml = self::getSimpleXML( $xmlString );
		if ( $xml == false ) {
			return false;
		}
		$headerTitle = $xml->xpath("//ctc:teiHeader//ctc:title");
		if ( count($headerTitle) > 0 ) {
			foreach ($headerTitle as $title) {
				$value = strip_tags( $title->asXml() );
				// return the first title only.
				return $value;
			}
		}
		return false;
	}

	/**
	 * Get excerpt from XML string.
	 * @return array|false
	 */
	public static function getExcerpts( $xmlString, $selector = "//ctc:p[@n='2']" ) {
		$xml = self::getSimpleXML( $xmlString );
		if ( $xml == false ) {
			return false;
		}
		$selectionArr = $xml->xpath( $selector );
		if ( $selectionArr !== null && $selectionArr !== 'undefined' ) {
			return $selectionArr;
		} else {
			return false;
		}
	}

	/**
	 * Returns true/false depending on presence of TEI Header
	 * @return bool
	 */
	public function hasTEIHeader( $xmlString ): bool {
		$xml = self::getSimpleXML( $xmlString, "ctc" );
		if ( $xml == false ) {
			return false;
		}
		$teiHeader = $xml->xpath( "//ctc:teiHeader" );
		if ( gettype($teiHeader) == 'array' && count( $teiHeader ) > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 
	 */
	public static function removeAndAddDocType( $xmlStr ) {
		$cleanStr = self::removeDocType( $xmlStr );
		$res = self::addDocType( $cleanStr );
		return $res;
	}

	/**
	 * Security measure: remove DOCTYPE
	 */
	public static function removeDocType ( $xmlStr ) {
		$patternEntity = "/(?i)<!ENTITY[^<>]*>/";
		$patternDoctype = "/(?i)<!DOCTYPE[^<>]*(?:<[^<>]*>[^<>]*|)+?>/";
		$output = preg_replace( $patternEntity, "", $xmlStr );
		$output = preg_replace( $patternDoctype, "", $output );
		return $output;
	}

	public static function addDocType( $xmlStr ) {
		global $IP;
		$config = RequestContext::getMain()->getConfig();
		$dtd = $config->get( 'CeteiDTD' );
		$entitiesPath = $IP . $dtd;
		if (file_exists( $entitiesPath )) {
			$entitiesStr = file_get_contents( $entitiesPath );
			$pattern = "/(?i)<\?xml .*?>/";
			$openingTagInt = preg_match( $pattern, $xmlStr, $matches );
			if ( $openingTagInt > 0 ) {
				$xmlStr = preg_replace( $pattern, "", $xmlStr);
				$output = $matches[0] . $entitiesStr . $xmlStr;
			} else {
				$output = $entitiesStr . $xmlStr;
			}
			return $output;
		} else {
			return $xmlStr;
		}
	}

	/**
	 * Transform the XML string with our XSL stylesheet
	 * 
	 * @param string|null $xmlStr
	 * @param string|null $xmlPath
	 **/
	public static function transformXMLwithXSL( $xmlStr = null, $xmlPath = null ) {
		$config = RequestContext::getMain()->getConfig();
		global $IP;
		$xslRelPath = $config->get( 'CeteiXsl' );
		$xslPath = $IP . $xslRelPath;
		$domDoc = new \DOMDocument();
		$allowsEntitySubst = $config->get( 'CeteiAllowEntitySubstitution' );
		$domDoc->substituteEntities = $allowsEntitySubst; //boolean
		$domDoc->resolveExternals = false;
		//$loadOptions = ( $allowsEntitySubst == true ) ? LIBXML_NOENT : null;
		$xsltProc = new \XSLTProcessor();
		$domDoc->load( $xslPath );
		$xsltProc->importStyleSheet( $domDoc );
		if ( $xmlPath !== null ) {
			$domDoc->load( $xmlPath );
			$output = $xsltProc->transformToXML( $domDoc );
		} elseif ( $xmlStr !== null ) {
			$domDoc->loadXML( $xmlStr);
			$output = $xsltProc->transformToXML( $domDoc );
		} else {
			$output = null;
		}
		return $output;
	}

	/**
	 * Change xml:id attrs to make them unique again.
	 * Do after simplexml_load_string( ... )
	 * $xml SimpleXMLElement object
	 */
	public static function changeXmlIdsOlder( $simpleXml, $nsprefix ) {
		$xmlIdSel = "//" . $nsprefix . ":*/@xml:id";
		$xmlIdArr = $simpleXml->xpath( $xmlIdSel );
		if ( $xmlIdArr === false || $xmlIdArr === null ) {
			//print_r( "changeXmlIds failed...<br>" );
			return;
		}
		foreach ( $xmlIdArr as $node ) {
			$currVal = $node[0];
			$newVal = $nsprefix . "-" . $currVal;
			$node[0] = $newVal;
		}
	}

	public static function changeXmlIds( $simpleXml, $nsprefix ) {
		// NOT: $xmlIdSel = "//" . $nsprefix . ":*/@xml:id";
		$xmlIdSel = "//@xml:id";
		$xmlIdArr = $simpleXml->xpath( $xmlIdSel );
		if ( $xmlIdArr === false || $xmlIdArr === null ) {
			//print_r( "changeXmlIds failed...<br>" );
			return;
		}
		foreach ( $xmlIdArr as $node ) {
			$currVal = $node[0];
			$newVal = $nsprefix . "-" . $currVal;
			$node[0] = $newVal;
		}
	}

	/**
	 * @todo
	 * Get all attribute values from XML string
	 * Return some sort of array
	 */
	public static function getAttributeValues( $xmlStr = "", $mode = "values only" ): array {
		$xml = simplexml_load_string( $xmlStr, 'SimpleXMLElement', LIBXML_NOERROR|LIBXML_NOWARNING );
		// Select all elements that have attributes
		$allElements = $xml->xpath( "//@*" ); // or .//* or //* or //@*
		$res = [];
		if ( $allElements !== null ) {
			foreach ( $allElements as $obj ) {
				$elArr  = (array)$obj;
				$allAttributeVals = array_values( $elArr['@attributes'] );
				//print_r( $allAttributeVals[0] );
				foreach ( $allAttributeVals as $v ) {
					$res[] = $v;
				}
			}
		}
		return $res;
	}

}
