<!--
			@input="onEvent( 'input', $event )"
			@focus="onEvent( 'focus', $event )"
			@blur="onEvent( 'blur', $event )"
--><template>
	<div>
		<cdx-text-input
			v-model="inputValue"
			aria-label="TextInput basic usage demo"
			@change="onEventDoRequest( $event )"
			@update:model-value="onEvent( 'update:modelValue', $event )"
			:placeholder="placeholder"
		></cdx-text-input>
		<div class="cetei-query-preamble">
			<span>{{ resultScore }}</span>
		</div>
		<div
			class="cetei-query-results"
		>
			<template v-for="item in queryResults">
				<div :id="resultid" class="cetei-query-result">
					<div v-if=item.href class="mw-search-result-heading">
						<a :href=item.href v-html="item.displaytitle"></a>
					</div>
					<div v-html="item.snippet" class="searchresult"></div>
				</div>
			</template>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref, computed } = require( "vue" );
const { CdxTextInput } = require( "@wikimedia/codex" );

module.exports = defineComponent( {
	name: "Search",
	props: {},
	components: { CdxTextInput },
	setup(props) {
		const inputValue = ref( "" );
		const queryResults = ref( [] );
		const resultScore = ref( "" );
		// @todo make placeholder configurable
		const placeholder = ref( "Find TEI XML document" );

		/**
		 * @param {string} eventName
		 * @param {Event|string} event
		 */
		const onEvent = function ( eventName, event ) {
			// eslint-disable-next-line no-console
			// console.log( eventName + ' event emitted with value:', event );
		};

		function onEventDoRequest( event ) {
			var searchterm = event.target.value
			if (searchterm === "") {
				return;
			}
			const actionApiBaseUrl = mw.config.get( "wgServer" ) + (mw.config.get( "wgScriptPath" ) || "") + "/api.php";
			const actionApi = new mw.Api( actionApiBaseUrl, { anonymous: true } );
			const apiUrlParams = {
				action: "query",
				generator: "search",
				gsrwhat: "text",
				gsrnamespace: "350",
				gsrprop: "snippet",
				gsrlimit: "100",
				prop: "pageprops",
				ppprop: "displaytitle",
				format: "json",
				formatversion: "2",
				gsrsearch: searchterm
			};
			actionApi.post( apiUrlParams )
			.done( function (data) {
				// console.log( data, "data" );
				if( typeof data.query !== "undefined" ) {
					//console.log( `Result for ${searchterm}` );
					adaptApiResponse( data.query.pages );
				} else {
					// console.log( "Result undefined" );
					resultScore.value = "No matching results";
				}
			});
		}

		function adaptApiResponse( results ) {
			// console.log( results );
			// reset
			queryResults.value = [];
			const baseUrl = mw.config.get( "wgServer" ) + (mw.config.get( "wgScriptPath" ) || "");
			results.forEach( function( res ) {
				//const href = baseUrl + `/${ encodeURIComponent( res.title ) }`;
				const href = mw.util.getUrl(res.title);
				const displaytitle = res.pageprops.displaytitle;
				const snippet = res.snippet !== null ? res.snippet.trim() + "..." : "";
				queryResults.value.push({
					resultid: `cetei-query-result-` + ( res.pageid ?? "" ),
					href: href,
					pageid: res.pageid ?? "",
					title: res.title ?? "",
					displaytitle: displaytitle,
					snippet: snippet
				});
			});
			resultScore.value = results.length === 1 
				? results.length + " result"
				: results.length + " results";
		}

		return {
			inputValue,
			placeholder,
			resultScore,
			onEvent,
			onEventDoRequest,
			queryResults
		};

	}
} );
</script>

<style>
.cetei-query-preamble {
	padding: 1rem 0;
}
.cetei-query-preamble span {
	font-size: .9rem;
	font-style: italics;
}
.cetei-query-results {
	margin-top: 1rem;
}
.cetei-query-result {
	margin-bottom: 1rem;
}
.cetei-query-result .searchmatch {
	font-weight:bold;
}
.cetei-query-result .mw-search-result-heading {
	text-transform: inherit;
	font-weight: bold;
	font-size:1.1rem;
}
</style>
