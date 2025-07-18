<template>
	<cdx-menu-button
		v-model:selected="selection"
		:menu-items="menuItems"
		@update:selected="onSelect"
	>
		<status-display
			:page-approved="pageApproved"
			:approval-timestamp="approvalTimestamp"
			:approver="approver"
		></status-display>
		<cdx-icon
			:icon="cdxIconExpand"
			size="x-small"
		></cdx-icon>
	</cdx-menu-button>
</template>

<script>
const { defineComponent, ref, computed, watch, inject } = require( 'vue' );
const { CdxMenuButton, CdxIcon } = require( '../../codex.js' );
const { cdxIconCheck, cdxIconClose, cdxIconExpand } = require( '../icons.json' );
const { setPageApprovalStatus } = require( '../utils/api.js' );
const StatusDisplay = require( './StatusDisplay.vue' );

module.exports = defineComponent( {
	name: 'ApprovalButton',
	components: { CdxMenuButton, CdxIcon, StatusDisplay },
	setup() {
		const selection = ref( null );
		const props = inject( 'props' );

		const pageApproved = ref( props.pageApproved );
		const approvalTimestamp = ref( props.approvalTimestamp );
		const approver = ref( props.approver );

		const rootEl = document.getElementById( 'ext-pageapprovals' );

		const menuItems = computed( () => {
			if ( pageApproved.value ) {
				return [ {
					label: mw.msg( 'pageapprovals-unapprove-button' ),
					icon: cdxIconClose,
					value: 'unapprove'
				} ];
			} else {
				return [ {
					label: mw.msg( 'pageapprovals-approve-button' ),
					icon: cdxIconCheck,
					value: 'approve'
				} ];
			}
		} );

		watch( pageApproved, ( newValue ) => {
			rootEl.dataset.mwPageApproved = newValue ? 'true' : 'false';
		} );
		watch( approvalTimestamp, ( newValue ) => {
			rootEl.dataset.mwApprovalTimestamp = newValue;
		} );
		watch( approver, ( newValue ) => {
			rootEl.dataset.mwApprover = newValue;
		} );

		// eslint-disable-next-line es-x/no-async-functions
		async function onSelect( value ) {
			try {
				if ( value === 'approve' ) {
					const response = await setPageApprovalStatus( true );
					if ( response && response.approvalTimestamp ) {
						// Use the actual approval timestamp from the API response
						approvalTimestamp.value = response.approvalTimestamp;
					} else {
						// Fallback to current timestamp if API doesn't return one
						approvalTimestamp.value = new Date().toISOString();
					}
					if ( response && response.approver ) {
						approver.value = response.approver;
					} else {
						approver.value = mw.config.get( 'wgUserName' );
					}
					pageApproved.value = true;
					mw.notify( mw.msg( 'pageapprovals-approved' ), { type: 'success' } );
				} else if ( value === 'unapprove' ) {
					const response = await setPageApprovalStatus( false );
					approvalTimestamp.value = null;
					approver.value = null;
					pageApproved.value = false;
					mw.notify( mw.msg( 'pageapprovals-unapproved' ), { type: 'success' } );
				}
			} catch ( error ) {
				// Error notification is already handled in the API utility
				mw.log.error( 'Approval operation failed:', error );
			}
		}

		return {
			selection,
			menuItems,
			pageApproved,
			approvalTimestamp,
			approver,
			onSelect,
			cdxIconExpand
		};
	}
} );
</script>

<style lang="less">
.ext-pageapprovals {
	.cdx-toggle-button .cdx-tooltip {
		white-space: normal; // Fix incorrect wrapping in tooltip inheriting from the menu button
	}
}
</style>
