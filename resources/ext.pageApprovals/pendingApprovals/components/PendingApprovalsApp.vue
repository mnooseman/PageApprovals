<template>
	<div class="pending-approvals">
		<cdx-table 
			:data="pendingApprovals"
			:columns="tableColumns"
			:sort-options="sortOptions"
			class="wikitable sortable"
		>
			<template #page="{ row }">
				<span v-html="row.pageLink"></span>
			</template>
			<template #lastEditTime="{ row }">
				<span :data-sort-value="row.lastEditTimestamp">
					{{ row.lastEditTimeFormatted }}
				</span>
			</template>
			<template #actions="{ row }">
				<div class="approval-actions">
					<cdx-button
						action="progressive"
						size="small"
						:disabled="loadingStates[`approve-${row.pageId}`]"
						@click="approvePage(row)"
					>
						{{ loadingStates[`approve-${row.pageId}`] ? msg('pageapprovals-loading') : msg('pageapprovals-approve-button') }}
					</cdx-button>
				</div>
			</template>
		</cdx-table>
	</div>
</template>

<script>
const { CdxButton, CdxTextInput, CdxField, CdxTable } = require('../../../codex.js');

module.exports = exports = {
	name: 'PendingApprovalsApp',
	components: {
		CdxButton,
		CdxTable
	},
	props: {
		initialPendingApprovals: {
			type: Array,
			default: () => []
		},
		headers: {
			type: Object,
			default: () => ({})
		},
		csrfToken: {
			type: String,
			required: true
		}
	},
	data() {
		return {
			pendingApprovals: this.initialPendingApprovals,
			loadingStates: {},
			tableColumns: [
				{
					id: 'page',
					label: this.headers.page || mw.msg('pageapprovals-pending-approvals-page'),
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'categories',
					label: this.headers.categories || mw.msg('pageapprovals-pending-approvals-categories'),
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'lastEditTime',
					label: this.headers.lastEditTime || mw.msg('pageapprovals-pending-approvals-last-edit-time'),
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'lastEditUserName',
					label: this.headers.lastEditBy || mw.msg('pageapprovals-pending-approvals-last-edit-by'),
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'actions',
					label: mw.msg('pageapprovals-actions'),
					textAlign: 'center',
					allowSort: false
				}
			],
			sortOptions: {
				mustSort: true,
				defaultSort: {
					column: 'lastEditTime',
					direction: 'desc'
				}
			}
		};
	},
	mounted() {
		// Try to refresh data from REST API on mount
		this.refreshPendingApprovals();
	},

	methods: {
		msg(key) {
			return mw.msg(key);
		},

		async refreshPendingApprovals() {
			try {
				const restClient = new mw.Rest();
				const data = await restClient.get('/page-approvals/v0/pending-approvals');
				if (data && Array.isArray(data)) {
					this.pendingApprovals = data;
				}
			} catch (error) {
				console.warn('Failed to load pending approvals from REST API:', error);
				// Continue with initial data from HTML attributes
			}
		},
		
		async approvePage(row) {
			const loadingKey = `approve-${row.pageId}`;
			this.loadingStates[loadingKey] = true;

			try {
				// Get the latest revision ID for the page
				const api = new mw.Api();
				const pageInfo = await api.get({
					action: 'query',
					pageids: row.pageId,
					prop: 'revisions',
					rvprop: 'ids',
					rvlimit: 1
				});

				const page = pageInfo.query.pages[row.pageId];
				if (!page || !page.revisions || !page.revisions[0]) {
					throw new Error('Could not get page revision information');
				}

				const revisionId = page.revisions[0].revid;

				// Use the REST API for page approval
				const restClient = new mw.Rest();
				const endpoint = `/page-approvals/v0/revision/${revisionId}/approve`;

				const response = await restClient.post(endpoint);

				if (response) {
					// Remove the approved item from the list
					this.pendingApprovals = this.pendingApprovals.filter(
						approval => approval.pageId !== row.pageId
					);
					
					mw.notify(mw.msg('pageapprovals-approved'), { type: 'success' });
				}
			} catch (error) {
				console.error('Error approving page:', error);
				if (error.xhr && error.xhr.responseJSON && error.xhr.responseJSON.message) {
					mw.notify(error.xhr.responseJSON.message, { type: 'error' });
				} else {
					mw.notify(mw.msg('pageapprovals-approve-authorization-failed'), { type: 'error' });
				}
			} finally {
				this.loadingStates[loadingKey] = false;
			}
		},
		
		formatDate(timestamp) {
			// This would use MediaWiki's date formatting
			// For now, using basic JavaScript Date
			return new Date(timestamp * 1000).toLocaleString();
		},
		
		refreshPendingApprovals() {
			// Method to refresh the pending approvals data
			// This could be called after actions are performed
			window.location.reload();
		}
	}
};
</script>