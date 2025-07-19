<?php

namespace Ctc\Content;

//use MediaWiki\MediaWikiServices;
//use MediaWiki\MainConfigNames;
//use MediaWiki\StatusValue;
//use OutputPage;
use TextContent;
//use OOUI\IndexLayout;
//use OOUI\PanelLayout;
//use OOUI\TabPanelLayout;
//use OOUI\TabSelectWidget;
//use OOUI\TabOptionWidget;
//use Ctc\Content\ctcRender;
use Ctc\Process\ctcXmlProc;

class ctcContent extends TextContent {

	/**
	 * @param string $text
	 * @param string $modelId
	 * @param bool $thorough
	 */
	 public const MODEL = CONTENT_MODEL_XML;
	 public function __construct( $text, $modelId = self::MODEL ) {
		parent::__construct( $text, $modelId );
	 }

	/**
	 * Gets content for wiki's search index
	 * Adds attribute values separately.
	 * @return string
	 */
	public function getTextForSearchIndex() {
		if( $this->getText() === "" ) {
			return "";
		}

		$ctcXmlProc = new ctcXmlProc();
		$rawStr = ctcXmlProc::removeDocType( $this->getText() );
		$xmlStr = ctcXmlProc::addDocType( $rawStr );

		// The text itself, with entities
		$xmlStrTransformed = $ctcXmlProc->transformXMLwithXSL( $xmlStr, null );
		$taglessStr = strip_tags( $xmlStrTransformed );

		// Make attribute values searchable, too
		$attrValsArr = ctcXmlProc::getAttributeValues( $xmlStr );
		$attrVals = implode( " | ", $attrValsArr );

		$res = html_entity_decode( $taglessStr . " " . $attrVals, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		return $res;
	}

	/**
	 * @return string|bool
	 * The content to include when it is transcluded by another wikitext page.
	 * Return false if the content is not includable in a wikitext page.
	 * Transclusion probably only makes sense if we want to reveal the unprocessed content in pre tags
	 * @todo: make this work with both SyntaxHighlight and Highlight_Integratiion?
	 */
	public function getWikitextForTransclusion() {
		if( $this->getText() === "" ) {
			return "";
		}
		$textObject = $this->convert( CONTENT_MODEL_TEXT );
		'@phan-var WikitextContent $wikitext';
		if ( $textObject ) {
			$text = $textObject->getText();
			$text = htmlentities( $text, ENT_QUOTES, 'UTF-8' );
			return $text;
		} else {
			return false;
		}
	}

	public function getContentRefreshed( $output ) {
		$output->updateCacheExpiry(0);
		$res = $this->getText();
		return $res;
	}

	public function getTextForContentHandler() {
		global $wgTextModelsToParse;
		if ( in_array( $this->getModel(), $wgTextModelsToParse ) ) {
			// Parse to get links, etc., into database; HTML is replaced below.
			// Meant to invalidate cache but updateCacheExpiry can only be called on the ParserOutput object.
			$textToParse = $this->updateCacheExpiry(0)->getText();
		} else {
			// Same for now
			$textToParse = $this->updateCacheExpiry(0)->getText();
		}
		return $textToParse;
	}

}
