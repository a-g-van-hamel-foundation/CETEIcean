<?php

/**
 * Utility methods for enhancing semi-fuzzy searchability
 * especially through the use of SMW/FTS
 */

namespace Ctc\Content;

use Ctc\Core\ctcUtils;
use Ctc\Process\ctcXmlProc;

class ctcSearchableContentUtils {

	public function __construct() {
		//
	}

	public function splitContentIntoOverlappingChunks( $str, $chunkSize = 100, $overlap = 5 ) {
		$tokens = explode( " ", $str );
		$counter = count( $tokens );
		$chunks = [];
		for ( $i = 0; $i < $counter; $i++ ) {
			$offset = ( $i * $chunkSize ) - ( $i * $overlap );
			$tokenChunk = array_slice( $tokens, $offset, $chunkSize, true );
			$chunks[] = implode( " ", $tokenChunk );
		}
		return $chunks;
	}

	/**
	 * Use special-purpose XSL to transform XML string for search.
	 * 
	 * @param mixed $text - xml string
	 * @param bool $doFlatten - whether to output a stripped down text version. Set to false if you want an XML string for further processing.
	 * @return string
	 */
	public function transformXMLForSearch( string $text, bool $doFlatten = true ) {
		$absoluteExtPath = ctcUtils::getExtensionPath( "absolute" );
		$xslPath = $absoluteExtPath . "/xml/ext.ctc.search.xsl";

		$relativeExtPath = ctcUtils::getExtensionPath( "relative" );
		$dtdPath = $relativeExtPath . "/xml/ext.ctc.search.entities.dtd";
		$ctcXmlProc = new ctcXmlProc( null, $dtdPath  );
		$text = $ctcXmlProc->removeAndAddDocType( $text );

		$text = $ctcXmlProc->transformXMLwithXSL( $text, null, $xslPath );
		if ( ! $text ) {
			// XSLT unsuccessful
			return "";
		}

		// strip tags and all
		if ( $doFlatten ) {
			$text = $this->flattenForSearch( $text );
		}
		return $text;
	}

