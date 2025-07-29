jQuery(document).ready(function($) {
	'use strict';
	
	/**
	 * Handle featured recipe toggle clicks
	 */
	$('.toggle-featured-status').on('click', function(e) {
		e.preventDefault();
		
		var $this = $(this);
		var postId = $this.data('postid');
		var newStatus = $this.data('status');
		var nonce = $this.data('nonce');
		var $icon = $this.find('.dashicons');
		
		// Prevent multiple clicks during AJAX request
		if ($this.hasClass('processing')) {
			return;
		}
		
		$this.addClass('processing');
		$icon.css('opacity', '0.5');
		
		$.ajax({
			url: adminRecipeFeaturedToggle.ajax_url,
			type: 'POST',
			data: {
				action: 'toggle_featured_recipe_status',
				post_id: postId,
				new_status: newStatus,
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					// Update icon class
					$icon.removeClass('dashicons-star-filled dashicons-star-empty')
						 .addClass(response.data.new_icon);
					
					// Update title attribute
					$this.attr('title', response.data.new_title);
					
					// Update data-status for next click
					$this.data('status', response.data.new_status === '1' ? '0' : '1');
					
					// Restore opacity
					$icon.css('opacity', '1');
					
					// Show brief success indication
					$icon.animate({
						'font-size': '24px'
					}, 150).animate({
						'font-size': '20px'
					}, 150);
					
				} else {
					// Show error message
					alert('Error: ' + (response.data ? response.data.message : 'Unknown error occurred'));
					$icon.css('opacity', '1');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
				alert('An error occurred while updating the featured status. Please try again.');
				$icon.css('opacity', '1');
			},
			complete: function() {
				$this.removeClass('processing');
			}
		});
	});
	
	/**
	 * Add hover effects for better UX
	 */
	$('.toggle-featured-status').hover(
		function() {
			$(this).find('.dashicons').css('transform', 'scale(1.1)');
		},
		function() {
			$(this).find('.dashicons').css('transform', 'scale(1)');
		}
	);
	
	/**
	 * Add smooth transitions
	 */
	$('.toggle-featured-status .dashicons').css({
		'transition': 'all 0.2s ease',
		'cursor': 'pointer'
	});
});