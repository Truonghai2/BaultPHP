<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Infrastructure\Models\PageTemplate;

class PageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding page templates...');

        $templates = $this->getDefaultTemplates();

        foreach ($templates as $template) {
            PageTemplate::firstOrCreate(
                ['name' => $template['name']],
                $template,
            );

            $this->command->line("  ✓ Template '{$template['name']}' created/updated");
        }

        $this->command->info('✅ Page templates seeded successfully!');
    }

    /**
     * Get default templates
     */
    private function getDefaultTemplates(): array
    {
        return [
            // 1. Homepage Template
            [
                'name' => 'Homepage',
                'description' => 'Full-featured homepage with hero, features, and statistics',
                'category' => 'marketing',
                'thumbnail' => '/assets/images/templates/homepage.png',
                'blocks_config' => [
                    [
                        'block_type_name' => 'homepage-hero',
                        'region' => 'hero',
                        'sort_order' => 0,
                    ],
                    [
                        'block_type_name' => 'homepage-features',
                        'region' => 'content',
                        'sort_order' => 1,
                    ],
                    [
                        'block_type_name' => 'homepage-stats',
                        'region' => 'content',
                        'sort_order' => 2,
                    ],
                ],
                'default_seo' => [
                    'meta_title' => 'Welcome to Our Site',
                    'meta_description' => 'Discover our amazing products and services',
                    'og_type' => 'website',
                ],
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 0,
            ],

            // 2. About Page Template
            [
                'name' => 'About Us',
                'description' => 'About page with text content and team section',
                'category' => 'content',
                'thumbnail' => '/assets/images/templates/about.png',
                'blocks_config' => [
                    [
                        'block_type_name' => 'text-block',
                        'region' => 'content',
                        'sort_order' => 0,
                    ],
                    [
                        'block_type_name' => 'team',
                        'region' => 'content',
                        'sort_order' => 1,
                    ],
                ],
                'default_seo' => [
                    'meta_title' => 'About Us',
                    'meta_description' => 'Learn more about our company and team',
                ],
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 1,
            ],

            // 3. Services Page Template
            [
                'name' => 'Services',
                'description' => 'Services page with features grid',
                'category' => 'content',
                'thumbnail' => '/assets/images/templates/services.png',
                'blocks_config' => [
                    [
                        'block_type_name' => 'text-block',
                        'region' => 'hero',
                        'sort_order' => 0,
                    ],
                    [
                        'block_type_name' => 'homepage-features',
                        'region' => 'content',
                        'sort_order' => 1,
                    ],
                ],
                'default_seo' => [
                    'meta_title' => 'Our Services',
                    'meta_description' => 'Explore our range of services',
                ],
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 2,
            ],

            // 4. Contact Page Template
            [
                'name' => 'Contact',
                'description' => 'Contact page with text and HTML blocks',
                'category' => 'content',
                'thumbnail' => '/assets/images/templates/contact.png',
                'blocks_config' => [
                    [
                        'block_type_name' => 'text-block',
                        'region' => 'content',
                        'sort_order' => 0,
                    ],
                    [
                        'block_type_name' => 'html-block',
                        'region' => 'content',
                        'sort_order' => 1,
                    ],
                ],
                'default_seo' => [
                    'meta_title' => 'Contact Us',
                    'meta_description' => 'Get in touch with us',
                ],
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 3,
            ],

            // 5. Blog Post Template
            [
                'name' => 'Blog Post',
                'description' => 'Blog post with content and sidebar',
                'category' => 'blog',
                'thumbnail' => '/assets/images/templates/blog.png',
                'blocks_config' => [
                    [
                        'block_type_name' => 'text-block',
                        'region' => 'content',
                        'sort_order' => 0,
                    ],
                    [
                        'block_type_name' => 'recent_pages',
                        'region' => 'sidebar',
                        'sort_order' => 0,
                    ],
                ],
                'default_seo' => [
                    'meta_title' => 'Blog Post',
                    'meta_description' => 'Read our latest blog post',
                    'og_type' => 'article',
                ],
                'is_active' => true,
                'is_system' => false,
                'sort_order' => 4,
            ],

            // 6. Landing Page Template
            [
                'name' => 'Landing Page',
                'description' => 'Marketing landing page with hero and features',
                'category' => 'marketing',
                'thumbnail' => '/assets/images/templates/landing.png',
                'blocks_config' => [
                    [
                        'block_type_name' => 'homepage-hero',
                        'region' => 'hero',
                        'sort_order' => 0,
                    ],
                    [
                        'block_type_name' => 'homepage-features',
                        'region' => 'content',
                        'sort_order' => 1,
                    ],
                    [
                        'block_type_name' => 'text-block',
                        'region' => 'content',
                        'sort_order' => 2,
                    ],
                ],
                'default_seo' => [
                    'meta_title' => 'Special Offer',
                    'meta_description' => 'Check out our special offer',
                ],
                'is_active' => true,
                'is_system' => false,
                'sort_order' => 5,
            ],

            // 7. Blank Page Template
            [
                'name' => 'Blank Page',
                'description' => 'Empty page - start from scratch',
                'category' => 'general',
                'thumbnail' => '/assets/images/templates/blank.png',
                'blocks_config' => [],
                'default_seo' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 99,
            ],
        ];
    }
}
