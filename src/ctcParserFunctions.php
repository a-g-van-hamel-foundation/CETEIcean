<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;
use MediaWiki\PPFrame;
/* Required: */
use MediaWiki\Revision\RevisionRecord;
use Ctc\Core\ctcXmlProc;

class ctcParserFunctions {

  /* Run #cetei2 string */
  public static function runCeteiPF( $parser, $frame, $args ) {

  	$xmlStr = self::getDocXmlStr( $parser, $frame, $args );
      //$xml = simplexml_load_string( $xmlStr, 'SimpleXMLElement' );
      //$xml->registerXPathNamespace("def", "http://www.tei-c.org/ns/1.0");
    $randomNo1 = rand(1000, 9999);
    $randomNo2 = rand(1000, 9999);
    $output = \Html::rawElement( 'div', [
      'id' => 'cetei-' . $randomNo1 . '-' . $randomNo2,
      'class' => 'cetei-instance cetei-rendering',
      'noparse' => true,
      'isHTML' => true
      ], $xmlStr
    );

    return [ $output, 'noparse' => true, 'isHTML' => true ];

  }

  /* Get XML string as object */
  protected static function getDocXmlStr( $parser, $frame, $params ) {
    $paramDoc = $paramUrl = ''; //default
    $paramSel = null; //default
    $outputDefault = null;
    //$outputDefault = '<div class="cetei-no-document-found"><i>' . wfMessage( 'cetei-no-document-found' )->parse() . '</i></div>';
    if ( $params == null || $params == 'undefined' ) {
      return $outputDefault;
    }

    foreach ( $params as $i => $param ) {
      $paramExpanded = $frame->expand($param);
      $keyValPair = explode( '=', $paramExpanded, 2 );
      if ( count( $keyValPair ) > 1 ) {
        $paramName = trim( $keyValPair[0] );
        $value = trim( $keyValPair[1] );
      } elseif ( $i == 1
          && count( $keyValPair ) == 1
          && trim($keyValPair[0]) !== 'doc' ) {
        $paramName = 'doc'; // for shorthand {{#cetei:<doc>}}
        $value = trim( $keyValPair[0] );
      } else {
        $paramName = null;
        $value = trim( $paramExpanded );
      }
      /* */
      switch ( $paramName ) {
        case 'doc':
        $paramDoc = $value;
        break;
        case 'url':
        $paramUrl = $value;
        break;
        case 'sel':
        $paramSel = $value;
        break;
      }
      /*
      if ( $paramName == 'doc' ) {
        $paramDoc = $value;
      } elseif ( $paramName == 'sel' ) {
        $paramSel = $value;
      }
      */
    }//foreach

    $text = '';
    if ( $paramDoc !== '' ) {
      $text = self::getContentfromTitleStr( $paramDoc );
    } else if ( $paramUrl !== '' ) {
      //$config = \RequestContext::getMain()->getConfig();
      $allowUrl = \RequestContext::getMain()->getConfig()->get( 'CeteiAllowUrl' );
      $defaultNoUrl = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><p>URLs not allowed.</p></text></TEI>';
      $text = ( $allowUrl == true ) ? self::getContentfromUrl( $paramUrl ) : $defaultNoUrl ;
    }

    if ( $paramSel == null ) {
      $output = self::getFullDocXmlStr ( $paramDoc, $text );
    } else {
      $output = self::getExcerptDocXmlStr ( $paramDoc, $paramSel, $text );
    }
    $output = str_replace(array("\r\n", "\r", "\n"), "", trim($output) );
    return $output;
  }//end of method

  public static function getFullDocXmlStr ( $paramDoc, $text ) {
    //@todo: xsltprocessor
    $ctcXmlProc = new ctcXmlProc();
    $text = ctcXmlProc::addDocType( $text );
    $output = $ctcXmlProc->transformXMLwithXSL( $text, null );
    return $output;
  }

  public static function getExcerptDocXmlStr ( $paramDoc, $paramSel, $text ) {
    $ctcXmlProc = new ctcXmlProc();
    $text = ctcXmlProc::addDocType( $text );
    $excerpts = [];
    //$seltest = "//ctc:p[@xml:id='p1']";
    $excerpts = ctcXmlProc::getExcerpts( $text, $paramSel );
    $excerptStr = '';
    if ( $excerpts !== false ) {
      foreach ( $excerpts as $excerpt ) {
        $excerptStr .= '<div type="excerpt">'. $excerpt->asXml() . '</div>';
      }
    } else {
      //do nothing?
    }
    $teiOpen = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text type="excerpts">';
    $teiClose = '</text></TEI>';
    $text = $teiOpen . $excerptStr . $teiClose;
    $text = ctcXmlProc::addDocType( $text );

    $output = $ctcXmlProc->transformXMLwithXSL( $text, null );

    return $output;
  }

  /* Utility - accept title (string) and return fulltext content  */
  static private function getContentfromTitleStr( $titleStr ): string {
    //maybe resolve redirects first?
    $titleObj = \Title::newFromText( $titleStr );
    $wikiObj = \WikiPage::factory( $titleObj );
    $wikiContent = $wikiObj->getContent( RevisionRecord::RAW );
    $text = '';
    $text = \ContentHandler::getContentText( $wikiContent );

    /* Important */
    $ctcXmlProc = new ctcXmlProc();
    $output = ctcXmlProc::removeDocType( $text );

    return $output;
  }

  /* Utility - accept URL and return fulltext content */
  static private function getContentfromUrl( $url ): string {
    //$uri = urlencode( $url );
    $text = file_get_contents( $url );
    /* Important */
    if ( $text == false ) {
      $output = "No file.";
    } else {
      $ctcXmlProc = new ctcXmlProc();
      $output = ctcXmlProc::removeDocType( $text );
    }
    return $output;
  }

  static private function getDtdContent () {
    //would be useful
  }

} //end of class
