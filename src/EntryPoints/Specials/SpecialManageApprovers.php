<?php

namespace ProfessionalWiki\PageApprovals\EntryPoints\Specials;

use MediaWiki\Html\Html;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use ProfessionalWiki\PageApprovals\Application\Approver;
use ProfessionalWiki\PageApprovals\Application\ApproverRepository;
use ProfessionalWiki\PageApprovals\Application\UseCases\GetApproversWithCategories;

class SpecialManageApprovers extends SpecialPage {

	public function __construct(
		private readonly ApproverRepository $approverRepository,
		private readonly UserFactory $userFactory
	) {
		parent::__construct( 'ManageApprovers', restriction: 'manage-approvers' );
	}

	public function getGroupName(): string {
		return 'users';
	}

	public function isListed(): bool {
		return $this->getUser()->isAllowed( 'manage-approvers' );
	}

	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->checkPermissions();
		$this->checkReadOnly();

		$request = $this->getRequest();
		if ( $request->wasPosted() ) {
			$this->handlePostRequest( $request );
		}

		$approversWithCategories = new GetApproversWithCategories( $this->approverRepository );
		$approversCategories = $approversWithCategories->getApproversWithCategories();

		$this->renderHtml( $this->filterOutApproversWithNoCategories( $approversCategories ) );

		$this->getOutput()->addModules( 'ext.pageApprovals.manageApprovers' );
	}

	private function handlePostRequest( WebRequest $request ): void {
		$action = $request->getText( 'action' );
		$username = $request->getText( 'username' );
		$category = $request->getText( 'category' );

		$user = $this->userFactory->newFromName( $username );
		$userId = $user->getId();

		if ( !$userId ) {
			return;
		}

		$this->processCategoryAction( $action, $category, $userId );
	}

	private function processCategoryAction( string $action, string $category, int $userId ): void {
		$currentCategories = $this->approverRepository->getApproverCategories( $userId );

		switch ( $action ) {
			case 'add-approver':
			case 'add':
				$currentCategories[] = $category;
				break;
			case 'delete':
				$currentCategories = array_filter( $currentCategories, fn( string $cat ) => $cat !== $category );
				break;
			default:
				return;
		}
		$this->approverRepository->setApproverCategories( $userId, $currentCategories );
	}

	/**
	 * @param array<Approver> $approversCategories
	 */
	private function renderHtml( array $approversCategories ): void {
		$approversData = $this->approversToViewModel( $approversCategories );
		
		// Create Vue mount point with data
		$html = Html::element( 'div', [
			'id' => 'manage-approvers-app',
			'data-approvers' => json_encode( $approversData )
		] );
		
		$this->getOutput()->addHTML( $html );
	}

	/**
	 * @param array<Approver> $approvers
	 *
	 * @return array<Approver>
	 */
	private function filterOutApproversWithNoCategories( array $approvers ): array {
		// Show all approvers so they can be managed
		// Previously this method filtered out approvers with no categories,
		// but that created a catch-22 where you couldn't assign categories 
		// to approvers you couldn't see
		return $approvers;
	}

	/**
	 * @param array<Approver> $approvers
	 *
	 * @return array<array<string, mixed>>
	 */
	private function approversToViewModel( array $approvers ): array {
		return array_values(
			array_map(
				fn( Approver $approver ) => $this->approverToViewModel( $approver ),
				$approvers
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function approverToViewModel( Approver $approver ): array {
		return [
			'username' => $approver->username,
			'userId' => $approver->userId,
			'categories' => $approver->categories
		];
	}

}
