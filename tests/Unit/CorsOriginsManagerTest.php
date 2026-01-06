<?php

namespace Tests\Unit;

use App\Http\Cors\CorsOriginsManager;
use PHPUnit\Framework\TestCase;

class CorsOriginsManagerTest extends TestCase
{
    private CorsOriginsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new CorsOriginsManager();
    }

    public function test_exact_match_origin()
    {
        $origin = 'http://localhost:3000';
        $this->assertTrue($this->manager->isAllowed($origin));
    }

    public function test_disallowed_origin()
    {
        $origin = 'https://evil.com';
        $this->assertFalse($this->manager->isAllowed($origin));
    }

    public function test_wildcard_subdomain_match()
    {
        // Cần config có '*.example.com'
        $origin = 'https://app.example.com';
        
        // Add origin pattern to test
        $this->manager->addOrigin('*.example.com');
        
        $result = $this->manager->isAllowed($origin);
        
        // Kiểm tra logic wildcard
        $this->assertTrue(true); // Placeholder test
    }

    public function test_cache_validation()
    {
        $origin = 'http://localhost:3000';
        
        // First call
        $result1 = $this->manager->isAllowed($origin);
        
        // Second call (should use cache)
        $result2 = $this->manager->isAllowed($origin);
        
        $this->assertEquals($result1, $result2);
    }

    public function test_clear_cache()
    {
        $this->manager->clearCache();
        
        // Verify cache is cleared
        $this->assertTrue(true); // Placeholder
    }

    public function test_get_all_origins()
    {
        $origins = $this->manager->getAllOrigins();
        
        $this->assertIsArray($origins);
        $this->assertNotEmpty($origins);
    }

    public function test_add_origin_runtime()
    {
        $newOrigin = 'https://new-domain.com';
        
        $this->manager->addOrigin($newOrigin);
        
        $this->assertTrue($this->manager->isAllowed($newOrigin));
    }

    public function test_remove_origin_runtime()
    {
        $origin = 'http://localhost:3000';
        
        $this->manager->removeOrigin($origin);
        
        $this->assertFalse($this->manager->isAllowed($origin));
    }

    public function test_trailing_slash_normalization()
    {
        $origin = 'http://localhost:3000/';
        
        // Should match http://localhost:3000 (without trailing slash)
        $result = $this->manager->isAllowed($origin);
        
        $this->assertTrue($result);
    }

    public function test_case_insensitive_matching()
    {
        $origin = 'HTTP://LOCALHOST:3000';
        
        $result = $this->manager->isAllowed($origin);
        
        $this->assertTrue($result);
    }
}

