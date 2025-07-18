<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\PageApprovals\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\PageApprovals\Application\ApproverRepository;
use ProfessionalWiki\PageApprovals\Application\UseCases\GetApproversWithCategories;

class GetApproversApi extends SimpleHandler {

	public function __construct(
		private ApproverRepository $approverRepository
	) {
	}

	public function run(): Response {
		if ( !$this->getAuthority()->isAllowed( 'manage-approvers' ) ) {
			return $this->getResponseFactory()->createHttpError(
				403,
				[ 'message' => 'Permission denied' ]
			);
		}

		$useCase = new GetApproversWithCategories( $this->approverRepository );
		$approvers = $useCase->getApproversWithCategories();

		// Convert Approver objects to arrays for JSON serialization
		$approversData = array_map(
			fn( \ProfessionalWiki\PageApprovals\Application\Approver $approver ) => [
				'username' => $approver->username,
				'userId' => $approver->userId,
				'categories' => $approver->categories
			],
			$approvers
		);

		// Return all approvers (including those with no categories) to match Special page behavior
		// This allows users to manage approvers even if they currently have no categories assigned
		return $this->getResponseFactory()->createJson( array_values( $approversData ) );
	}

}
