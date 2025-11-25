function createCollapsibleNote( elt, position ) {	
	var numberValue = elt.getAttribute("n");
	if ( numberValue == null ) {
		var btn = `<span type="button" class="tei-anchor-collapsible">[note]</span>`;
	} else {
		var btn = `<span type="button" class="tei-anchor-collapsible">[n. ${numberValue}]</span>`;
	}
	var note = `<span class="tei-note-collapsible tei-note-${position}">` + elt.innerHTML + `</span>`;
	return btn + note;
}

function createNumberedDiv( elt ) {
	var numberValue = elt.getAttribute("n");
	//console.log( "Length: " + numberValue.length );
	if ( numberValue.length > 3 ) {
		var numberSpan = `<div class="tei-number div-number tei-number-wide">${numberValue}</div>`;
	} else {
		var numberSpan = `<div class="tei-number div-number tei-number-narrow">[${numberValue}]</div>`;
	}
	return numberSpan + "<div class='cetei-group'>" + elt.innerHTML + "</div>";
}

var configCustomBehaviors = {
	"tei": {
		/* Smallest level behaviours first */
		//"l[n]": ["<span class=\"tei-number line-number\">[l. $@n]</span>"],
		"l": [
			["tei-l", function(elt) {
				var p = document.createElement("template");
				var numberValue = elt.getAttribute("n");
				if ( numberValue == null ) {
					p.innerHTML = "<div class='tei-number no-line-number'></div>" + "<div class='tei-line'>" + elt.innerHTML + "</div>";
				} else {
					var numberSpan = document.createElement("span");
					numberSpan.setAttribute("class", "tei-number line-number");
					numberSpan.innerHTML = numberValue;
					p.innerHTML = numberSpan.outerHTML + "<div class='tei-line'>" + elt.innerHTML + "</div>";
				}				
				//elt.insertAdjacentHTML("afterbegin", numberSpan.charAt(0) );
				return p.content;
			}]
		],
		"lb": ["<span class=\"tei-number line-break-number\">$@n</span>"],
		"lg": [
			["tei-lg", function( elt ) {
				var p = document.createElement("template");
				var numberValue = elt.getAttribute("n");
				if ( numberValue == null ) {
					p.innerHTML = "<div class='tei-line-group'>" + elt.innerHTML + "</div>";
				} else {
					var numberSpan = document.createElement("span");
					numberSpan.setAttribute("class", "tei-number line-group-number");
					numberSpan.innerHTML = numberValue;
					p.innerHTML = numberSpan.outerHTML + "<div class='tei-line-group'>" + elt.innerHTML + "</div>";
				}
				//var numberSpan = ( numberValue != "" ) ? `<span class="tei-number line-group-number">[${numberValue}]</span>` : "";				
				return p.content;
				//["<span class=\"tei-number line-group-number\">[$@n]</span>"],
			}]
		],
		"said": ["<span class='cetei-said'>“</span>", "<span class='cetei-said'>”</span>"],
		"add": ["<span class='cetei-add'>(", ")</span>"],
		//"note": ["<span class='mw-tippy-link'>[note]<span class='mw-tippy-content'>", "</span></span>"],
		//"app": [],
		"note": [
			// inline: as marked paragraph/section in body of text
			["tei-note[place='inline']", function(elt) {
				var p = document.createElement("template");
				// @todo - square brackets don't sit well with block elements
				// p.innerHTML = '<span class="tei-note-inline">[' + elt.innerHTML + ']</span>';
				p.innerHTML = '<span class="tei-note-inline">' + elt.innerHTML + '</span>';
				return p.content;
			}],
			// interlinear: between lines of the text
			["tei-note[place='interlinear']", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = '<span class="tei-note-interlinear">[' + elt.innerHTML + ' <sup>(interlinear)</sup>]</span>';
				return p.content;
			}],
			["tei-note[place='left']", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = '<span class="tei-note-left">[' + elt.innerHTML + ' <sup>(left margin)</sup>]</span>';
				return p.content;
			}],
			["tei-note[place='right']", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = '<span class="tei-note-left">[' + elt.innerHTML + ' <sup>(right margin)</sup>]</span>';
				return p.content;
			}],
			["tei-note[place='bottom']", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = createCollapsibleNote( elt, "bottom" );
				return p.content;
			}],
			["tei-note[place='end']", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = createCollapsibleNote( elt, "end" );
				return p.content;
			}],
			["tei-notegrp > tei-note[n]", function(elt) {
				var p = document.createElement("template");
				var numberStr = '<sup>[' + elt.getAttribute("n") + "]</sup> ";
				p.innerHTML = numberStr + elt.innerHTML;
				return p.content;
			}]
		],
		"div": [
			["tei-div[n]", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = createNumberedDiv( elt );
				return p.content;
			}]
		],
		"div1": [
			["tei-div1[n]", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = createNumberedDiv( elt );
				return p.content;
			}]
		],
		"div2": [
			["tei-div2[n]", function(elt) {
				var p = document.createElement("template");
				p.innerHTML = createNumberedDiv( elt );
				return p.content;
			}]
		],
		"p": [
			["tei-p[n]", function(elt) {
				var numberValue = elt.getAttribute("n");
				var p = document.createElement("template");
				var numberSpan = document.createElement("span");
				numberSpan.setAttribute("class", "tei-number paragraph-number");
				numberSpan.textContent = '[¶ ' + numberValue + ']';
				p.innerHTML = numberSpan.outerHTML + elt.innerHTML;
				return p.content;
			}]
		],
		"rdg": [
			["tei-rdg[wit]", function(elt) {
				var wit = elt.getAttribute("wit");
				var witLabel = "";
				if ( wit !== null ) {
					wit = wit.replaceAll( "#", "" );
					witLabel = "<span class='tei-rdg-wit'>[" + wit + "] </span>";
				}
				var p = document.createElement("template");
				p.innerHTML = `<span class="tei-rdg">` + witLabel + elt.innerHTML + `</span>`;
				return p.content;
			}]
		]
	}
};
