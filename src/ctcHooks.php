<?php

//namespace Ctc\Core;

//use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;

//use Parser;
//use Title;
//use RequestContext;
//use Config;
//use HtmlArmor;
//use ExtensionRegistry;
	// use ALTree, ALSection, ALRow;
use Ctc\Content\ctcRender;
use Ctc\Special\ctcSpecialUtils;
use Ctc\Content\SMWUtils;

class ctcHooks {

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 */
	public static function onBeforePageDisplay( $out, $skin ): void {

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
	 * Content handler for namespace through callback
	 * (extension.json has "callback": "ctcHooks::onRegister")
	 */
	public static function onRegister() {
		define( 'CONTENT_MODEL_XML', 'cetei' );
		//define( 'CONTENT_FORMAT_XML', 'application/tei+xml' );
		//define( 'NS_CETEI', 350, true);
		//define( 'NS_CETEI_TALK', 351, true);
	}

	/**
	 * Content model XML default in NS_CETEI NS, except for /doc pages
	 */
	public static function contentHandlerDefaultModelFor( Title $title, &$model ) {
		$isDoc = ctcRender::isDocPage( $title->getPrefixedText() );
		$nameSpace = $title->getNamespace();
		if ( $nameSpace === NS_CETEI && $isDoc !== true ) {
			$model = CONTENT_MODEL_XML;
			return true;
		} else {
			return true;
		}
	}

	/**
	 * Enable CodeEditor through a hook.
	 * Must abort (return false) after 'xml' added.
	 * @link https://github.com/wikimedia/mediawiki-extensions-CodeEditor/blob/master/includes/Hooks/CodeEditorGetPageLanguageHook.php
	 * @link https://github.com/wikimedia/mediawiki-extensions-CodeEditor/blob/master/includes/Hooks/HookRunner.php
	 **/
	public static function onCodeEditorGetPageLanguage( Title $title, &$lang ) {
		$pageTitle = $title->getPrefixedText();
		$isDoc = ctcRender::isDocPage( $pageTitle );
		if ( !$isDoc && $title->getNamespace() === NS_CETEI ) {
			$lang = 'xml';
			//return false;
		}
		return true;
	}

	/**
	 * Register hooks for parser functions #cetei, #cetei-align, #cetei-ace
	 **/
	public static function onParserFirstCallInit( Parser $parser ) {
		// Register any render callbacks with the parser
		$flags = Parser::SFH_OBJECT_ARGS;
		$parser->setFunctionHook( 'cetei', [ 'Ctc\ParserFunctions\ctcParserFunctions', 'runCeteiPF' ], $flags );
		$parser->setFunctionHook( 'cetei-align', [ 'Ctc\ParserFunctions\ctcParserFunctions', 'runCeteiAlignPF' ], $flags );
		$parser->setFunctionHook( 'cetei-ace', [ 'Ctc\ParserFunctions\ctcParserFunctions', 'runCeteiAcePF' ], $flags );
		return true;
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler for setting a config variable
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars, string $skin, Config $config ) {
		global $wgCeteiBehaviorsJsFile;
		$vars['wgCeteiBehaviorsJsFile'] = $wgCeteiBehaviorsJsFile;
		return true;
	}

	/**
	 * w/ ParserAfterTidy
	 */
	public static function disableParserCache( Parser &$parser, string &$text ) {
		$nameSpace = $parser->getTitle()->getNamespace();
		if ( $nameSpace == NS_CETEI ) {
			$parser->getOutput()->updateCacheExpiry( 0 );
		}
	}

	public static function onSpecialSearchProfiles( &$searchprofiles ) {
		ctcSpecialUtils::customiseSearchProfiles( $searchprofiles );
	}

	/**
	 * Add links to special page of AdminLinks extension
	 * 
	 * @param ALTree &$adminLinksTree
	 * @return bool
	 */
	public static function addToAdminLinks( ALTree &$adminLinksTree ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Admin Links' ) == false ) {
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

	/**
	 * Make sure the display title sticks to page links
	 */
	public static function onHtmlPageLinkRendererBegin( $linkRenderer, $linkTarget, &$text, &$customAttribs, &$query, &$ret ) {
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

}
