<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;
use MediaWiki\PPFrame;
use MediaWiki\CoreParserFunctions;
use Ctc\Core\ctcXmlProc;
use Ctc\Core\ctcTabWidget;

class ctcRender {

	/**
	 * Build page in Cetei: namespace
	 * $pageTitle // $out->getTitle() ?
	 *  // $out->getTitle()->getText() ?
	 **/
	public static function buildPage(
		\OutputPage $out,
		string $retrievedText,
		$pageTitle,
		$pageName
	) {
		$context = \RequestContext::getMain();
		//$out = \RequestContext::getMain()->getOutput();
		$ctcXmlProc = new ctcXmlProc();

		// Transform XML and create HTML
		$newXmlStr = $ctcXmlProc->removeAndAddDocType( $retrievedText );
		$transformedXml = $ctcXmlProc->transformXMLwithXSL( $newXmlStr, null );		
		$ceteiInstanceDiv = \Html::rawElement( 'div', [
			'id' => 'cetei',
			'class' => 'cetei-instance cetei-ns-instance cetei-rendering'
			//'data-doc' => $pageUrlRaw
		], $transformedXml );

		// XML source code to be shown in pre tags
		$preSourceContent = \Html::element(
			'pre', [
				'lang'=>'xml',
				'class'=>'language-xml cetei-source-xml'
			], $retrievedText
		);
		if ( strlen( $transformedXml ) < 1000000 ) {
			// Not yet suitable for large documents
			$out->addModules( [ 'ext.highlight' ] );
		}

		// Hidden comments could potentially be an issue to xml parse
		$sourceContent = preg_replace( '/<!--.*?-->/s', '', $newXmlStr );

		// /doc subpage:
		$docPageTitle = $pageTitle . '/doc';
		if ( self::hasDocPage( $pageTitle, $out ) == true ) {
			$docAddMsg = $context->msg( 'cetei-edit-documentation' )->parse();
			//$linkDocUrl = Title::newFromText( $docPageTitle )->getFullURL( 'action=edit' );
			$linkDocUrl = $context->msg( 'cetei-edit-documentation-url' )->params( $docPageTitle )->text();
			if ( ctcUtils::isUser() == true ) {
				$docBtnStr = self::createButtonWidget( $out, $docAddMsg, $linkDocUrl, 'edit', null );
			} else {
				$docBtnStr = ''; //default
			}
			$docPageStr = self::showDocPage( $out, $pageTitle );
		} else {
			// defaults
			$docBtnStr = $docPageStr = "";
			if ( ctcUtils::isUser() ) {
				//$linkDocUrl = Title::newFromText( $docPageTitle )->getFullURL( 'action=edit' );
				$linkDocUrl = $context->msg( 'cetei-edit-documentation-url' )->params( $docPageTitle )->text();
				$docAddMsg = $context->msg( 'cetei-add-documentation' )->parse();
				$docBtnStr = self::createButtonWidget( $out, $docAddMsg, $linkDocUrl, 'edit', null );
			} else {
				$docPageStr = '';
			}
		}

		/* Retrieve basic data from document through ctcXmlProc class  */
		$ctcXmlProc = new ctcXmlProc();

		/* Moved to ctcContentHandler, using ParserOutput instead not OutputPage
		$displayTitle = self::cleanAndGetHeaderTitle( $retrievedText, $pageName );
		// Add to output page - maybe not necessary @todo
		$out->setPageTitle( $displayTitle );
		$out->setDisplayTitle( $displayTitle );
		*/

		$hasTeiHeader = $ctcXmlProc->hasTEIHeader( $sourceContent );

		/* Build tab widget and assign content
		* $ceteiInstanceDiv = html for CETEIcean;
		* $docBtnStr = button for doc subpage;
		* $docPageStr = wikitext from doc subpage;
		* $preSourceContent = source code to be rendered with pre tags;
		*/
		$tabWidget = new ctcTabWidget();
		$res = $tabWidget->run(
			$out,
			$pageTitle,
			$ceteiInstanceDiv,
			$docBtnStr,
			$docPageStr,
			$preSourceContent,
			$hasTeiHeader
		);

		return $res;
	}

