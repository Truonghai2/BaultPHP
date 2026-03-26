<?php

namespace Tests\Security;

use Core\ORM\RawExpression;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    /** @test */
    public function raw_expression_blocks_sql_injection_attempts(): void
    {
        $maliciousPayloads = [
            "'; DROP TABLE users--",
            "1 UNION SELECT * FROM users",
            "1' OR '1'='1",
            "admin'--",
            "1; DELETE FROM users WHERE 1=1",
        ];
        
        foreach ($maliciousPayloads as $payload) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('SQL injection');
            
            new RawExpression($payload);
        }
    }
    
    /** @test */
    public function raw_expression_allows_safe_expressions(): void
    {
        $safeExpressions = [
            'COUNT(*)',
            '`views` + 1',
            'SUM(`amount`)',
            'AVG(`rating`)',
        ];
        
        foreach ($safeExpressions as $expr) {
            $raw = new RawExpression($expr);
            $this->assertEquals($expr, $raw->getValue());
        }
    }
    
    /** @test */
    public function raw_expression_increment_is_safe(): void
    {
        $raw = RawExpression::increment('views', 5);
        
        $this->assertStringContainsString('views', $raw->getValue());
        $this->assertStringContainsString('+ 5', $raw->getValue());
    }
    
    /** @test */
    public function raw_expression_blocks_invalid_column_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        RawExpression::increment("'; DROP TABLE users--");
    }
    
    /** @test */
    public function raw_expression_count_is_safe(): void
    {
        $raw = RawExpression::count();
        $this->assertEquals('COUNT(*)', $raw->getValue());
        
        $raw = RawExpression::count('users');
        $this->assertStringContainsString('COUNT', $raw->getValue());
    }
    
    /** @test */
    public function raw_expression_sum_validates_column(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        RawExpression::sum("price; DROP TABLE users");
    }

    /** @test */
    public function escaper_protects_against_xss(): void
    {
        // Require the helper to ensure function exists
        require_once dirname(__DIR__, 2) . '/src/Core/helpers.php';
        
        $xss = "<script>alert('XSS')</script>";
        
        // Context: HTML
        $escapedHtml = esc($xss, 'html');
        $this->assertEquals("&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;", $escapedHtml);
        
        // Context: Attribute
        $attrXss = '"><script>alert(1)</script>';
        $escapedAttr = esc($attrXss, 'attr');
        $this->assertStringNotContainsString('<', $escapedAttr);
        $this->assertStringNotContainsString('>', $escapedAttr);
        
        // Context: JS
        $jsXss = "'; alert(1); var x='";
        $escapedJs = esc($jsXss, 'js');
        $this->assertStringNotContainsString("';", $escapedJs);
    }

    /** @test */
    public function model_hides_sensitive_attributes(): void
    {
        $user = new class extends \Core\ORM\Model {
            protected array $fillable = ['username', 'password', 'api_key'];
            protected array $guarded = []; // Ensure not guarded
            protected array $hidden = ['password', 'api_key'];
        };

        $user->fill([
            'username' => 'hackerman',
            'password' => 'secret123',
            'api_key' => 'abcdef123456',
        ]);

        $array = $user->toArray();
        $json = $user->toJson();

        $this->assertArrayHasKey('username', $array);
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('api_key', $array);
        
        $this->assertStringContainsString('hackerman', $json);
        $this->assertStringNotContainsString('secret123', $json);
    }

    /** @test */
    public function str_random_generates_unique_secure_strings(): void
    {
        $str1 = \Core\Support\Str::random(16);
        $str2 = \Core\Support\Str::random(16);

        $this->assertEquals(16, strlen($str1));
        $this->assertEquals(16, strlen($str2));
        $this->assertNotEquals($str1, $str2);
        
        // Ensure alphanumeric
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $str1);
    }
}
