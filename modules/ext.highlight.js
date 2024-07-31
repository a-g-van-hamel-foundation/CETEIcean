// class="language-xml"
( function( $, mw ) {
	const hljs = require( 'ext.highlight.lib' );

	function loadHighLightJs( cssClass ) {
		//cetei-source-xml
		$('pre.cetei-source-xml').each( function( el ) {
			hljs.configure({
				cssSelector: cssClass,
				languages: 'xml'
			});
			hljs.highlightAll( el );
		});		
	}
	
	$.when( mw.loader.using( "ext.highlight.lib" ) ).then(
		loadHighLightJs( '.cetei-source-xml' )
	);

	/* maybe offer button to activate syntax highlighting?
	function activateButton() {
		const activateBtn = document.getElementById("active-highlight-js");
		if ( activateBtn != null ) {
			activateBtn.addEventListener("click", loadHighLightJs( '.cetei-source-xml-lazy' ) );
		}
	}
	*/

}( jQuery, mediaWiki ) );