	/**
	 * Clean XML string and get a title from TEI Header
	 * Defaults to $pageName
	 */
	public static function cleanAndGetHeaderTitle( $xmlStr, string $pageName ) {
		$ctcXmlProc = new ctcXmlProc();
		// sanitise: remove inline comments
		$xmlStr = preg_replace( '/<!--.*?-->/s', '', $xmlStr );
		// out with the old, in with the new doc type
		$newXmlStr = $ctcXmlProc->removeAndAddDocType( $xmlStr );
		//$sourceContent = preg_replace( '/<!--.*?-->/s', '', $newXmlStr );
		$ctcHeaderTitle = $ctcXmlProc->getHeaderTitle( $newXmlStr );
		$displayTitle = ( $ctcHeaderTitle !== null ) ? self::sanitiseDisplayTitle($ctcHeaderTitle) : $pageName;
		return $displayTitle;
	}

	/**
	 * Sanitise title string for use as display title
	 * @todo refine
	 * @todo maybe use HtmlArmor
	 */
	public static function sanitiseDisplayTitle( $str ) {
		$str = strip_tags( $str );
		$res = $str;
		//$title = Title::newFromText( \Sanitizer::stripAllTags( $str ) );
		// Decode entities in $text the same way that Title::newFromText does
		//$filteredText = \Sanitizer::decodeCharReferencesAndNormalize( $str );
		/* 
		$bad = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'blockquote', 'ol', 'ul', 'li', 'hr', 'table', 'tr', 'th', 'td', 'dl', 'dd', 'caption', 'p', 'ruby', 'rb', 'rt', 'rtc', 'rp', 'br' ];
		$res = \Sanitizer::removeSomeTags( $str, [
			'removeTags' => $bad,
		]);
		*/
		return $res;
	}

	/* Check whether or not current page is an associated /doc subpage */
	public static function isDocPage( $title ) {
		if (preg_match("/\/doc/i", $title )) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check whether or not TEI page ($title) has an associated /doc subpage 
	 **/
	public static function hasDocPage( $title, $outputPage ) {
		if ( $title ) {
			$docPageStr = $title . '/doc';
		} else {
			$docPageStr = $outputPage->getTitle() . '/doc';
		}
		$docPageObj = \Title::newFromText( $docPageStr );
		$docPageID = $docPageObj->getArticleID() ;
		// if Page ID equals 0, page does not exist
		if ( $docPageID !== 0 ) {
			return true;
			} else {
			return false;
		}
	}

	/**
	 * Transclude /doc subpage 
	 **/
	private static function showDocPage( $out, $forPageTitle ) {

		$docPageTitle = $forPageTitle . '/doc';
		$docPageObj = \Title::newFromText( $docPageTitle );
		$docPageID = $docPageObj->getText();
		$docPageStr = '{{' . $docPageTitle . '}}<hr />';
		$docWikitext = \Html::rawElement( 'div', [
			'class' => 'cetei-doc-page' ],
				// Line breaks are needed so that wikitext would be
				// appropriately isolated for correct parsing. See Bug 60664.
			"\n" . $docPageStr . "\n"
			);
		return $docWikitext;

	}

	/* create OOUI-styled button link */
	public static function createButtonWidget( $out, $text, $linkUrl = null, $icon = null, $id = null ) {

		$out->enableOOUI();
		$out->setupOOUI('default','ltr');
		$out->addModules( [ 'ext.oojs.assets' ] );
		$out->addModuleStyles( [ 'oojs-ui.styles.icons-content', 'oojs-ui.styles.icons-editing-core' ] );

		$btn = new \OOUI\ButtonWidget( [
			'label' => $text,
			'title' => $text,
			'href' => $linkUrl,
			'icon' => $icon,
			'id' => $id
		] );

		return $btn;

	}

	/**
	 * Deprecated. Moved to ctcUtils.
	 * Check if present user is in the 'user' group
	 * @todo: change hardcoded 'user' to language-independent value?
	 * @todo: check whether user has editing rights = preferred action
	*/
	private static function isUser() {
		$presentUser = \RequestContext::getMain()->getUser();
		$presentUserGroups = [];
		$presentUserGroups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $presentUser );
		if ( in_array( 'user', $presentUserGroups ) ) {
			return true;
		} else {
			return false;
		}
	}

}
