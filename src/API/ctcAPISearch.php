<?php

/**
 * @since 0.8
 */

namespace Ctc\API;

use ApiBase;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;
use Ctc\SMW\ctcSMWQuery;
use Ctc\Content\ctcSearchableContentUtils;
use Ctc\Core\ctcUtils;

class ctcAPISearch extends ApiBase {

	private $smwContentProperty;
	private $wgCeteiPublicationCheck;
	private $smwPublicationProperty = false;
	private $smwPublicLabel;

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$this->smwContentProperty = $config->get( "CeteiSMWPropertyForSearchIndex" );
		$this->wgCeteiPublicationCheck = $config->get( "CeteiPublicationCheck" );		
		if ( gettype( $this->wgCeteiPublicationCheck ) === "array" ) {
			$this->smwPublicationProperty = $this->wgCeteiPublicationCheck["smwProperty"] ?? false;
			$this->smwPublicLabel = $this->wgCeteiPublicationCheck["valueIfPublic"];
		}
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$prefix = $params["prefix"] ?? null;
		if ( $prefix === null ) {
			return [ "result" => [] ];
		}

		$smwStore = ctcSMWQuery::getSMWStore();
		if ( $smwStore === null ) {
			return false;
		}

		// Buld query syntax
		$smwContentProperty = $this->smwContentProperty;
		if ( $this->smwPublicationProperty !== false && ! ctcUtils::isUser() ) {
			// Search 'public' only
			$rawQuery = "[[Cetei:+]] [[{$smwContentProperty}::~{$prefix}]] [[-Has subobject.{$this->smwPublicationProperty}::{$this->smwPublicLabel}]]";
			// example: [[Cetei:+]] [[Searchstring::+]] [[-Has subobject.Is published::Yes]]			
		} else {
			// Search everything
			$rawQuery = "[[Cetei:+]] [[{$smwContentProperty}::~{$prefix}]]";
		}
		$printoutPublicationProperty = $this->smwPublicationProperty !== false
			// Use custom label which we can reliably use in the Vue widget
			? "?-Has subobject.{$this->smwPublicationProperty}=Published"
			: "?";

		$rawQueryComponents = [
			$rawQuery,
			"?=Page",
			"?{$smwContentProperty}",
			$printoutPublicationProperty,
			"link=none",
			"offset=0",
			"searchlabel=",
			"limit=100"
			// @todo disabled for now because of potential issues with chained properties:
			// "sort=-Has subobject.Display title of"
		];

		// QueryObj
		$smwQuery = ctcSMWQuery::createSMWQueryObjFromRawQuery( $rawQueryComponents, false );
		// unless errors occur...?
		$smwQueryArr = $smwQuery->toArray();

		$smwQueryRes = $smwStore->getQueryResult( $smwQuery );
		$smwQueryResArr = $smwQueryRes->toArray();

		$ctcSearchableContentUtils = new ctcSearchableContentUtils();

		$result = [];
		foreach( $smwQueryResArr["results"] as $objName => $v ) {
			$content = [];
			$isPublished = "undefined";
			foreach( $v["printouts"] as $prop => $vals ) {				
				if ( $prop === $smwContentProperty ) {
					foreach( $vals as $val ) {
						// maybe preserve arrays?
						$highlighted = $ctcSearchableContentUtils->highlightTerm( $val, $prefix, "extracts" );
						if( !empty( $highlighted ) ) {
							$content = array_merge( $content, $highlighted );
						}
					}
				}
				if ( $prop === "Published" ) {
					$isPublishedBool = implode( "", $vals ) === $this->smwPublicLabel;
					$isPublished = $isPublishedBool ? "1" : "0";
				}
			}
			// pagename without subobject's fragment identifier
			$pagename = explode( "#", $objName )[0];
			if ( array_key_exists( $pagename, $result ) ) {
				$content = array_merge( $result[$pagename]["snippets"], $content );
			}
			$result[$pagename] = [
				"id" => $pagename,
				"name" => $v["displaytitle"] ?? $pagename,
				"published" => $isPublished,
				"snippets" => $content
			];
		}
		$result = array_values( $result );

		$res = [
			"result" => $result
			// should be commented out in production
			// "smwquery" => $smwQueryArr,
			// "smwresult" => $smwQueryResArr,
			// "rawquery" => $rawQuery
		];

		$apiResult = $this->getResult();
		foreach( $res as $key => $val ) {
			$apiResult->addValue( null, $key, $val );
		}
	}

	public function getAllowedParams() : array {
		return [
			"prefix" => [
				ParamValidator::PARAM_TYPE => "string",
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}

}
