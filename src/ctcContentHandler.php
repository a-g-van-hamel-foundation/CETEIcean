<?php
namespace Ctc\Core;

use Content;
use ContentHandler; //?
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use Html;
use MediaWiki\OutputPage as OutputPage;
use RequestContext;
use Title;
use ParserOutput;
use Ctc\Core\ctcContent;
use Ctc\Core\ctcRender;

class ctcContentHandler extends \CodeContentHandler {

	/**
	 * @param string $modelId
	*/
	//public const MODEL = CONTENT_MODEL_XML;

	public function __construct(
		$modelId = CONTENT_MODEL_XML,
		$formats = [ CONTENT_FORMAT_TEXT ]
	) {
		parent::__construct( $modelId, $formats );
	}

	/**
	 * @see TextContentHandler::getContentClass
	 * @return string
	 */
	protected function getContentClass() {
		return ctcContent::class;
	}

	/**
	 * Create empty cottent object and set default text for starting a new page
	 * @see ContentHandler::makeEmptyContent
	 * @return ctcContent
	 */
	public function makeEmptyContent() {
		return new ctcContent(
			wfMessage( 'cetei-default-content' )->plain()
		);
	}

	/** Returns false if namespace is not NS_CETEI
		* @return bool
		*/
	public function canBeUsedOn( Title $title ) {
	  //Only in NS_CETEI
	  if ( $title->getNamespace() !== NS_CETEI ) {
	    return false;
	  }
	  // make an exception for doc pages?
	  return parent::canBeUsedOn( $title );
	}

	/**
	 * Since MW 1.38, originally in ctcContent.php
	 * Fills provided ParserOutput object with information derived from the content
	 * Unless $generateHtml is false, includes HTML representation of content provided by getHtml().
	 * For content models listed in $wgTextModelsToParse, this method will call the MediaWiki wikitext parser on the text to extract any (wikitext) links, magic words, etc., but note that the Table of Contents will not be generated (feature added by T307691, but should be refactored: T313455).
	 * Subclasses may override this to provide custom content processing. For custom HTML generation alone, it is sufficient to override getHtml().
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams, 
		ParserOutput &$parserOutput
	) {
		$services = MediaWikiServices::getInstance();
		$pageIdentity = $cpoParams->getPage();
		$title = \Title::castFromPageReference( $pageIdentity ); //same as $title = $services->getTitleFactory()->castFromPageReference( $pageIdentity ); // ?
		$options = $cpoParams->getParserOptions();
		$revId = $cpoParams->getRevId();

		//checks
		$generateHtml = $cpoParams->getGenerateHtml(); // true or false
		if ( $generateHtml == false ) {
			$parserOutput->setText( "..." );
			// @todo insert error messsage here
			return;
		}
		$textModelsToParse = $services->getMainConfig()->get( MainConfigNames::TextModelsToParse );
		// preferred to global $wgTextModelsToParse;
		$contentModel = $content->getModel(); // expected: cetei
		if ( in_array( $contentModel, $textModelsToParse ) ) {
			// Not cetei. Parse just to get links, etc., into database; HTML is replaced below.
			$parserOutput = $services->getParserFactory()->getInstance()->parse(
                     $content->getText(),
                     $pageIdentity,
                     $options,
                     true,
                     true,
                     $revId
                 );
		}
		self::checkContentStatus( $parserOutput, $content );

		// Get OutputPage from context and buildPage
		// alternatives haven't worked because of OOUI 
		$context = new \RequestContext();
		$outputPage = \RequestContext::getMain()->getOutput(); // Get RequestContext object associated with the main request
		$freshContent = $content->getContentRefreshed( $parserOutput );
		ctcRender::buildPage(
			$outputPage,
			$freshContent, // $content->getNativeData(),  // 
			$title, //same as $out->getTitle(),
    		$title->getText() // same as $out->getTitle()->getText()
		);
		// 
		$displayTitle = ctcRender::cleanAndGetHeaderTitle( $freshContent, $title->getText() );
		$parserOutput->setDisplayTitle( $displayTitle );

		$res = "";
		$parserOutput->clearWrapperDivClass();
		$parserOutput->setText( "" );
	}

	/**
	 * Not used
	**/
	private static function checkContentStatus( &$parserOutput, $content ) {
		if ( $content->isValid() == false ) {
			$el = Html::rawElement( 'div', [ 'class' => 'error' ], "" );
			$parserOutput->setText( $el );
			return;
		}
	}

}
