(function(scope){
	var contentWindow,
		prevTBposition,
		prevTBremove,
		currentSelectedIndex,
		currentMedia,
		currentCallback;

	jQuery(document).ready(function(){
		var select = jQuery('#storyform-templates-select');
		var loading = jQuery('#storyform-templates-loading');
		var postOptions = jQuery('.storyform-post-options');

		// May be on media upload page, not post edit page
		if(!select.length){
			return;
		}

		if(storyform.template){
			var option = document.createElement('option');
			option.value = storyform.template;
			option.textContent = storyform.template;
			option.setAttribute('selected', true);
			postOptions.show();
			select.append(option);
			select.append(loading); // Move loading to the end	
		}

		if(select.val() === 'pthemedefault'){
			postOptions.hide();
		} else {
			monitorPostImprovements();
		}	
		
		select.one("focus", function(){
			jQuery.ajax({
				url: storyform.hostname + '/users/me/templates',
				data: {
					appKey: storyform.app_key,
				},
				type: 'GET',
				dataType : "json",
				success: function(json){
					loading.remove();
					var templates = json.templates;
					templates.forEach(function(template){
						if(template.id !== storyform.template){
							var option = document.createElement('option');
							option.value = template.id;
							option.textContent = template.name;
							select.append(option);
						}
					});
				},
				error: function(){
					document.getElementById('storyform-status').innerHTML = 'Cannot retrieve templates. Please ensure you\'ve logged into the <a href="' + storyform.settingsUrl + '">Storyform Settings</a>.';
				}
			});
		});

		select.one("click", function(ev){
			ev.preventDefault();
		});

		select.change(function change(){
			if(this.value === 'pthemedefault'){
				postOptions.hide();
				stopMonitorPostImprovements();
			} else {
				postOptions.show();
				monitorPostImprovements();
			}
			setLayoutType();
		});
		
		jQuery('.storyform-layout-type').change(function(){
			setLayoutType();
		});

		jQuery('[data-storyform-tooltip]').map(function(index, el){
			var html = el.outerHTML;
			jQuery(el).hide();
			var attr = el.getAttribute('data-storyform-tooltip');
			jQuery( '#' + attr ).tooltip({ 
				items: '*',
				position: { my: "center top+10", at: "center bottom", collision: "flipfit" },
				content: function(){
					return '<div class="ui-tooltip-arrow-n"></div>' + html;
				}
			});	
		});

		jQuery('.storyform-improve-text').click(function(){
			jQuery(this).toggleClass('storyform-improve-open');
			jQuery('.storyform-improve-items').slideToggle();
		});

		jQuery('.storyform-improve-caption-action').click(function(){
			showPopupForCurrentAttachments();
		});
		jQuery('.storyform-improve-crop-action').click(function(){
			showPopupForCurrentAttachments();
		});

		var keys = {};

		jQuery(document).keydown(function (e) {
		    keys[e.which] = true;
		    if(keys[65] && keys[66] && keys[83]){ // abs
		    	jQuery('#storyform-ab-test').toggleClass('hidden');
		    }
		});

		jQuery(document).keyup(function (e) {
		    delete keys[e.which];
		});
		
	});

	/** 
	 *	Sets the user chosen layout type on the editor (either the TinyMCE editor via the plugin or text editor manually)
	 *
	 */
	function setLayoutType(){
		var val = jQuery('#post')[0]['storyform-layout-type'].value;
		if(window.tinyMCE && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			tinyMCE.activeEditor.plugins.storyform.setLayoutType(val);	
		} else {
			var textarea = jQuery('#' + wpActiveEditor);
			var text = textarea.val();
			var decorational = val === 'freeflow' ? 'article' : 'pinned';
			textarea.val(text.replace(/\sdata\-decorational(?:=[\'\"]([^\'\"]*)[\'\"])?/ig, ' data-decorational="' + decorational + '"'));
		}
	}

	var improvementsInterval;
	function monitorPostImprovements(){
		improvementsInterval = setInterval(checkImprovements, 10 * 1000);
		checkImprovements();
	}
	function checkImprovements(){
		if(!jQuery('.storyform-improve').length){
			return;
		}

		var items = 0;
		
		// Check if we need pullquotes
		var hasPQ = hasPullquote();
		var pq = jQuery('#storyform-improve-description-pullquote');
		var pqText = jQuery('#storyform-improve-pullquote-text');
		if(hasPQ){
			pq.show();
			pq.addClass('storyform-improve-good');
			pq.removeClass('storyform-improve-bad');
			pqText.text(pqText.attr('data-yes'));
		} else {
			if(isLongPost()){
				items++;
				pq.show();
				pq.addClass('storyform-improve-bad');
				pq.removeClass('storyform-improve-good');
				pqText.text(pqText.attr('data-no'));
			} else {
				pq.hide();
			}
		}

		// Check if captions have overlay locations
		var figures = getFiguresHaveOverlay();
		jQuery('#storyform-improve-caption').toggle(!!figures.total);
		jQuery('#storyform-improve-caption-count').text(figures.count);
		jQuery('#storyform-improve-caption-total').text(figures.total);
		jQuery('#storyform-improve-caption').toggleClass('storyform-improve-bad', figures.total > figures.count);
		jQuery('#storyform-improve-caption').toggleClass('storyform-improve-good', figures.total <= figures.count);
		if(figures.total > figures.count){
			items++;
		} else {

		}

		// Check if images have crop zone (subject identification)
		var images = getImagesHaveCrop();
		jQuery('#storyform-improve-crop').toggle(!!images.total);
		jQuery('#storyform-improve-crop-count').text(images.count);
		jQuery('#storyform-improve-crop-total').text(images.total);
		jQuery('#storyform-improve-crop').toggleClass('storyform-improve-bad', images.total > images.count);
		jQuery('#storyform-improve-crop').toggleClass('storyform-improve-good', images.total <= images.count);
		if(images.total > images.count){
			items++;
		}

		jQuery('.storyform-improve-count').text(items);
		var progress = jQuery('.storyform-improve-progress');
		progress.val(progress[0].max - items);
		progress.toggleClass('storyform-improve-low', items > 2);
		progress.toggleClass('storyform-improve-medium', items === 1 || items === 2);
		progress.toggleClass('storyform-improve-high', items <= 0);

	}

	function stopMonitorPostImprovements(){
		clearInterval(improvementsInterval);
	}

	/** 
	 *	Determines if post needs a pullquote depending on whether it has one and how long the text is
	 *
	 */
	function hasPullquote(){
		if(window.tinyMCE && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			var plugin = tinyMCE.activeEditor.plugins.storyform
			return plugin.hasPullquote();
		} else {
			var textarea = jQuery('#' + wpActiveEditor);
			if(!textarea){
				return false;
			}
			var text = textarea.val();

			return text.indexOf('pullquote') !== -1
		}
	}

	function isLongPost(){
		var characterLen = 3000;
		if(window.tinyMCE && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			var plugin = tinyMCE.activeEditor.plugins.storyform
			return plugin.getBodyTextLength() > characterLen;
		} else {
			var textarea = jQuery('#' + wpActiveEditor);
			if(!textarea){
				return false;
			}
			var text = textarea.val();

			var lines = text.split('\n');
			var count = 0;
			lines.forEach(function(line){
				var firstChar = line.substr(0, 1)
				if(firstChar !== '[' && firstChar !== '<'){
					count += line.length;
				}
			});
			return count > characterLen;
		}
	}

	function getFiguresHaveOverlay(){
		var total = 0,
			count = 0;
		if(window.tinyMCE && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			var plugin = tinyMCE.activeEditor.plugins.storyform
			var figures = plugin.getFigures();
			total = figures.length;
			[].forEach.call(figures, function(figure){
				if(figure.querySelector('[data-text-overlay]')){
					count++;
				}
			});

		} else {
			var textarea = jQuery('#' + wpActiveEditor),
				text = textarea.val(),
				regex = /\[caption (.*)?\[\/caption\]/gi,
				result;

			while ( (result = regex.exec(text)) ) {
				total++;
				if(result[1].indexOf('data-text-overlay') !== -1){
					count++;
				}
			}
		}
		return {total: total, count: count};
	}

	function getImagesHaveCrop(){
		var total = 0,
			count = 0;
		if(window.tinyMCE && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			var plugin = tinyMCE.activeEditor.plugins.storyform
			var images = plugin.getImages();
			total = images.length;
			[].forEach.call(images, function(image){
				if(image.hasAttribute('data-area-crop')){
					count++;
				}
			});

		} else {
			var images = getTextViewImagesMetadata();
			total = images.length;
			images.forEach(function(image){
				count += image.areas.crop ? 1 : 0;
			});
		}
		return {total: total, count: count};
	}

	var imageRegex = /\<img ([^\>]*)/gi,
		srcRegex = /src=['"]([^'"]*)/i,
		idRegex = /wp-image-(\d*)/i,
		cropRegex = /data-area-crop=['"]([^'"]*)/i,
		captionRegex = /data-text-overlay=['"]([^'"]*)/i;
	function getTextViewImagesMetadata(){
		var textarea = jQuery('#' + wpActiveEditor),
			text = textarea.val(),
			result,
			images = [];

		while ( (result = imageRegex.exec(text)) ) {
			var res, 
				image = {areas: {}};

			if(res = srcRegex.exec(result[1])){
				image.url = res[1];
			} else {
				continue;
			}

			if(res = idRegex.exec(result[1])){
				image.id = parseInt(res[1]);
			}

			if(res = cropRegex.exec(result[1])){
				image.areas.crop = parseCoordString(res[1]);
			}

			if(res = captionRegex.exec(result[1])){
				image.areas.caption = res[1].split(',').map(function(a){return parseCoordString(a)});
			}
			images.push(image);
		}
		return images;
	}

	function setCaptionAreaOnTextViewAttachment(id, areas){
		var textarea = jQuery('#' + wpActiveEditor),
			text = textarea.val();

		var attrib = areas.map(function(a){return serializeCoord(a)}).join(',');

		var regex = new RegExp('\<img (?:[^\>]*)?wp-image-' + id + '(?:[^\>]*)', 'i');
		textarea.val(text.replace(regex, function(match){
			match = match.replace(/ data-text-overlay=['"].*?['"]/, '');
			if(attrib){
				match = match.replace('<img ', '<img data-text-overlay="' + attrib + '" ');	
			}
			return match;
		}));
	}

	function setCropAreaOnTextViewAttachment(id, areas){
		var textarea = jQuery('#' + wpActiveEditor),
			text = textarea.val();

		var attrib = areas ? serializeCoord(areas) : '';

		var regex = new RegExp('\<img (?:[^\>]*)?wp-image-' + id + '(?:[^\>]*)', 'i');
		textarea.val(text.replace(regex, function(match){
			match = match.replace(/ data-area-crop=['"].*?['"]/, '');
			if(attrib){
				match = match.replace('<img ', '<img data-area-crop="' + attrib + '" ');	
			}
			return match;
		}));
	}

	function parseCoordString (str){
        var parts = str.split(" ");
        return {
        	shape: parts[0],
            x1: parseFloat(parts[1]),
            y1: parseFloat(parts[2]),
            x2: parseFloat(parts[3]),
            y2: parseFloat(parts[4]),
            other: parts.slice(5).join(" ")
        }
    }

    function serializeCoord(coord){
        return coord.shape + " " + coord.x1 + " " + coord.y1 + " " + coord.x2 + " " + coord.y2 + ( coord.other ? " " + coord.other : '');
    }

	/**
	 *	Initializes the attachment fields (shown on the edit media and media uploader pages) to setup events and display captions
	 *
	 */
	function initAttachmentFields(id, url, areas){
		var button = document.querySelector('#storyform-add-overlay');
		areas.caption = areas.caption && areas.caption.split(',').map(function(a){return parseCoordString(a)});
		areas.crop = areas.crop && parseCoordString(areas.crop);

		// Check as it may not exist on WP 3.5
		if(button){
			button.addEventListener('click', function(ev){
				showPopupForAttachment(id, url, areas, function(type, idRef, newAreas){
					if(idRef === id){
						areas[type] = newAreas;	
					}
				});
				ev.preventDefault(); // prevent form from submitting
			}, false);

			refreshCaptions(areas);
		}
	}

	/**
	 *	Displays the correct text about how many overlay areas there are.
	 *
	 */
	function refreshCaptions(areas){
		var buttonEl = document.querySelector('#storyform-add-overlay');

		if(areas.caption || areas.crop){
			buttonEl.textContent = buttonEl.getAttribute('data-textContent-multiple');
		} else {
			buttonEl.textContent = buttonEl.getAttribute('data-textContent');
		}	
	}

	/**
	 *	Convert a string format of the area definition to an array format
	 *
	 */
	function areaStringToArray(str) {
		return str.split(",").map(function(str){
			return str.trim();
		}).filter(function(str){
			return str && str !== '';
		});
	}

	/**
	 *	Gets the current overlay areas, either from a hidden field or async
	 *
	 */
	function getCurrentAreas(cb){
		if(storyform.attachment && storyform.attachment.areas !== undefined){
			return cb(areaStringToArray(storyform.attachment.areas));
		}
		var input = document.querySelector('#storyform-text-overlay-areas');
		if(input){
			cb(areaStringToArray(input.value));	
			return;
		}
		var data = {
			'action': 'storyform_get_overlay_areas',
			'attachment_id': storyform.attachment.id
		};

		jQuery.post(ajaxurl, data, function(response) {
			cb(areaStringToArray(response));
		});
	}

	/**
	 *	Display the caption/crop area UI for a specific single attachment (not on a specific attachment UI)
	 *
	 * @param {integer} id Id for the attachment we are showing the popup for. Use null if its not an attachment in the DB.
     * @param {string} url Absolute URL to the image
     * @param {string} areas Current known areas given in a comma separated list of relative text overlay area definitions
	 */
	function showPopupForAttachment(id, url, areas, cb){
		showPopup(0, [{
			id: id,
			url: url,
			areas: areas
		}], cb);
	}

	/**
	 *	Display the caption/crop area UI for all current attachments in post, selecting the given one.
	 *
	 * @param {integer} id Id for the attachment we want to select
     * @param {string} areas Current known areas given in a comma separated list of relative text overlay area definitions
	 */
	function showPopupForCurrentAttachments(id){
		var plugin;
		if(window.tinyMCE && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			plugin = tinyMCE.activeEditor.plugins.storyform
			var media = plugin.getImagesMetadata();

		} else {
			var media = getTextViewImagesMetadata();
		}
		var selectedIndex = 0;
		if(id){
			media.forEach(function(media, index){
				if(media.id === id){
					selectedIndex = index;
				}
			});	
		}

		showPopup(selectedIndex, media, function(type, id, areas){
			if(plugin){
				if(type === 'caption'){
					plugin.setCaptionAreaOnAttachment(id, areas);
				} else if(type === 'crop'){
					plugin.setCropAreaOnAttachment(id, areas);
				}
			} else {
				if(type === 'caption'){
					setCaptionAreaOnTextViewAttachment(id, areas);	
				} else if(type === 'crop'){
					setCropAreaOnTextViewAttachment(id, areas);
				}
			}

		});
	}

	function showPopup(selectedIndex, media, callback){
		// Media upload overrides the tb_position, tb_remove, so we need to override that, but only temporarily
		prevTBposition = tb_position;
		prevTBremove = tb_remove;
		tb_position = thickboxPosition;
		tb_remove = thickboxRemove;

		currentSelectedIndex = selectedIndex;
		currentMedia = media;
		currentCallback = callback;
		window.addEventListener("message", messageReceived, false);

		// Match media upload size
		tb_show("", storyform.url + "/edit/media-areas?environment=wp_thickbox&TB_iframe=true");

		var iframe = document.getElementById('TB_iframeContent');
		contentWindow = iframe.contentWindow;	
	}

	function messageReceived(ev){
		if(ev.origin.indexOf(storyform.url) == -1){
			return;
		}

		switch(ev.data.action){
			case 'ready':
				contentWindow.postMessage({ action: 'edit-media-areas', selectedIndex: currentSelectedIndex, media: currentMedia }, '*');	
				break;
	
			case 'save-areas-caption':
				var id = ev.data.id;
				var areas = ev.data.areas;
				saveCaptionAreas(id, areas);
				currentCallback && currentCallback('caption', id, areas);
				break;

			case 'save-areas-crop':
				var id = ev.data.id;
				var areas = ev.data.areas;
				saveCropAreas(id, areas);
				currentCallback && currentCallback('crop', id, areas);	
				break;

			case 'close':
				tb_remove();
				break;

			default:
				break;
		}
	}

	/**
	 *	Position and size the popup
	 *
	 */
	function thickboxPosition(){
		var width = window.innerWidth - 89;
		var height = window.innerHeight - 59;

		jQuery('#TB_title').addClass('no-bar');
		jQuery('#TB_iframeContent').css({width: (width + 29) + "px", height: (height + 12) + "px"});
		jQuery("#TB_window").css({marginLeft: '-' + parseInt(((width + 30) / 2),10) + 'px', width: (width + 30) + 'px'});
		jQuery("#TB_window").css({marginTop: '-' + parseInt(((height + 20) / 2),10) + 'px', lineHeight: 0});
	};

	/**
	 *	Hide the popup
	 *
	 */
	function thickboxRemove(){
		/* Taken from base thickbox.js */
		jQuery("#TB_imageOff").unbind("click");
		jQuery("#TB_closeWindowButton").unbind("click");
		jQuery("#TB_window").fadeOut("fast",function(){jQuery('#TB_window,#TB_overlay,#TB_HideSelect').trigger("tb_unload").unbind().remove();});
		jQuery( 'body' ).removeClass( 'modal-open' );
		jQuery("#TB_load").remove();
		if (typeof document.body.style.maxHeight == "undefined") {//if IE 6
			jQuery("body","html").css({height: "auto", width: "auto"});
			jQuery("html").css("overflow","");
		}
		jQuery(document).unbind('.thickbox');
		/* end from thickbox.js */

		// Restore previous functions
		tb_position = prevTBposition;
		tb_remove = prevTBremove; 

		checkImprovements();
		// Reset these 
		attachment = {};
		currentSelectedIndex = null;
		currentMedia = null;
		currentCallback = null;

		window.removeEventListener("message", messageReceived, false);

		return false;
	}

	/**
	 *	Save the caption area to the attachment
	 *
	 */
	function saveCaptionAreas(id, areas){
		// Saving via ajax on editing page
		var data = {
			'action': 'storyform_save_overlay_areas',
			'attachment_id': id,
			'storyform_text_overlay_areas': areas.map(function(a){ return serializeCoord(a);}).join(',')
		};

		jQuery.post(ajaxurl, data, function(response) {});
	}

	/**
	 *	Save the crop area to the attachment
	 *
	 */
	function saveCropAreas(id, areas){
		// Saving via ajax on editing page
		var data = {
			'action': 'storyform_save_crop_areas',
			'attachment_id': id,
			'storyform_crop_areas': areas ? serializeCoord(areas) : null
		};

		jQuery.post(ajaxurl, data, function(response) {});
	}
	
	/**
	 *	postMessage is used to communicate with the iframe edit overlay UI
	 *
	 */
	
	scope.storyform = scope.storyform || {};

	// Accessible from inline script next to attachment field (Viewed in "Add Media popup")
	scope.storyform.initAttachmentFields = initAttachmentFields;

	// Accessible from TinyMCE plugin code on visual editor
	scope.storyform.parseCoordString = parseCoordString;
	scope.storyform.serializeCoord = serializeCoord;
	scope.storyform.showPopupForCurrentAttachments = showPopupForCurrentAttachments;
	
})(this);
