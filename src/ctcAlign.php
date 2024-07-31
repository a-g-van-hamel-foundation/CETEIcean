<?php

/**
 * Parser function for aligning related sections side by side.
 * @author: Dennis Groenewegen
 * @file
 * @ingroup
 */

namespace Ctc\Core;

class ctcAlign {

	/**
	 * Accepts multiple documents
	 * Returns columns rows based on XPath selection
	 * 
	 * @note rangeSep is an optional parameter used when using ranges.
	 */
	public static function align( 
		string $resourceStr,
		string $resourceSep,
		string $selectors,
		string $alignCsvStr,
		string $valSep,
		string|null $rangeSep = null
	): string {
		$resourceArr = array_map( 'trim', explode( $resourceSep, $resourceStr ) );
		$selectorArr = array_map( 'trim', explode( $resourceSep, $selectors ) );

		$textArr = [];
		foreach ( $resourceArr as $key => $doc ) {
			$text = ctcUtils::getContentFromPageTitleOrUrl( $doc, "" );
			$textArr[$key] = $text;
		}

		if ( $rangeSep !== null ) {
			$rows = explode( "\n", $alignCsvStr );
			$newAlignStr = "";
			foreach ( $rows as $row ) {
				$rowArr = self::decodeRangesInRow( $row, $valSep, $rangeSep );
				foreach ( $rowArr as $alignRow ) {
					$newAlignStr .= implode( $valSep, $alignRow ) . "\n";
				}
			}
			$csvObj = self::convertCsvToObject( $newAlignStr, $valSep );
		} else {
			$csvObj = self::convertCsvToObject( $alignCsvStr, $valSep );
		}

		$xmlStr = "";
		foreach( $csvObj as $lineNumber => $vals ) {
			$rowXmlStr = self::createExcerptRowStr( $textArr, $selectorArr, $vals, $valSep, $lineNumber );
			$xmlStr .= $rowXmlStr;
		}

		$xmlStr = "<TEI xmlns='http://www.tei-c.org/ns/1.0'><group type='parallel-excerpts'>{$xmlStr}</group></TEI>";
		$xmlStr = ctcXmlProc::addDocType( $xmlStr );
		return $xmlStr;
	}

	/**
	 * Convert csv-type input to an array.
	 */
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

	/**
	 * Helper method to decode ranges
	 * @todo: what about "a.20--a.45; b.20-b.40" >
	 * Only if you specify a rangesep, then
	 */
	private static function decodeRangesInRow( $str, $colSep = ";", $rangeSep = "--" ): array {
		$cols = explode( $colSep, $str );
		$ranges = $rowCounts = [];
		foreach( $cols as $col ) {
			$rangeLimits = explode( $rangeSep, trim( $col ) ); // a.20-a.21, 20--, ...
			if ( !array_key_exists( 1, $rangeLimits ) ) {
				// No range detected
				$rangeArr = array( $rangeLimits[0] );
			} elseif ( is_numeric( $rangeLimits[0] ) && is_numeric( $rangeLimits[1] ) ) {
				$rangeArr = range( $rangeLimits[0], $rangeLimits[1], 1 );
			} else {
				// @todo e.g. a.20
				return [];
			}
			$rowCounts[] = count( $rangeArr );
			$ranges[] = $rangeArr;
		}

		// Now combine numerical arrays. Ranges may not be equal.
		$highestRowCount = max( $rowCounts );
		$combinedArr = [];
		for ( $i = 0; $i <= $highestRowCount; $i++ ) {
			foreach( $ranges as $range ) {
				$combinedArr[$i][] = ( array_key_exists( $i, $range ) ) ? $range[$i] : "";
			}
		}
		return $combinedArr;
	}

}
