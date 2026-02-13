<?php

namespace Ctc\Core;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Title\Title;
use MediaWiki\Context\RequestContext;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\MediaWikiServices;
use MediaWiki\Config\Config;
use HtmlArmor;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\SpecialSearchProfilesHook;
use MediaWiki\Hook\SetupAfterCacheHook;
use ALTree;
use ALSection;
use ALItem;
use ALRow;
use Ctc\Content\ctcRender;
use Ctc\Special\ctcSpecialUtils;
use Ctc\Content\ctcSearchableContentUtils;
use Ctc\SMW\ctcSMWStore;
use Ctc\ParserFunctions\ctcParserFunctions;
use Ctc\ParserFunctions\ctcAlign;
use Ctc\ParserFunctions\ctcFetch;
use Ctc\ParserFunctions\ctcSearch;
use Ctc\ParserFunctions\ctcHighlight;

class ctcHooks implements
	ParserFirstCallInitHook,
	BeforePageDisplayHook,
	HtmlPageLinkRendererBeginHook,
	ContentHandlerDefaultModelForHook,
	ResourceLoaderGetConfigVarsHook,
	ParserAfterTidyHook,
	SpecialSearchProfilesHook,
	SetupAfterCacheHook
{

	public function __construct() {
		//
	}

	/**
	 * Content handler for namespace through callback
	 * (extension.json has "callback": "ctcHooks::registrationCallback")
	 */
	public static function registrationCallback() {
		define( 'CONTENT_MODEL_XML', 'cetei' );
		//define( 'CONTENT_FORMAT_XML', 'application/tei+xml' );
		//define( 'NS_CETEI', 350, true);
		//define( 'NS_CETEI_TALK', 351, true);
	}

	/**
	 * Register hooks for parser functions
	 * #cetei, #cetei-align, #cetei-ace, etc.
	 **/
	public function onParserFirstCallInit( $parser ) {
		$flags = Parser::SFH_OBJECT_ARGS;

		$parser->setFunctionHook(
			"cetei",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcParserFunctions;
				return $pf->runCeteiPF( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			"cetei-align",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcAlign;
				return $pf->runCeteiAlignPF( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			"cetei-ace",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcParserFunctions;
				return $pf->runCeteiAcePF( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			"cetei-fetch",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcFetch;
				return $pf->runCeteiFetchPF( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			"cetei-search",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcSearch;
				return $pf->runCeteiSearchPF( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			"cetei-smw-search",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcSearch;
				return $pf->runCeteiSMWSearchPF( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			"cetei-highlight",
			function( Parser $parser, PPFrame $frame, array $args ) {
				$pf = new ctcHighlight;
				return $pf->runCeteiHighlightPF( $parser, $frame, $args );
			},
			$flags
		);

		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 */
	public function onBeforePageDisplay( $out, $skin ): void {

		$namespaceConstant = $out->getTitle()->getNamespace();
		$action = $out->getRequest()->getVal( 'action' );

		$out->addModuleStyles( [ 'ext.ctc.styles' ] );
		$out->addModules( [ 'ext.ctc' ] );

		if ( $namespaceConstant === NS_CETEI ) {
			$out->addModuleStyles( [ 'ext.tabs.styles' ] ); // prevent FOUC
			$out->addModules( [ 'ext.tabs.assets' ] );
			if ( $action == 'edit' || $action == 'submit' ) {
				$out->addModuleStyles( [ 'ext.ctc.editor.styles' ] );
				$out->addModules( [ 'ext.ctc.wikieditor' ] );
			}
		}

	}

	/**
	 * Make sure the display title sticks to page links
	 */
	public function onHtmlPageLinkRendererBegin( $linkRenderer, $linkTarget, &$text, &$customAttribs, &$query, &$ret ) {
		// Only if namespace = Cetei and not a /doc subpage
		$requestContext = RequestContext::getMain();
		$title = $requestContext->getTitle();
		if ( $title && $title->canExist() && $title->getNamespace() === NS_CETEI ) {			
			$isDoc = ctcRender::isDocPage( $title );
			if ( !$isDoc ) {
				if ( $text instanceof HtmlArmor ) {
					// this shouldn't have happened
					$textCompared = HtmlArmor::getHtml( $text );
				} else {
					$textCompared = trim( $text );
				}
				$displayTitle = $requestContext->getOutput()->getDisplayTitle();
				if ( $textCompared == $displayTitle ) {
					// skip
					$text = $displayTitle;
				} elseif( $textCompared == $title->getText()
				|| $textCompared == $title->getPrefixedText() ) {
					$text = $displayTitle;
				} else {
					// Do nothing, assuming the link label has 
					// been customised
				}
			}
		}
	}

	/**
	 * Content model XML default in NS_CETEI NS, except for /doc pages
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( self::isCETEITitle( $title ) ) {
			$model = CONTENT_MODEL_XML;
		}
		return true;
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler for setting a config variable
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		global $wgCeteiBehaviorsJsFile;
		$vars['wgCeteiBehaviorsJsFile'] = $wgCeteiBehaviorsJsFile;
	}

	/**
	 * Disable parser cache if user is working in
	 * the Cetei namespace
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		$nameSpace = $parser->getTitle()->getNamespace();
		if ( $nameSpace == NS_CETEI ) {
			$parser->getOutput()->updateCacheExpiry( 0 );
		}
	}

	public function onSpecialSearchProfiles( &$searchprofiles ) {
		ctcSpecialUtils::customiseSearchProfiles( $searchprofiles );
	}

	public function onSetupAfterCache() {
		/* not now
		// check if SESP is installed
		if ( MediaWikiServices::getInstance()->getExtensionRegistry()->isLoaded( "SemanticExtraSpecialProperties" ) ) {
			$sesp = new Ctc\SMW\ctcSMWExtraSpecialProperties();
			$sesp->extendSESP();
		}
		*/
	}

	/**
	 * Enable CodeEditor through a hook.
	 * Must abort (return false) after 'xml' added.
	 * @link https://github.com/wikimedia/mediawiki-extensions-CodeEditor/blob/master/includes/Hooks/CodeEditorGetPageLanguageHook.php
	 * @link https://github.com/wikimedia/mediawiki-extensions-CodeEditor/blob/master/includes/Hooks/HookRunner.php
	 **/
	public static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		$pageTitle = $title->getPrefixedText();
		$isDoc = ctcRender::isDocPage( $pageTitle );
		if ( !$isDoc && $title->getNamespace() === NS_CETEI ) {
			$lang = 'xml';
			//return false;
		}
		return true;
	}

	/**
	 * If preferred property is set (datatype Text), add chunks of 
	 * search index content to subobjects
	 * @since 0.8 (experimental)
	 * @param mixed $store
	 * @param mixed $semanticData
	 * @return bool
	 */
	public static function onBeforeDataUpdateComplete( $store, $semanticData ): bool {
		$title = $semanticData->getSubject()->getTitle();
		if ( $title === null || !self::isCETEITitle( $title ) ) {
			return true;
		}
		$propertyName = MediaWikiServices::getInstance()->getMainConfig()->get( "CeteiSMWPropertyForSearchIndex" );
		if ( $propertyName === false || $propertyName === null ) {
			return true;
		}

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$contentObj = $wikiPage->getContent( RevisionRecord::RAW );
		$ctcSearchableContentUtils = new ctcSearchableContentUtils();
		$propertyValues = $ctcSearchableContentUtils->splitContentIntoOverlappingChunks( $contentObj->getTextForSearchIndex(), 150, 8 );

		$ctcSMWStore = new ctcSMWStore();
		$subobjectData = [];
		foreach( $propertyValues as $k => $val ) {
			if( $val === "" ) {
				continue;
			}
			$subobjectData[] = [
				$propertyName => [ $val ]
			];
		}
		$diWikiPage = $semanticData->getSubject();
		$subobjectSemanticData = $ctcSMWStore->storeinSubobjects( $title, $subobjectData, $diWikiPage );

		$ctcSMWStore->updateSemanticData( $semanticData, $subobjectSemanticData );

		return true;
	}

	/**
	 * Helper method to check if a Title is a TEI page
	 * (not a /doc page) in the Cetei namespace
	 * @since 0.8
	 * @param mixed $title
	 * @return bool
	 */
	public static function isCETEITitle( $title ) {
		if ( $title === null ) {
			return false;
		}
		$isDoc = ctcRender::isDocPage( $title->getPrefixedText() );
		if ( $title->getNamespace() === NS_CETEI && $isDoc !== true ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Add links to special page of AdminLinks extension
	 * 
	 * @param ALTree &$adminLinksTree
	 * @return bool
	 */
	public static function onAdminLinks( ALTree &$adminLinksTree ) {
		$extRegistry = MediaWikiServices::getInstance()->getExtensionRegistry();
		if ( !$extRegistry->isLoaded( 'Admin Links' ) ) {
			return true;
		}
		global $wgScript;
		$linkSection = $adminLinksTree->getSection( 'CODECS' );
		if ( is_null( $linkSection ) ) {
			$section = new ALSection( 'CODECS' );
			$adminLinksTree->addSection(
				$section,
				wfMessage( 'adminlinks_general' )->text()
			);
			$linkSection = $adminLinksTree->getSection( 'CODECS' );
			$extensionsRow = new ALRow( 'extensions' );
			$linkSection->addRow( $extensionsRow );
		}

		$extensionsRow = $linkSection->getRow( 'extensions' );

		if ( is_null( $extensionsRow ) ) {
			$extensionsRow = new ALRow( 'extensions' );
			$linkSection->addRow( $extensionsRow );
		}

		$realUrl = str_replace( '/index.php', '', $wgScript );
		$extensionsRow->addItem(
			ALItem::newFromExternalLink(
				$realUrl . '/index.php/Special:CETEIcean',
				'CETEIcean'
			)
		);
		return true;
	}

}
