<?php

/**
 * Self-documentation.
 */

namespace Ctc\ParserFunctions;

use Ctc\Core\ctcUtils;

class ctcParserFunctionsInfo {

	public static function getParserFunctionInfo( string $name ): string {
		$info = [];
		if ( $name == 'cetei' ) {
			$info = self::getCeteiPFInfo();
		} elseif( $name == 'cetei-align' ) {
			$info = self::getCeteiAlignPFInfo();
		} elseif( $name == 'cetei-ace' ) {
			// $info = self::getCeteiAcePFInfo();
            $info = [];
		}
		$str = ctcUtils::showArrayAsJsonInWikiText( $info );
		return $str;
	}

	/**
	 * Self-documentation.
	 * @todo
	 */
	private static function getCeteiPFInfo(): array {
		$name = "cetei";
		$parameters = [
			"doc" => [
				"description" => "The name of the wiki page written in TEI XML."
			],
			"url" => [
				"description" => "URL of the document written in TEI XML, provided that it is accessible in that it is (a) not blocked by CORS-related security measures and (b) the config setting `wgCeteiAllowUrl` is set to true."
			],
			"sel" => [
				"description" => "The XPath expression to use on the document. Each tag name must be preceded by a namespace prefix (e.g. ctc:). Example: `//ctc:div2[@n='U1123.2']`."
			],
			"break1" => [
				"description" => "If we were to attempt to extract a fragment between self-closing tags (experimental), a representation of the first tag, given as `tagname`---`attribute`---`attribute value`, for instance `pb---n---4`."
			],
			"break2" => [
				"description" => "The final self-closing tag. See break1."
			],
			"action" => [
				"description" => "Can be set to `info` to get self-documentation for this parser function instead."
			]
		];
		return [
			"name" => $name,
			"parameters" => $parameters
		];
	}

	private static function getCeteiAlignPFInfo(): array {
		$name = "cetei-align";
		$parameters = [
			"resources" => [
				"description" => "A list of resources (wiki pages or URLs) separated by `resourcesep`.",
			],
			"resourcesep" => [
				"description" => "Separator to use for `resources`. ",
				"default" => "^^"
			],
			"map" => [
				"description" => "A variable table constructed like a CSV, using semi-colons to delimit the values. Each line represents a separate row and each value within a line a separate column or 'cell'."
			],
			"selectors" => [
				"description" => "List of XPath selectors used for each resource, but with a placeholder (`***`) for the variable which comes from the `map` table below."
			],
			"valsep" => [
				"description" => "Separator to use for values in `map`.",
				"default" => ";"
			],
			"rangesep" => [
				"description" => "The separator to use for numerical ranges in `map`.",
				"default" => "-"
			],
			"action" => [
				"description" => "Can be set to `info` to get self-documentation for this parser function instead."
			]
		];
		return [
			"name" => $name,
			"parameters" => $parameters,
			"example" => "https://codecs.vanhamel.nl/Show:Lab/CETEIcean/Arthur"
		];
	}

}
