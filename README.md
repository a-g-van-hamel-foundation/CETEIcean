# CETEIcean extension for MediaWiki (beta)

The CETEIcean extension is an extension to MediaWiki which implements the [CETEIcean](https://github.com/TEIC/CETEIcean) library (pronounce: `/sɪˈti:ʃn/`) to let users collaborate on [TEI XML](https://tei-c.org/) documents and present them on the wiki. CETEIcean converts your source document using HTML5 Custom Elements (CE) of the Web Components standards, preserving much of the structure of the original document. This extension attempts to combine the best of both worlds: XSLT for the initial stage of processing XML and JavaScript for registering CE and applying custom behaviours.

Its support for working with TEI XML is twofold: first, TEI XML documents can be created, edited and displayed in a dedicated namespace of the wiki; and second, a parser function (`#cetei`) can be used to embed documents, or even discrete sections of them, inline in wikitext.

This extension is being created for the CODECS website (https://codecs.vanhamel.nl), a project published by the [A. G. van Hamel Foundation for Celtic Studies](https://stichting.vanhamel.nl). It is currently in beta status and functionality at this stage is likely to be tied closely to the needs of the CODECS platform. It is not dependent on this environment, however, and you’re welcome to try it out and provide feedback or patches.

### Functionality: store and present documents
This extension creates a dedicated namespace with the `Cetei:` namespace prefix, which is where TEI XML documents can be stored and displayed.

#### 1. Text
The section headed "Text" renders the document. The TEI Header is hidden by default, but its visibility can be toggled on and off. A message appears instead if none has been provided.

#### 2. About
The section headed "About" is intended for metadata, i.e. information about the document. It lets you transclude wikitext content from a `/doc` subpage, similar to how the Scribunto extension lets you associate documentation pages with Lua modules. If you have installed Semantic MediaWiki, you can add semantic properties. Just be aware that it is up to you to prevent semantic information from becoming duplicated as a result of transclusion. The rationale is that it should be up to you whether semantic data gets attached to the document (through transclusion) or to the `/doc` subpage. See Customisation below for some help.

#### 3. Source code
To allow others to inspect the shape of the document and maybe learn from it, the raw source code is directly exposed in the final tabbed section.

## Usage: edit documents

### The text editor
To best assist your users in editing documents, it is recommended that you have both [CodeEditor](https://www.mediawiki.org/wiki/Extension:CodeEditor) and [WikiEditor](https://www.mediawiki.org/wiki/Extension:WikiEditor) installed on your wiki. Here is why:

#### The Ace code editor

The extension hooks into CodeEditor to support XML editing with Ace. [Ace](https://ace.c9.io) is a popular embeddable code editor whose features include colour-coded syntax highlighting, tabs, automatic indentation, line numbering, code folding and syntax checking.

#### The WikiEditor toolbar

The toolbar at the top that comes with WikiEditor is configured and extended to serve as an aid to writing (TEI) XML. CodeEditor adds some useful features, including search/replace, soft wrap, indentation and a button to let you toggle back and forth between CodeEditor and the regular WikiEditor interface in case you have need of it.

The CETEIcean extension extends the toolbar further by introducing
- a new booklet section, "TEI XML", which lets you insert elements and code snippets into the document. Please be aware that the present arrangement is basic and only provisional. It is currently organised into a number of categories, such as "Preliminaries", "Verse" and "Dictionaries", and will be revised, re-arranged and expanded in the future.
- a button to launch the editor in full-screen mode.

## Usage: The `#cetei` parser function
Use the `#cetei` parser function to retrieve a document, or select excerpts from it, on a regular wiki page:

### Retrieve the document
To retrieve the document, use either `doc` or `url`. The `doc` parameter expects the title of the wiki page you want to include.

```
{{#cetei:doc=Cetei:MyTEIpage
}}
```

Instead of `doc`, you can also use the `url` parameter, which expects a full URL that is both public and accessible to the server. This may refer to a wiki page, a document on the server, or possibly, if CORS is enabled for a particular remote source, an external document. To retrieve a wiki page in this way, use the `fullurl` magic word with the `action=raw` parameter, as shown below (note that it depends on your `$wgServer` setting whether you should use the prefix `https`).

```
{{#cetei:url=https://example.com/mywiki/my-tei-xml-file.xml
}}
```
```
{{#cetei:url=https:{{fullurl:Cetei:Some document|action=raw}}
}}
```

### Excerpts
When either `doc` or `url` is used without a further argument, `#cetei` will attempt to retrieve the full document. Alternatively, you can fetch one or multiple excerpts by running a simple XPath query: add your XPath expression to a second parameter called `sel`, which is short for selector. The namespace prefix registered for this is `ctc`.

```
{{#cetei:doc=...
|sel=//ctc:...
}}
```

Example. The following retrieves the paragraph (element `p`) where the attribute `xml:id` has a value of `"p2"` :
```
{{#cetei:doc=Cetei:Some document
|sel=//ctc:p[@xml:id='p2']
}}
```
For reasons that are specific to MediaWiki, you cannot use the pipe character (`|`) as OR operator, but there is a simple workaround: write `{{!}}` instead.

## Special:CETEIcean
The special page `Special:CETEIcean` contains basic information about the extension and lists pages in the `Cetei:` namespace.

## Customisation
The extension comes with [system messages](https://www.mediawiki.org/wiki/Help:System_message) that can be customised if so desired. See the file `/i18n/en.json` (English only for now). The following examples are worth mentioning explicitly:
- If you require a button or link to be added to the top right of a page in the extension namepace, i.e. on the opposite end of the tabbed headers on the left, you can add it to `MediaWiki:Cetei-top-right-content` (an empty div by default).
- You are free to alter `MediaWiki:Cetei-edit-documentation-url` to set a different URL for the button that lets you edit the `/doc` subpage. The parameter `$1` will give you the title of that page. This can be useful if for instance, you prefer to use Page Forms or FlexForm instead of the regular wiki editor.

## Installation
- Download the files and add the folder (`Ceteicean`) to your `/extensions` directory.
- Enable the extension in your `LocalSettings.php` file:
```
wfLoadExtension( 'Ceteicean' );
define("NS_CETEI", 350);
define("NS_CETEI_TALK", 351);
```
Because MediaWiki does not support retrieving globals from extensions, the latter two lines may be required, for instance when you want to add the namespace to Semantic MediaWiki’s `$smwgNamespacesWithSemanticLinks`).
- Add configuration options if necessary (see below).
- Navigate to `Special:Version` on your wiki to verify that the extension is successfully installed.
- You should be good to go.

### Configuration

- `$wgCeteiXsl` (default value: `/extensions/CETEIcean/modules/ext.ctc.xsl`): XSL transformations using Custom Elements.
- `$wgCeteiDTD` (default value: `/extensions/CETEIcean/modules/ext.ctc.entities.dtd`): the DTD declaration containing character entity references
- `$wgCeteiBehaviorsJsFile` (default value: `/extensions/CETEIcean/modules/ext.ctc.behaviors.js`): JavaScript behaviours.
- `$wgCeteiAllowEntitySubstitution` (default value: `false`)
- `$wgCeteiAllowUrl` (default value: `false`)

#### `$wgCeteiBehaviorsJsFile`
The extension comes with a default set of [JavaScript behaviours](https://github.com/TEIC/CETEIcean/wiki/Anatomy-of-a-behaviors-object) intended to add “custom styles, event handlers, and widgets ... to your TEI elements”. This set, which is defined in the file `/modules/ext.ctc.behaviors.js`, is still somewhat experimental and may or may not suit your own particular use case. If you want, you can opt out and point the wiki to a file with your own custom behaviors. Add the following to your `LocalSettings.php`, after the lines that enable the extension, and substitute the file location.
```
$wgCeteiBehaviorsJsFile = '/example-my-custom-behaviors.js';
```
In your custom file, the configuration should be assigned to a variable named `configCustomBehaviors`.
```
var configCustomBehaviors = {
 "tei": {
     ...
 }
}
```
### `$wgCeteiDTD`
Relative path to the DTD file containing character entity references. For security reasons, any internal DTD that users include in the XML document itself will be automatically removed before XSL transformation.

### `$wgCeteiAllowEntitySubstitution`
Default: false (boolean). Whether entity substitution is allowed. See note on security below.

### `#wgCeteiAllowUrl`
Default: false (boolean). Whether the parser function when used with `url` should be allowed to retrieve the contents of a document from a public URL accessible to the server.

## Notes
### Security: character entities and XXE vulnerabilities
TEI XML files may contain strings that are intended to encode special characters or symbols. You will recognise these units by the ampersand and semi-colon on either side, e.g. `&ampersir;` for the Insular et-symbol. It is up to the XML parser to substitute them on the basis of character entity references that are defined in a so-called DTD. It can be laborious for users if they are expected to point to a DTD every time they create a new XML document. More seriously, in a wiki environment that is designed to be open to users, character substitution may be especially prone to malicious XML External Entity (XXE) injections (look for LIBXML_NOENT on the web, e.g. [here](https://owasp.org/www-community/vulnerabilities/XML_External_Entity_(XXE)_Processing)).

These issues are currently addressed in the following way:
- DTDs are ignored if they are defined or referenced directly in the document. Although the source code remains untouched, they are removed from the document before the XML parser can have its way with them.
- A single DTD caters for all TEI XML documents on the wiki. CETEIcean comes with a default DTD, but site admins have the option to come up with their own definitions instead. See `$wgCeteiDTD`.
- Character substitution is disallowed by default, just to be on the safe side, but can be switched back on through the `$wgCeteiAllowEntitySubstitution` config setting (boolean).

### Limitations
- It is mandatory that the TEI element contains a namespace declaration such as `xmlns="http://www.tei-c.org/ns/1.0"`.
- Because some content retrieved with the `#cetei` parser function is lazy-loaded, you cannot reuse it for new purposes in wikitext. For queries with XPath, see the ExternalData extension.
- When attempting to save especially large documents (1MB or over), you may hit the limits of processing power and memory. In part, this is due to the usual restrictions relating to `$wgMaxArticleSize` and the HTTP/HTTPS connector request size, but other factors may come into play, too.
- This extension was not designed for a public wiki where anyone can edit. It is currently unknown if any additional security measures would be required.

### Known issues
- ACE tends not to play nice with character entity definitions and produces error warnings for every instance it fails to identify. In some documents, this may throw numerous error warnings saying "Entity not found", obscuring any messages that do matter. By way of a quick and dirty solution, you can hack into `CodeEditor/modules/ace/xml-worker.js` and suppress those warnings by commenting out the line beginning `ErrorHandler.error('entity not found:'+a)`. Make sure to purge cache afterwards, which you may have to attempt repeatedly because it can be stubborn.
- Syntax errors in your XML document may not always fail gracefully.
- It is possible that there are still issues relating to certain types of caching, such as parser cache. You may notice that after a page edit, the output does not represent the latest revision and that a hard refresh is required to fetch it.

### Developer notes
- Because this extension was written and tested with MW 1.35, which does not offer support for ES6 with ResourceLoader, the code in CETEIcean’s JS files has been transpiled to ES5 using [Babel js](https://babeljs.io) and a polyfill for custom elements is added as a dependency.
