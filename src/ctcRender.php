<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;
use MediaWiki\PPFrame;
//use MediaWiki\PPNode;
use Ctc\Core\ctcXmlProc;
use Ctc\Core\ctcTabWidget;

class ctcRender {

  /* Build page in Cetei: namespace */
  public static function buildPage( $out, $retrievedText ) {

    $parser = MediaWikiServices::getInstance()->getParserFactory()->create();
    $pageTitle = $out->getTitle();
    $pageName = $out->getTitle()->getText();
    //$pageUrlRaw = $out->getTitle()->getFullURL( 'action=raw' );
    $tempSpinner = \Html::element( 'span', [
      'class' => 'spinner-dual-ring'
    ], '' );
    /*
    $ceteiInstanceDiv = Html::element( 'div', [
      'id' => 'cetei',
      'class' => 'cetei-instance',
      'data-doc' => $pageUrlRaw
    ], '' );
    */
    $ctcXmlProc = new ctcXmlProc();
    $newXmlStr = $retrievedText;
    $newXmlStr = $ctcXmlProc->removeDocType( $newXmlStr );
    $newXmlStr = $ctcXmlProc->addDocType( $newXmlStr );

    $transformedXml = $ctcXmlProc->transformXMLwithXSL( $newXmlStr, null );
    $ceteiInstanceDiv = \Html::rawElement( 'div', [
      'id' => 'cetei',
      'class' => 'cetei-instance cetei-ns-instance cetei-rendering'
      //'data-doc' => $pageUrlRaw
    ], $transformedXml );

    $preSourceContent = \Html::element( 'pre',
      ['lang'=>'xml',
       'class'=>'cetei-source-xml
      '], $retrievedText ); //Source code to be shown in pre tags
    /* Because hidden comments could potentially be an issue to xml parse: */
    //@todo change back to $retrievedText

    $sourceContent = preg_replace( '/<!--.*?-->/s', '', $newXmlStr );

    /* /doc subpage: */
    $docPageTitle = $pageTitle . '/doc';
    if ( self::hasDocPage( $pageTitle ) == true ) {
      $docAddMsg = wfMessage( 'cetei-edit-documentation' )->parse();
      //$linkDocUrl = Title::newFromText( $docPageTitle )->getFullURL( 'action=edit' );
      $linkDocUrl = wfMessage( 'cetei-edit-documentation-url' )->params( $docPageTitle )->text();
      if ( self::isUser() == true ) {
        $docBtnStr = self::createButtonWidget( $out, $docAddMsg, $linkDocUrl, 'edit', null );
      } else {
        $docBtnStr = ''; //default
      }
      $docPageStr = self::showDocPage( $out, $pageTitle );
    } else {
      $docBtnStr = ''; //default
      $docPageStr = ''; //default
      if ( self::isUser() == true ) {
        //$linkDocUrl = Title::newFromText( $docPageTitle )->getFullURL( 'action=edit' );
        $linkDocUrl = wfMessage( 'cetei-edit-documentation-url' )->params( $docPageTitle )->text();
        $docAddMsg = wfMessage( 'cetei-add-documentation' )->parse();
        $docBtnStr = self::createButtonWidget( $out, $docAddMsg, $linkDocUrl, 'edit', null );
      } else {
        $docPageStr = '';
      }
    }

    /* Retrieve basic data from document through ctcXmlProc class  */
    $ctcXmlProc = new ctcXmlProc();
    $ctcHeaderTitle = $ctcXmlProc->getHeaderTitle( $sourceContent );
    if ( $ctcHeaderTitle != null ) {
      $out->setPageTitle( strip_tags($ctcHeaderTitle) );
    } else {
      $out->setPageTitle( $pageName );
    }
    $hasTeiHeader = $ctcXmlProc->hasTEIHeader( $sourceContent );

    /* Build tab widget and assign content
    * $ceteiInstanceDiv = html for CETEIcean;
    * $docBtnStr = button for doc subpage;
    * $docPageStr = wikitext from doc subpage;
    * $preSourceContent = source code to be rendered with pre tags;
    */
    $tabWidget = new ctcTabWidget();
    $output = $tabWidget->run(
      $out,
      $pageTitle,
      $ceteiInstanceDiv,
      $docBtnStr,
      $docPageStr,
      $preSourceContent,
      $hasTeiHeader
    );

    return $output;
  }

  /* Check whether or not current page is an associated /doc subpage */
  public static function isDocPage( $title ) {
    if (preg_match("/\/doc/i", $title )) {
      return true;
    } else {
      return false;
    }
  }

  /* Check whether or not TEI page ($title) has an associated /doc subpage */
  public static function hasDocPage( $title ) {
    if ( $title ) {
      $docPageStr = $title . '/doc';
    } else {
      $out = \RequestContext::getMain()->getOutput();
      $docPageStr = $out->getTitle() . '/doc';
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

  /* Transclude /doc subpage */
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

  /*
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
