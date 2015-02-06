(function(){

	var hidden = false,
		toggle;
	function toggleAdvanced(){
		hidden = !hidden;
		var table = document.querySelector('.storyform_toggle_advanced + table');
		table.style.display = hidden ? 'none' : '';
		toggle.textContent = toggle.getAttribute('data-' + (hidden ? 'show' : 'hide') + '-text');
	}

	document.addEventListener("DOMContentLoaded", function(){

		WinJS.UI.processAll();

		toggle = document.querySelector('.storyform_toggle_advanced');
		toggleAdvanced();
		toggle.addEventListener('click', function(){
			toggleAdvanced();
			return false;
		});


		jQuery(document).ready(function($){
			$('.storyform-color-picker').wpColorPicker();
		});

		// Uploading files
		var file_frame;
	  	jQuery('.storyform-select-logo').click(function( event ){
	 
	    	event.preventDefault();
	 
	    	// If the media frame already exists, reopen it.
	    	if ( file_frame ) {
	      		file_frame.open();
	      		return;
	    	}
	 
	    	// Create the media frame.
	    	file_frame = wp.media.frames.file_frame = wp.media({
	      		title: jQuery( this ).data( 'uploaderTitle' ),
	      		button: {
	        		text: jQuery( this ).data( 'uploaderButtonText' ),
	      		},
	      		multiple: false 
	    	});
	 
	    	// When an image is selected, run a callback.
	    	file_frame.on( 'select', function() {
	      		// We set multiple to false so only get one image from the uploader
	      		attachment = file_frame.state().get('selection').first().toJSON();
	 
	 			jQuery( '#storyform-navigation-logo' ).val( attachment.url );
	      		// Do something with attachment.id and/or attachment.url here
	    	});
	 
	    	// Finally, open the modal
	    	file_frame.open();
	  	});

	  	jQuery('.storyform-navigation-width').click(function( event ) {
	  		if( jQuery(this).val() === 'full' ){
	  			jQuery('.storyform-navigation-border').prop('disabled', false);
	  			jQuery('.storyform-navigation-side').prop('disabled', true);
	  			jQuery('.storyform-navigation-title').prop('disabled', false);
	  		} else {
	  			jQuery('.storyform-navigation-border').prop('disabled', true);
	  			jQuery('.storyform-navigation-side').prop('disabled', false);
	  			jQuery('.storyform-navigation-title').prop('disabled', true);
	  		}
	  	});

	  	if( jQuery('#storyform-navigation-width-full').prop('checked') ){
  			jQuery('.storyform-navigation-border').prop('disabled', false);
  			jQuery('.storyform-navigation-side').prop('disabled', true);
  			jQuery('.storyform-navigation-title').prop('disabled', false);
  		} else {
  			jQuery('.storyform-navigation-border').prop('disabled', true);
  			jQuery('.storyform-navigation-side').prop('disabled', false);
  			jQuery('.storyform-navigation-title').prop('disabled', true);
  		}

  		jQuery('.storyform-reset-all-button').click(function( event ) {
  			event.preventDefault();
  			var confirmed = confirm("Are you sure you want to reset all Storyform settings and posts?");
  			if(!confirmed){
				return;
  			}

  			var data = {
				'action': 'storyform_reset_all',
				'_ajax_nonce': storyformAjaxNonce
			};
			jQuery.post(ajaxurl, data, function(response) {
				document.location.reload();
			});
  			
  		});

	}, false);

})();