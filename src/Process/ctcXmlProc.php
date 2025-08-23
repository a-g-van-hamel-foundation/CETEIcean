<?php

/**
 * Methods for converting between XML, PHP arrays and HTML output
 * Reads, extracts and modifies XML.
 * 
 * @todo consolidate variant approaches SimpleXml, DOMDocument, DOMXpath, XSLTProcessor
 */

namespace Ctc\Process;

//use MediaWiki\MediaWikiServices;
use RequestContext;
use Ctc\Content\ctcSearchableContentUtils;

class ctcXmlProc {

	public $errorMsg = "";
	private $config;
	// Paths relative to MediaWiki root folder
	private $xslPath;
	private $dtdPath;
	private $teiNamespace = "http://www.tei-c.org/ns/1.0";
	// default prefix:
	private $teiNamespacePrefix = "ctc";

	public function __construct( $xslPath = null, $dtdPath = null ) {
		$this->config = RequestContext::getMain()->getConfig();
		$this->xslPath = $xslPath === null ? $this->getXslPath() : $xslPath;
		$this->dtdPath = $dtdPath === null ? $this->config->get( "CeteiDTD" ) : $dtdPath;
	}

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
				// print_r( $message );
			}
			libxml_clear_errors();
			return false;
		}
		$xml->registerXPathNamespace( $nsprefix, "http://www.tei-c.org/ns/1.0" );
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
		$headerTitle = $xml->xpath( "//ctc:teiHeader//ctc:title" );
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
	 * Get excerpt from XML string using SimpleXML.
	 * @return array|false
	 */
	public static function getExcerpts( $xmlString, $selector = "//ctc:p[@n='2']" ) {
		$xml = self::getSimpleXML( $xmlString );
		if ( $xml === false ) {
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
		if ( $xml === false ) {
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
	 * Remove any existing DOCTYPE if any
	 * and replace it with our own.
	 */
	public function removeAndAddDocType( $xmlStr ) {
		$cleanStr = self::removeDocType( $xmlStr );
		$res = self::addDocType( $cleanStr, $this->dtdPath );
		return $res;
	}

	/**
	 * Security measure: remove DOCTYPE
	 */
	public static function removeDocType( $xmlStr ) {
		$patternEntity = "/(?i)<!ENTITY[^<>]*>/";
		$patternDoctype = "/(?i)<!DOCTYPE[^<>]*(?:<[^<>]*>[^<>]*|)+?>/";
		$output = preg_replace( $patternEntity, "", $xmlStr );
		$output = preg_replace( $patternDoctype, "", $output );
		return $output;
	}

	/**
	 * @param mixed $xmlStr
	 * @param mixed $dtdPath - path relative to MediaWiki root folder /extensions/CETEIcean/xml/...
	 */
	public static function addDocType( $xmlStr, $dtdPath = null ) {
		global $IP;
		if ( $dtdPath === null ) {			
			$dtdPath = RequestContext::getMain()->getConfig()->get( "CeteiDTD" );
			// @todo what if extension is installed in another dir?
		}
		$entitiesPath = $IP . $dtdPath;

		if ( file_exists( $entitiesPath ) ) {
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
	 * @param string|null $xslPath - optionally, use alternative stylesheet
	 * @return mixed
	 */
	public function transformXMLwithXSL(
		mixed $xmlStr = null,
		mixed $xmlPath = null,
		mixed $xslPath = null
	) {
		if ( $xmlStr === null && $xmlPath === null ) {
			return null;
		}
		if ( $xslPath !== null ) {
			$this->xslPath = $xslPath;
		}
		$domDoc = $this->setupEmptyDOMDocument();
		$res = $this->transformDOMDocumentWithXSL( $domDoc, $xmlPath, $xmlStr );
		return $res;
	}

	private function transformDOMDocumentWithXSL(
		$domDoc,
		$xmlPath = null,
		$xmlStr = null
	) {
		$domDoc->load( $this->xslPath );

		$xsltProc = new \XSLTProcessor();
		$isImported = $xsltProc->importStyleSheet( $domDoc );
		if ( !$isImported ) {
			return null;
		}

		if ( $xmlPath !== null ) {
			$domDoc->load( $xmlPath );
			return $xsltProc->transformToXML( $domDoc );
		} elseif ( $xmlStr !== null ) {
			$domDoc->loadXML( $xmlStr );
			return $xsltProc->transformToXML( $domDoc );
		} else {
			return null;
		}
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
	 * @param string $xmlStr
	 * @param mixed $xmlObj
	 * @param string $mode - currently unused
	 * @return array
	 */
	public static function getAttributeValues(
		$simpleXml = null,
		$xmlStr = "",
		$mode = "values only"
	): array {
		if ( $simpleXml !== null ) {
			$xml = $simpleXml;
		} else {
			$xml = simplexml_load_string( $xmlStr, 'SimpleXMLElement', LIBXML_NOERROR|LIBXML_NOWARNING );
		}
		// Select all elements that have attributes
		$allElements = $xml->xpath( "//@*" ); // or .//* or //* or //@*
		$res = [];
		if ( $allElements !== null ) {
			foreach ( $allElements as $obj ) {
				$elArr = (array)$obj;
				$allAttributeVals = array_values( $elArr['@attributes'] );
				//print_r( $allAttributeVals[0] );
				foreach ( $allAttributeVals as $v ) {
					$res[] = $v;
				}
			}
		}
		return $res;
	}

	/**
	 * Removes tag elements from XML string and returns both the
	 * new document and the extracted elements (strings).
	 * @link https://stackoverflow.com/questions/29407648/php-dom-with-xpath-to-remove-tag
	 * @link https://stackoverflow.com/questions/32792418/removing-nodes-in-xml-with-xpath-and-php
	 * 
	 * @return string[]
	 */
	public function removeTags( string $xmlStr, array $tagNames = [ "note" ] ) {
		$domDoc = $this->setupEmptyDOMDocument();
		$domDoc->loadxml( $xmlStr );
		$newNodes = [];

		// XPath
		$xpath = new \DOMXPath( $domDoc );
		$xpath->registerNamespace( $this->teiNamespacePrefix, $this->teiNamespace );
		foreach( $tagNames as $tagName ) {
			$nodes = $xpath->query( "//{$this->teiNamespacePrefix}:{$tagName}" );
			foreach( $nodes as $k => $node ) {
				// Collect DOMNode $nodes -> string
				$newNodes[] = $domDoc->saveHTML( $node );
				// Remove from DOMDocument
				if ( version_compare(PHP_VERSION, '8.0.0') >= 0 ) {
					$node->remove();
				} else {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		return [
			$domDoc->saveXml(),
			implode( " ", $newNodes )
		];
	}

	/**
	 * @deprecated Moved to ctcSearchableContentUtils
	 * @todo Remove
	 */
	public function flattenTextForSearch( $str ) {
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		return $ctcSearchableContentUtils->flattenTextForSearch( $str );
	}

	/**
	 * Helper method to insert extracted nodes into
	 * a new document so that XSLT can be applied to them.
	 * Should not be expected to pass TEI validation.
	 */
	public function createDocumentStringForExtracts( string $xmlStr ): string {
		$newXmlStr = "<xml><TEI xmlns='{$this->teiNamespace}'><text>{$xmlStr}</text></TEI></xml>";
		$newXmlStr = self::addDocType( $newXmlStr );
		return $newXmlStr;
	}

	/**
	 * Setup an empty DOM document, with the default stylesheet, before XSL.
	 * No XSLPath loaded
	 * @return \DOMDocument
	 */
	private function setupEmptyDOMDocument() {
		$allowsEntitySubst = $this->config->get( "CeteiAllowEntitySubstitution" );
		$domDoc = new \DOMDocument();
		$domDoc->substituteEntities = $allowsEntitySubst; //boolean
		$domDoc->resolveExternals = false;
		//$loadOptions = ( $allowsEntitySubst == true ) ? LIBXML_NOENT : null;
		return $domDoc;
	}

	/**
	 * Get the path to the default XSL stylesheet
	 * @return string
	 */
	private function getXslPath() {
		global $IP;
		$xslRelPath = $this->config->get( "CeteiXsl" );
		$xslPath = $IP . $xslRelPath;
		return $xslPath;
	}

}
