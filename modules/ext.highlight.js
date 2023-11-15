// class="language-xml"
( function( $, mw ) {
	
	const hljs = require( 'ext.highlight.lib' );
	$.when( mw.loader.using( "ext.highlight.lib" ) ).then(
	    function () {
		//cetei-source-xml
			$('pre.cetei-source-xml').each( function( el ) {
				hljs.configure({
					cssSelector: '.cetei-source-xml',
					languages: 'xml'
				});
				hljs.highlightAll( el );
			})
		}
	);

}( jQuery, mediaWiki ) );

