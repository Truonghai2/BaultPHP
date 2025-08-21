<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAdminRequest;
use Core\Events\EventDispatcherInterface;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Http\ResponseFactory;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller to handle the initial application setup, specifically
 * creating the first administrator account.
 */
class SetupController extends Controller
{
    /**
     * The response factory instance.
     */
    protected ResponseFactory $responseFactory;
    /**
     * The event dispatcher instance.
     */
    protected EventDispatcherInterface $dispatcher;

    public function __construct(ResponseFactory $responseFactory, EventDispatcherInterface $dispatcher)
    {
        $this->responseFactory = $responseFactory;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Display the form to create the first admin account.
     * If an admin already exists, it redirects to the home page.
     */
    #[Route('/setup/create-admin', method: 'GET', group: 'web')]
    public function showCreateAdminForm(): ResponseInterface
    {
        // This check prevents accessing the setup page after completion.
        if (User::query()->exists()) {
            return $this->responseFactory->redirect('/');
        }

        // Render the setup view using the framework's view engine.
        return $this->responseFactory->make(view('setup.create-admin'));
    }

    /**
     * Process the creation of the first admin account from the submitted form.
     *
     * @param CreateAdminRequest $request The validated form request.
     * @return ResponseInterface
     */
    #[Route('/setup/create-admin', method: 'POST', group: 'web')]
    public function processCreateAdmin(CreateAdminRequest $request): ResponseInterface
    {
        // Double-check to prevent race conditions or direct POST requests after setup.
        if (User::query()->exists()) {
            return $this->responseFactory->redirect('/');
        }

        // The validation is now handled automatically by the CreateAdminRequest.
        // We can get the validated data directly.
        $validated = $request->validated();

        // Create the first user.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => password_hash($validated['password'], PASSWORD_DEFAULT),
        ]);

        // Dispatch an event to notify other parts of the system.
        $this->dispatcher->dispatch(new UserWasCreated($user));

        // IMPORTANT: Assign the Super Admin role to this user.
        // This is more robust than using a hardcoded ID.
        // It assumes a 'super-admin' role exists, which should be created by a seeder.
        $superAdminRole = Role::where('name', 'super-admin')->first();

        if (!$superAdminRole) {
            // In a real application, you might want to log this critical error.
            // For now, we'll throw an exception to halt the process.
            throw new \RuntimeException('The "super-admin" role does not exist. Please run the database seeders.');
        }

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $superAdminRole->id,
            'context_id' => 1, // The root/system context ID.
        ]);

        // Redirect to the homepage after successful creation.
        return $this->responseFactory->redirect('/');
    }
}
