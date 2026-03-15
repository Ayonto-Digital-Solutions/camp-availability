/* Camp Availability Integration - Admin JavaScript */

function asCaiAdminApp() {
	return {
		stats: {
			active_reservations: 0,
			reserved_products: 0,
			expired_today: 0,
			system_healthy: true
		},
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
					alert(asCaiAdmin.i18n.cleared);
					this.loadStats();
					if (typeof location !== 'undefined') {
						location.reload();
					}
				} else {
					alert(data.data.message || asCaiAdmin.i18n.error);
				}
			} catch (error) {
				alert(asCaiAdmin.i18n.error);
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
	console.log('Ayonto Camp Availability Admin initialized');
});
