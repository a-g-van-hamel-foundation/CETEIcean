<?php

/**
 * @since 0.8
 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/bb7ec311e768f4118b91aff7424e4b61bbca16fe/docs/examples/semanticdata.access.md
 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/bb7ec311e768f4118b91aff7424e4b61bbca16fe/docs/examples/hook.pagecontentsavecomplete.md?plain=1#L2
 */

namespace Ctc\SMW;

use Parser;
use SMW\Services\ServicesFactory;
use SMW\DataValueFactory;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\SemanticData;
use SMW\Subobject;
use SMW\ParserData;
use SMWDataItem;
use SMWDIBlob;
use Ctc\Process\ctcXmlProc;
use Ctc\Content\ctcSearchableContentUtils;

class ctcSMWStore {

	public function __construct() {
		//
	}

	/**
	 * Get SemanticData from either ParserOutput or Title
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/includes/SemanticData.php
	 * 
	 * @param mixed $parserOutput
	 * @param mixed $title
	 * @return SemanticData
	 */
	public function getSemanticData( $parserOutput = null, $title = null ) {
		$semanticData = $parserOutput !== null
			? $parserOutput->getExtensionData( ParserData::DATA_ID )
			: null;
		if ( $title !== null && !( $semanticData instanceof SemanticData ) ) {
			$diWikiPage = new DIWikiPage( $title->getDBkey(), $title->getNamespace(), $title->getInterwiki(), "" );
			// $diWikiPage = DIWikiPage::newFromTitle( $title );
			$semanticData = new SemanticData( $diWikiPage, true );
		}
		return $semanticData;
	}

	/**
	 * @todo
	 */
	public function updateSemanticData( $semanticData, $newSemanticData ) {
		$semanticData->importDataFrom( $newSemanticData );
	}

	// @todo
	// Parser
	public function storePropertyValuePair(
		$parser,
		$title,
		mixed $propertyName = null,
		array $values = []
	) {
		$this->storePropertyValuePairsInParserOutput( $parser->getOutput(), $title, $propertyName, $values );		
	}

	/**
	 * ~ #set
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/175ea803aeb831a5efa0ffe4266073e604f71046/src/ParserFunctions/SetParserFunction.php
	 * @link https://github.com/ProfessionalWiki/SemanticWikibase/blob/ce0c8da9e099f24b061d4bf85b4e5ab941345edb/src/SMW/SemanticEntity.php
	 * 
	 * @param Title $title
	 * @param string $propertyName
	 * @param string $value
	 * @return
	 */
	public function storePropertyValuePairsInParserOutput(
		$parserOutput,
		$title,
		mixed $propertyName = null,
		array $values = []
	) {
		$parserData = ServicesFactory::getInstance()->newParserData(
			$title,
			$parserOutput
		);

		$diProperty = new DIProperty( str_replace( " ", "_", $propertyName ) );
		if ( ! $diProperty instanceof DIProperty ) {
			//return;
		}

		// SMWDataValueFactory::newPropertyObjectValue($property, "The End of Mr Y");
		// $parserData->getSemanticData()->addPropertyObjectValue( ...)
		// same as $parserData->addDataValue( $dataValue );

		$diWikiPage = $parserData->getSubject();
		foreach( $values as $value ) {
			if( $value === "" || $value === null ) {
				continue;
			}
			$dataItem = new SMWDIBlob( $value );
			$parserData->getSemanticData()->addPropertyObjectValue( $diProperty, $dataItem );

			$dataValue = DataValueFactory::getInstance()->newDataValueByProperty(
				$diProperty,
				trim( $value ),
				false,
				$diWikiPage
			);
			$parserData->addDataValue( $dataValue );
		}

		// SMWDataValueFactory::newPropertyObjectValue($property, "The End of Mr Y");		
		// todo foreach value...
		$parserData->copyToParserOutput();
		//return $semanticData;
		ServicesFactory::getInstance()->getStore()->updateData(
			$parserData->getSemanticData()
		);
	}

