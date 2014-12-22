(function(scope){
	var contentWindow,
		prevTBposition,
		prevTBremove,
		awaitingCallback;

	jQuery(document).ready(function(){
		var select = jQuery('#storyform-templates-select');
		var loading = jQuery('#storyform-templates-loading');
		var postOptions = jQuery('.storyform-post-options');

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
			} else {
				postOptions.show();
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

	function setLayoutType(){
		var val = jQuery('.storyform-layout-type').val();
		if(tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()){
			tinyMCE.activeEditor.plugins.storyform.setLayoutType(val);	
		} else {
			var textarea = jQuery('#' + wpActiveEditor);
			var text = textarea.val();
			var decorational = val === 'freeflow' ? 'article' : 'pinned';
			textarea.val(text.replace(/\sdata\-decorational(?:=[\'\"]([^\'\"]*)[\'\"])?/ig, ' data-decorational="' + decorational + '"'));
		}
	}

	/**
	 *	Initializes the attachment fields (shown on the edit media and media uploader pages) to setup events and display captions
	 *
	 */
	function initAttachmentFields(){
		var button = document.querySelector('#storyform-add-overlay');
		button.addEventListener('click', function(ev){
			showPopup();
			ev.preventDefault(); // prevent form from submitting
		}, false);

		refreshCaptions();
	}

	/**
	 *	Displays the correct text about how many overlay areas there are.
	 *
	 */
	function refreshCaptions(){
		getCurrentAreas(function(areas){
			var countEl = document.querySelector('.storyform-overlay-count');
			var buttonEl = document.querySelector('#storyform-add-overlay');

			if(countEl){
				if(areas.length){
					countEl.textContent = countEl.getAttribute('data-textContent-multiple').replace("{{count}}", areas.length);
					buttonEl.textContent = buttonEl.getAttribute('data-textContent-multiple');
				} else {
					countEl.textContent = countEl.getAttribute('data-textContent');
					buttonEl.textContent = buttonEl.getAttribute('data-textContent');
				}	
			}
		});
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
	 *	Display the text overlay area UI for a specific attachment (not on a specific attachment UI)
	 *
	 * @param {integer} id Id for the attachment we are showing the popup for. Use null if its not an attachment in the DB.
     * @param {string} url Absolute URL to the image
     * @param {string} areas Current known areas given in a comma separated list of relative text overlay area definitions
     * @param {function} callback The callback to be called after the user saves the text overlay. No callback is called on cancel.
	 */
	function showPopupForAttachment(id, url, areas, callback){
		storyform.attachment = storyform.attachment || {};
		storyform.attachment.url = url;
		storyform.attachment.id = id;
		storyform.attachment.areas = areas;
		awaitingCallback = callback;
		showPopup();
	}

	/**
	 *	Show popup for the current attachment
	 *
	 */
	function showPopup(){
		// Media upload overrides the tb_position, tb_remove, so we need to override that, but only temporarily
		prevTBposition = tb_position;
		prevTBremove = tb_remove;
		tb_position = thickboxPosition;
		tb_remove = thickboxRemove;

		window.addEventListener("message", messageReceived, false);
		// Match media upload size
		tb_show("Add text overlay", storyform.url + "/posts/edit-media-overlay?environment=wp_thickbox&TB_iframe=true");

		var iframe = document.getElementById('TB_iframeContent');
		contentWindow = iframe.contentWindow;	
	}

	/**
	 *	Position and size the popup
	 *
	 */
	function thickboxPosition(){
		var width = window.innerWidth - 89;
		var height = window.innerHeight - 89;

		jQuery('#TB_iframeContent').css({width: (width + 29) + "px", height: (height + 12) + "px"});
		jQuery("#TB_window").css({marginLeft: '-' + parseInt(((width + 30) / 2),10) + 'px', width: (width + 30) + 'px'});
		jQuery("#TB_window").css({marginTop: '-' + parseInt(((height + 40) / 2),10) + 'px'});
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

		// Reset these 
		storyform.attachment.areas = null;
		awaitingCallback = null;
		return false;
	}

	/**
	 *	Save the overlay area either directly from the attachment specific UI form or ajax
	 *
	 */
	function saveOverlayAreas(areas){
		if(awaitingCallback){
			awaitingCallback(areas);
		}

		tb_remove();

		var input = document.getElementById('storyform-text-overlay-areas');
		if(input){
			// Saving via form for specific attachment
			input.value = areas.join(',');
			jQuery('#storyform-text-overlay-areas').trigger('change');
			refreshCaptions();
			return;
		} 

		if (storyform.attachment.id) {
			// Saving via ajax on editing page
			var data = {
				'action': 'storyform_save_overlay_areas',
				'attachment_id': storyform.attachment.id,
				'storyform_text_overlay_areas': areas.join(',')
			};

			jQuery.post(ajaxurl, data, function(response) {});
		} 

		
	}
	
	/**
	 *	postMessage is used to communicate with the iframe edit overlay UI
	 *
	 */
	function messageReceived(ev){
		if(ev.origin.indexOf(storyform.url) == -1){
			return;
		}

		switch(ev.data.action){
			case 'ready':
				getCurrentAreas(function(areas){
					contentWindow.postMessage({ action: 'edit-media-overlay', url: storyform.attachment.url, areas: areas }, '*');	
				});
				break;
	
			case 'save-overlay-areas':
				var areas = ev.data.areas;
				saveOverlayAreas(areas);
				break;

			default:
				break;
		}
	}
	scope.storyform = scope.storyform || {};
	scope.storyform.initAttachmentFields = initAttachmentFields;
	scope.storyform.showPopup = showPopup;
	scope.storyform.showPopupForAttachment = showPopupForAttachment;
	
})(this);
