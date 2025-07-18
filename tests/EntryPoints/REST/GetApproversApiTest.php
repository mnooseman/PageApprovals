<?php

namespace ProfessionalWiki\PageApprovals\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\PageApprovals\Adapters\InMemoryApproverRepository;
use ProfessionalWiki\PageApprovals\EntryPoints\REST\GetApproversApi;
use ProfessionalWiki\PageApprovals\Tests\PageApprovalsIntegrationTest;

/**
 * @covers \ProfessionalWiki\PageApprovals\EntryPoints\REST\GetApproversApi
 * @group Database
 */
class GetApproversApiTest extends PageApprovalsIntegrationTest {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	private InMemoryApproverRepository $approverRepository;

	protected function setUp(): void {
		parent::setUp();
		$this->approverRepository = new InMemoryApproverRepository();
	}

	public function testGetApproversRequiresPermission(): void {
		$response = $this->executeHandler(
			$this->newGetApproversApi(),
			new RequestData( [ 'method' => 'GET' ] ),
			authority: $this->mockRegisteredAuthorityWithoutPermissions( [ 'manage-approvers' ] )
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}

	public function testGetApproversReturnsFilteredList(): void {
		// Add approvers with categories
		$this->approverRepository->setApproverCategories( 1, [ 'Category1', 'Category2' ] );
		$this->approverRepository->setApproverCategories( 2, [ 'Category3' ] );
		// Add approver with no categories (should be filtered out)
		$this->approverRepository->setApproverCategories( 3, [] );

		$response = $this->executeHandler(
			$this->newGetApproversApi(),
			new RequestData( [ 'method' => 'GET' ] ),
			authority: $this->mockRegisteredUltimateAuthority()
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$responseData = json_decode( $response->getBody()->getContents(), true );
		
		// Should only return approvers with categories
		$this->assertCount( 2, $responseData );
		
		// Check structure
		$this->assertArrayHasKey( 'userId', $responseData[0] );
		$this->assertArrayHasKey( 'categories', $responseData[0] );
	}

	private function newGetApproversApi(): GetApproversApi {
		return new GetApproversApi( $this->approverRepository );
	}

}
