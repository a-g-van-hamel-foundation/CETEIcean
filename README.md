# CETEIcean extension for MediaWiki

The CETEIcean extension is an extension to MediaWiki which implements the [CETEIcean](https://github.com/TEIC/CETEIcean) library (pronounce: `/sɪˈti:ʃn/`) to let users collaborate on [TEI XML](https://tei-c.org/) documents and present them on the wiki. CETEIcean converts your source document using HTML5 Custom Elements (CE) of the Web Components standards, preserving much of the structure of the original document. This extension attempts to combine the best of both worlds: XSLT for the initial stage of processing XML and JavaScript for registering CE and applying custom behaviours.

Its support for working with TEI XML is twofold: first, TEI XML documents can be created, edited and displayed in a dedicated namespace of the wiki; and second, a parser function (`#cetei`) can be used to embed documents, or even discrete sections of them, inline in wikitext.

Since this extension is created for the CODECS website (https://codecs.vanhamel.nl), a project published by the [A. G. van Hamel Foundation for Celtic Studies](https://stichting.vanhamel.nl), functionality at this stage is likely to be tied closely to the needs of the CODECS platform. It is not dependent on this environment, however, and you’re welcome to try it out and provide feedback or patches.

### Functionality: store and present documents
This extension creates a dedicated namespace with the `Cetei:` namespace prefix, which is where TEI XML documents can be stored and displayed.

#### 1. Text
The section headed "Text" renders the document. The TEI Header is hidden by default, but its visibility can be toggled on and off. A message appears instead if none has been provided.

#### 2. About
The section headed "About" is intended for metadata, i.e. information about the document. This part of the interface can be used to present such information as well as offer a form to let users edit and improve it. There are two ways in which you can create the wikitext content to be rendered here.

**Method 1: /doc subpage**

To get any wikitext content transcluded in this section, create a `/doc` subpage of the document and write your content there. This approach is similar to how the Scribunto extension lets you associate documentation pages with Lua modules. For instance, if the full pagename of your document is `Cetei:Aeneid`, create the subpage `Cetei:Aeneid/doc`.

If you have installed Semantic MediaWiki and want to add semantic properties to the subpage, just be aware that it is up to you to prevent semantic information from becoming duplicated as a result of transclusion. See Customisation below for some help.

**Method 2: template** (since v0.7)

Another way to get things done is to let a dedicated wiki template manage whatever content it is that you need, which will be rendered in this specific spot. Although you can use it to show generic information, the intended scenario here is one which your data live elsewhere, in a [content slot](https://www.mediawiki.org/wiki/Multi-Content_Revisions) or even on a different wiki page, and would benefit from a template to handle their display and revision.

CODECS, for instance, stores structured data about the TEI XML document in a JSON slot of the page and uses a template in a wikitext slot to store data in Semantic MediaWiki properties. This largely follows the approach of the [OpenCSP](https://www.open-csp.org)) project, using a setup that involves the use of the WSSlots and FlexForm extensions, but does not depend on the (Chameleon) skin and system messages to provide the interface. Instead it is through the template that this extension takes care of presenting metadata and making the JSON editable by loading a form.

To tell the wiki which template must be used, set its name (without namespace prefix) in the `#wgCeteiAboutSectionTemplate` config setting. The template parameter `FULLPAGENAME` is automatically assigned the name of the page (though you can still use `{{FULLPAGENAME}}` to achieve the same result). Do not use it to store data in Semantic MediaWiki properties because update scripts like rebuildData may ignore it.

#### 3. Source code
To allow others to inspect the shape of the document and maybe learn from it, the raw source code is directly exposed in the final tab pane.

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

### Excerpts (XPath)
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

### Fragments between self-closing tags (new since 0.3)
```
{{#cetei:doc=...
  |break1=pb---n---23
  |break2=pb---n---24
}}
```
In (TEI) XML, not all units are necessarily encoded through matching pairs of opening and closing tags. A new page or column may start with a self-closing tag which marks a new beginning, e.g. `<pb n="23" type="page" />"`, and may end with the one marking the next break (when there is none, you've probably reached the end of the document). XPath is not designed for this use case: such units are not part of DOM trees and probably impossible to align with them if the position of the self-closing tag is relatively free, e.g. within or after a paragraph. This experimental feature is intended to let you extract content between two self-closing tags; an attempt is then made to (semi-)repair the XML fragment by supplying the missing opening and closing tags; and a new version rendered as HTML is returned.

- Use the `break1` and `break2` parameters to identify the first and final closing tags.
- For each closing tag, use three consecutive hyphens to delimit the tag name, attribute and value, as in the example above.

## `#cetei-align:`
```
{{#cetei-align:
|resources=Wikipage1^^Wikipage2
|resourcesep=^^
|selectors=//text:*[@xml:id='***']^^//tr:*[@n='***']
|align=1;1
2;2
3;3
4;4
|valsep=;
}}
```

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

## Configuration

- `$wgCeteiXsl` (default value: `/extensions/CETEIcean/modules/ext.ctc.xsl`): XSL transformations using Custom Elements.
- `$wgCeteiDTD` (default value: `/extensions/CETEIcean/modules/ext.ctc.entities.dtd`): the DTD declaration containing character entity references
- `$wgCeteiBehaviorsJsFile` (default value: `/extensions/CETEIcean/modules/ext.ctc.behaviors.js`): JavaScript behaviours.
- `$wgCeteiAllowEntitySubstitution` (default value: `false`)
- `$wgCeteiAllowUrl` (default value: `false`)
- `$wgCeteiAboutSectionTemplate` (default value: `false`)
- `$wgCeteiPublicationCheck` (default value: `false`)

### `$wgCeteiBehaviorsJsFile`
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

### `$wgCeteiPublicationCheck`
Default: false (boolean). Unless page access was prevented through some other means, the default is that documents are visible to anonymous visitors. To tell the extension that a given document should or should not be public, you can register a Semantic MediaWiki property for recording the intended status. Depending on the value of this property, the extension will then hide or show the content of the page and a placeholder message (editable as a system message) will appear if visitors are not allowed to view the full page.

Note that this is only a soft protection for the sake of convenience. No attempt has been made to shut down alternative routes of access. For specialised solutions concering wiki pages more generally, see [the general MediaWiki guide](https://www.mediawiki.org/wiki/Manual:Preventing_access) and [this list of page-specific user rights extensions](https://www.mediawiki.org/wiki/Category:Page_specific_user_rights_extensions).

Fictitious example:
```
$wgCeteiPublicationCheck = [
  // Using Property:Is public:
	"smwProperty" => "Is public",
	"valueIfPublic" => "Yes",
	"valueIfPrivate" => "No",
  // Fallback if neither value was provided:
	"default" => "No"
];
```

## Notes
### Security: character entities and XXE vulnerabilities
TEI XML files may contain strings that are intended to encode special characters or symbols. You will recognise these units by the ampersand and semi-colon on either side of the string, e.g. `&ampersir;` for the Insular et-symbol. It is up to the XML parser to substitute them on the basis of character entity references that are defined in a so-called DTD. It can be laborious for users if they are expected to point to a DTD every time they create a new XML document. More seriously, in a wiki environment that is designed to be open to users, character substitution may be especially prone to malicious XML External Entity (XXE) injections (look for LIBXML_NOENT on the web, e.g. [here](https://owasp.org/www-community/vulnerabilities/XML_External_Entity_(XXE)_Processing)).

These issues are currently addressed in the following way:
- DTDs are ignored if they are defined or referenced directly in the document. Although the source code remains untouched, they are removed from the document before the XML parser can interpret them.
- A single DTD caters for all TEI XML documents on the wiki. CETEIcean comes with a default DTD, but site admins have the option to come up with their own definitions instead. See `$wgCeteiDTD`.
- Character substitution is disallowed by default, just to be on the safe side, but can be switched back on through the `$wgCeteiAllowEntitySubstitution` config setting (boolean).

### Limitations
- It is mandatory that the TEI element contains a namespace declaration such as `xmlns="http://www.tei-c.org/ns/1.0"`.
- Because some content retrieved with the `#cetei` parser function is lazy-loaded, you cannot reuse it for new purposes in wikitext. For queries with XPath, see the ExternalData extension.
- When attempting to save especially large documents (1MB or over), you may hit the limits of processing power and memory. In part, this is due to the usual restrictions relating to `$wgMaxArticleSize` and the HTTP/HTTPS connector request size, but other factors may come into play, too.
- This extension was not designed for a public wiki where anyone can edit. It is currently unknown if any additional security measures would be required.

### Known issues
- ACE tends not to play nice with character entity definitions and produces error warnings for every instance it fails to identify. In some documents, this may throw numerous error warnings saying "Entity not found", obscuring any messages that do matter. By way of a quick and dirty solution, you can hack into `CodeEditor/modules/ace/xml-worker.js` and suppress those warnings by commenting out the line beginning `ErrorHandler.error('entity not found:'+a)`. Make sure to purge cache afterwards, which you may have to attempt repeatedly because it can be stubborn.
- Syntax errors in your XML document, such as missing tags that result in malformed XML, do not fail gracefully and will prevent the document from getting saved. To help the unsuspecting user, however, the Save button will throw a dialog box if any errors or warnings were detected.
- It is possible that there are still issues relating to certain types of caching, such as parser cache. You may notice that after a page edit, the output does not represent the latest revision and that a hard refresh is required to fetch it.

### Developer notes
- Because this extension was first written and tested with MW 1.35, which does not offer support for ES6 with ResourceLoader, the code in CETEIcean’s JS files has been transpiled to ES5 using [Babel js](https://babeljs.io) and a polyfill for custom elements is added as a dependency.

## Version history
- 0.7. Created alternative to /doc subpages: set a wiki template in `wgCeteiAboutSectionTemplate` for rendering content in the 'About' section (see above), especially with a view to managing data in JSON slots; added `wgCeteiPublicationCheck` (optional) to let a semantic property dictate whether a given document should be shown to anonymous visitors. Added keyboard shortcut, Windows key+i (Windows) or Cmd(⌘)+i (OS), that can be used to enclose a selection of text with `expan` tags, which are ubiquitous in manuscript-based editions. Added fix for incomplete query on Special:CETEIcean. Added aliases file for special page and reorganised classes. Minor fixes.
- 0.6. Added support for ranges in `#cetei-align`. Further default entities added (all from iso-grk1.ent, n/N with macron, etc.). Use 'displaytitle' to sort and show results in Special:CETEIcean. Reduced sensitivity to XML errors. Fixed preview in edit mode. Deactivated syntax highlighting for exceptionally lengthy documents to prevent it from freezing the browser. With `action=info`, both `#cetei` and `#cetei-align` can provide self-documentation about parameters used. Styling changes. Removed 'beta' status.
- 0.5. Added syntax highlighting to "Source code" tab in Cetei namespace (highlight.js). Changed output used for wiki search to be more search-friendly (rendered all entities, added section with attribute values). Added display title to indexing through ParserOutput. Added TEI XML to search profiles. Made certain notes collapsible/expandable. Special:CETEIcean improved and linked from AdminLinks. Custom dialog in event of error or warnings. Styling changes and minor modifications.
- 0.4. Added `#cetei-align` parser function. Added Ace editor for use in FlexForm and Page Forms (using `#cetei-ace` to load JS).
- 0.3. Added an experimental feature to `#cetei` for breaking out a fragment between two self-closing tags, typically `pb` or `mls`/`milestone`, having the XML repaired and retrieving an HTML rendering. This is intended for documents in which the position of such tags is too problematic and unpredictable for XPath selection. Extended list of character entities.
- 0.2. Pre-processing now in XSLT, with continued support for 'behaviors'.
- 0.1. First release.
