<?php

namespace Ctc\Content;

//use MediaWiki\MediaWikiServices;
//use Parser;
use OutputPage;
use RequestContext;
use Html;

/**
 * Builds the tab navigation seen in the Cetei namespace.
 */

class ctcTabWidget {

	private $context;

	public function __construct() {
		// @todo fewer static methods below
		$this->context = RequestContext::getMain();
	}

	public static function run(
		OutputPage &$out,
		$titleObj,
		mixed $pageTitle,
		string $ceteiInstanceDiv = "",
		string $docBtnStr = "",
		string $docPageStr = "",
		string $sourceCode = "",
		bool $hasTeiHeader = true
	) {
		$context = RequestContext::getMain();

		// Headers (text, doc, source) and top right content
		$ctcTabName1 = $context->msg( 'cetei-tabheader-1' )->parse();
		$ctcTabName2 = $context->msg( 'cetei-tabheader-2' )->parse();
		$ctcTabName3 = $context->msg( 'cetei-tabheader-3' )->parse();
		$ctcTopRight = $context->msg( 'cetei-top-right-content' )->params( $pageTitle )->parse();
		$ctcTabHeaders = self::buildTabHeaders( $ctcTabName1, $ctcTabName2, $ctcTabName3, $ctcTopRight );
		
		$ctcBeforeHeader = self::buildBeforeHeaderContent( $hasTeiHeader, $context );

		$ctcDoc = ( $docPageStr !== '' )
			? '<div class="cetei-doc">' . $docPageStr . '</div>'
			: '<div class="cetei-no-documentation">' . $context->msg( 'cetei-no-documentation' )->parse() . '</div>';

		$ctcCreditsBottom = $context->msg( 'cetei-credits-bottom' )->parse();

		// HTML before wikitext, which is not yet well-formed
		$ctcTabFragment1 = <<<EOT
		<div class="cetei-tab-content">
			<div class="cetei-tab-pane active" id="nav-pane-1">$ctcBeforeHeader
			$ceteiInstanceDiv
			</div>
		<div class="cetei-tab-pane" id="nav-pane-2">
		<div class="cetei-edit-doc">$docBtnStr</div>
		EOT;

		// HTML after wikitext, which is not yet well-formed
		$ctcTabFragment2 = <<<EOT
		</div>
		<div class="cetei-tab-pane" id="nav-pane-3">$sourceCode</div>
		</div>
		<hr/>
		<div class="cetei-credits-bottom">$ctcCreditsBottom</div>
		EOT;

		$out->addHTML( "<div class='cetei-nav-container'>" . $ctcTabHeaders . $ctcTabFragment1 );
		$out->addWikiTextAsContent(
			$ctcDoc,
			true,
			// Do not use $out->getTitle() or $context->getTitle() = may be null
			$titleObj
		);
		$out->addHTML( $ctcTabFragment2 . "</div>" );
	}

	/**
	 * Build header section for tab navigation
	 */
	private static function buildTabHeaders (
		string $ctcTabName1,
		string $ctcTabName2,
		string $ctcTabName3,
		string $ctcTopRight
	): string {
		
		$ctcTabHeaders = <<<EOT
		<div class="cetei-tab-wrapper">
		<div class="cetei-nav-tabs">
			<a class="nav-tab-item active" href="#nav-pane-1">$ctcTabName1</a>
			<a class="nav-tab-item" href="#nav-pane-2">$ctcTabName2</a>
			<a class="nav-tab-item" href="#nav-pane-3">$ctcTabName3</a>
		</div>$ctcTopRight</div>
		EOT;
		return $ctcTabHeaders;
	}

	/**
	 * Prepare toggle for TEI Header
	 */
	private static function buildBeforeHeaderContent( bool $hasTeiHeader, RequestContext $context ) {
		if ( $hasTeiHeader ) {
			$ctcToggleBtn = Html::element( 'button', [
					'id' => 'toggle-tei-header',
					'class' => 'cetei-btn'
				],
				$context->msg( 'cetei-teiheader-toggle-show' )->parse()
			);
			$ctcBeforeHeader = '<div class="cetei-toggle-wrapper">' . $ctcToggleBtn . '</div>';
		} else {
			$ctcBeforeHeader = '<div class="cetei-header-not-available">' . $context->msg( 'cetei-header-not-available' )->parse() . '</div>';
		}
		return $ctcBeforeHeader;
	}

}
