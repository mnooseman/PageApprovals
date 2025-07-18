<template>
	<div class="manage-approvers">
		<div class="form-container">
			<form @submit.prevent="addApprover" class="add-approver-form">
				<cdx-field>
					<cdx-lookup
						v-model:selected="selectedUser"
						v-model:input-value="newUsername"
						:menu-items="userSuggestions"
						:placeholder="msg('pageapprovals-username')"
						@input="searchUsers"
						@update:selected="onUserSelected"
					/>
				</cdx-field>
				<cdx-button 
					type="submit"
					action="progressive"
					class="add-approver-button"
					:disabled="isLoading"
				>
					{{ isLoading ? msg('pageapprovals-loading') : msg('pageapprovals-add-approver') }}
				</cdx-button>
			</form>
		</div>

		<cdx-table 
			:data="approvers"
			:columns="tableColumns"
			class="form-container"
			id="categories-form-container"
		>
			<template #item-categories="{ item, row }">
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
								:disabled="loadingStates[`delete-${row.username}-${category}`]"
							>
								{{ loadingStates[`delete-${row.username}-${category}`] ? msg('pageapprovals-loading') : msg('pageapprovals-delete') }}
							</cdx-button>
						</form>
					</div>
					<form @submit.prevent="addCategories(row.username)" class="add-category-form">
						<cdx-field>
							<cdx-multiselect-lookup
								v-model:selected="selectedCategories[row.username]"
								v-model:input-chips="inputChips[row.username]"
								v-model:input-value="newCategoryInput[row.username]"
								:menu-items="categorySuggestions[row.username] || []"
								:placeholder="msg('pageapprovals-add-category')"
								@input="searchCategories(row.username, $event)"
								@update:selected="onCategoriesSelected(row.username, $event)"
							/>
						</cdx-field>
						<cdx-button 
							type="submit" 
							size="small"
							class="add-button"
							:disabled="loadingStates[`add-${row.username}`] || !selectedCategories[row.username] || selectedCategories[row.username].length === 0"
						>
							{{ loadingStates[`add-${row.username}`] ? msg('pageapprovals-loading') : msg('pageapprovals-add') }}
						</cdx-button>
					</form>
				</div>
			</template>
		</cdx-table>
	</div>
</template>

<script>
const { CdxButton, CdxTextInput, CdxField, CdxTable, CdxLookup, CdxMultiselectLookup } = require('../../../codex.js');

