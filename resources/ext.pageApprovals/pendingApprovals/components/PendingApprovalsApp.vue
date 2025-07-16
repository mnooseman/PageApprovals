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
		</cdx-table>
	</div>
</template>

<script>
import { CdxTable } from '@wikimedia/codex';

export default {
	name: 'PendingApprovalsApp',
	components: {
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
		}
	},
	data() {
		return {
			pendingApprovals: this.initialPendingApprovals,
			tableColumns: [
				{
					id: 'page',
					label: this.headers.page || 'Page',
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'categories',
					label: this.headers.categories || 'Categories',
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'lastEditTime',
					label: this.headers.lastEditTime || 'Last Edit Time',
					textAlign: 'start',
					allowSort: true
				},
				{
					id: 'lastEditUserName',
					label: this.headers.lastEditBy || 'Last Edit By',
					textAlign: 'start',
					allowSort: true
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
	methods: {
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

<style scoped>
.pending-approvals {
	max-width: 100%;
}

/* Maintain compatibility with MediaWiki's wikitable styling */
:deep(.cdx-table) {
	border-collapse: collapse;
	margin: 1em 0;
	background: #fff;
}

:deep(.cdx-table th) {
	background-color: #eaecf0;
	text-align: left;
	padding: 0.5em;
	border: 1px solid #a2a9b1;
}

:deep(.cdx-table td) {
	padding: 0.5em;
	border: 1px solid #a2a9b1;
}

:deep(.cdx-table tbody tr:nth-child(even)) {
	background-color: #f8f9fa;
}
</style>