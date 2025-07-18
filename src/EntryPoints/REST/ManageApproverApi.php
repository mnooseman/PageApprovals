<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\PageApprovals\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use ProfessionalWiki\PageApprovals\Application\ApproverRepository;
use Wikimedia\ParamValidator\ParamValidator;

class ManageApproverApi extends SimpleHandler {

	public function __construct(
		private ApproverRepository $approverRepository,
		private UserFactory $userFactory
	) {
	}

	public function run(): Response {
		if ( !$this->getAuthority()->isAllowed( 'manage-approvers' ) ) {
			return $this->getResponseFactory()->createHttpError(
				403,
				[ 'message' => 'Permission denied' ]
			);
		}

		$body = $this->getValidatedBody();
		$action = $body['action'] ?? '';
		$username = $body['username'] ?? '';

		$user = $this->userFactory->newFromName( $username );
		if ( !$user || !$user->getId() ) {
			return $this->getResponseFactory()->createHttpError(
				400,
				[ 'message' => 'Invalid username' ]
			);
		}

		$userId = $user->getId();

		switch ( $action ) {
			case 'add-approver':
				return $this->addApprover( $userId );
			case 'add':
				$category = $body['category'] ?? '';
				return $this->addCategory( $userId, $category );
			case 'delete':
				$category = $body['category'] ?? '';
				return $this->deleteCategory( $userId, $category );
			default:
				return $this->getResponseFactory()->createHttpError(
					400,
					[ 'message' => 'Invalid action' ]
				);
		}
	}

	private function addApprover( int $userId ): Response {
		// Adding an approver just means ensuring they exist in the system
		// The actual logic is handled by adding categories
		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	private function addCategory( int $userId, string $category ): Response {
		if ( trim( $category ) === '' ) {
			return $this->getResponseFactory()->createHttpError(
				400,
				[ 'message' => 'Category cannot be empty' ]
			);
		}

		$existingCategories = $this->approverRepository->getApproverCategories( $userId );
		if ( !in_array( $category, $existingCategories ) ) {
			$existingCategories[] = $category;
			$this->approverRepository->setApproverCategories( $userId, $existingCategories );
		}

		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	private function deleteCategory( int $userId, string $category ): Response {
		$existingCategories = $this->approverRepository->getApproverCategories( $userId );
		$updatedCategories = array_filter( 
			$existingCategories, 
			fn( $cat ) => $cat !== $category 
		);
		
		$this->approverRepository->setApproverCategories( $userId, array_values( $updatedCategories ) );

		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	public function getBodyParamSettings(): array {
		return [
			'action' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'username' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'category' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

}
