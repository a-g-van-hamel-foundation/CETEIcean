"use strict";

( function() {

	const Vue = require("vue");
	Vue.configureCompat( {
		MODE: 3
	} );

	function mountApp( Vue, App, item, configProps ) {
		const createdApp = Vue.createMwApp( App, { configProps } );
		//createdApp.use( Vuex );
		createdApp.mount( item );
	}

	const ceteiSearchWidgets = document.querySelectorAll(".cetei-search-widget");
	ceteiSearchWidgets.forEach( function(item) {
		var SearchApp = require("ext.ctc.search.components").Search;
		if (typeof SearchApp !== "undefined") {
			const configProps = item.dataset ?? {};
			mountApp( Vue, SearchApp, item, configProps );
		}
	});

	const ceteiSMWSearchWidgets = document.querySelectorAll(".cetei-smwsearch-widget");
	ceteiSMWSearchWidgets.forEach( function(item) {
		var SMWSearchApp = require("ext.ctc.search.components").SMWSearch;
		if (typeof SMWSearchApp !== "undefined") {
			const configProps = item.dataset ?? {};
			mountApp( Vue, SMWSearchApp, item, configProps );
		}
	});

}() );
