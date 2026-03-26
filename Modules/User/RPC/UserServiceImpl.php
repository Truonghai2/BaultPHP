<?php

namespace Modules\User\RPC;

use Core\RPC\Grpc\Server\GrpcService;
use Core\CQRS\{CommandBus, QueryBus};
use Modules\User\Application\Commands\CreateUserCommand;
use Modules\User\Application\Queries\{GetUserQuery, ListUsersQuery};

/**
 * gRPC UserService Implementation.
 * 
 * Integrates with CQRS CommandBus and QueryBus.
 * Follows the same pattern as REST API controllers.
 */
class UserServiceImpl extends GrpcService
{
    /**
     * Get user by ID.
     * 
     * RPC: GetUser(GetUserRequest) returns (UserResponse)
     */
    public function getUser($request)
    {
        try {
            // Create query from gRPC request
            $query = new GetUserQuery(
                userId: $request->getUserId()
            );

            // Execute via QueryBus
            $result = $this->executeQuery($query);

            // Convert Result to gRPC response
            return $this->resultToResponse($result, function($user) {
                return $this->userToResponse($user);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Create a new user.
     * 
     * RPC: CreateUser(CreateUserRequest) returns (UserResponse)
     */
    public function createUser($request)
    {
        try {
            // Create command from gRPC request
            $command = new CreateUserCommand(
                name: $request->getName(),
                email: $request->getEmail(),
                password: $request->getPassword()
            );

            // Execute via CommandBus
            $result = $this->executeCommand($command);

            // Convert Result to gRPC response
            return $this->resultToResponse($result, function($user) {
                return $this->userToResponse($user);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Update user.
     * 
     * RPC: UpdateUser(UpdateUserRequest) returns (UserResponse)
     */
    public function updateUser($request)
    {
        try {
            // Create command from gRPC request
            $command = new UpdateUserCommand(
                userId: $request->getUserId(),
                name: $request->getName(),
                email: $request->getEmail()
            );

            // Execute via CommandBus
            $result = $this->executeCommand($command);

            // Convert Result to gRPC response
            return $this->resultToResponse($result, function($user) {
                return $this->userToResponse($user);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * List users.
     * 
     * RPC: ListUsers(ListUsersRequest) returns (ListUsersResponse)
     */
    public function listUsers($request)
    {
        try {
            // Create query from gRPC request
            $query = new ListUsersQuery(
                page: $request->getPage() ?: 1,
                perPage: $request->getPerPage() ?: 20,
                search: $request->getSearch() ?: null
            );

            // Execute via QueryBus
            $result = $this->executeQuery($query);

            // Convert Result to gRPC response
            return $this->resultToResponse($result, function($data) {
                return $this->usersToListResponse($data);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Convert user model to gRPC UserResponse.
     */
    private function userToResponse($user)
    {
        $response = new \User\UserResponse();
        $response->setId($user['id']);
        $response->setName($user['name']);
        $response->setEmail($user['email']);
        $response->setCreatedAt($user['created_at']);
        $response->setUpdatedAt($user['updated_at']);
        
        return $response;
    }

    /**
     * Convert users list to gRPC ListUsersResponse.
     */
    private function usersToListResponse($data)
    {
        $response = new \User\ListUsersResponse();
        
        foreach ($data['users'] as $user) {
            $response->addUsers($this->userToResponse($user));
        }
        
        $response->setTotal($data['total']);
        $response->setPage($data['page']);
        $response->setPerPage($data['per_page']);
        
        return $response;
    }
}
