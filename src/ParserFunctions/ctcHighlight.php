<?php

/**
 * Parser function for highlighting a (search) term
 */

namespace Ctc\ParserFunctions;

use Parser;
use PPFrame;
use Ctc\ParserFunctions\ctcParserFunctionUtils;
use Ctc\Content\ctcSearchableContentUtils;

class ctcHighlight {

	public static function runCeteiHighlightPF( Parser $parser, PPFrame $frame, $args ) {
		if ( $args == null || $args == 'undefined' ) {
			return null;
		}

		$paramsAllowed = [
			"text" => null,
			"term" => "",
			"mode" => "extracts"
		];
		[ $text, $term, $mode ] = array_values( ctcParserFunctionUtils::extractParams( $frame, $args, $paramsAllowed ) );

		if ( $text === null || $text === "" ) {
			// Nothing to highlight
			return "";
		}

		$ctcSearchableContentUtils = new ctcSearchableContentUtils();

		if ( $mode === "full" ) {
			$res = $ctcSearchableContentUtils->highlightTerm( $text, $term, $mode );
		} else {
			$extracts = $ctcSearchableContentUtils->highlightTerm( $text, $term, $mode );
			$res = "";
			foreach( $extracts as $extract ) {
				$res .= "<div class='cetei-search-extract'>$extract</div>";
			}
		}
		return $res;
	}

}
