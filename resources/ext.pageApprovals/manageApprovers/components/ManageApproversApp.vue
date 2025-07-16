<template>
	<div class="manage-approvers">
		<div class="form-container">
			<form @submit.prevent="addApprover" class="add-approver-form">
				<cdx-field>
					<cdx-text-input 
						v-model="newUsername"
						placeholder="Username"
						required
					/>
				</cdx-field>
				<cdx-button 
					type="submit"
					action="progressive"
					class="add-approver-button"
				>
					Add Approver
				</cdx-button>
			</form>
		</div>

		<cdx-table 
			:data="approvers"
			:columns="tableColumns"
			class="form-container"
			id="categories-form-container"
		>
			<template #categories="{ row }">
				<div class="categories-cell">
					<div 
						v-for="category in row.categories" 
						:key="category"
						class="category-entry"
					>
						<span class="category-name">{{ category }}</span>
						<form @submit.prevent="deleteCategory(row.username, category)" class="category-row">
							<cdx-button 
								type="submit"
								action="destructive"
								size="small"
								class="approval-delete-btn"
							>
								Delete
							</cdx-button>
						</form>
					</div>
					<form @submit.prevent="addCategory(row.username)" class="add-category-form">
						<cdx-field>
							<cdx-text-input 
								v-model="newCategories[row.username]"
								:placeholder="'New Category'"
								required
							/>
						</cdx-field>
						<cdx-button 
							type="submit" 
							size="small"
							class="add-button"
						>
							Add
						</cdx-button>
					</form>
				</div>
			</template>
		</cdx-table>
	</div>
</template>

<script>
import { CdxButton, CdxTextInput, CdxField, CdxTable } from '@wikimedia/codex';

export default {
	name: 'ManageApproversApp',
	components: {
		CdxButton,
		CdxTextInput,
		CdxField,
		CdxTable
	},
	props: {
		initialApprovers: {
			type: Array,
			default: () => []
		}
	},
	data() {
		return {
			approvers: this.initialApprovers,
			newUsername: '',
			newCategories: {},
			tableColumns: [
				{
					id: 'username',
					label: 'User',
					textAlign: 'start'
				},
				{
					id: 'categories',
					label: 'Categories',
					textAlign: 'start'
				}
			]
		};
	},
	methods: {
		async addApprover() {
			if (!this.newUsername.trim()) {
				return;
			}

			try {
				const formData = new FormData();
				formData.append('username', this.newUsername);
				formData.append('action', 'add-approver');

				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});

				if (response.ok) {
					// Refresh the page data
					await this.refreshApprovers();
					this.newUsername = '';
				}
			} catch (error) {
				console.error('Error adding approver:', error);
				mw.notify('Error adding approver', { type: 'error' });
			}
		},

		async addCategory(username) {
			const category = this.newCategories[username];
			if (!category || !category.trim()) {
				return;
			}

			try {
				const formData = new FormData();
				formData.append('username', username);
				formData.append('category', category);
				formData.append('action', 'add');

				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});

				if (response.ok) {
					// Update local state
					const approver = this.approvers.find(a => a.username === username);
					if (approver) {
						approver.categories.push(category);
					}
					this.$set(this.newCategories, username, '');
				}
			} catch (error) {
				console.error('Error adding category:', error);
				mw.notify('Error adding category', { type: 'error' });
			}
		},

		async deleteCategory(username, category) {
			try {
				const formData = new FormData();
				formData.append('username', username);
				formData.append('category', category);
				formData.append('action', 'delete');

				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData);

				if (response.ok) {
					// Update local state
					const approver = this.approvers.find(a => a.username === username);
					if (approver) {
						approver.categories = approver.categories.filter(c => c !== category);
					}
				}
			} catch (error) {
				console.error('Error deleting category:', error);
				mw.notify('Error deleting category', { type: 'error' });
			}
		},

		async refreshApprovers() {
			try {
				const response = await fetch(window.location.href);
				const html = await response.text();
				
				// Parse the response to extract updated approvers data
				// This would need to be implemented based on your PHP response format
				// For now, we'll reload the page
				window.location.reload();
			} catch (error) {
				console.error('Error refreshing approvers:', error);
			}
		}
	},
	mounted() {
		// Initialize newCategories for all approvers
		this.approvers.forEach(approver => {
			this.$set(this.newCategories, approver.username, '');
		});

		// Prevent back button issues
		if (window.history.replaceState) {
			window.history.replaceState(null, null, window.location.href);
		}
	}
};
</script>

<style scoped>
.manage-approvers {
	.form-container {
		text-align: left;
		max-width: 650px;
		background-color: #f2f2f2;
	}

	.categories-cell {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.category-entry {
		display: flex;
		justify-content: space-between;
		align-items: center;
		background-color: #f9f9f9;
		padding: 5px;
		border-radius: 5px;
	}

	.category-name {
		font-weight: bold;
		padding-left: 15px;
	}

	.category-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin: 0;
	}

	.add-category-form {
		display: flex;
		gap: 8px;
		align-items: flex-end;
		margin-top: 8px;
	}

	.add-approver-form {
		display: flex;
		gap: 12px;
		align-items: flex-end;
		padding: 15px;
		background-color: #f8f8f8;
		border: 1px solid #e7e7e7;
		border-radius: 8px;
		box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
	}
}
</style>