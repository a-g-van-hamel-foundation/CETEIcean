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
					<div v-if=item.showheading class="mw-search-result-heading">
						<a :href=item.href v-html="item.displaytitle"></a>
						<span v-if=item.unpublished class="label-unpublished"> (unpublished)</span>
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
		// console.log( "ctc.search init " );
		const inputValue = ref( "" );
		const queryResults = ref( [] );
		const resultScore = ref( "" );
		// @todo make placeholder configurable
		const placeholder = ref( "Find or search TEI XML documents" );

		/**
		 * @param {string} eventName
		 * @param {Event|string} event
		 */
		const onEvent = function ( eventName, event ) {
			// eslint-disable-next-line no-console
			// console.log( eventName + ' event emitted with value:', event );
		};

		function onEventDoRequest( event ) {
			var searchterm = event.target.value;
			var searchterm = searchterm.trim();
			if (searchterm === "") {
				return;
			}
			const actionApiBaseUrl = mw.config.get( "wgServer" ) + (mw.config.get( "wgScriptPath" ) || "") + "/api.php";
			const actionApi = new mw.Api( actionApiBaseUrl, { anonymous: true } );
			const apiUrlParams = {
				action: "cetei-search",
				limit: "100",
				format: "json",
				formatversion: "2",
				prefix: searchterm
			};
			actionApi.post( apiUrlParams )
			.done( function (data) {
				// console.log( data, "data" );
				if( typeof data.result !== "undefined" ) {
					//console.log( `Result for ${searchterm}` );
					adaptApiResponse( data.result );
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
			results.forEach( function( res, index, results ) {
				//const href = baseUrl + `/${ encodeURIComponent( res.id ) }`;
				const href = mw.util.getUrl(res.id);
				// Don't repeat the heading if already shown
				const prevName = results[index - 1] !== undefined ? results[index - 1].name : null;
				const showHeading = prevName != res.name ? true : false;

				const maxSnippets = Math.min( 10, res.snippets.length );
				var snippetConcat = "";
				for (var i=0; i < maxSnippets; i++) {
					//container [index++ % maxlen] = "storing" + i;
					snippetConcat += "<div class='cetei-search-extract'>" + res.snippets[i] + "</div>";
				}
				if ( res.snippets.length > 10 ) {
					snippetConcat += "<div class='cetei-search-etc'><i>etcetera</i></div>";
				}
				//const snippetConcat = res.snippets.join("");
				htmlid = Math.random()
				queryResults.value.push({
					resultid: `cetei-query-result-` + htmlid,
					href: href,
					pageid: htmlid,
					title: res.id ?? "",
					displaytitle: res.name,
					snippet: snippetConcat,
					showheading: showHeading,
					unpublished: ( res.published == "0" )
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
.cetei-query-result .mw-search-result-heading .label-unpublished {
	font-weight:normal;
	margin-left:.2rem;
	font-style: italic;
}

.cetei-search-etc {
	color:grey;
}
</style>
