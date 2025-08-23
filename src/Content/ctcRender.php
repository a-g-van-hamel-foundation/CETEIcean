<?php

namespace Ctc\Content;

// use MediaWiki\MainConfigNames;
use OutputPage;
use Title;
use RequestContext;
use Html;
// use MediaWiki\ParserOutput;
// use MediaWiki\PPFrame;
// use MediaWiki\CoreParserFunctions;
use OOUI\ButtonWidget;
use Ctc\Core\ctcUtils;
use Ctc\Process\ctcXmlProc;
use Ctc\Content\ctcTabWidget;
use Ctc\SMW\ctcSMWPublisher;

class ctcRender {

	/**
	 * Build interface for page in Cetei: namespace
	 * @todo While static, it calls ctcTabWidget to add content dynamically.
	 * @return void
	 */
	public static function buildPage(
		OutputPage $outputPage,
		RequestContext $context,
		string $retrievedText,
		Title $titleObj,
		mixed $teiHeaderTitle = null
	) {
		$pageTitle = $titleObj->getPrefixedText();
		$ctcXmlProc = new ctcXmlProc();

		// Transform XML and create HTML
		if ( $retrievedText !== "" ) {
			$newXmlStr = $ctcXmlProc->removeAndAddDocType( $retrievedText );
			$transformedXml = $ctcXmlProc->transformXMLwithXSL( $newXmlStr, null );		
			$ceteiInstanceDiv = Html::rawElement( 'div', [
				'id' => 'cetei',
				'class' => 'cetei-instance cetei-ns-instance cetei-rendering'
				//'data-doc' => $pageUrlRaw
			], $transformedXml );
			// XML source code to be shown in pre tags
			$preSourceContent = Html::element(
				'pre', [
					'lang'=>'xml',
					'class'=>'language-xml cetei-source-xml'
				], $retrievedText
			);
			if ( strlen( $transformedXml ) < 1000000 ) {
				// Not yet suitable for large documents
				$outputPage->addModules( [ 'ext.highlight' ] );
			}
			// Hidden comments could potentially be an issue to xml parse
			$sourceContent = preg_replace( '/<!--.*?-->/s', '', $newXmlStr );
			/* Retrieve basic data from document through ctcXmlProc class  */
			$hasTeiHeader = $ctcXmlProc->hasTEIHeader( $sourceContent );
		} else {
			// Allow for starting out with a blank sheet
			$preSourceContent = $sourceContent = $ceteiInstanceDiv = "";
			$hasTeiHeader = false;
		}
		
		$aboutSectionTemplate = RequestContext::getMain()->getConfig()->get( "CeteiAboutSectionTemplate" );
		if ( $aboutSectionTemplate !== false ) {
			// Optionally, use designated template for 'About' section
			$aboutSectionFromTemplate = self::letTemplateRenderAboutSection( $aboutSectionTemplate, $pageTitle, $teiHeaderTitle );
			$aboutSectionContent = $aboutSectionFromTemplate;
			$docBtnStr = "";
		} else {
			// Else use /doc page, with button widget
			$aboutSectionFromTemplate = "";
			[ $docPageStr, $docBtnStr ] = self::getDocPage( $pageTitle, $context, $outputPage );
			$aboutSectionContent = $docPageStr;
		}

		// Optionally, check if public viewing is allowed according to SMW property
		$isPublic = true;
		$wgCeteiPublicationCheck = $context->getConfig()->get( "CeteiPublicationCheck" );
		if ( gettype( $wgCeteiPublicationCheck) === "array" && class_exists( '\SMW\StoreFactory' ) ) {
			$ctcSMWPublisher = new ctcSMWPublisher();
			$isPublic = $ctcSMWPublisher->smwIsPagePublic( $titleObj, $wgCeteiPublicationCheck );
		} elseif( $wgCeteiPublicationCheck !== false && !class_exists( '\SMW\StoreFactory' ) ) {
			// If SMW was disabled, hide everything just in case? @todo
			$isPublic = false;
		}

		if( !ctcUtils::isUser() && !$isPublic ) {
			$notPublicMsg = "<div class='cetei-alert'>" . $context->msg( "cetei-not-public" )->parse() . "</div>";
			$outputPage->addWikiTextAsContent(
				// @dev - we still need section to render, or we'll lose properties
				$notPublicMsg . "<div style='display:none;'>" . $aboutSectionContent . "</div>",
				true,
				$titleObj
			);
			return;
		}

		/* Build tab widget and assign content
		* $ceteiInstanceDiv = html for CETEIcean;
		* $docBtnStr = button for doc subpage;
		* $docPageStr = wikitext from doc subpage;
		* $preSourceContent = source code to be rendered with pre tags;
		*/
		$tabWidget = new ctcTabWidget();
		$tabWidget->run(
			$outputPage,
			$titleObj,
			$pageTitle,
			$ceteiInstanceDiv,
			$docBtnStr,
			$aboutSectionContent,
			$preSourceContent,
			$hasTeiHeader
		);
		return;
	}

	/**
	 * Clean XML string and get a title from TEI Header
	 * Defaults to $pageName
	 */
	public static function cleanAndGetHeaderTitle(
		string $xmlStr,
		string $pageName
	): string {
		$ctcXmlProc = new ctcXmlProc();
		// Sanitise: remove inline comments
		$xmlStr = preg_replace( '/<!--.*?-->/s', '', $xmlStr );
		// Out with the old, in with the new doc type
		$newXmlStr = $ctcXmlProc->removeAndAddDocType( $xmlStr );
		$ctcHeaderTitle = $ctcXmlProc->getHeaderTitle( $newXmlStr );
		$displayTitle = $ctcHeaderTitle !== null ? self::sanitiseDisplayTitle($ctcHeaderTitle) : $pageName;
		return $displayTitle;
	}

