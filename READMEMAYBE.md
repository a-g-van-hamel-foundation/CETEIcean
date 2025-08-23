v0.8 comes with some experimental features that you are welcome to try out. Note that they may well be revised or removed in the future. 

## Settings
### `$wgCeteiSMWPropertyForSearchIndex` (experimental)
Default: false (boolean). As explained elsewhere, content for the search index is automatically mashed and simplified into a version that should be more suitable for 'fuzzy' searching with MySQL. If you use Semantic MediaWiki with [Full-Text Search (FTS)](https://www.semantic-mediawiki.org/wiki/Help:Full-text_search), this config setting lets you use a semantic property of datatype 'Text' to have that content chunked and stored as property values in subobjects. 

Note that Full-Text Search is not ideal. It is known that it does not scale well with large volumes, but you may well be stuck with it as your best option, or perhaps you follow the principle of minimal computing. This option will not work if you use Semantic MediaWiki with the default SQL settings because the maximum string length allowed for properties would be too limited. If you use Semantic MediaWiki with Elasticsearch, there is no need to use a dedicated property.

The particulars of this design are intended as as workaround for some of the limitations of MySQL and SMW that I experienced:
- Because semantic queries, apparently unlike MediaWiki's own full-text search, often fail to find matches in book-length XML documents, the content is first subdivided into chunks.
- These chunks will contain some overlap between them so that searches can be made across textual boundaries. One unfortunate side-effect is that you cannot look for multiple phrases that are separated from one another at a distance; or conversely, you'll have more luck if they occur in close proximity.
- Even chunking offers no guarantee that text is fully searchable, but it was found that queries struggle less when individual property values are containerised as subobjects as opposed to being assigned to a multi-valued property in a regular `#set`.

## Parser function

### `#cetei-smw-search`

If `$wgCeteiSMWPropertyForSearchIndex` is set, you can use a simple widget to let users search the property's content. Results will be presented as text snippets with the search term highlighted in bold. It allows for the use of asterisk wildcards and exact phrase matching with double quotation marks. 

If you also configured `$wgCeteiPublicationCheck`, then anonymous users should only see query results from documents that are available to them. This access restriction does not apply to logged in users.

```
{{#cetei-smw-search:}}
```

### `#cetei-highlight` and `#cetei:` with action=highlight
The feature in `#cetei-smw-search` that takes a lump of text and creates extracts relevant to a search term can also be called separately. 

You can do so by calling the parser function `#cetei-highlight` in the following format:

```
{{#cetei-highlight:
|text= // The text
|term= // The term you wish to highlight
|mode= // The default is "extracts"; if you require the whole of the text provided with the terms highlighted, set this to "full"
}}
```

An equivalent feature is available as part of the `#cetei` parser function if you use the `highlight` action. It automatically munges the text retrieved from the document and returns highlighted extracts. Example:

```
{{#cetei:
|doc=Cetei:AU-test
|sel=//ctc:div2[@n='U1123.2']
|action=highlight
|term=comarb
}}
```