	/**
	 * Do property value pairs instead
	 * @param mixed $title
	 * @param string $propertyName
	 * @param array $values
	 * 
	 * @param array $propValuePairs - pairs of property (array key) and property value(s) (array value, itself a sequential array)
	 * @return void
	 */
	public function storePropertyValuePairsInSubobject( $title, array $propValuePairs ) {
		// Set everything up
		// $subject = new DIWikiPage( 'Foo', NS_MAIN );
		$diWikiPage = new DIWikiPage( $title->getDBkey(), $title->getNamespace(), $title->getInterwiki(), "" );
		$semanticData = new SemanticData( $diWikiPage );
		$subobject = new Subobject( $diWikiPage->getTitle() );
		// @todo
		$subobject->setEmptyContainerForId( "cetei-excerpt-" . rand(1000,9999) );

		foreach( $propValuePairs as $propertyName => $values ) {
			foreach( $values as $value ) {
				$dataValue = DataValueFactory::getInstance()->newDataValueByText(
					$propertyName,
					$value
				);
				$subobject->addDataValue( $dataValue );
			}
		}

		$semanticData->addPropertyObjectValue(
				$subobject->getProperty(),
				$subobject->getContainer()
			);
		
		ServicesFactory::getInstance()->getStore()->updateData(
			$semanticData
		);
	}

	/**
	 * PLURAL
	 */
	public function storeInSubobjects( $title, array $subobjectData, $diWikiPage = null ) {
		// Set everything up
		if ( $diWikiPage === null ) {
			// $subject = new DIWikiPage( 'Foo', NS_MAIN );
			$diWikiPage = new DIWikiPage( $title->getDBkey(), $title->getNamespace(), $title->getInterwiki(), "" );
		}
		$newSemanticData = new SemanticData( $diWikiPage );

		foreach( $subobjectData as $k => $subobjectItem ) {
			$subobject = new Subobject( $diWikiPage->getTitle() );
			// @todo
			$subobject->setEmptyContainerForId( "cetei-excerpt-" . $k );
			foreach( $subobjectItem as $propertyName => $values ) {
				foreach( $values as $value ) {
					$dataValue = DataValueFactory::getInstance()->newDataValueByText(
						$propertyName,
						$value
					);
					$subobject->addDataValue( $dataValue );
				}
				$newSemanticData->addPropertyObjectValue(
					$subobject->getProperty(),
					$subobject->getContainer()
				);
			}
		}

		/* server-heavy 
		ServicesFactory::getInstance()->getStore()->updateData(
			$newSemanticData
		);
		*/
		return $newSemanticData;
	}

	/**
	 * Expects the 
	 * @param \Parser $parser
	 * @param mixed $text - string to be chunked. Any XSLT should be done in advance.
	 * @param mixed $title - Title that must hold semantic properties
	 * @param mixed $propertyName - name of the property (must be datatype Text) to be used
	 * @return void
	 */
	public function createChunksAndSemantify(
		$parser,
		$text,
		$title,
		$propertyName = null
	) {
		if( $propertyName === null ) {
			return;
		}
		
		// Split up string into chunks with some overlap to allow for matching across boundaries 
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		$propertyValues = $ctcSearchableContentUtils->splitContentIntoOverlappingChunks( $text, 300, 10 );
		$this->storePropertyValuePair( $parser, $title, $propertyName, $propertyValues );
	}

	/**
	 * @todo Work in progress because objectives are not clear
	 * Currently stores excerpt as stripped down text string in a subobject
	 * 
	 * @param mixed $title
	 * @param string $text
	 * @param string $paramSel
	 * @param string|null $propertyName - name of property to use 
	 * @return void
	 */
	public function createExcerptsAndSemantify(
		$title,
		string $xmlStr,
		string $paramSel,
		$propertyName = null
	) {
		// First create XML excerpts
		//$ctcXmlProc = new ctcXmlProc();

		// Create version with entities replaced
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		$text = $ctcSearchableContentUtils->transformXMLForSearch( $xmlStr, false );
		//print_r( "<pre>" . htmlentities($text) . "</pre>" );

		// @todo - the DTD used relegates notes to a separate section. Is that what we want though?

		// Create excerpts
		$excerpts = [];
		$excerpts = ctcXmlProc::getExcerpts( $text, $paramSel );
		if ( $excerpts == false ) {
			return;
		}
		$flatTexts = [];
		foreach( $excerpts as $excerpt ) {
			$excerptStr = $excerpt->asXml();
			$flatText = $ctcSearchableContentUtils->flattenForSearch( trim($excerptStr) );
			if ( $flatText !== "" ) {
				// print_r( "<pre>" . htmlentities($flatText) . "</pre>" );
				$flatTexts[] = $flatText;
			}
		}		
		$propValuePairs = [
			$propertyName => $flatTexts
		];
		// @todo Supposing we want a subobject
		$this->storePropertyValuePairsInSubobject( $title, $propValuePairs );
	}

}