	/**
	 * Remove element tags, remove line breaks and decode entities.
	 * @param mixed $text
	 * @return string
	 */
	public function flattenForSearch( $text ) {
		$text = strip_tags( $text );
		$text = preg_replace('!\s+!', " ", $text);
		//$text = str_replace( [ "\r", "\n", "\t" ], [ " ", " ", " " ], $text );
		$text = str_replace(
			[ "[", "]", "{", "}", "&amp;" ],
			[ "", "", "", "", "&" ],
			$text
		);
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, "UTF-8" );
		return $text;
	}

	/**
	 * @deprecated - originally in ctcXmlProc
	 */
	public function flattenTextForSearch( $str ) {
		if( $str === "" ) {
			return "";
		}
		$ctcXmlProc = new ctcXmlProc();
		$xmlStr = $ctcXmlProc->removeAndAddDocType( $str );
		$simpleXml = simplexml_load_string( $xmlStr, 'SimpleXMLElement', LIBXML_NOERROR|LIBXML_NOWARNING );

		// Get attribute values separately before deleting them with strip_tags
		$attrValsArr = ctcXmlProc::getAttributeValues( $simpleXml, $xmlStr );
		$attrVals = implode( " | ", $attrValsArr );

		// Handle notes separately
		[ $newXml, $extractedTags ] = $this->removeTags( $xmlStr, [ "note" ] );
		$extractedTagsDoc = $ctcXmlProc->createDocumentStringForExtracts( $extractedTags );

		// The text itself, with entities
		$xmlStrTransformed = $$ctcXmlProc->transformXMLwithXSL( $newXml, null );
		$extractedTagsTransformed = $$ctcXmlProc->transformXMLwithXSL( $extractedTagsDoc, null );

		// strip tags
		$taglessStr = strip_tags( $xmlStrTransformed . $extractedTagsTransformed );
		// square brackets are used eg when 'supplied' is rendered
		$taglessStr = str_replace( [ "[", "]", "\r", "\n" ], [ "", "", " ", " " ], $taglessStr );
		$res = html_entity_decode( $taglessStr . " " . $attrVals, ENT_QUOTES | ENT_XML1, "UTF-8" );

		return $res;
	}

	/**
	 * Returns a string, or extracts, with instances of a given 'term(s)' highlighted.
	 * Supports case / accent folding.
	 * 
	 * Thanks to https://stackoverflow.com/questions/3582916/accent-insensitive-substring-matching for the pattern.
	 * 
	 * @param mixed $str
	 * @param mixed $term
	 * @param $mode - either "full" (default) to get the full string with terms highlighted; or "extracts" to get extracts for highlighted terms 
	 * @return string|array|null - Returns a string if $mode is "full" (or null if preg_replace fails); an array if $mode is "extracts".
	 */
	public function highlightTerm( $text, $term, $mode = "full" ): array|string|null {
		// Normalise
		$text = \Normalizer::normalize( $text, \Normalizer::FORM_KD );

		//$term = \Normalizer::normalize( $term, \Normalizer::FORM_KD );
		// Uncomment to get back to the old behaviour
		if ( substr( $term, 0, 1 ) === "\""
			&& substr( $term, -1 ) === "\"" ) {
			// quotation marks "..."
			$term = [ str_replace( "\"", "", $term ) ];
		} else {
			$term = explode( " ", $term );
		}

		// Pattern is case/accent insensitive
		if( gettype( $term ) === "string" ) {
			// Unused
			$term = \Normalizer::normalize( $term, \Normalizer::FORM_KD );
			$pattern = '/(' . preg_replace( '/\p{L}/u', '$0\p{Mn}?', preg_quote($term, '/')) . ')/ui';
		} elseif( gettype( $term ) === "array" ) {
			$subjects = [];
			foreach( $term as $t ) {
				$t = str_replace( [ "*", "<", ">" ], "", trim($t) );
				$t = \Normalizer::normalize( $t, \Normalizer::FORM_KD );
				// Diacriics, but see ReconciliationAPI's StringModifier for a better handler
				$t = iconv( 'UTF-8', 'US-ASCII//TRANSLIT//IGNORE', $t );
				$firstChar = substr( $t, 0, 1 );
				// boolean operators
				if( $firstChar === "-" ) {
					continue;
				} elseif( $firstChar === "+" ) {
					// remove boolean operators if any
					$t = substr( $t, 1 );
				}
				$subjects[] = preg_replace(
					'/\p{L}/u',
					'$0\p{Mn}?',
					preg_quote(trim($t), '/')
				);
			}
			// u (PCRE_UTF8) and i (PCRE_CASELESS)
			$pattern = '/(' . implode( "|", $subjects ) . ')/ui';
		}

		if( $mode === "extracts" ) {
			$enclosingStrings = preg_split( $pattern, htmlspecialchars($text) );
			$matchCount = preg_match_all( $pattern, htmlspecialchars($text), $matches );

			$extracts = [];
			$tokenRadius = 12;
			// note: enclosingStrings count = matchCount + 1
			foreach( $enclosingStrings as $k => $str ) {
				$start = $end = "";
				if ( $k < (count($enclosingStrings ) - 1) ) {
					$end = $this->extractTokens( $str, "end", $tokenRadius );
					$extracts[$k] = "&hellip;$end<strong>" . $matches[1][$k] . "</strong>"; // ??
				}
				if ( $k !== 0 ) {
					$start = $this->extractTokens( $str, "start", $tokenRadius );
					$extracts[$k - 1] .= $start . "&hellip;";
				}
			}

			return $extracts;
		}
		// default output
		$highlightedText = preg_replace( $pattern, '<strong>$0</strong>', htmlspecialchars($text) );
		return $highlightedText;
	}

	private function extractTokens( string $text, string $position = "start", int $length = 10 ) {
		$tokens = explode( " ", $text );
		$sub = "";
		if( $position === "start" ) {
			$firstTokens = count($tokens) >= $length
				? array_slice( $tokens, 0, $length  )
				: $tokens;
			$sub = implode( " ", $firstTokens );
		}
		if( $position === "end" ) {
			$finalTokens = count($tokens) >= $length
				? array_slice( $tokens, count($tokens) - $length )
				: $tokens;
			$sub = implode( " ", $finalTokens );
		}
		return $sub;
	}

	/**
	 * Borrowed from MediaWiki's SearchMySQL
	 * 
	 * @param mixed $string
	 * @return array|string|null
	 */
	public function normalizeText( $string ) {
		$out = \SearchDatabase::normalizeText( $string );

		// MySQL fulltext index doesn't grok utf-8, so we
		// need to fold cases and convert to hex
		$out = preg_replace_callback(
			"/([\\xc0-\\xff][\\x80-\\xbf]*)/",
			[ $this, 'stripForSearchCallback' ],
			\MediaWikiServices::getInstance()->getContentLanguage()->lc( $out ) );

		// And to add insult to injury, the default indexing
		// ignores short words... Pad them so we can pass them
		// through without reconfiguring the server...
		$minLength = $this->minSearchLength();
		if ( $minLength > 1 ) {
			$n = $minLength - 1;
			$out = preg_replace(
				"/\b(\w{1,$n})\b/",
				"$1u800",
				$out );
		}

		// Periods within things like hostnames and IP addresses
		// are also important -- we want a search for "example.com"
		// or "192.168.1.1" to work sensibly.
		// MySQL's search seems to ignore them, so you'd match on
		// "example.wikipedia.com" and "192.168.83.1" as well.
		return preg_replace(
			"/(\w)\.(\w|\*)/u",
			"$1u82e$2",
			$out
		);
	}

	private function stripForSearchCallback( $matches ) {
		return 'u8' . bin2hex( $matches[1] );
	}

}
