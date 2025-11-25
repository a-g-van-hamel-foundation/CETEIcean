<?php

/**
 * Optional SMW methods to check if a document is allowed to be shown.
 * 
 * Does not prevent parser functions from retrieving content.
 * To be instantiated only after installation of SMW has been verified.
 */

namespace Ctc\SMW;

use Title;
use StoreFactory;
use SMWQueryProcessor;
use Ctc\SMW\ctcSMWQuery;

class ctcSMWPublisher {

	private $wgCeteiPublicationCheck;

	public function __construct() {
	}

	/**
	 * Checks a given semantic property if the page is allowed
	 * to be public. Property and possible values are set in config.
	 * 
	 * @param \Title $title
	 * @param mixed $smwProperty
	 * @return bool
	 * checkSMWFor
	 * smwIsPagePublic
	 */
	public function smwIsPagePublic( Title $title, $wgCeteiPublicationCheck = [] ) {
		if ( !$wgCeteiPublicationCheck ) {
			return true;
		}
		$this->wgCeteiPublicationCheck = $wgCeteiPublicationCheck;
		$smwProperty = $this->wgCeteiPublicationCheck["smwProperty"] ?? "";

		// First create a query object
		$fullPagename = $title->getPrefixedText();
		$rawQuery = "[[{$fullPagename}]]";
		$rawQueryComponents = [
			$rawQuery,
			"?{$smwProperty}",
			"link=none",
			"offset=0",
			"searchlabel="
		];
		$smwQueryObj = ctcSMWQuery::createSMWQueryObjFromRawQuery( $rawQueryComponents, false );

		// Now run the query and get a QueryResult
		$smwStore = self::getSMWStore();
		if ( $smwStore === null ) {
			return false;
		}
		$smwQueryRes = $smwStore->getQueryResult( $smwQueryObj );

		// Get answer from QueryResult
		$answer = $this->getValueFromQueryResult( $smwQueryRes, $fullPagename, $smwProperty );

		//Evaluate
		$valueIfPublic = $this->wgCeteiPublicationCheck["valueIfPublic"] ?? "";
		$valueIfPrivate = $this->wgCeteiPublicationCheck["valueIfPrivate"] ?? "";
		$valueDefault = $this->wgCeteiPublicationCheck["default"] ?? "";
		if ( $answer === null ) {
			return $valueDefault === $valueIfPublic;
		} elseif ( $answer === $valueIfPrivate ) {
			return false;
		} elseif( $answer === $valueIfPublic ) {
			return true;
		}
		return $valueDefault === $valueIfPublic;
	}

	public static function getSMWStore(): mixed {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			return \SMW\StoreFactory::getStore();
		} else {
			return null;
		}
	}

	/**
	 * @deprecated
	 */
	public static function createSMWQueryObjFromRawQuery( array $rawQueryArr, $useShowMode = false ) {
		return ctcSMWQuery::createSMWQueryObjFromRawQuery( $rawQueryArr, $useShowMode );
	}

	/**
	 * @param mixed $smwQueryRes
	 * @param mixed $id
	 * @return string|bool|null - null if no value was found or errors occurred
	 */
	private function getValueFromQueryResult( $smwQueryRes, $fullPagename, $smwProperty ) {
		if ( $smwQueryRes->getErrors() !== [] ) {
			return null;
		}
		$queryResultArr = $smwQueryRes->toArray();
		if ( !array_key_exists( $fullPagename, $queryResultArr["results"] ) ) {
			// Silently abort if no relevant page was found
			return null;
		}

		$printouts = $queryResultArr["results"][$fullPagename]["printouts"];
		foreach( $printouts as $prop => $vals ) {
			if ( $prop === $smwProperty ) {
				// Assuming single values are produced				
				return $vals[0] ?? null;
			}
		}
		return null;
	}

}
