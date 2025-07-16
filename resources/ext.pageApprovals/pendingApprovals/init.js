const { createApp } = require('vue');
const PendingApprovalsApp = require('./components/PendingApprovalsApp.vue');

/**
 * Initialize the PendingApprovals Vue application
 */
function init() {
	const mountPoint = document.getElementById('pending-approvals-app');
	
	if (!mountPoint) {
		console.error('PendingApprovals: Mount point not found');
		return;
	}

	// Get initial data from the mount point's data attributes
	const initialData = {
		pendingApprovals: JSON.parse(mountPoint.dataset.pendingApprovals || '[]'),
		headers: JSON.parse(mountPoint.dataset.headers || '{}')
	};

	// Create and mount the Vue app
	const app = createApp(PendingApprovalsApp, {
		initialPendingApprovals: initialData.pendingApprovals,
		headers: initialData.headers
	});

	app.mount(mountPoint);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}