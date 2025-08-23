<?php

namespace Ctc\SMW;

use SMWQueryProcessor;

class ctcSMWQuery {

	public function __construct() {
		//
	}

	public static function getSMWStore(): mixed {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			return \SMW\StoreFactory::getStore();
		} else {
			return null;
		}
	}

	/**
	 * Accepts a raw query in the array format and
	 * creates from it an SMWQuery object.
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/src/MediaWiki/Api/Query.php
	 * 
	 * @param array $rawQueryArr
	 * @param mixed $useShowMode - whether to use #show instead of #ask
	 * @return object SMWQuery
	 */
	public static function createSMWQueryObjFromRawQuery( array $rawQueryArr, $useShowMode = false ) {
		[ $queryString, $processedParams, $printouts ] = SMWQueryProcessor::getComponentsFromFunctionParams( $rawQueryArr, $useShowMode );
		SMWQueryProcessor::addThisPrintout( $printouts, $processedParams );
		$processedParams = SMWQueryProcessor::getProcessedParams( $processedParams, $printouts );

		// Run query (SMWQuery) and return SMWQuery obj
		$queryObj = SMWQueryProcessor::createQuery(
			$queryString,
			$processedParams,
			SMWQueryProcessor::SPECIAL_PAGE,
			"",
			$printouts
		);
		return $queryObj;
	}

}
