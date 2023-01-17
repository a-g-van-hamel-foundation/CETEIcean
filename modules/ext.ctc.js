"use strict";

/* Runs CETEICEAN  */
//var declareDocType = "<!DOCTYPE TEI SYSTEM './extensions/CETEIcean/modules/ext.char-entities.dtd'>";

if ( mw.config.exists( 'wgCeteiBehaviorsJsFile' ) == true ) {
	var customBehaviorsFile = mw.config.get( 'wgCeteiBehaviorsJsFile' );
} else {
  var customBehaviorsFile = '/extensions/CETEIcean/modules/ext.ctc.behaviors.js';
}

mw.loader.getScript( customBehaviorsFile ).then( function () {

/*
	* Register elements if pre-processed through XSLT
	* Reference: https://github.com/TEIC/CETEIcean/blob/master/src/CETEI.js
*/
function ceteiRegisterEls ( div ) {
	var ctc = new CETEI({
    ignoreFragmentId: true
		//debug: true
  });
	ctc.addBehaviors( configCustomBehaviors );
	ctc.processPage();
	div.classList.remove('cetei-rendering');
	div.classList.add('cetei-registered', 'cetei-rendered');
}

function convertCeteiInstances( divEl ) {
	var targetEl = document.querySelectorAll(divEl);
	targetEl.forEach(div => {
		ceteiRegisterEls ( div );
	});
}

jQuery(document).ready(function($) {
	//var ceteiTriggerSel = '.cetei-instance[data-doc]';
	var ceteiTriggerSel = '.cetei-instance';
	convertCeteiInstances( ceteiTriggerSel );
});

// The enclosed code runs only after the page has been loaded and parsed.

} ); //mw.loader.using
