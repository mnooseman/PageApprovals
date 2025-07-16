const { createApp } = require('vue');
const ManageApproversApp = require('./components/ManageApproversApp.vue');

/**
 * Initialize the ManageApprovers Vue application
 */
function init() {
	const mountPoint = document.getElementById('manage-approvers-app');
	
	if (!mountPoint) {
		console.error('ManageApprovers: Mount point not found');
		return;
	}

	// Get initial data from the mount point's data attributes
	const initialData = {
		approvers: JSON.parse(mountPoint.dataset.approvers || '[]'),
		csrfToken: mw.user.tokens.get('csrfToken')
	};

	// Create and mount the Vue app
	const app = createApp(ManageApproversApp, {
		initialApprovers: initialData.approvers,
		csrfToken: initialData.csrfToken
	});

	app.mount(mountPoint);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}