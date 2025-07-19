<template>
	<div class="ext-pageapprovals-manage-approvers">
		<!-- Add Approver Section -->
		<section class="ext-pageapprovals-manage-approvers__add-section">
			<h2 class="ext-pageapprovals-manage-approvers__add-section__title">
				{{ msg('pageapprovals-add-approver') }}
			</h2>
			<form 
				@submit.prevent="addApprover" 
				class="ext-pageapprovals-manage-approvers__add-section__form"
			>
				<div class="ext-pageapprovals-manage-approvers__add-section__form__input-wrapper">
					<cdx-field>
						<cdx-lookup
							v-model:selected="selectedUser"
							v-model:input-value="newUsername"
							:menu-items="userSuggestions"
							:placeholder="msg('pageapprovals-username')"
							@input="searchUsers"
						/>
					</cdx-field>
				</div>
				<cdx-button 
					type="submit"
					action="progressive"
					class="ext-pageapprovals-manage-approvers__add-section__form__button"
					:disabled="isLoading"
				>
					{{ isLoading ? msg('pageapprovals-loading') : msg('pageapprovals-add-approver') }}
				</cdx-button>
			</form>
		</section>

		<!-- Current Approvers Section -->
		<section class="ext-pageapprovals-manage-approvers__approvers-section">
			<!-- Approvers Table -->
			<div v-if="approvers.length > 0" class="ext-pageapprovals-manage-approvers__table">
				<cdx-table 
					:data="approvers"
					:columns="tableColumns"
					:caption="msg('manageapprovers')"
				>
					<template #item-categories="{ item, row }">
					<div class="ext-pageapprovals-manage-approvers__categories-display">
						<span 
							v-if="row.categories.length === 0"
							class="ext-pageapprovals-manage-approvers__categories-display--empty"
						>
							{{ msg('pageapprovals-no-categories-assigned') }}
						</span>
						<span 
							v-else
							class="ext-pageapprovals-manage-approvers__categories-display__list"
						>
							{{ row.categories.join(', ') }}
						</span>
					</div>
					</template>
					
					<template #item-actions="{ item, row }">
						<div class="ext-pageapprovals-manage-approvers__actions">
							<cdx-button
								:aria-label="msg('pageapprovals-edit-categories')"
								@click="openEditDialog(row.username)"
								:disabled="loadingStates[`edit-${row.username}`]"
							>
								<cdx-icon :icon="cdxIconEdit"></cdx-icon>
						</cdx-button>
						<cdx-button
							:aria-label="msg('pageapprovals-remove-approver')"
							@click="confirmRemoveApprover(row.username)"
							:disabled="loadingStates[`remove-${row.username}`]"
							action="destructive"
						>
							<cdx-icon :icon="cdxIconTrash"></cdx-icon>
						</cdx-button>					
						</div>
					</template>
				</cdx-table>
			</div>
			
			<!-- Empty State -->
			<div 
				v-else 
				class="ext-pageapprovals-manage-approvers__empty-state"
			>
				<div class="ext-pageapprovals-manage-approvers__empty-state__icon"><cdx-icon :icon="cdxIconUserGroup"></cdx-icon></div>
				<div class="ext-pageapprovals-manage-approvers__empty-state__message">
					No approvers configured
				</div>
				<div class="ext-pageapprovals-manage-approvers__empty-state__hint">
					Add your first approver using the form above
				</div>
			</div>
		</section>

		<!-- Edit Categories Dialog -->
		<cdx-dialog
			v-model:open="editDialogOpen"
			class="ext-pageapprovals-manage-approvers__edit-dialog"
			:title="editingUser ? msg('pageapprovals-edit-categories-for', editingUser) : ''"
			:use-close-button="true"
			:close-button-label="msg('pageapprovals-close-edit-dialog')"
			:primary-action="hasChanges(editingUser) ? {
				label: savingChanges ? msg('pageapprovals-saving') : msg('pageapprovals-save-changes'),
				disabled: savingChanges,
				actionType: 'progressive'
			} : null"
			:default-action="{
				label: msg('pageapprovals-close'),
				disabled: savingChanges
			}"
			@update:open="onDialogOpenChange"
			@primary="saveAllChanges(editingUser)"
			@default="closeEditDialog"
		>
			<div v-if="editingUser" class="ext-pageapprovals-manage-approvers__edit-dialog__content">
				<!-- Categories Management -->
				<div class="ext-pageapprovals-manage-approvers__edit-dialog__categories">
					<h4>{{ msg('pageapprovals-categories') }}</h4>
					
					<!-- MultiselectLookup for category management -->
					<cdx-field>
						<cdx-multiselect-lookup
							v-model:input-chips="inputChips[editingUser]"
							v-model:selected="selectedCategories[editingUser]"
							v-model:input-value="newCategoryInput[editingUser]"
							:menu-items="categorySuggestions[editingUser] || []"
							:placeholder="msg('pageapprovals-search-add-category')"
							:separate-input="true"
							@input="searchCategories(editingUser, $event)"
							@update:input-chips="onInputChipsChange(editingUser, $event)"
							@update:selected="onSelectedCategoriesChange(editingUser, $event)"
						/>
					</cdx-field>
				</div>
			</div>
		</cdx-dialog>
	</div>