	/**
	 * Sanitise title string for use as display title
	 * @todo Refine?
	 */
	public static function sanitiseDisplayTitle( $str ) {
		$str = strip_tags( $str );
		$res = html_entity_decode( $str );
		return $res;
	}

	/**
	 * Get /doc subpage as well as button string
	 * @return array
	 */
	public static function getDocPage(
		mixed $pageTitle,
		RequestContext $context,
		OutputPage $outputPage
	) {
		$docPageTitle = $pageTitle . "/doc";
		if ( ctcUtils::hasDocPage( $pageTitle, $outputPage ) ) {
			$docAddMsg = $context->msg( "cetei-edit-documentation" )->parse();
			//$linkDocUrl = Title::newFromText( $docPageTitle )->getFullURL( 'action=edit' );
			$linkDocUrl = $context->msg( "cetei-edit-documentation-url" )->params( $docPageTitle )->text();
			if ( ctcUtils::isUser() ) {
				$docBtnStr = self::createButtonWidget( $outputPage, $docAddMsg, $linkDocUrl, 'edit', null );
			} else {
				 //default
				$docBtnStr = "";
			}
			$docPageStr = self::showDocPage( $pageTitle );
		} else {
			// defaults
			$docBtnStr = $docPageStr = "";
			if ( ctcUtils::isUser() ) {
				//$linkDocUrl = Title::newFromText( $docPageTitle )->getFullURL( 'action=edit' );
				$linkDocUrl = $context->msg( "cetei-edit-documentation-url" )->params( $docPageTitle )->text();
				$docAddMsg = $context->msg( "cetei-add-documentation" )->parse();
				$docBtnStr = self::createButtonWidget( $outputPage, $docAddMsg, $linkDocUrl, "edit", null );
			} else {
				$docPageStr = "";
			}
		}
		return [ $docPageStr, $docBtnStr ];
	}

	/**
	 * Check whether or not current page is an associated /doc subpage
	 * on the basis of the title
	 */
	public static function isDocPage( string $pageTitle ) {
		return preg_match("/\/doc/i", $pageTitle ) ? true : false;
	}

	/**
	 * @deprecated Moved to ctcUtils
	 */
	public static function hasDocPage(
		mixed $title = false,
		mixed $outputPage = null
	) {
		return ctcUtils::hasDocPage($title, $outputPage );
	}

	/**
	 * Transclude /doc subpage
	 */
	private static function showDocPage( string $forPageTitle ) {
		$docPageTitle = $forPageTitle . "/doc";
		// @todo maybe attempt to normalise title?
		// $docPageTitle = Title::newFromText( $docPageTitle )->getPrefixedText();
		$docPageStr = "{{" . $docPageTitle . "}}<hr />";
		$docWikitext = Html::rawElement(
			"div",
			[ "class" => "cetei-doc-page" ],
				// Line breaks are needed so that wikitext would be
				// appropriately isolated for correct parsing. 
				// @link https://phabricator.wikimedia.org/T62664
			"\n" . $docPageStr . "\n"
		);
		return $docWikitext;
	}

	/**
	 * Leaves it up to wiki template ($wgCeteiAboutSectionTemplate)
	 * what content should be in the 'About' section.
	 * @param string|null $templateName
	 * @param string $pageTitle
	 * @param string|null $teiHeaderTitle - the title we found in the TEI Header
	 */
	private static function letTemplateRenderAboutSection(
		mixed $templateName,
		string $pageTitle,
		mixed $teiHeaderTitle = null
	) {
		if ( $templateName === null OR $templateName === "" ) {
			return "";
		}
		$displayTitle = $teiHeaderTitle ?? "";
		// fullpagename
		$txt = "{{{$templateName}
		|FULLPAGENAME={$pageTitle}
		|Fullpagename={$pageTitle}
		|Displaytitle={$displayTitle}
		}}";
		return Html::rawElement(
			"div",
			[ "class" => "cetei-doc-page" ],
			"\n" . $txt . "\n"
		);
	}

	/**
	 * Create OOUI-styled button link.
	 */
	public static function createButtonWidget( $out, $text, $linkUrl = null, $icon = null, $id = null ) {
		$out->enableOOUI();
		$out->setupOOUI('default','ltr');
		$out->addModules( [ 'ext.oojs.assets' ] );
		$out->addModuleStyles( [ 'oojs-ui.styles.icons-content', 'oojs-ui.styles.icons-editing-core' ] );

		$btn = new ButtonWidget( [
			'label' => $text,
			'title' => $text,
			'href' => $linkUrl,
			'icon' => $icon,
			'id' => $id
		] );

		return $btn;
	}

	/**
	 * @deprecated. Moved to ctcUtils.
	*/
	private static function isUser() {
		return ctcUtils::isUser();
	}

	/**
	 * @todo On the roadmap.
	 * Method for getting data from slot defined in wgCeteiMetadataSlot
	 * so we can assign them directly to the template.
	 * Will perhaps depend on TemplateFunc extension
	 */
	private static function getSlotData( Title $titleObj ) {
		$dataSlot = RequestContext::getMain()->getConfig()->get( "CeteiMetadataSlot" );
		if( $dataSlot !== false ) {
			$slotContent = ctcUtils::getRawContentFromTitleObj( $titleObj, $dataSlot );
			if ( $slotContent !== false && $slotContent !== "" ) {
				// @todo
			}
		}
	}

}
