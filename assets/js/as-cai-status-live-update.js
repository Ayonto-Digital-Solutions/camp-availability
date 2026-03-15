/**
 * Real-time status updates for availability status box.
 *
 * @package AS_Camp_Availability_Integration
 * @since   1.3.59
 */
(function ($) {
	'use strict';

	var StatusLiveUpdate = function () {
		this.updateInterval = 15000; // 15 seconds
		this.init();
	};

	StatusLiveUpdate.prototype.init = function () {
		var self = this;

		// Auto-refresh for all status boxes.
		$('.as-cai-status-box').each(function () {
			self.startAutoRefresh($(this));
		});

		// Manual refresh button.
		$(document).on('click', '.as-cai-refresh-button', function (e) {
			e.preventDefault();
			var $box = $(e.target).closest('.as-cai-status-box');
			self.refreshStatus($box);
		});

		// Notify button — show email prompt.
		$(document).on('click', '.as-cai-notify-button, .as-cai-waitlist-button', function (e) {
			e.preventDefault();
			var productId = $(this).data('product-id') || $(this).closest('.as-cai-status-box').data('product-id');
			self.showNotifyPrompt(productId);
		});

		// Countdown updates every second.
		this.updateCountdowns();
		setInterval(function () {
			self.updateCountdowns();
		}, 1000);
	};

	StatusLiveUpdate.prototype.startAutoRefresh = function ($box) {
		var self     = this;
		var interval = $box.data('refresh-interval') || this.updateInterval;

		setInterval(function () {
			self.refreshStatus($box);
		}, interval);
	};

	StatusLiveUpdate.prototype.refreshStatus = function ($box) {
		var self      = this;
		var productId = $box.data('product-id');

		if (!productId || !as_cai_vars) {
			return;
		}

		$box.addClass('updating');

		$.ajax({
			url:  as_cai_vars.ajax_url,
			type: 'POST',
			data: {
				action:     'as_cai_get_status',
				product_id: productId,
				nonce:      as_cai_vars.nonce
			},
			success: function (response) {
				if (response.success) {
					self.updateStatusBox($box, response.data);
					self.showUpdateNotification($box);
				}
			},
			error: function () {
				// Silent fail — will retry on next interval.
			},
			complete: function () {
				$box.removeClass('updating');
				self.updateTimestamp($box);
			}
		});
	};

	StatusLiveUpdate.prototype.updateStatusBox = function ($box, data) {
		var self = this;

		// Update availability numbers.
		$box.find('.availability-main strong').text(
			data.available + ' von ' + data.total + ' Parzellen'
		);

		// Update progress bar.
		$box.find('.progress-available').css('width', data.percent_free + '%');
		var reservedPercent = data.total > 0 ? (data.reserved / data.total) * 100 : 0;
		$box.find('.progress-reserved').css('width', reservedPercent + '%');
		$box.find('.label-available').text(Math.round(data.percent_free) + '% frei');

		// Update badges.
		if (data.reserved > 0) {
			if ($box.find('.reserved-badge').length) {
				$box.find('.reserved-badge').html('&#128336; ' + data.reserved + ' reserviert');
			}
		}
		if (data.sold > 0) {
			if ($box.find('.sold-badge').length) {
				$box.find('.sold-badge').html('&#10003; ' + data.sold + ' verkauft');
			}
		}

		// Change status class if needed.
		var statusClasses = ['status-available', 'status-limited', 'status-critical', 'status-reserved-full', 'status-sold-out'];
		var currentClass  = '';

		for (var i = 0; i < statusClasses.length; i++) {
			if ($box.hasClass(statusClasses[i])) {
				currentClass = statusClasses[i];
				break;
			}
		}

		var newClass = 'status-' + data.status.replace('_', '-');
		if (currentClass !== newClass) {
			$box.removeClass(statusClasses.join(' ')).addClass(newClass);
			self.showStatusChangeAlert(data.status);
		}

		// Update timer if present.
		if (data.next_free_in) {
			var targetTime = Math.floor(Date.now() / 1000) + data.next_free_in;
			$box.find('.timer-countdown').attr('data-target', targetTime);
		}
	};

	StatusLiveUpdate.prototype.updateCountdowns = function () {
		$('.timer-countdown').each(function () {
			var $el       = $(this);
			var target    = parseInt($el.data('target'), 10);
			var now       = Math.floor(Date.now() / 1000);
			var remaining = Math.max(0, target - now);

			if (remaining > 0) {
				$el.text(StatusLiveUpdate.prototype.formatTime(remaining));
				$el.removeClass('expired');
			} else {
				$el.text('Parzellen sollten jetzt frei sein!');
				$el.addClass('expired');
			}
		});
	};

	StatusLiveUpdate.prototype.formatTime = function (seconds) {
		if (seconds < 60) {
			return seconds + ' Sek';
		} else if (seconds < 3600) {
			var mins = Math.floor(seconds / 60);
			var secs = seconds % 60;
			return mins + ':' + secs.toString().padStart(2, '0') + ' Min';
		} else {
			var hours   = Math.floor(seconds / 3600);
			var minutes = Math.floor((seconds % 3600) / 60);
			return hours + ':' + minutes.toString().padStart(2, '0') + ' Std';
		}
	};

	StatusLiveUpdate.prototype.updateTimestamp = function ($box) {
		var now     = new Date();
		var timeStr = now.toLocaleTimeString('de-DE', {
			hour:   '2-digit',
			minute: '2-digit',
			second: '2-digit'
		});
		$box.find('.update-time').text(timeStr);
	};

	StatusLiveUpdate.prototype.showUpdateNotification = function ($box) {
		var $indicator = $box.find('.auto-refresh-indicator');
		$indicator.addClass('pulse').html('&#9679; Aktualisiert');
		setTimeout(function () {
			$indicator.removeClass('pulse').html('&#9679; Auto-Refresh aktiv');
		}, 2000);
	};

	StatusLiveUpdate.prototype.showStatusChangeAlert = function (newStatus) {
		var messages = {
			'available':     'Parzellen wieder verfügbar!',
			'limited':       'Nur noch wenige Parzellen!',
			'critical':      'Letzte Parzellen!',
			'reserved_full': 'Alle Parzellen reserviert',
			'sold_out':      'Ausgebucht'
		};

		if (messages[newStatus]) {
			this.showToast(messages[newStatus], newStatus);
		}
	};

	StatusLiveUpdate.prototype.showToast = function (message, status) {
		var $toast = $('<div class="as-cai-toast toast-' + status.replace('_', '-') + '">' + message + '</div>')
			.appendTo('body');

		setTimeout(function () {
			$toast.addClass('show');
		}, 100);

		setTimeout(function () {
			$toast.removeClass('show');
			setTimeout(function () {
				$toast.remove();
			}, 300);
		}, 3000);
	};

	StatusLiveUpdate.prototype.showNotifyPrompt = function (productId) {
		// Try to get email from logged-in user context.
		var defaultEmail = '';

		var email = prompt('Ihre E-Mail-Adresse für die Benachrichtigung:', defaultEmail);
		if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
			return;
		}

		$.ajax({
			url:  as_cai_vars.ajax_url,
			type: 'POST',
			data: {
				action:     'as_cai_register_notification',
				product_id: productId,
				email:      email,
				nonce:      as_cai_vars.nonce
			},
			success: function (response) {
				if (response.success) {
					alert(response.data.message);
				} else {
					alert('Fehler: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
				}
			},
			error: function () {
				alert('Verbindungsfehler. Bitte versuchen Sie es erneut.');
			}
		});
	};

	// Initialize on page load.
	$(document).ready(function () {
		if ($('.as-cai-status-box').length) {
			new StatusLiveUpdate();
		}
	});

})(jQuery);
