<?php

namespace ProfessionalWiki\PageApprovals\Tests\Integration;

use ProfessionalWiki\PageApprovals\Application\ApproverRepository;
use ProfessionalWiki\PageApprovals\Application\PendingApprovalRetriever;
use ProfessionalWiki\PageApprovals\EntryPoints\Specials\SpecialPendingApprovals;
use ProfessionalWiki\PageApprovals\PageApprovals;
use SpecialPageTestBase;

/**
 * @group Database
 * @covers \ProfessionalWiki\PageApprovals\EntryPoints\Specials\SpecialPendingApprovals
 */
class SpecialPendingApprovalsTest extends SpecialPageTestBase {

	private ApproverRepository $approverRepository;

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'approver_config';
		$this->tablesUsed[] = 'approved_html';
		$this->approverRepository = PageApprovals::getInstance()->getApproverRepository();
	}

	protected function newSpecialPage(): SpecialPendingApprovals {
		return PageApprovals::newSpecialPendingApprovals();
	}

	public function testAnonymousUserSeesLoginMessage(): void {
		// Create an anonymous user by not passing any user
		[ $output ] = $this->executeSpecialPage( '', null, 'qqx', null );
		$this->assertStringContainsString(
			'pageapprovals-not-logged-in',
			$output,
			'Anonymous users should see login message'
		);
	}

	private function viewPage( $user = null ): string {
		[ $output ] = $this->executeSpecialPage(
			'',
			null,
			'qqx',
			$user ?? $this->getTestUser()->getUser()
		);
		return $output;
	}

	public function testUserWithNoCategoriesSeesNoApprovalsMessage(): void {
		$output = $this->viewPage( user: $this->getTestUser()->getUser() );
		$this->assertStringContainsString(
			'pageapprovals-no-categories',
			$output,
			'Users with no categories should see appropriate message'
		);
	}

	public function testUserWithCategoriesButNoPendingApprovalsSeesNoApprovalsMessage(): void {
		$testUser = $this->getTestUser()->getUser();
		$this->approverRepository->setApproverCategories( $testUser->getId(), [ 'TestCategory' ] );

		$output = $this->viewPage( user: $testUser );
		$this->assertStringContainsString(
			'pageapprovals-no-pending-approvals',
			$output,
			'Users with categories but no pending approvals should see appropriate message'
		);
	}

	public function testUserWithPendingApprovalsSeesVueMountPoint(): void {
		$testUser = $this->getTestUser()->getUser();
		$this->approverRepository->setApproverCategories( $testUser->getId(), [ 'TestCategory' ] );

		// Create a test page that would appear in pending approvals
		$testPage = $this->getExistingTestPage( 'TestPage' );
		
		$output = $this->viewPage( user: $testUser );
		
		// With the Vue implementation, we should see the mount point
		$this->assertStringContainsString(
			'id="pending-approvals-app"',
			$output,
			'Expected HTML output with Vue mount point'
		);
		$this->assertStringContainsString(
			'data-pending-approvals',
			$output,
			'Expected data-pending-approvals attribute for Vue initialization'
		);
		$this->assertStringContainsString(
			'data-headers',
			$output,
			'Expected data-headers attribute for Vue initialization'
		);
	}

	public function testPendingApprovalCountMessageIsShown(): void {
		$testUser = $this->getTestUser()->getUser();
		$this->approverRepository->setApproverCategories( $testUser->getId(), [ 'TestCategory' ] );

		// Create a test page that would appear in pending approvals
		$testPage = $this->getExistingTestPage( 'TestPage' );
		
		$output = $this->viewPage( user: $testUser );
		
		// Should show the count message
		$this->assertStringContainsString(
			'pageapprovals-pending-approval-count',
			$output,
			'Should show pending approval count message'
		);
	}
}
