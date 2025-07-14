<?php

namespace ProfessionalWiki\PageApprovals\EntryPoints\Specials;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\PageApprovals\Application\ApproverRepository;
use ProfessionalWiki\PageApprovals\Application\PendingApproval;
use ProfessionalWiki\PageApprovals\Application\PendingApprovalRetriever;
use MediaWiki\Html\TemplateParser;

class SpecialPendingApprovals extends SpecialPage {

	public function __construct(
		private readonly ApproverRepository $approverRepository,
		private readonly PendingApprovalRetriever $pendingApprovalRetriever,
		private readonly LinkRenderer $linkRenderer
	) {
		parent::__construct( 'PendingApprovals' );
	}

	public function getGroupName(): string {
		return 'changes';
	}

	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->checkPermissions();
		$this->checkReadOnly();

		if ( $this->getUser()->isAnon() ) {
			$this->getOutput()->addWikiMsg( 'pageapprovals-not-logged-in' );
			return;
		}

		$categories = $this->approverRepository->getApproverCategories( $this->getUser()->getId() );

		if ( $categories === [] ) {
			$this->getOutput()->addWikiMsg( 'pageapprovals-no-categories' );
			return;
		}

		$pendingApprovals = $this->pendingApprovalRetriever->getPendingApprovalsForApprover( $this->getUser()->getId() );

		if ( $pendingApprovals === [] ) {
			$this->getOutput()->addWikiMsg( 'pageapprovals-no-pending-approvals' );
			return;
		}

		$this->showSummary( $pendingApprovals );

		$this->renderPendingApprovalsTable( $pendingApprovals );
	}

	/**
	 * @param PendingApproval[] $pendingApprovals
	 */
	private function showSummary( array $pendingApprovals ): void {
		$this->getOutput()->addWikiMsg( 'pageapprovals-pending-approval-count', count( $pendingApprovals ) );
	}

	/**
	 * @param array<PendingApproval> $pendingApprovals
	 */
	private function renderPendingApprovalsTable( array $pendingApprovals ): void {
		$templateParser = new TemplateParser( $this->getTemplateDirectory() );
		
		$html = $templateParser->processTemplate(
			'PendingApprovals',
			[
				'pendingApprovals' => $this->pendingApprovalsToViewModel( $pendingApprovals ),
				'headers' => $this->getTableHeaders()
			]
		);
		
		$this->getOutput()->addHTML( $html );
	}

	/**
	 * Get the directory where templates are stored
	 */
	private function getTemplateDirectory(): string {
		return __DIR__ . '/../../../templates';
	}

		/**
	 * @return array<string, string>
	 */
	private function getTableHeaders(): array {
		return [
			'page' => $this->msg( 'pageapprovals-pending-approvals-page' )->escaped(),
			'categories' => $this->msg( 'pageapprovals-pending-approvals-categories' )->escaped(),
			'lastEditTime' => $this->msg( 'pageapprovals-pending-approvals-last-edit-time' )->escaped(),
			'lastEditBy' => $this->msg( 'pageapprovals-pending-approvals-last-edit-by' )->escaped()
		];
	}

	/**
	 * @param array<PendingApproval> $pendingApprovals
	 * @return array<array<string, mixed>>
	 */
	private function pendingApprovalsToViewModel( array $pendingApprovals ): array {
		return array_map(
			fn( PendingApproval $pendingApproval ) => $this->pendingApprovalToViewModel( $pendingApproval ),
			$pendingApprovals
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function pendingApprovalToViewModel( PendingApproval $pendingApproval ): array {
		return [
			'pageLink' => $this->linkRenderer->makeLink( $pendingApproval->title ),
			'categories' => implode( ', ', $pendingApproval->categories ),
			'lastEditTimestamp' => $pendingApproval->lastEditTimestamp,
			'lastEditTimeFormatted' => $this->getLanguage()->userTimeAndDate( 
				$pendingApproval->lastEditTimestamp, 
				$this->getUser() 
			),
			'lastEditUserName' => $pendingApproval->lastEditUserName
		];
	}
}
