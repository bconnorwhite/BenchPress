var editing = false;
var fileFrame;

const contentEditor = '<div class="editor-block-toolbar"><div role="presentation"><div class="components-toolbar"><div><button type="button" aria-pressed="false" aria-label="Align left" data-command="justifyLeft" class="components-button components-icon-button components-toolbar__control"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-editor-alignleft" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M12 5V3H3v2h9zm5 4V7H3v2h14zm-5 4v-2H3v2h9zm5 4v-2H3v2h14z"></path></svg></button></div><div><button type="button" aria-pressed="false" aria-label="Align center" data-command="justifyCenter" class="components-button components-icon-button components-toolbar__control"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-editor-aligncenter" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M14 5V3H6v2h8zm3 4V7H3v2h14zm-3 4v-2H6v2h8zm3 4v-2H3v2h14z"></path></svg></button></div><div><button type="button" aria-pressed="false" aria-label="Align right" data-command="justifyRight" class="components-button components-icon-button components-toolbar__control"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-editor-alignright" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M17 5V3H8v2h9zm0 4V7H3v2h14zm0 4v-2H8v2h9zm0 4v-2H3v2h14z"></path></svg></button></div></div></div><div role="presentation"><div class="editor-format-toolbar"><div class="components-toolbar"><div><button type="button" aria-label="Bold" data-command="bold" class="components-button components-icon-button components-toolbar__control"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-editor-bold" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M6 4v13h4.54c1.37 0 2.46-.33 3.26-1 .8-.66 1.2-1.58 1.2-2.77 0-.84-.17-1.51-.51-2.01s-.9-.85-1.67-1.03v-.09c.57-.1 1.02-.4 1.36-.9s.51-1.13.51-1.91c0-1.14-.39-1.98-1.17-2.5C12.75 4.26 11.5 4 9.78 4H6zm2.57 5.15V6.26h1.36c.73 0 1.27.11 1.61.32.34.22.51.58.51 1.07 0 .54-.16.92-.47 1.15s-.82.35-1.51.35h-1.5zm0 2.19h1.6c1.44 0 2.16.53 2.16 1.61 0 .6-.17 1.05-.51 1.34s-.86.43-1.57.43H8.57v-3.38z"></path></svg></button></div><div><button type="button" aria-label="Italic" data-command="italic" class="components-button components-icon-button components-toolbar__control"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-editor-italic" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M14.78 6h-2.13l-2.8 9h2.12l-.62 2H4.6l.62-2h2.14l2.8-9H8.03l.62-2h6.75z"></path></svg></button></div><div><button type="button" aria-label="Underline" data-command="underline" class="components-button components-icon-button components-toolbar__control"><span class="dashicons dashicons-editor-underline"></span></button></div><div><button type="button" id="essence-content-toolbar-createlink" aria-label="Link" data-command="createlink" class="components-button components-icon-button components-toolbar__control"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-admin-links" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M17.74 2.76c1.68 1.69 1.68 4.41 0 6.1l-1.53 1.52c-1.12 1.12-2.7 1.47-4.14 1.09l2.62-2.61.76-.77.76-.76c.84-.84.84-2.2 0-3.04-.84-.85-2.2-.85-3.04 0l-.77.76-3.38 3.38c-.37-1.44-.02-3.02 1.1-4.14l1.52-1.53c1.69-1.68 4.42-1.68 6.1 0zM8.59 13.43l5.34-5.34c.42-.42.42-1.1 0-1.52-.44-.43-1.13-.39-1.53 0l-5.33 5.34c-.42.42-.42 1.1 0 1.52.44.43 1.13.39 1.52 0zm-.76 2.29l4.14-4.15c.38 1.44.03 3.02-1.09 4.14l-1.52 1.53c-1.69 1.68-4.41 1.68-6.1 0-1.68-1.68-1.68-4.42 0-6.1l1.53-1.52c1.12-1.12 2.7-1.47 4.14-1.1l-4.14 4.15c-.85.84-.85 2.2 0 3.05.84.84 2.2.84 3.04 0z"></path></svg></button></div><div><button type="button" aria-label="Unlink" data-command="unlink" class="essence-toolbar-hide components-button components-icon-button components-toolbar__control is-active" aria-pressed="true"><svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-editor-unlink" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M17.74 2.26c1.68 1.69 1.68 4.41 0 6.1l-1.53 1.52c-.32.33-.69.58-1.08.77L13 10l1.69-1.64.76-.77.76-.76c.84-.84.84-2.2 0-3.04-.84-.85-2.2-.85-3.04 0l-.77.76-.76.76L10 7l-.65-2.14c.19-.38.44-.75.77-1.07l1.52-1.53c1.69-1.68 4.42-1.68 6.1 0zM2 4l8 6-6-8zm4-2l4 8-2-8H6zM2 6l8 4-8-2V6zm7.36 7.69L10 13l.74 2.35-1.38 1.39c-1.69 1.68-4.41 1.68-6.1 0-1.68-1.68-1.68-4.42 0-6.1l1.39-1.38L7 10l-.69.64-1.52 1.53c-.85.84-.85 2.2 0 3.04.84.85 2.2.85 3.04 0zM18 16l-8-6 6 8zm-4 2l-4-8 2 8h2zm4-4l-8-4 8 2v2z"></path></svg></button></div></div></div></div></div>';

