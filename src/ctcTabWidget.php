<?php
namespace Ctc\Core;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser;

class ctcTabWidget {

  function run (
    &$out,
    $pageTitle,
    string $ceteiInstanceDiv = '',
    string $docBtnStr = '',
    string $docPageStr = '',
    string $sourceCode = '',
    bool $hasTeiHeader = true
  ) {

    $ctcTabName1 = wfMessage( 'cetei-tabheader-1' )->parse(); //Text
    $ctcTabName2 = wfMessage( 'cetei-tabheader-2' )->parse(); //Doc
    $ctcTabName3 = wfMessage( 'cetei-tabheader-3' )->parse(); //Source
    $ctcTopRight = wfMessage( 'cetei-top-right-content' )->params( $pageTitle )->parse();
    $ctcCreditsBottom = wfMessage( 'cetei-credits-bottom' )->parse();

    /* Prepare toggle for TEI Header */
    if ( $hasTeiHeader === true ) {
      $ctcToggleShow = wfMessage( 'cetei-teiheader-toggle-show' )->parse();
      $ctcToggleBtn = \Html::element( 'button', [
          'id' => 'toggle-tei-header',
          'class' => 'cetei-btn'
        ], $ctcToggleShow );
      $ctcBeforeHeader = '<div class="cetei-toggle-wrapper">' . $ctcToggleBtn . '</div>';
    } else {
      $ctcBeforeHeader = '<div class="cetei-header-not-available">' . wfMessage( 'cetei-header-not-available' )->parse() . '</div>';
    }

    if ( $docPageStr !== '' ) {
      $ctcDoc = '<div class="cetei-doc">' . $docPageStr . '</div>';
    } else {
      $ctcDoc = '<div class="cetei-no-documentation">' . wfMessage( 'cetei-no-documentation' )->parse() . '</div>';
    }

    $ctcOpen = '<div class="cetei-nav-container">';
    $ctcClose = '</div>';

    $ctcTabHeaders = <<<EOT
<div class="cetei-tab-wrapper">
<div class="cetei-nav-tabs">
  <a class="nav-tab-item active" href="#nav-pane-1">$ctcTabName1</a>
  <a class="nav-tab-item" href="#nav-pane-2">$ctcTabName2</a>
  <a class="nav-tab-item" href="#nav-pane-3">$ctcTabName3</a>
</div>$ctcTopRight</div>
EOT;

    $ctcTabContent1 = <<<EOT
<div class="cetei-tab-content">
  <div class="cetei-tab-pane active" id="nav-pane-1">$ctcBeforeHeader
  $ceteiInstanceDiv
  </div>
  <div class="cetei-tab-pane" id="nav-pane-2">
    <div class="cetei-edit-doc">$docBtnStr</div>
EOT;
    //doc here = wikitext
    $ctcTabContent2 = <<<EOT
</div>
<div class="cetei-tab-pane" id="nav-pane-3">$sourceCode</div>
</div>
<hr/>
<div class="cetei-credits-bottom">$ctcCreditsBottom</div>
EOT;

    $out->addHTML( $ctcOpen . $ctcTabHeaders . $ctcTabContent1 );
    $out->addWikiTextAsContent( $ctcDoc );
    $out->addHTML( $ctcTabContent2 . $ctcClose );

  }

}
