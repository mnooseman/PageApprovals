/**
 * Set the approval status of a page by sending a request to the API
 *
 * @param {boolean} approve
 * @return {Promise<Object>}
 */
function setPageApprovalStatus( approve ) {
	const restClient = new mw.Rest();
	const revisionId = mw.config.get( 'wgRevisionId' );
	const endpoint = `/page-approvals/v0/revision/${ revisionId }/${ approve ? 'approve' : 'unapprove' }`;

	return restClient.post( endpoint )
		.then( ( data ) => data )
		.catch( ( error, data ) => {
			mw.log.error( `API request failed: ${ error }` );
			
			// Safely extract error message
			let errorMessage = `API request failed: ${ error }`;
			
			if ( data && data.xhr && data.xhr.responseJSON && data.xhr.responseJSON.message ) {
				errorMessage = data.xhr.responseJSON.message;
			} else if ( data && data.xhr && data.xhr.status ) {
				errorMessage = `API request failed: HTTP ${ data.xhr.status }`;
			}
			
			mw.notify( errorMessage, { type: 'error' } );
			throw new Error( errorMessage );
		} );
}

module.exports = {
	setPageApprovalStatus
};