window.onload = function() {
	fileFrame = wp.media.frames.file_frame = wp.media({
		title: 'Select Image',
		button: {
			text: 'Select',
		},
		multiple: false,
	});
}

function toggleEditMode() {
	if(!editing) {
		startEditing();
		var fields = document.querySelectorAll('[field]');
		for(var f=0; f<fields.length; f++) {
			if(fields[f].tagName == 'A') {
				jQuery(fields[f]).mousedown(function(e){
					setLinkarea(e.target);
					wpLink.open('essence-content-linkarea');
					return false;
				});
			} else {
				fields[f].addEventListener("mousedown", fieldMousedown);
				var subImages = fields[f].querySelectorAll('img, [bg]');
				for(var i=0; i<subImages.length; i++) {
					subImages[i].addEventListener('mousedown', fieldMousedown);
				}
			}
			fields[f].contentEditable = true;
			fields[f].onkeydown = function(e) {
				if(e.keyCode === 13) { //ENTER
					document.execCommand('insertHTML', false, '<br />');
					return false;
				}
			}
		}
	} else if(editing) {
		stopEditing();
		var meta = {};
		var fields = document.querySelectorAll('[field]');
		for(var f=0; f<fields.length; f++) {
			fields[f].removeEventListener("mousedown", fieldMousedown);
			fields[f].contentEditable = false;
			if(fields[f].tagName == "IMG") {
				if(fields[f].getAttribute('media-id')) {
					meta[fields[f].getAttribute('field')] = fields[f].getAttribute('media-id');
				}
			} else if(fields[f].tagName == "A") {
				meta[fields[f].getAttribute('field')] = {
					'title': fields[f].innerHTML.trim(),
					'url': fields[f].href,
					'target': fields[f].target,
				}
			} else {
				meta[fields[f].getAttribute('field')] = fields[f].innerHTML.trim().replace(/<br ?\/?>/g, "\n");
			}
		}
		saveUpdates(meta);
	}
}

function execCommand(field, command) {
	if(command == 'createlink') {
		var link = document.getElementById('essence-content-linkarea').value;
		if(field.tagName == 'A') {
			var anchorDiv = document.createElement('DIV');
			anchorDiv.innerHTML = link.trim();
			field.innerHTML = anchorDiv.firstChild.innerHTML;
			field.href = anchorDiv.firstChild.href;
			field.target = anchorDiv.firstChild.target;
		} else {
			restoreSelection(selectionRange);
			document.execCommand('insertHTML', false, link);
			document.getElementById('essence-content-linkarea').value = "";
		}
	} else {
		document.execCommand(command, false, null);
	}
}

function clearSelection() {
 if(window.getSelection) {
	 window.getSelection().removeAllRanges();
 } else if(document.selection) {
	 document.selection.empty();
 }
}

function fieldMousedown(e) {
	var field = getParentField(e.target);
	if(field) {
		if(e.target.tagName == 'IMG' || e.target.getAttribute('bg')) {
			field = e.target;
		}
		if(e.target.tagName == 'IMG') {
			fileFrame.on('select', function() {
				var attatchment = fileFrame.state().get('selection').first().toJSON();
				field.src = attatchment.url;
				field.setAttribute('media-id', attatchment.id);
			});
			fileFrame.open();
		} else if(e.target.getAttribute('bg')) {
			fileFrame.on('select', function() {
				var attatchment = fileFrame.state().get('selection').first().toJSON();
				field.style.backgroundImage = attatchment.url ? "url(" + attatchment.url + ")" : "";
				field.setAttribute('media-id', attatchment.id);
			});
			fileFrame.open();
		} else { //TODO: allow all text elements
			openToolbar(field);
			setLinkarea(field);
			var unlink = document.querySelectorAll('button[data-command=unlink]')[0];
			if(isLink()) {
				unlink.classList.remove('essence-toolbar-hide');
			} else {
				unlink.classList.add('essence-toolbar-hide');
			}
		}
	}
}

