<?php

namespace Ctc\ParserFunctions;

use Parser;
use PPFrame;
//use Title;
//use WikiPage;
//use MediaWiki\Revision\RevisionRecord;
use Html;
use Ctc\ParserFunctions\ctcParserFunctionUtils;
//use Ctc\Core\ctcUtils;

class ctcSearch {

	public static function runCeteiSearchPF( Parser &$parser, PPFrame $frame, $params ) {
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

	public static function runCeteiSMWSearchPF( Parser &$parser, PPFrame $frame, $params ) {
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
