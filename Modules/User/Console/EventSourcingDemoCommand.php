<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Application\Services\UserAggregateService;

/**
 * EventSourcingDemoCommand
 *
 * Demonstrates Event Sourcing usage with User aggregate.
 */
class EventSourcingDemoCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'event-sourcing:demo 
                {action : Action (register|change-email|verify|suspend|show)}
                {--user-id= : User ID (for existing users)}
                {--email= : Email address}
                {--name= : User name}
                {--reason= : Suspension reason}';
    }

    public function description(): string
    {
        return 'Demonstrate Event Sourcing with User aggregate';
    }

    public function handle(): int
    {
        /** @var UserAggregateService $service */
        $service = $this->app->make(UserAggregateService::class);

        $action = $this->argument('action');

        try {
            switch ($action) {
                case 'register':
                    return $this->registerUser($service);
                case 'change-email':
                    return $this->changeEmail($service);
                case 'verify':
                    return $this->verifyEmail($service);
                case 'suspend':
                    return $this->suspendUser($service);
                case 'show':
                    return $this->showUser($service);
                default:
                    $this->io->error("Unknown action: {$action}");
                    return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function registerUser(UserAggregateService $service): int
    {
        $email = $this->option('email') ?? $this->io->ask('Email:', 'user@example.com');
        $name = $this->option('name') ?? $this->io->ask('Name:', 'John Doe');

        $this->io->writeln('<info>Registering user via Event Sourcing...</info>');

        $userId = $service->registerUser($email, $name);

        $this->io->success('User registered!');
        $this->io->table(
            ['Field', 'Value'],
            [
                ['User ID', $userId],
                ['Email', $email],
                ['Name', $name],
                ['Status', 'pending'],
            ],
        );

        $this->io->newLine();
        $this->io->writeln('<comment>✓ Events saved to event store</comment>');
        $this->io->writeln("<comment>✓ Use this ID for other actions: --user-id={$userId}</comment>");

        return self::SUCCESS;
    }

    private function changeEmail(UserAggregateService $service): int
    {
        $userId = $this->option('user-id');
        if (!$userId) {
            $this->io->error('--user-id is required');
            return self::FAILURE;
        }

        $newEmail = $this->option('email') ?? $this->io->ask('New Email:');

        $this->io->writeln('<info>Changing email via Event Sourcing...</info>');

        $service->changeUserEmail($userId, $newEmail);

        $this->io->success('Email changed!');
        $this->showUser($service);

        return self::SUCCESS;
    }

    private function verifyEmail(UserAggregateService $service): int
    {
        $userId = $this->option('user-id');
        if (!$userId) {
            $this->io->error('--user-id is required');
            return self::FAILURE;
        }

        $this->io->writeln('<info>Verifying email via Event Sourcing...</info>');

        $service->verifyUserEmail($userId);

        $this->io->success('Email verified!');
        $this->showUser($service);

        return self::SUCCESS;
    }

    private function suspendUser(UserAggregateService $service): int
    {
        $userId = $this->option('user-id');
        if (!$userId) {
            $this->io->error('--user-id is required');
            return self::FAILURE;
        }

        $reason = $this->option('reason') ?? $this->io->ask('Suspension reason:', 'Policy violation');

        $this->io->writeln('<info>Suspending user via Event Sourcing...</info>');

        $service->suspendUser($userId, $reason);

        $this->io->success('User suspended!');
        $this->showUser($service);

        return self::SUCCESS;
    }

    private function showUser(UserAggregateService $service): int
    {
        $userId = $this->option('user-id');
        if (!$userId) {
            $this->io->error('--user-id is required');
            return self::FAILURE;
        }

        $state = $service->getUserState($userId);

        if (!$state) {
            $this->io->warning("User {$userId} not found in event store");
            return self::FAILURE;
        }

        $this->io->writeln('<info>User State (reconstituted from events):</info>');
        $this->io->table(
            ['Field', 'Value'],
            [
                ['User ID', $state['id']],
                ['Email', $state['email']],
                ['Name', $state['name']],
                ['Status', $state['status']],
                ['Is Active', $state['is_active'] ? 'Yes' : 'No'],
                ['Is Verified', $state['is_verified'] ? 'Yes' : 'No'],
                ['Version', $state['version']],
            ],
        );

        return self::SUCCESS;
    }
}
