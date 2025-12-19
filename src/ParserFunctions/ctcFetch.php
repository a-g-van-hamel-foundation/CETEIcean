<?php

namespace Ctc\ParserFunctions;

use Parser;
use PPFrame;
use Title;
use WikiPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Html;
use Ctc\ParserFunctions\ctcParserFunctionUtils;
use Ctc\Core\ctcUtils;


class ctcFetch {

	/**
	 * Parser function for fetching a document and getting it to be used inside a form.
	 * {{#cetei-fetch: page= }}
	 */
	public static function runCeteiFetchPF( Parser &$parser, PPFrame $frame, $params ) {
		$paramsAllowed = [
			"page" => null
		];
		[ $page ] = array_values( ctcParserFunctionUtils::extractParams( $frame, $params, $paramsAllowed ) );

		$titleObj = Title::newFromText( $page );
		if ( $titleObj === null ) {
			return "";
		}
		$wikiObj = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $titleObj );
		$str = $wikiObj->getContent( RevisionRecord::RAW )->getText();

		// Important. This is done to prevent character entities from being decoded
		$str = htmlentities( $str );
		//print_r( "<pre>$str</pre>" );

		return  [ $str, 'noparse' => true, 'isHTML' => true ];
	}

}