module.exports = exports = {
	name: 'ManageApproversApp',
	components: {
		CdxButton,
		CdxTextInput,
		CdxField,
		CdxTable,
		CdxLookup,
		CdxMultiselectLookup
	},
	props: {
		initialApprovers: {
			type: Array,
			default: () => []
		},
		csrfToken: {
			type: String,
			required: true
		}
	},
	data() {
		// Initialize category properties for existing approvers
		const initialCategoryInput = {};
		const initialSelectedCategories = {};
		const initialCategorySuggestions = {};
		const initialInputChips = {};
		
		this.initialApprovers.forEach(approver => {
			initialCategoryInput[approver.username] = '';
			initialSelectedCategories[approver.username] = [];
			initialCategorySuggestions[approver.username] = [];
			initialInputChips[approver.username] = [];
		});

		return {
			approvers: this.initialApprovers,
			newUsername: '',
			selectedUser: null,
			userSuggestions: [],
			newCategoryInput: initialCategoryInput, // Input value for category search
			selectedCategories: initialSelectedCategories, // Selected categories for multiselect (will be initialized per user)
			categorySuggestions: initialCategorySuggestions, // Category suggestions per user (will be initialized per user)
			inputChips: initialInputChips, // Input chips for display in multiselect
			isLoading: false,
			loadingStates: {}, // Track loading state for individual operations
			tableColumns: [
				{
					id: 'username',
					label: mw.msg('pageapprovals-username'),
					textAlign: 'start'
				},
				{
					id: 'categories',
					label: mw.msg('pageapprovals-categories'),
					textAlign: 'start'
				}
			]
		};
	},
	methods: {
		msg(key) {
			return mw.msg(key);
		},

		// Autocomplete methods
		async searchUsers(query) {
			if (!query || query.length < 2) {
				this.userSuggestions = [];
				return;
			}

			try {
				const api = new mw.Api();
				const result = await api.get({
					action: 'query',
					list: 'allusers',
					auprefix: query,
					aulimit: 10,
					format: 'json'
				});

				this.userSuggestions = result.query.allusers.map(user => ({
					label: user.name,
					value: user.name
				}));
			} catch (error) {
				console.error('Error searching users:', error);
				this.userSuggestions = [];
			}
		},

		async searchCategories(username, query) {
			if (!query || query.length < 2) {
				this.categorySuggestions[username] = [];
				return;
			}

			try {
				const api = new mw.Api();
				const result = await api.get({
					action: 'query',
					list: 'allcategories',
					acprefix: query,
					aclimit: 10,
					format: 'json'
				});

				const suggestions = result.query.allcategories.map(cat => ({
					label: cat['*'],
					value: cat['*']
				}));

				this.categorySuggestions[username] = suggestions;
			} catch (error) {
				console.error('Error searching categories:', error);
				this.categorySuggestions[username] = [];
			}
		},

		onUserSelected(selectedItem) {
			if (selectedItem) {
				this.newUsername = selectedItem.value;
			}
		},

		onCategoriesSelected(username, selectedItems) {
			// selectedItems is an array of selected category objects or strings
			this.selectedCategories[username] = selectedItems || [];
			
			// Update inputChips to match the selected items for proper display
			// Handle both object format {value: "cat", label: "cat"} and string format "cat"
			this.inputChips[username] = (selectedItems || []).map(item => {
				if (typeof item === 'string') {
					return { value: item, label: item };
				} else {
					return { value: item.value, label: item.label };
				}
			});
		},
		validateUsername(username) {
			// Basic MediaWiki username validation
			if (!username || !username.trim()) {
				return false;
			}
			// Check for invalid characters
			const invalidChars = /[<>"\[\]|{}]/;
			return !invalidChars.test(username) && username.trim().length <= 255;
		},
		validateCategory(category) {
			// Basic category validation
			if (!category || !category.trim()) {
				return false;
			}
			return category.trim().length <= 255;
		},
		async addApprover() {
			if (!this.validateUsername(this.newUsername) || this.isLoading) {
				if (!this.validateUsername(this.newUsername)) {
					mw.notify(mw.msg('pageapprovals-invalid-username'), { type: 'error' });
				}
				return;
			}

			this.isLoading = true;
			try {
				const formData = new FormData();
				formData.append('username', this.newUsername);
				formData.append('action', 'add-approver');
				formData.append('wpEditToken', this.csrfToken);

				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});

				if (response.ok) {
					// Refresh the page data
					await this.refreshApprovers();
					this.newUsername = '';
				} else {
					mw.notify(mw.msg('pageapprovals-error-adding-approver'), { type: 'error' });
				}
			} catch (error) {
				console.error('Error adding approver:', error);
				mw.notify(mw.msg('pageapprovals-error-adding-approver'), { type: 'error' });
			} finally {
				this.isLoading = false;
			}
		},

		async addCategories(username) {
			const selectedCategories = this.selectedCategories[username] || [];
			
			if (selectedCategories.length === 0) {
				return;
			}

			const loadingKey = `add-${username}`;
			this.loadingStates[loadingKey] = true;

			try {
				const approver = this.approvers.find(a => a.username === username);
				if (!approver) {
					return;
				}

				// Add each selected category that's not already assigned
				for (const categoryItem of selectedCategories) {
					// Handle both object format {value: "category"} and string format "category"
					const category = typeof categoryItem === 'string' ? categoryItem : categoryItem.value;
					
					if (!this.validateCategory(category)) {
						mw.notify(mw.msg('pageapprovals-invalid-category'), { type: 'error' });
						continue;
					}

					// Skip if category already exists for this user
					if (approver.categories.includes(category)) {
						continue;
					}

					const formData = new FormData();
					formData.append('username', username);
					formData.append('category', category);
					formData.append('action', 'add');
					formData.append('wpEditToken', this.csrfToken);

					const response = await fetch(window.location.href, {
						method: 'POST',
						body: formData
					});

					if (response.ok) {
						// Update local state
						approver.categories.push(category);
					} else {
						mw.notify(mw.msg('pageapprovals-error-adding-category'), { type: 'error' });
					}
				}

				// Clear the selections for this user
				this.selectedCategories[username] = [];
				this.inputChips[username] = [];
				this.newCategoryInput[username] = '';
			} catch (error) {
				console.error('Error adding categories:', error);
				mw.notify(mw.msg('pageapprovals-error-adding-category'), { type: 'error' });
			} finally {
				this.loadingStates[loadingKey] = false;
			}
		},

		async deleteCategory(username, category) {
			const loadingKey = `delete-${username}-${category}`;
			this.loadingStates[loadingKey] = true;

			try {
				const formData = new FormData();
				formData.append('username', username);
				formData.append('category', category);
				formData.append('action', 'delete');
				formData.append('wpEditToken', this.csrfToken);

				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});

				if (response.ok) {
					// Update local state
					const approver = this.approvers.find(a => a.username === username);
					if (approver) {
						approver.categories = approver.categories.filter(c => c !== category);
					}
				} else {
					mw.notify(mw.msg('pageapprovals-error-deleting-category'), { type: 'error' });
				}
			} catch (error) {
				console.error('Error deleting category:', error);
				mw.notify(mw.msg('pageapprovals-error-deleting-category'), { type: 'error' });
			} finally {
				this.loadingStates[loadingKey] = false;
			}
		},

		async refreshApprovers() {
			try {
				// Use the new REST API to get fresh approvers data
				const restClient = new mw.Rest();
				const approvers = await restClient.get('/page-approvals/v0/approvers');
				this.approvers = approvers || [];
				
				// Initialize category properties for any new approvers
				this.approvers.forEach(approver => {
					if (!this.newCategoryInput.hasOwnProperty(approver.username)) {
						this.newCategoryInput[approver.username] = '';
						this.selectedCategories[approver.username] = [];
						this.categorySuggestions[approver.username] = [];
						this.inputChips[approver.username] = [];
					}
				});
			} catch (error) {
				console.error('Error refreshing approvers:', error);
				// Fallback to page reload
				window.location.reload();
			}
		},

		// Helper method to try REST API calls with fallback
		async tryRestApiCall(action, data) {
			try {
				const restClient = new mw.Rest();
				await restClient.post('/page-approvals/v0/approvers', {
					action: action,
					...data
				});
				return true;
			} catch (error) {
				console.warn('REST API call failed, falling back to form POST:', error);
				return false;
			}
		}
	},
	mounted() {
		// Prevent back button issues
		if (window.history.replaceState) {
			window.history.replaceState(null, null, window.location.href);
		}
	}
};
</script>