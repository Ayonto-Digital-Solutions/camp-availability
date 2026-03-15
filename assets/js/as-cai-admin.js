/* Camp Availability Integration - Admin JavaScript */

/**
 * Toast notification system.
 */
const asCaiToast = {
	container: null,

	init() {
		if (this.container) return;
		this.container = document.createElement('div');
		this.container.className = 'as-cai-toast-container';
		document.body.appendChild(this.container);
	},

	show(message, type = 'info', duration = 3500) {
		this.init();

		const toast = document.createElement('div');
		toast.className = `as-cai-toast ${type}`;

		const icons = {
			success: 'fa-check',
			error: 'fa-xmark',
			info: 'fa-info'
		};

		toast.innerHTML = `
			<span class="as-cai-toast-icon"><i class="fas ${icons[type] || icons.info}"></i></span>
			<span>${message}</span>
		`;

		this.container.appendChild(toast);

		// Trigger animation
		requestAnimationFrame(() => {
			requestAnimationFrame(() => {
				toast.classList.add('show');
			});
		});

		// Auto-remove
		setTimeout(() => {
			toast.classList.remove('show');
			toast.classList.add('removing');
			setTimeout(() => toast.remove(), 300);
		}, duration);
	}
};

window.asCaiToast = asCaiToast;

function asCaiAdminApp() {
	return {
		stats: {
			active_reservations: 0,
			reserved_products: 0,
			expired_today: 0,
			system_healthy: true
		},
		statsLoaded: false,

		init() {
			this.loadStats();
			setInterval(() => this.loadStats(), 30000);
		},

		async loadStats() {
			try {
				const response = await fetch(asCaiAdmin.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'as_cai_get_stats',
						nonce: asCaiAdmin.nonce
					})
				});

				const data = await response.json();
				if (data.success) {
					this.stats = data.data;
					this.statsLoaded = true;
				}
			} catch (error) {
				console.error('Failed to load stats:', error);
			}
		},

		async clearAllReservations() {
			if (!confirm(asCaiAdmin.i18n.confirm_clear)) {
				return;
			}

			try {
				const response = await fetch(asCaiAdmin.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'as_cai_clear_reservations',
						nonce: asCaiAdmin.nonce
					})
				});

				const data = await response.json();
				if (data.success) {
					asCaiToast.show(asCaiAdmin.i18n.cleared, 'success');
					this.loadStats();
				} else {
					asCaiToast.show(data.data.message || asCaiAdmin.i18n.error, 'error');
				}
			} catch (error) {
				asCaiToast.show(asCaiAdmin.i18n.error, 'error');
				console.error('Failed to clear reservations:', error);
			}
		},

		refreshReservations() {
			if (typeof location !== 'undefined') {
				location.reload();
			}
		}
	};
}

// Make function globally available for Alpine.js
window.asCaiAdminApp = asCaiAdminApp;

// Initialize when Alpine is ready
document.addEventListener('alpine:init', () => {
	asCaiToast.init();
});
