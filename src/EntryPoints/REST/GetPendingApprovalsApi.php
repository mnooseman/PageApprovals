<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\PageApprovals\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\PageApprovals\Application\ApproverRepository;
use ProfessionalWiki\PageApprovals\Application\PendingApproval;
use ProfessionalWiki\PageApprovals\Application\PendingApprovalRetriever;

class GetPendingApprovalsApi extends SimpleHandler {

	public function __construct(
		private PendingApprovalRetriever $pendingApprovalRetriever,
		private ApproverRepository $approverRepository
	) {
	}

	public function run(): Response {
		$user = $this->getAuthority()->getUser();
		
		if ( $user->isAnon() ) {
			return $this->getResponseFactory()->createJson( [] );
		}

		$categories = $this->approverRepository->getApproverCategories( $user->getId() );

		if ( $categories === [] ) {
			return $this->getResponseFactory()->createJson( [] );
		}

		$pendingApprovals = $this->pendingApprovalRetriever->getPendingApprovalsForApprover( $user->getId() );

		$viewModel = array_map(
			fn( PendingApproval $pendingApproval ) => $this->pendingApprovalToViewModel( $pendingApproval ),
			$pendingApprovals
		);

		return $this->getResponseFactory()->createJson( $viewModel );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function pendingApprovalToViewModel( PendingApproval $pendingApproval ): array {
		return [
			'pageId' => $pendingApproval->title->getArticleID(),
			'pageTitle' => $pendingApproval->title->getPrefixedText(),
			'categories' => implode( ', ', $pendingApproval->categories ),
			'lastEditTimestamp' => $pendingApproval->lastEditTimestamp,
			'lastEditTimeFormatted' => wfTimestamp( TS_ISO_8601, $pendingApproval->lastEditTimestamp ),
			'lastEditUserName' => $pendingApproval->lastEditUserName
		];
	}

}