</template>

<script>
const { CdxButton, CdxTextInput, CdxField, CdxTable, CdxLookup, CdxMultiselectLookup, CdxIcon, CdxDialog } = require('../../../codex.js');
const { cdxIconEdit, cdxIconTrash, cdxIconClose, cdxIconUserGroup } = require('../icons.json');

module.exports = exports = {
	name: 'ManageApproversApp',
	components: {
		CdxButton,
		CdxTextInput,
		CdxField,
		CdxTable,
		CdxLookup,
		CdxMultiselectLookup,
		CdxIcon,
		CdxDialog
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
		const initialCategorySuggestions = {};
		const initialInputChips = {};
		const initialSelectedCategories = {};
		
		this.initialApprovers.forEach(approver => {
			initialCategoryInput[approver.username] = '';
			initialCategorySuggestions[approver.username] = [];
			// Convert categories to inputChips format (value/label objects)
			initialInputChips[approver.username] = approver.categories.map(cat => ({
				value: cat,
				label: cat
			}));
			// Selected should be just the values
			initialSelectedCategories[approver.username] = [...approver.categories];
		});

		return {
			approvers: this.initialApprovers,
			newUsername: '',
			selectedUser: null,
			userSuggestions: [],
			newCategoryInput: initialCategoryInput, // Input value for category search
			categorySuggestions: initialCategorySuggestions, // Category suggestions per user (MenuItemData format)
			inputChips: initialInputChips, // Category chips for MultiselectLookup (ChipInputItem format)
			selectedCategories: initialSelectedCategories, // Selected category values for MultiselectLookup
			originalCategories: {}, // Track original state for comparison - will be set in openEditDialog
			isLoading: false,
			loadingStates: {}, // Track loading state for individual operations
			editDialogOpen: false, // Controls the Codex Dialog visibility
			editingUser: null, // Currently editing user for the dialog
			savingChanges: false, // Track saving state
			chipObserver: null, // MutationObserver to watch for chip changes
			// Codex icons
			cdxIconEdit,
			cdxIconTrash,
			cdxIconClose,
			cdxIconUserGroup,
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
				},
				{
					id: 'actions',
					label: mw.msg('pageapprovals-actions'),
					textAlign: 'center'
				}
			]
		};
	},
	methods: {
		msg(key, ...parameters) {
			return mw.msg(key, ...parameters);
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

				// Format for MultiselectLookup (MenuItemData format)
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

		onCategoriesSelected(username, selectedItems) {
			// This method is no longer used but kept for compatibility
		},
		
		// Handler for MultiselectLookup input chips changes
		onInputChipsChange(username, newChips) {
			this.inputChips[username] = newChips;
			
			// Use nextTick to apply styling after DOM updates
			this.$nextTick(() => {
				this.applyChipStyling(username);
			});
		},
		
		// Apply styling to chips based on whether they're original or new
		applyChipStyling(username) {
			if (!this.editingUser || !username) return;
			
			const originalCategories = this.originalCategories[username] || [];
			
			// Try multiple selectors to find chip elements
			let chipElements = document.querySelectorAll('.cdx-dialog.ext-pageapprovals-manage-approvers__edit-dialog .cdx-input-chip');
			
			if (chipElements.length === 0) {
				chipElements = document.querySelectorAll('.ext-pageapprovals-manage-approvers__edit-dialog .cdx-input-chip');
			}
			
			if (chipElements.length === 0) {
				chipElements = document.querySelectorAll('.cdx-dialog .cdx-input-chip');
			}
			
			if (chipElements.length === 0) {
				chipElements = document.querySelectorAll('.cdx-input-chip');
			}
			
			if (chipElements.length === 0) {
				chipElements = document.querySelectorAll('.cdx-chip-input__item');
			}
			
			if (chipElements.length === 0) {
				return;
			}
			
			chipElements.forEach((chipEl, index) => {
				const chipText = chipEl.textContent?.trim();
				
				if (!chipText) return;
				
				// Remove existing classes
				chipEl.classList.remove('ext-pageapprovals-chip--existing', 'ext-pageapprovals-chip--new');
				
				// Add appropriate class based on whether it's original or new
				if (originalCategories.includes(chipText)) {
					chipEl.classList.add('ext-pageapprovals-chip--existing');
				} else {
					chipEl.classList.add('ext-pageapprovals-chip--new');
				}
			});
		},
		
		// Set up MutationObserver to watch for chip changes and reapply styling
		setupChipObserver(username) {
			this.cleanupChipObserver(); // Clean up any existing observer
			
			// Try different selectors to find the dialog container
			let dialogContainer = document.querySelector('.cdx-dialog.ext-pageapprovals-manage-approvers__edit-dialog');
			if (!dialogContainer) {
				dialogContainer = document.querySelector('.ext-pageapprovals-manage-approvers__edit-dialog');
			}
			if (!dialogContainer) {
				dialogContainer = document.querySelector('.cdx-dialog');
			}
			if (!dialogContainer) {
				dialogContainer = document.querySelector('[role="dialog"]');
			}
			if (!dialogContainer) {
				// Fall back to document body if we can't find a specific container
				dialogContainer = document.body;
			}
			
			this.chipObserver = new MutationObserver((mutations) => {
				let shouldReapply = false;
				
				mutations.forEach((mutation) => {
					// Check if chips were added, removed, or modified
					if (mutation.type === 'childList' || 
						(mutation.type === 'attributes' && mutation.attributeName === 'class')) {
						// Check if this mutation affects chip elements
						const addedChips = Array.from(mutation.addedNodes || [])
							.filter(node => node.nodeType === Node.ELEMENT_NODE)
							.some(node => node.classList?.contains('cdx-input-chip') || 
								  node.querySelector?.('.cdx-input-chip'));
						
						const removedChips = Array.from(mutation.removedNodes || [])
							.filter(node => node.nodeType === Node.ELEMENT_NODE)
							.some(node => node.classList?.contains('cdx-input-chip') || 
								  node.querySelector?.('.cdx-input-chip'));
						
						const targetIsChip = mutation.target?.classList?.contains('cdx-input-chip') ||
										   mutation.target?.closest?.('.cdx-input-chip');
						
						if (addedChips || removedChips || targetIsChip) {
							shouldReapply = true;
						}
					}
				});
				
				if (shouldReapply) {
					// Use a small delay to ensure DOM is fully updated
					setTimeout(() => {
						this.applyChipStyling(username);
					}, 10);
				}
			});
			
			// Start observing
			this.chipObserver.observe(dialogContainer, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class']
			});
		},
		
		// Clean up MutationObserver
		cleanupChipObserver() {
			if (this.chipObserver) {
				this.chipObserver.disconnect();
				this.chipObserver = null;
			}
		},
		
		// Handler for MultiselectLookup selected values changes  
		onSelectedCategoriesChange(username, newSelected) {
			this.selectedCategories[username] = newSelected;
			
			// Update inputChips to match selected values (no className needed now)
			this.inputChips[username] = newSelected.map(value => ({
				value: value,
				label: value
			}));
			
			// Apply styling after DOM updates
			this.$nextTick(() => {
				this.applyChipStyling(username);
			});
		},
		
		hasChanges(username) {
			if (!username || !this.selectedCategories[username] || !this.originalCategories[username]) {
				return false;
			}
			
			const current = [...this.selectedCategories[username]].sort();
			const original = [...this.originalCategories[username]].sort();
			
			return JSON.stringify(current) !== JSON.stringify(original);
		},
		
		async saveAllChanges(username) {
			if (!this.hasChanges(username) || this.savingChanges) {
				this.closeEditDialog();
				return;
			}
			
			this.savingChanges = true;
			
			try {
				const currentCategories = this.originalCategories[username] || [];
				const newCategories = this.selectedCategories[username] || [];
				
				// Determine which categories to add and remove
				const categoriesToAdd = newCategories.filter(cat => !currentCategories.includes(cat));
				const categoriesToRemove = currentCategories.filter(cat => !newCategories.includes(cat));
				
				// Remove categories first
				for (const category of categoriesToRemove) {
					await this.deleteCategoryDirect(username, category);
				}
				
				// Add new categories
				for (const category of categoriesToAdd) {
					await this.addCategoryDirect(username, category);
				}
				
				// Update the original categories to match selected categories
				this.originalCategories[username] = [...newCategories];
				
				// Update the approvers data
				const approver = this.approvers.find(a => a.username === username);
				if (approver) {
					approver.categories = [...newCategories];
				}
				
				// Reclassify all chips as "existing" since they're now saved
				this.inputChips[username] = newCategories.map(value => ({
					value: value,
					label: value
				}));
				
				// Apply styling to show all chips as "existing" after save
				this.$nextTick(() => {
					this.applyChipStyling(username);
				});
				
				mw.notify(mw.msg('pageapprovals-categories-saved'), { type: 'success' });
				this.closeEditDialog();
				
			} catch (error) {
				console.error('Error saving category changes:', error);
				mw.notify(mw.msg('pageapprovals-error-saving-categories'), { type: 'error' });
			} finally {
				this.savingChanges = false;
			}
		},
		
		async addCategoryDirect(username, category) {
			const formData = new FormData();
			formData.append('username', username);
			formData.append('category', category);
			formData.append('action', 'add');
			formData.append('wpEditToken', this.csrfToken);

			const response = await fetch(window.location.href, {
				method: 'POST',
				body: formData
			});

			if (!response.ok) {
				throw new Error(`Failed to add category: ${category}`);
			}
		},
		
		async deleteCategoryDirect(username, category) {
			const formData = new FormData();
			formData.append('username', username);
			formData.append('category', category);
			formData.append('action', 'delete');
			formData.append('wpEditToken', this.csrfToken);

			const response = await fetch(window.location.href, {
				method: 'POST',
				body: formData
			});

			if (!response.ok) {
				throw new Error(`Failed to remove category: ${category}`);
			}
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
			// Determine the username to submit: use selectedUser if it exists, otherwise fall back to newUsername
			const usernameToSubmit = this.selectedUser || this.newUsername;
			
			// Debug logging
			console.log('Debug addApprover:', {
				selectedUser: this.selectedUser,
				newUsername: this.newUsername,
				usernameToSubmit: usernameToSubmit,
				isValid: this.validateUsername(usernameToSubmit)
			});
			
			if (!this.validateUsername(usernameToSubmit) || this.isLoading) {
				if (!this.validateUsername(usernameToSubmit)) {
					console.log('Validation failed for username:', usernameToSubmit);
					mw.notify(mw.msg('pageapprovals-invalid-username'), { type: 'error' });
				}
				return;
			}

			this.isLoading = true;
			try {
				const formData = new FormData();
				formData.append('username', usernameToSubmit);
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
					this.selectedUser = null;
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
			// This method is deprecated but kept for compatibility
			// Category management is now handled by saveAllChanges
		},

		async deleteCategory(username, category) {
			// This method is deprecated but kept for compatibility  
			// Category management is now handled by saveAllChanges
		},

		async refreshApprovers() {
			try {
				// Use the new REST API to get fresh approvers data
				const restClient = new mw.Rest();
				const approvers = await restClient.get('/page-approvals/v0/approvers');
				
				// Get list of current usernames to clean up stale data
				const currentUsernames = new Set((approvers || []).map(approver => approver.username));
				
				// Clean up reactive data for removed approvers
				Object.keys(this.newCategoryInput).forEach(username => {
					if (!currentUsernames.has(username)) {
						delete this.newCategoryInput[username];
						delete this.categorySuggestions[username];
						delete this.inputChips[username];
						delete this.selectedCategories[username];
						delete this.originalCategories[username];
					}
				});
				
				this.approvers = approvers || [];
				
				// Initialize category properties for any new approvers
				this.approvers.forEach(approver => {
					if (!this.newCategoryInput.hasOwnProperty(approver.username)) {
						this.newCategoryInput[approver.username] = '';
						this.categorySuggestions[approver.username] = [];
						this.inputChips[approver.username] = approver.categories.map(cat => ({
							value: cat,
							label: cat
						}));
						this.selectedCategories[approver.username] = [...approver.categories];
						this.originalCategories[approver.username] = [...approver.categories];
					}
				});
			} catch (error) {
				console.error('Error refreshing approvers:', error);
				// Fallback to page reload
				window.location.reload();
			}
		},

		confirmRemoveApprover(username) {
			// Show confirmation dialog
			const confirmed = confirm(
				mw.msg('pageapprovals-confirm-remove-approver', username)
			);
			
			if (confirmed) {
				this.removeApprover(username);
			}
		},

		async removeApprover(username) {
			const loadingKey = `remove-${username}`;
			this.loadingStates[loadingKey] = true;

			try {
				// Use REST API instead of form submission
				const restClient = new mw.Rest();
				await restClient.post('/page-approvals/v0/approvers', {
					action: 'remove-approver',
					username: username
				});

				// Refresh the approvers data from the server
				await this.refreshApprovers();
				
				mw.notify(mw.msg('pageapprovals-approver-removed'), { type: 'success' });
			} catch (error) {
				console.error('Error removing approver:', error);
				mw.notify(mw.msg('pageapprovals-error-removing-approver'), { type: 'error' });
			} finally {
				this.loadingStates[loadingKey] = false;
			}
		},

		// Edit dialog methods
		openEditDialog(username) {
			this.editingUser = username;
			this.editDialogOpen = true;
			// Initialize category data for this user if not already present
			if (!this.newCategoryInput.hasOwnProperty(username)) {
				const approver = this.approvers.find(a => a.username === username);
				const categories = approver ? approver.categories : [];
				
				this.newCategoryInput[username] = '';
				this.categorySuggestions[username] = [];
				this.inputChips[username] = categories.map(cat => ({
					value: cat,
					label: cat
				}));
				this.selectedCategories[username] = [...categories];
				this.originalCategories[username] = [...categories];
			} else {
				// Reset to current state when reopening
				const approver = this.approvers.find(a => a.username === username);
				const categories = approver ? approver.categories : [];
				this.inputChips[username] = categories.map(cat => ({
					value: cat,
					label: cat
				}));
				this.selectedCategories[username] = [...categories];
				this.originalCategories[username] = [...categories];
			}
			
			// Apply chip styling after dialog opens and set up observer
			this.$nextTick(() => {
				this.applyChipStyling(username);
				this.setupChipObserver(username);
			});
		},

		closeEditDialog() {
			this.cleanupChipObserver();
			this.editDialogOpen = false;
			this.editingUser = null;
			this.savingChanges = false;
		},

		onDialogOpenChange(isOpen) {
			if (!isOpen) {
				this.closeEditDialog();
			}
		},

		getApproverCategories(username) {
			const approver = this.approvers.find(a => a.username === username);
			return approver ? approver.categories : [];
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
	},
	beforeUnmount() {
		// Clean up mutation observer when component is destroyed
		this.cleanupChipObserver();
	}
};
</script>