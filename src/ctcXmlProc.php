<?php
namespace Ctc\Core;

/**
 * Methods for converting between XML, PHP arrays and HTML output
 * Reads, extracts and modifies XML
 */

use MediaWiki\MediaWikiServices;

class ctcXmlProc {

  private static function getSimpleXML( $xmlString ) {
      $xml = simplexml_load_string( $xmlString, 'SimpleXMLElement' );
      if ( $xml == false ) {
        return false;
      }
      $xml->registerXPathNamespace("ctc", "http://www.tei-c.org/ns/1.0");
      return $xml;
  }

  public function getHeaderTitle( $xmlString ) {
      $xml = self::getSimpleXML( $xmlString );
      $headerTitle = $xml->xpath("//ctc:teiHeader//ctc:title");
      if ( count($headerTitle) > 0 ) {
        foreach ($headerTitle as $title) {
            $value = strip_tags( $title->asXml() );
            return $value;
          }
        } else {
          return false;
      }
  }

  /* Test: returns an array */
  public static function getExcerpts( $xmlString, $selector = "//ctc:p[@n='2']" ) {
      $xml = self::getSimpleXML( $xmlString );
      $selectionArr = $xml->xpath( $selector );
      if ( $selectionArr !== null && $selectionArr !== 'undefined' ) {
        return $selectionArr;
      } else {
        return false;
      }
  }

  /* Returns true/false depending on presence of TEI Header */
  public function hasTEIHeader( $xmlString ) {
    $xml = self::getSimpleXML( $xmlString );
    $teiHeader = $xml->xpath("//ctc:teiHeader");
    if ( count($teiHeader) > 0 ) {
      return true;
    } else {
      return false;
    }
  }

  /**
    * Security measure: remove DOCTYPE
  **/
  public static function removeDocType ( $xmlStr ) {
    $patternEntity = "/(?i)<!ENTITY[^<>]*>/";
    $patternDoctype = "/(?i)<!DOCTYPE[^<>]*(?:<[^<>]*>[^<>]*|)+?>/";
    $output = preg_replace( $patternEntity, "", $xmlStr );
    $output = preg_replace( $patternDoctype, "", $output );
    return $output;
  }

  public static function addDocType( $xmlStr ) {
    global $IP;
    $config = \RequestContext::getMain()->getConfig();
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
    $config = \RequestContext::getMain()->getConfig();
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

}
