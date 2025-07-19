"use strict";

/* Runs CETEICEAN  */
//var declareDocType = "<!DOCTYPE TEI SYSTEM './extensions/CETEIcean/modules/ext.char-entities.dtd'>";

if ( mw.config.exists( 'wgCeteiBehaviorsJsFile' ) == true ) {
	var customBehaviorsFile = mw.config.get( 'wgCeteiBehaviorsJsFile' );
} else {
	var basePath = mw.config.get( 'wgExtensionAssetsPath', '' );
	var customBehaviorsFile = basePath + '/CETEIcean/modules/ext.ctc.behaviors.js';
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

function makeNotesCollapsible( className ) {
	var notes = [];
	var notes = document.getElementsByClassName( className );
	for (var i = 0; i < notes.length; i++) {
		notes[i].addEventListener( "click", function() {
			this.classList.toggle( "active" );
			var content = this.nextElementSibling;
			if (content.style.display === "block") {
				content.style.display = "none";
			} else {
				content.style.display = "block";
			}
		});
	}
}

jQuery(document).ready(function($) {
	//var ceteiTriggerSel = '.cetei-instance[data-doc]';
	var ceteiTriggerSel = '.cetei-instance';
	convertCeteiInstances( ceteiTriggerSel );

	if ( $(".tei-anchor-collapsible")[0] ) {
		makeNotesCollapsible( "tei-anchor-collapsible" );
	}

});

// The enclosed code runs only after the page has been loaded and parsed.

} ); //mw.loader.using
