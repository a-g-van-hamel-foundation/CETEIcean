<?php

namespace Ctc\ParserFunctions;

use PPFrame;

class ctcParserFunctionUtils {

	public static function extractParams( PPFrame $frame, array $params, $paramsAllowed ) {
		$incomingParams = [];
		foreach ( $params as $param ) {
			$paramExpanded = $frame->expand( $param );
			$keyValPair = explode('=', $paramExpanded, 2);
			$paramName = trim( $keyValPair[0] );
			$value = ( array_key_exists( 1, $keyValPair) ) ? trim( $keyValPair[1] ) : "";
			$incomingParams[$paramName] = $value;
		}
		$params = [];
		foreach ( $paramsAllowed as $paramName => $default ) {
			$params[$paramName] = ( array_key_exists( $paramName, $incomingParams ) ) ? $incomingParams[$paramName] : $default;
		}
		return $params;
	}

}
