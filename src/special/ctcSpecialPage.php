<?php

/**
 * Create special page with documentation and list of XML pages in the NS_CETEI namespace.
 *
 * @author: Dennis Groenewegen
 * @file
 * @ingroup
 */

namespace Ctc\Special;

use MediaWiki\MediaWikiServices;
use Title;
//use MediaWiki\Revision\RevisionStoreFactory;
use QueryPage;
use RequestContext;
use Html;
use MWTimestamp;

class ctcSpecialPage extends QueryPage {

	public function __construct( $name = 'CETEIcean' ) {
		parent::__construct( $name );
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function getPageHeader() {
		$out = RequestContext::getMain()->getOutput();
		$queryHeader = Html::element( 'p', null, $this->msg( 'cetei-specialpage-queryheader' )->text() );
		$jsonStr = self::fetchExtensionJson();
		$headerOutput = '';
		if ( $jsonStr !== false ) {
			
			$extDescription = $jsonStr['description'];
			$headerOutput .= "<div class='cetei-specialpage-header'>";
			$headerOutput .= "<div class='cetei-item'><strong class='label'>Description</strong><div class='description'>{$extDescription}</div></div>";
			
			$extCurrentVersion = $jsonStr['version'];
			$headerOutput .= "<div class='cetei-item'><strong class='label'>Extension version</strong><div class='description'>{$extCurrentVersion}</div></div>";

			$extAuthorInfo = '';
			$extAuthors = $jsonStr['author']; //array
			foreach ( $extAuthors as $i => $author ) {
				$extAuthorInfo .= '<div>' . $author . '</div>';
			}
			$headerOutput .= "<div class='cetei-item'><strong class='label'>Authors</strong><div class='description'>{$extAuthorInfo}</div></div>";

			$codeUrl = $this->msg( 'ceteicean-repo-url' )->text();			
			$headerOutput .= "<div class='cetei-item'><strong class='label'>Code repository</strong><div class='description'>[{$codeUrl} Github]</div></div>";

			$libVersion = $this->msg( 'ceteicean-lib-version' );
			$libUrl = $this->msg( 'ceteicean-lib-url' )->text();
			$headerOutput .= "<div class='cetei-item'><strong class='label'>Library</strong><div class='description'>[{$libUrl} CETEIcean] {$libVersion}<br>[https://github.com/highlightjs/highlight.js highlight.js]</div></div>";

			$headerOutput .= "<div class='cetei-item'><strong class='label'>Recommended</strong><div class='description'>[https://www.mediawiki.org/wiki/Extension:CodeEditor CodeEditor], [https://www.mediawiki.org/wiki/Extension:WikiEditor WikiEditor]</div></div>";

			$headerOutput .= "</div>";
		}
		$headerOutput .= '<h2>Documents</h2><div class="cetei-specialpage-queryheader">' . $queryHeader . '</div>';

		$out->addWikiTextAsContent( $headerOutput );
	}

	function getPageFooter() {
	}

	/**
	 * Query for pages in the NS_CETEI namespace.
	 * Skip those with wikitext content models = /doc
	 * Uses IDatabase::select() internally
	 * 
	 * pp_value may or may not contain the name of the display title.
	 */
	public function getQueryInfo() {
		$res = [];
		if ( NS_CETEI !== 'undefined' ) {
			$pages = [
				'tables' => [ 'page', 'page_props' ],
				'fields' => [
					'title' => 'page_title', // pagename
					'value' => 'pp_value',
					'displaytitle' => 'pp_value',
					'pageid' => 'page_id',
					'pagelatest' => 'page_latest'
				],
				'conds' => [
					'page_namespace' => NS_CETEI,
					'page_is_redirect' => 0,
					'page_content_model' => 'cetei'
				],
				"join_conds" => [
					"page_props" => [
						"LEFT JOIN",
						[ 
							"pp_page = page_id",
							"pp_propname = 'displaytitle'"
						]
					]
				]
			];
			$res = $pages;
		}
		return $res;
	}

	function sortDescending() {
		return false;
	}

	function formatResult( $skin, $result ) {
		$oldestTimestamp = "";
		$pageName = $result->title;
		//$pagelatest = $result->pagelatest;

		$title = Title::makeTitle( NS_CETEI, $pageName ); // object
		$docTitle = Title::makeTitle( NS_CETEI, $pageName . "/doc" );
		$visibleTitle = ( $result->displaytitle !== "" && $result->displaytitle !== null )
			? $result->displaytitle
			: str_replace( "_", " ", $result->title );

		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$wikiPage = $wikiPageFactory->newFromTitle( $title );
		$timestampUnix = MWTimestamp::convert( TS_UNIX, $wikiPage->getTimestamp() );
		$timestamp = date( 'Y-m-d', $timestampUnix );
		//$revRecord = $wikiPage->getRevisionRecord();

		$latestUserID = $wikiPage->getUser();
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$latestUser = $userFactory->newFromId( $latestUserID );
		$latestUserName = $latestUser->getName();

		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$firstRevision = $revisionStore->getFirstRevision( $title );
		$oldestTimestampUnix = MWTimestamp::convert( TS_UNIX, $firstRevision->getTimestamp() );
		$oldestTimestamp = date( 'Y-m-d', $oldestTimestampUnix );
		// same as $firstRevision->getUser() :
		$creator = $wikiPage->getCreator()->getName();

		$editUrl = Title::newFromText( $pageName, NS_CETEI )->getFullURL( 'action=edit' );
		$editLink = Html::element( "a", [ "href" => $editUrl ], "edit" );

		// $titleLink = $this->getLinkRenderer()->makeKnownLink( $title, htmlspecialchars( $title->getText() ) );
		$titleLink = $this->getLinkRenderer()->makeKnownLink( $title, $visibleTitle );
		// testing
		//$titleLink = $visibleTitle;
		$docTitleLink = $this->getLinkRenderer()->makeKnownLink( $docTitle, 'doc' );

		$titleStr = "<div class='title-link'><span>{$titleLink}</span> ($editLink) / <span>$docTitleLink</span></div>";
		$detailStr = "<div class='details'><span class='created'>Created by {$creator} on {$oldestTimestamp}</span><span class='lastedited'>Last edited by {$latestUserName} on {$timestamp}</span></div>";
		$res = "<div class='cetei-specialpage-result'>{$titleStr}{$detailStr}</div>";

		return $res;
	}

	protected function getGroupName() {
		return 'cetei_group';
	}

	public static function fetchExtensionJson() {
		global $IP;
		$jsonSource = "$IP/extensions/CETEIcean/extension.json";
		if ( file_exists( $jsonSource ) ) {
			$jsonContents = file_get_contents( $jsonSource );
			$jsonStr = json_decode( $jsonContents, true );
			if ( $jsonStr !== false ) {
				return $jsonStr;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

}
