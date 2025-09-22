<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAdminRequest;
use App\Http\ResponseFactory;
use Core\Events\EventDispatcherInterface;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Hash;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Core\Validation\ErrorBag;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;

class SetupController extends Controller
{
    protected ResponseFactory $responseFactory;
    protected EventDispatcherInterface $dispatcher;

    public function __construct(ResponseFactory $responseFactory, EventDispatcherInterface $dispatcher)
    {
        $this->responseFactory = $responseFactory;
        $this->dispatcher = $dispatcher;
    }

    #[Route('/setup/create-admin', method: 'GET', group: 'web')]
    public function showCreateAdminForm(): ResponseInterface
    {
        if (User::query()->exists()) {
            return redirect('/');
        }

        $errors = app('session')->get('errors', new ErrorBag());

        return $this->responseFactory->make(view('setup.create-admin', compact('errors')));
    }

    #[Route('/setup/create-admin', method: 'POST', group: 'web')]
    public function processCreateAdmin(CreateAdminRequest $request): ResponseInterface
    {
        if (User::query()->exists()) {
            return redirect('/');
        }

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $this->dispatcher->dispatch(new UserWasCreated($user));

        $superAdminRole = Role::where('name', 'super-admin')->first();

        if (!$superAdminRole) {
            throw new \RuntimeException('The "super-admin" role does not exist. Please run the database seeders.');
        }

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $superAdminRole->id,
            'context_id' => 1,
        ]);

        return redirect('/');
    }
}
