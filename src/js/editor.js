'use strict';

import * as WindowMessageManager from 'window-messages';

var frame;
	
function init(){
	document.querySelector('#storyform-editor').focus();
}
document.addEventListener('DOMContentLoaded', init, false);

WindowMessageManager.addEventListener('set-post', function(ev){
	var id = ev.detail.data.id;
	var title = ev.detail.data.title;
	global.window.history.replaceState({}, 'Edit ' + title, document.location.href + '&post=' + id);
});

WindowMessageManager.addEventListener('create-post', function(ev){
	var data = ev.detail.data;

	jQuery.post(ajaxurl, { 
		action : 'storyform_create_post', 
		_ajax_nonce: storyform_nonce,
		post_title: data.title,
		post_excerpt: data.excerpt,
		post_content: data.content,
		post_type: data.postType,
		template: data.template,
		horizontal: data.horizontal
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		data.action = 'create-post';
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener('get-post', function(ev){
	var data = ev.detail.data;

	jQuery.post(ajaxurl, { 
		action : 'storyform_get_post', 
		_ajax_nonce: storyform_nonce,
		id: data.id
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		data.action = 'get-post';
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener('update-post', function(ev){
	var data = ev.detail.data;

	jQuery.post(ajaxurl, { 
		action : 'storyform_update_post', 
		_ajax_nonce: storyform_nonce,
		id: data.id,
		post_title: data.title,
		post_content: data.content,
		post_excerpt: data.excerpt,
		template: data.template,
		post_type: data.postType,
		horizontal: data.horizontal
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		data.action = 'update-post';
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener('get-publish-url', function(ev){
	var data = ev.detail.data;

	jQuery.post(ajaxurl, { 
		action : 'storyform_get_publish_url', 
		_ajax_nonce: storyform_nonce,
		id: data.id,
		name: data.name
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		data.action = 'get-publish-url';
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener('delete-post', function(ev){
	var source = ev.detail.source;
	var req = ev.detail.data;
	
	jQuery.post(ajaxurl, { 
		action : 'storyform_delete_post', 
		_ajax_nonce: storyform_nonce,
		id: req.id
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		data.action = 'delete-post';
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener('redirect', function(ev){
	var data = ev.detail.data;
	document.location.href = data.url;	
});

WindowMessageManager.addEventListener('redirect-admin-edit', function(ev){
	var req = ev.detail.data;
	jQuery.post(ajaxurl, { 
		action : 'storyform_redirect_admin_edit', 
		_ajax_nonce: storyform_nonce,
		id: req.id
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		document.location.href = data.url;
	});
});


WindowMessageManager.addEventListener('get-post-types', function(ev){
	var data = ev.detail.data;

	jQuery.post(ajaxurl, { 
		action : 'storyform_get_post_types', 
		_ajax_nonce: storyform_nonce
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		data.action = 'get-post-types';
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener('select-media', selectMedia);
function selectMedia(ev){
    if ( frame ) {
		frame.open();
		return;
    }
    
    frame = wp.media({
		title: 'Select or Upload Media',
		button: {
			text: 'Insert this media'
		},
		multiple: true
    });

    frame.on( 'select', function() {
		var models = frame.state().get('selection').models;
		var media = [];
		models.forEach(function(model){
			var data = model.toJSON();
			media.push(data);
		});

		if(media.filter(function(item){return item.type === 'video'}).length > 1){
			alert('Please choose only one video at a time');
			document.querySelector('#storyform-editor').focus();
			return;
		}
		var ids = media.map(function(a){return a.id});
		getMediaSizes( ids, function(sizes){
			media.forEach(function(item){
				item.sizes = sizes[item.id];
			});
			
			var pendingPoster = false;
			media.forEach(function(item){
				if(item.type === 'video'){
					pendingPoster = true;
					choosePoster(function(poster){
						item.poster = poster;
						WindowMessageManager.postMessage(ev.detail.source, {action: 'select-media', media: media});
					});
				}
			});

			!pendingPoster && WindowMessageManager.postMessage(ev.detail.source, {action: 'select-media', media: media});
		});

		
    });
    frame.on( 'close', function() {
    	document.querySelector('#storyform-editor').focus();
    });
    frame.open();
}

function choosePoster(cb){
    var posterFrame = wp.media({
		title: 'Select or Upload a video poster',
		button: {
			text: 'Use as poster'
		},
		multiple: false
    });

    posterFrame.on( 'select', function() {
		var media = posterFrame.state().get('selection').first().toJSON();
		getMediaSizes( [media.id], function(sizes){
			media.sizes = sizes[media.id];
			cb(media);
		});
		
    });

    posterFrame.open();
}

function getMediaSizes(ids, cb){
	jQuery.post(ajaxurl, { 
		action : 'storyform_get_media_sizes', 
		ids: ids.join(','),
		_ajax_nonce: storyform_nonce
	}, function(data, textStatus, jqXHR){
		data = JSON.parse(data);
		cb(data);
	});
}

//     {
//     "id": 2226,
//     "title": "Screen Shot 2015-01-29 at 1.31.07 PM",
//     "filename": "Screen-Shot-2015-01-29-at-1.31.07-PM.png",
//     "url": "http://storyform.co/demos/wp-content/uploads/2015/01/Screen-Shot-2015-01-29-at-1.31.07-PM.png",
//     "link": "http://storyform.co/demos/2015/01/29/understanding-big-data/screen-shot-2015-01-29-at-1-31-07-pm/",
//     "alt": "",
//     "author": "1",
//     "description": "",
//     "caption": "",
//     "name": "screen-shot-2015-01-29-at-1-31-07-pm",
//     "status": "inherit",
//     "uploadedTo": 2224,
//     "date": "2015-01-29T21:31:55.000Z",
//     "modified": "2015-01-29T21:31:55.000Z",
//     "menuOrder": 0,
//     "mime": "image/png",
//     "type": "image",
//     "subtype": "png",
//     "icon": "http://storyform.co/demos/wp-includes/images/media/default.png",
//     "dateFormatted": "2015/01/29",
//     "nonces": {
//         "update": "8b105bad9f",
//         "delete": "6e8e17ec1b",
//         "edit": "b853dbf4a0"
//     },
//     "editLink": "http://storyform.co/demos/wp-admin/post.php?post=2226&action=edit",
//     "meta": false,
//     "authorName": "narrative",
//     "uploadedToLink": "http://storyform.co/demos/wp-admin/post.php?post=2224&action=edit",
//     "uploadedToTitle": "Understanding <strong>Big Data</strong>",
//     "filesizeInBytes": 1184145,
//     "filesizeHumanReadable": "1 MB",
//     "sizes": {
//         "full": {
//             "url": "http://storyform.co/demos/wp-content/uploads/2015/01/Screen-Shot-2015-01-29-at-1.31.07-PM.png",
//             "height": 842,
//             "width": 1300,
//             "orientation": "landscape"
//         }
//     },
//     "height": 842,
//     "width": 1300,
//     "orientation": "landscape",
//     "compat": {
//         "item": "<input type=\"hidden\" name=\"attachments[2226][menu_order]\" value=\"0\" /><table class=\"compat-attachment-fields\">\t\t<tr class='compat-field-storyform_areas'>\t\t\t<th scope='row' class='label'><label for='attachments-2226-storyform_areas'><span class='alignleft'>Crop/Caption areas</span><br class='clear' /></label></th>\n\t\t\t<td class='field'><div><button class=\"button-primary\" id=\"storyform-add-overlay\" data-textContent-multiple=\"Edit crop/caption area(s)\" data-textContent=\"Add caption/crop area\"></button><script> \n\t\t\t\t(function(){\n\t\t\t\t\tvar id = \"2226\";\n\t\t\t\t\tvar url = \"http://storyform.co/demos/wp-content/uploads/2015/01/Screen-Shot-2015-01-29-at-1.31.07-PM.png\";\n\t\t\t\t\tvar areas = {\n\t\t\t\t\t\tcrop: \"rect 0.07074309185959671 0.06553398058252427 0.90865571321882 0.866504854368932\",\n\t\t\t\t\t\tcaption: \"rect 0.6546013869000692 0 1 0.3239907969044133 dark-theme\"\n\t\t\t\t\t};\n\t\t\t\t\tstoryform.initAttachmentFields && storyform.initAttachmentFields(id, url, areas);\n\t\t\t\t})()\n\t\t\t\t\n\t\t\t</script></div></td>\n\t\t</tr>\n</table>",
//         "meta": ""
//     }
// }"




