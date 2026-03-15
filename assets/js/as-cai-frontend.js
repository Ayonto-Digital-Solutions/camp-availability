/**
 * AS Camp Availability Integration - Frontend Script
 *
 * @package AS_Camp_Availability_Integration
 */

(function($) {
	'use strict';

	// Debug helper.
	const debug = {
		isEnabled: window.location.search.includes('as_cai_debug=1'),
		log: function(message, data) {
			if (this.isEnabled) {
				console.log('%c[AS CAI] ' + message, 'color: #2271b1; font-weight: bold;', data || '');
			}
		},
		error: function(message, data) {
			if (this.isEnabled) {
				console.error('%c[AS CAI ERROR] ' + message, 'color: #d63638; font-weight: bold;', data || '');
			}
		}
	};

	// Countdown timer instance.
	let countdownInterval = null;

	/**
	 * Initialize the plugin functionality.
	 */
	function init() {
		debug.log('init() called');

		if (typeof asCaiData === 'undefined') {
			debug.error('asCaiData is undefined! Plugin data not loaded.');
			return;
		}

		debug.log('asCaiData loaded:', asCaiData);

		const $seatPlannerButton = $('.stachesepl-single-add-to-cart-button-wrapper');
		const $seatPlannerRoot = $('.stachesepl-add-to-cart-button-root');
		const $statusBox = $('.as-cai-status-box');
		const $counterWrapper = $('.as-cai-availability-counter-wrapper');

		debug.log('Counter wrapper search:', {
			found: $counterWrapper.length,
			hasCounter: asCaiData.hasCounter,
			isAvailable: asCaiData.isAvailable
		});

		// While countdown is active: hide seat planner button, root, and status box.
		// They only become visible once the countdown expires.
		if (!asCaiData.isAvailable) {
			debug.log('Product NOT available - hiding button, root & status box until countdown expires');
			$seatPlannerButton.addClass('as-cai-button-hidden').hide();
			$seatPlannerRoot.addClass('as-cai-button-hidden').hide();
			$statusBox.addClass('as-cai-button-hidden').hide();
		} else {
			debug.log('Product IS available - showing button, root & status box');
			$seatPlannerButton.addClass('as-cai-button-visible').show();
			$seatPlannerRoot.addClass('as-cai-button-visible').show();
			$statusBox.addClass('as-cai-button-visible').show();
		}

		// If counter is active, initialize countdown.
		if (asCaiData.hasCounter && $counterWrapper.length > 0) {
			debug.log('Initializing countdown');
			initCountdown($counterWrapper, $seatPlannerButton, $seatPlannerRoot, $statusBox);
		} else {
			debug.log('Countdown NOT initialized:', {
				hasCounter: asCaiData.hasCounter,
				counterWrapperFound: $counterWrapper.length > 0
			});
		}
	}

	/**
	 * Initialize countdown timer.
	 *
	 * @param {jQuery} $wrapper - Counter wrapper element.
	 * @param {jQuery} $button - Seat planner button wrapper element.
	 * @param {jQuery} $buttonRoot - Seat planner add-to-cart root element.
	 * @param {jQuery} $statusBox - Status display box element.
	 */
	function initCountdown($wrapper, $button, $buttonRoot, $statusBox) {
		const targetTimestamp = parseInt($wrapper.data('target-timestamp'), 10);

		if (!targetTimestamp || isNaN(targetTimestamp)) {
			debug.error('Invalid target timestamp:', $wrapper.data('target-timestamp'));
			return;
		}

		const targetDate = new Date(targetTimestamp * 1000);

		debug.log('Countdown initialized:', {
			targetTimestamp: targetTimestamp,
			targetDate: targetDate.toString(),
			now: new Date().toString()
		});

		// Update countdown immediately.
		updateCountdown($wrapper, targetDate, $button, $buttonRoot, $statusBox);

		// Update countdown every second.
		countdownInterval = setInterval(function() {
			updateCountdown($wrapper, targetDate, $button, $buttonRoot, $statusBox);
		}, 1000);
	}

	/**
	 * Update countdown display.
	 *
	 * @param {jQuery} $wrapper - Counter wrapper element.
	 * @param {Date} targetDate - Target date/time.
	 * @param {jQuery} $button - Seat planner button wrapper element.
	 * @param {jQuery} $buttonRoot - Seat planner add-to-cart root element.
	 * @param {jQuery} $statusBox - Status display box element.
	 */
	function updateCountdown($wrapper, targetDate, $button, $buttonRoot, $statusBox) {
		const now = new Date();
		const timeDiff = targetDate - now;

		// Check if countdown has finished.
		if (timeDiff <= 0) {
			debug.log('COUNTDOWN FINISHED! Revealing buttons and status box.');

			// Clear interval.
			if (countdownInterval) {
				clearInterval(countdownInterval);
				countdownInterval = null;
			}

			// Hide counter with fade out.
			$wrapper.fadeOut(400, function() {
				$(this).remove();
			});

			// Show Seat Planner button wrapper.
			if ($button && $button.length > 0) {
				$button
					.removeClass('as-cai-button-hidden')
					.addClass('as-cai-button-visible')
					.fadeIn(400);
			}

			// Show Seat Planner add-to-cart root.
			if ($buttonRoot && $buttonRoot.length > 0) {
				$buttonRoot
					.removeClass('as-cai-button-hidden')
					.addClass('as-cai-button-visible')
					.fadeIn(400);
			}

			// Show status box.
			if ($statusBox && $statusBox.length > 0) {
				$statusBox
					.removeClass('as-cai-button-hidden')
					.addClass('as-cai-button-visible')
					.fadeIn(400);
			}

			// Also show standard WooCommerce add-to-cart buttons.
			$('.single_add_to_cart_button, .add_to_cart_button').fadeIn(400);

			// Show WooCommerce variations form if it was hidden.
			$('.variations_form, .cart').fadeIn(400);

			// Trigger custom event.
			$(document).trigger('as-cai-product-available');

			// Clear caches and reload to ensure fresh availability data.
			setTimeout(function() {
				if (typeof sessionStorage !== 'undefined') {
					sessionStorage.removeItem('wc_fragments');
					sessionStorage.removeItem('wc_cart_hash');
					sessionStorage.removeItem('wc_cart_created');
				}
				location.replace(location.href.split('#')[0] + (location.href.indexOf('?') > -1 ? '&' : '?') + '_nocache=' + Date.now());
			}, 500);

			return;
		}

		// Calculate time units.
		const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
		const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
		const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
		const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);

		// Update display.
		$wrapper.find('[data-unit="days"]').text(String(days).padStart(2, '0'));
		$wrapper.find('[data-unit="hours"]').text(String(hours).padStart(2, '0'));
		$wrapper.find('[data-unit="minutes"]').text(String(minutes).padStart(2, '0'));
		$wrapper.find('[data-unit="seconds"]').text(String(seconds).padStart(2, '0'));

		// Log every 10 seconds to avoid console spam.
		if (debug.isEnabled && seconds % 10 === 0) {
			debug.log('Countdown update:', {
				days: days,
				hours: hours,
				minutes: minutes,
				seconds: seconds,
				timeRemaining: Math.floor(timeDiff / 1000) + ' seconds'
			});
		}
	}

	/**
	 * Handle dynamic content updates (e.g., AJAX product loading).
	 */
	function handleDynamicContent() {
		debug.log('Setting up dynamic content handlers');

		// Re-initialize if content is dynamically loaded.
		$(document).on('updated_wc_div', function() {
			debug.log('WooCommerce content updated - re-initializing');
			
			// Clear existing interval if any.
			if (countdownInterval) {
				clearInterval(countdownInterval);
				countdownInterval = null;
			}
			
			init();
		});
		
		// Support for Elementor preview.
		if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks) {
			debug.log('Elementor detected - hooking into Elementor events');
			elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
				debug.log('Elementor element ready - re-initializing');
				
				// Clear existing interval if any.
				if (countdownInterval) {
					clearInterval(countdownInterval);
					countdownInterval = null;
				}
				
				init();
			});
		}
	}

	// Initialize on document ready.
	$(document).ready(function() {
		debug.log('Document ready - initializing plugin');
		init();
		handleDynamicContent();
	});

	// Re-initialize on window load (for late-loading elements).
	$(window).on('load', function() {
		debug.log('Window loaded - re-initializing after delay');
		// Slight delay to ensure all elements are rendered.
		setTimeout(function() {
			debug.log('Delayed re-initialization triggered');
			
			// Clear existing interval if any.
			if (countdownInterval) {
				clearInterval(countdownInterval);
				countdownInterval = null;
			}
			
			init();
		}, 100);
	});

})(jQuery);
