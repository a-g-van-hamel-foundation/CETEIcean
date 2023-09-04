<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputPage;
use MediaWiki\ParserOutput;

/**
 * @todo isUser, etc. not yet used since they are also part of ctcRender
 * @todo Move big chunk to sth like ctcXmlExtract::...
 * 
 */

class ctcUtils {

    /*
    * from ctcRender
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

    public static function printArray( $arr ) {
        print_r( "<pre>" );
        print_r( $arr);
        print_r( "</pre>" );
    }

    public static function printRawText( $str ) {
        print_r( "<pre>" );
        print_r( htmlspecialchars($str) );
        print_r( "</pre>" );
    }

}
