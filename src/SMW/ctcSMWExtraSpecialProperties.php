<?php

/**
 * Class for extending special properties through the
 * Special Extra Semantic Properties (SESP) extension
 * 
 * Currently covers searchable text only; but abandoned when
 * it appeared SMW queries using FTS could not cope with
 * lengthy strings;
 * and that a requires a rebuildData for every semantic wiki page
 */

namespace Ctc\SMW;

use SMW\DIProperty;
use SMW\DIWikiPage;
use SMWDataItem;
use SMWDIBlob;
use Ctc\Process\ctcXmlProc;
use Ctc\Content\ctcRender;
use Ctc\Content\ctcSearchableContentUtils;
use Ctc\Core\ctcUtils;

class ctcSMWExtraSpecialProperties {

	// Property
	private $searchableTextID = "__TEI_SEARCHABLE_TEXT";

	public function __construct() {
		//
	}

	public function extendSESP() {
		if ( !isset( $GLOBALS['sespgLocalDefinitions'] ) ) {
			$GLOBALS['sespgLocalDefinitions'] = [];
		}

		$GLOBALS["sespgLocalDefinitions"][$this->searchableTextID] = $this->setupSESPDefinitionForTEI( $this->searchableTextID );
		$GLOBALS['sespgEnabledPropertyList'][] = $this->searchableTextID;
	}

	// UNUSED
	public function getDIProperty( $id ) {
		return new DIProperty( $id );
	}

	/*
	$sespgLocalDefinitions['_MY_CUSTOM2'] = [
		'id'    => '___MY_CUSTOM2',
		'type'  => '_wpg',
		'alias' => 'some-...',
		'label' => 'SomeCustomProperty2',
		'callback'  => [ 'FooCustom', 'addAnnotation' ]
	];
	*/
	/**
	 * @todo Consider SMW_DI_Blob instead?
	 * @param
	 * @return array
	 */
	private function setupSESPDefinitionForTEI( string $id ) {
		$def = [
			"id"    => "__$id",
			"type"  => "_txt",
			"alias" => "cetei-property-searchable-content",
			"label" => "Has content for TEI search",
			"desc"  => "cetei-property-searchable-content-desc",
			"callback"  => [ ctcSMWExtraSpecialProperties::class, "addAnnotation" ]
		];
		return $def;
	}

	/**
	 * @param AppFactory $appFactory
	 * @param DIProperty $property
	 * @param SemanticData $semanticData
	 */
	public static function addAnnotation(
		$appFactory,
		$property,
		$semanticData
	) {
		// First check if the current page is relevant
		// subject = DIWikiPage
		$title = $semanticData->getSubject()->getTitle();
		if ( $title->getNamespace() !== NS_CETEI
			|| ctcRender::isDocPage( $title->getPrefixedText() )
		) {
			return;
		}

		$rawContent = ctcUtils::getRawContentFromTitleObj( $title );
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		$searchableContent = $ctcSearchableContentUtils->flattenTextForSearch( $rawContent );
		$dataItem = new SMWDIBlob( $searchableContent ?? "" );

		// To be on the safe side
		if ( ! $dataItem instanceof SMWDataItem ) {
			return;
		}
		/* Commented out for now: 
		$semanticData->addPropertyObjectValue(
			$property,
			$dataItem
		);
		*/
	}

}
