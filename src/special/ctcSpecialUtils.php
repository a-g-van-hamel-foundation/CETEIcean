<?php
/**
 *
 * @author: Dennis Groenewegen
 * @file
 * @ingroup
 */
namespace Ctc\Special;

use MediaWiki\MediaWikiServices;

class ctcSpecialUtils {

    public static function runSimple( $titleObj, $titleName, $link ) {
        $html = \Html::rawElement(
            "div",
            [ "class" => "cetei-result" ],
            $link
        );
        return $html;
    }

	/**
	 * Add TEI XML as a separate search profile to Special:Search
	 * Quite similar to LiquidThreads approach
	 */
    public static function customiseSearchProfiles( &$profiles ) {
        $namespaces = [ NS_CETEI ];
		$insert = [
			'cetei' => [
				'message' => 'searchprofile-cetei',
				'tooltip' => 'searchprofile-cetei-tooltip',
				'namespaces' => $namespaces,
				'namespace-messages' => MediaWikiServices::getInstance()->getSearchEngineConfig()
					->namespacesAsText( $namespaces ),
			],
		];
		// Insert before 'all' ('everything')
		$index = array_search( 'all', array_keys( $profiles ) );
		// Or just at the end if all is not found
		if ( $index === false ) {
			wfWarn( '"all" not found in search profiles' );
			$index = count( $profiles );
		}
		$profiles = array_merge(
			array_slice( $profiles, 0, $index ),
			$insert,
			array_slice( $profiles, $index )
		);
		return true;
	}

}
