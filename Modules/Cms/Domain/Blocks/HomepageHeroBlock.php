<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Homepage Hero Block
 * 
 * Renders the complete homepage hero section with:
 * - Logo & Badge
 * - Title & Description
 * - CTA Buttons
 * - Code Preview Panel
 */
class HomepageHeroBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'homepage-hero';
    }

    public function getTitle(): string
    {
        return 'Homepage Hero Section';
    }

    public function getDescription(): string
    {
        return 'Complete homepage hero with logo, title, description, buttons, and code preview';
    }

    public function getCategory(): string
    {
        return 'Homepage';
    }

    public function getIcon(): string
    {
        return '🎯';
    }

    public function getDefaultConfig(): array
    {
        return [
            // Logo
            'logo_url' => '/images/logo/BaultPHP.png',
            
            // Badge
            'show_badge' => true,
            'badge_text' => 'Just Released',
            'badge_link_text' => 'v1.0.0',
            'badge_link_url' => '#',
            
            // Hero Content
            'title' => 'Build Fast, Scale Easy with BaultPHP',
            'description' => 'A modern, high-performance PHP framework designed for building scalable applications. Powered by Swoole, DDD architecture, and cutting-edge features.',
            
            // CTA Buttons
            'primary_button_text' => 'Quick Start',
            'primary_button_url' => '#',
            'secondary_button_text' => 'View on GitHub',
            'secondary_button_url' => 'https://github.com/Truonghai2/BaultPHP',
            
            // Code Preview
            'show_code_preview' => true,
            'code_file_name' => 'CreateUser.php',
            'code_content' => 'namespace App\Modules\User\Domain\UseCases;

class CreateUser implements CommandHandler
{
    public function handle(CreateUserCommand $command): User
    {
        // Validate input using value objects
        $email = new Email($command->email);
        $password = new Password($command->password);

        // Create user entity
        $user = new User(
            UserId::generate(),
            $email,
            $password
        );

        // Save and dispatch events
        $this->repository->save($user);
        $this->eventDispatcher->dispatch(
            new UserCreated($user)
        );

        return $user;
    }
}',
            'code_language' => 'php',
            'code_label' => 'DDD Example',
            'code_badge' => 'Domain Logic',
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        return $this->renderView('cms::blocks.homepage-hero', $config);
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheLifetime(): int
    {
        return 3600; // 1 hour
    }
}

