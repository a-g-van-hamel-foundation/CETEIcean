<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;
use MediaWiki\PPFrame;
use Ctc\Core\ctcRender;
use Ctc\Core\ctcParserFunctions;

class ctcHooks {

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

	/*
	** Content handler for namespace through callback
	** extension.json has "callback": "ctcHooks::onRegister"
	*/
	public static function onRegister() {
		define( 'CONTENT_MODEL_XML', 'cetei' );
		//define( 'CONTENT_FORMAT_XML', 'application/tei+xml' );
		//define( 'NS_CETEI', 350, true);
		//define( 'NS_CETEI_TALK', 351, true);
	}

	/**
	 * Content model XML default in NS_CETEI NS, except for /doc pages 
	 **/
	public static function contentHandlerDefaultModelFor( Title $title, &$model ) {

		$isDoc = ctcRender::isDocPage( $title );
		$nameSpace = $title->getNamespace();
		if ( $nameSpace === NS_CETEI && $isDoc !== true ) {
			$model = CONTENT_MODEL_XML;
			return true;
		} else {
			return true;
		}

	}

	/**
	 * Enable CodeEditor through a hook
	 **/
	public static function onCodeEditorGetPageLanguage( Title $title, &$lang ) {
		$isDoc = ctcRender::isDocPage( $title );
		if ( $title->getNamespace() === NS_CETEI && $isDoc !== true ) {
			$lang = 'xml';
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Register hook for parser functions #cetei, #cetei-align, #cetei-ace
	 **/
	public static function onParserFirstCallInit( Parser $parser ) {
		// Register any render callbacks with the parser
		$name = 'cetei';
		$functionCallback = [ 'Ctc\Core\ctcParserFunctions', 'runCeteiPF' ];
		$flags = \Parser::SFH_OBJECT_ARGS;
		$parser->setFunctionHook( $name, $functionCallback, $flags );

		$parser->setFunctionHook( 'cetei-align', [ 'Ctc\Core\ctcParserFunctions', 'runCeteiAlignPF' ], $flags );

		$parser->setFunctionHook( 'cetei-ace', [ 'Ctc\Core\ctcParserFunctions', 'runCeteiAcePF' ], $flags );

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

	public static function onSpecialSearchProfiles( &$searchprofles ) {
		Ctc\Special\ctcSpecialUtils::customiseSearchProfiles( $searchprofles );
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

}
