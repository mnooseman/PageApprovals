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

		// Filter out approvers with no categories (as done in the Special Page)
		$filteredApprovers = array_filter(
			$approvers,
			fn( array $approver ) => $approver['categories'] !== []
		);

		return $this->getResponseFactory()->createJson( array_values( $filteredApprovers ) );
	}

}