var toolbar;

function openToolbar(field) {
	if(!document.getElementById('essence-content-toolbar')) { //Create toolbar if needed
		toolbar = document.createElement('DIV');
		toolbar.id = 'essence-content-toolbar';
		toolbar.innerHTML = contentEditor;
		document.body.insertBefore(toolbar, document.body.childNodes[0]);
		var top = (field.getBoundingClientRect().top - toolbar.clientHeight);
		if(top < document.getElementById('wpadminbar').getBoundingClientRect().bottom) {
			top = field.getBoundingClientRect().bottom;
		}
		toolbar.style.top = top + "px";
		jQuery('#essence-content-toolbar-createlink').click(function(){
			wpLink.open('essence-content-linkarea');
			return false;
		});
		field.addEventListener("focusout", unfocusField);
	}
}

var linkarea;

function setLinkarea(field) {
	if(!document.getElementById('essence-content-linkarea')) {
		linkarea = document.createElement('TEXTAREA');
		linkarea.id = 'essence-content-linkarea';
		linkarea.onchange = function() {
			execCommand(field, 'createlink');
		}
		document.body.insertBefore(linkarea, document.body.childNodes[0]);
	}
}

function isLink() {
	if(window.getSelection().anchorNode !== null) {
		var selection = window.getSelection().getRangeAt(0);
		if(selection) {
			if(hasParentTag(selection.startContainer, "A") || hasParentTag(selection.endContainer, "A")) {
				return [true, selection];
			}
		}
	}
	return false;
}

function hasParentTag(element, tagname) {
	if(!element) {
		return false;
	} else if(element.tagName == tagname) {
		return true;
	} else {
		return element.parentNode && hasParentTag(element.parentNode, tagname);
	}
}

function getParentField(element) {
	if(!element) {
		return false;
	} else if(element.hasAttribute('field')) {
		return element;
	} else {
		return element.parentNode && getParentField(element.parentNode);
	}
}


var selectionRange;

function saveSelection() {
    if (window.getSelection) {
        var sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            return sel.getRangeAt(0);
        }
    } else if (document.selection && document.selection.createRange) {
        return document.selection.createRange();
    }
    return null;
}

function restoreSelection(range) {
    if (range) {
        if (window.getSelection) {
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } else if (document.selection && range.select) {
            range.select();
        }
    }
}

function unfocusField(e) {
	if(hasParentId(e.relatedTarget, 'essence-content-toolbar')) {
		if(e.relatedTarget.getAttribute("data-command") == 'createlink') {
			selectionRange = saveSelection();
		} else {
			e.target.focus();
			execCommand(e.target, e.relatedTarget.getAttribute("data-command"));
		}
	} else {
		e.target.removeEventListener("focusout", unfocusField);
		toolbar.parentNode.removeChild(toolbar);
	}
	window.setTimeout(function(){ //unfocus any fields that get focused by clicking out
		var fields = document.querySelectorAll('[field]');
		for(var f=0; f<fields.length; f++) {
			fields[f].blur();
		}
	}, 0);//Somehow this works with 0 delay, but without the timeout it doesn't work.
}

function hasParentId(element, id) {
	if(!element) {
		return false;
	} else if(element.id && element.id == id) {
		return true;
	} else {
		return element.parentNode && hasParentId(element.parentNode, id);
	}
}

function saveUpdates(meta) {
	jQuery.ajax( {
		url: wpApiSettings.root + 'wp/v2/pages/' + postSettings.id,
		method: 'POST',
		beforeSend: function ( xhr ) {
			xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
		},
		data:{ meta },
	}).done(function() {
		location.reload();
	});
}

function startEditing() {
	editing = true;
	setEditModeButtonText('Save');
	getEditModeButton().classList.add('save');
	//TODO: only trigger this once they actually edit something
	window.addEventListener("beforeunload", confirmationMessage);
}

function stopEditing() {
	editing = false;
	setEditModeButtonText('Edit');
	getEditModeButton().classList.remove('save');
	window.removeEventListener("beforeunload", confirmationMessage);
}

function confirmationMessage(e) {
	var message = "Are you sure you want to discard unsaved changes?";
	(e || window.event).returnValue = message; //Gecko + IE
	return message; //Gecko + Webkit, Safari, Chrome etc.
}

function setEditModeButtonText(text) {
	var editModeLink = getEditModeButton().getElementsByTagName('A')[0];
	editModeLink.innerHTML = text;
}

function getEditModeButton() {
	return document.getElementById('wp-admin-bar-edit-mode');
}
