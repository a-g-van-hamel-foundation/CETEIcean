<?php

namespace Ctc\ParserFunctions;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Html\Html;
//use Ctc\ParserFunctions\ctcParserFunctionUtils;

class ctcSearch {

	public function runCeteiSearchPF( Parser &$parser, PPFrame $frame, $params ) {
		/*
		$paramsAllowed = [
			"page" => null
		];
		[ $page ] = array_values( ctcParserFunctionUtils::extractParams( $frame, $params, $paramsAllowed ) );
		*/

		$parser->getOutput()->addModules( [ "ext.ctc.search" ] );

		$res = Html::rawElement( "div", [ "class" => "cetei-search-widget" ], "" );
		return  [ $res, 'noparse' => true, 'isHTML' => true ];
	}

	public function runCeteiSMWSearchPF( Parser &$parser, PPFrame $frame, $params ) {
		/*
		$paramsAllowed = [
			"restrict" => true
		];
		[ $restrict ] = array_values( ctcParserFunctionUtils::extractParams( $frame, $params, $paramsAllowed ) );
		*/

		$parser->getOutput()->addModules( [ "ext.ctc.search" ] );

		$res = Html::rawElement( "div", [ "class" => "cetei-smwsearch-widget" ], "" );
		return  [ $res, 'noparse' => true, 'isHTML' => true ];
	}

}
