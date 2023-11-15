<?php

/**
 *
 * @author: Dennis Groenewegen
 * @file
 * @ingroup
 */

namespace Ctc\Core;

class ctcAlign {

	/**
	 * Accepts multiple documents
	 * Returns columns rows based on XPath selection
	 */
	public static function align( 
		string $resourceStr,
		string $resourceSep,
		string $selectors,
		string $alignCsvStr,
		string $valSep
	): string {
		$resourceArr = array_map( 'trim', explode( $resourceSep, $resourceStr ));
		$selectorArr = array_map( 'trim', explode( $resourceSep, $selectors ));

		$textArr = [];
		foreach ( $resourceArr as $key => $doc ) {
			$text = ctcUtils::getContentFromPageTitleOrUrl( $doc, "" );
			$textArr[$key] = $text;
		}

		$csvObj = self::convertCsvToObject( $alignCsvStr, $valSep );

		$xmlStr = "";
		foreach( $csvObj as $lineNumber => $vals ) {
			$rowXmlStr = self::createExcerptRowStr( $textArr, $selectorArr, $vals, $valSep, $lineNumber );
			$xmlStr .= $rowXmlStr;
		}

		$xmlStr = "<TEI xmlns='http://www.tei-c.org/ns/1.0'><group type='parallel-excerpts'>{$xmlStr}</group></TEI>";
		$xmlStr = ctcXmlProc::addDocType( $xmlStr );
		return $xmlStr;

	}

	public static function convertCsvToObject( $str, $valSep = ";", $lineSep = "\n" ) {
		$csvObj = [];
		$linesArr = str_getcsv( $str, $lineSep );
		foreach( $linesArr as $key => $line ) {
			$valsArr = str_getcsv( $line, $valSep );
			//$valsArr = explode( $valSep, $line );
			$csvObj[$key] = $valsArr;
		}
		return $csvObj;
	}

	/**
	 * Create multiple excerpts as columns in a single row
	 * xmlArray = simpleXMLelement object
	 */
	public static function createExcerptRowStr( 
		array $textArr,
		array $selectorArr,
		array $valArr,
		string $valSep,
		int $lineNumber
	) {
		//$rowXml = ctcAlign::createRow( $xmlArr, $selectors, $vals, $lineNumber );
		$rowStr = "";
		foreach ( $valArr as $k => $val ) {
			if ( trim($val) == "" || $textArr[$k] == "" ) {
				$rowStr .= "<cit type='tei-excerpt-col'></cit>";
				continue;
			}
			// $ctcXmlProc = new ctcXmlProc();
			$text = ctcXmlProc::addDocType( $textArr[$k] );
			$nsprefix = strtok( $selectorArr[$k], ":/" );
			$selector = str_replace( "***", trim($val), "{$selectorArr[$k]}" );

			// SimpleXML
			$xml = ctcXmlProc::getFullXML( $text, $nsprefix );
			if ( $xml == false ) {
				print_r( "<div>Could not create SimpleXml from string...</div>" );
				return "";
			}
			
			$selectionArr = $xml->xpath( $selector );

			$excerptStr = "";
			foreach ( $selectionArr as $selection ) {
				// Merging excerpts comes with one issue:
				// xml:ids are not guaranteed to be unique (col. 2 onwards)
				if ( $k !== 0 ) {
					ctcXmlProc::changeXmlIds( $selection, $nsprefix );     
				}
				$excerptStr .= "<quote type='excerpt'>" . $selection->asXml() . "</quote>";
			}
			$excerptStr = str_replace(array("\r\n", "\r", "\n", "  " ), " ", trim($excerptStr) );
			$rowStr .= "<cit type='tei-excerpt-col'>{$excerptStr}</cit>";
		}
		$res = "<text type='tei-excerpt-row' n='$lineNumber'>{$rowStr}</text>";
		return $res;
	}

}
