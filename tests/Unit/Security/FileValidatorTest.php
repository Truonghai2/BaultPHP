<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Core\Security\FileValidator;

/**
 * Security Tests for File Upload Validation.
 */
class FileValidatorTest extends TestCase
{
    private string $testFilesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testFilesDir = sys_get_temp_dir() . '/baultframe_test_files';
        
        if (!is_dir($this->testFilesDir)) {
            mkdir($this->testFilesDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testFilesDir)) {
            $files = glob($this->testFilesDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testFilesDir);
        }

        parent::tearDown();
    }

    /**
     * Test: Detects MIME type correctly.
     */
    public function test_detects_mime_type_correctly()
    {
        // Create a simple text file
        $filePath = $this->testFilesDir . '/test.txt';
        file_put_contents($filePath, 'Hello World');

        $mime = FileValidator::detectMimeType($filePath);
        
        $this->assertEquals('text/plain', $mime);
    }

    /**
     * Test: Detects PHP code in files.
     */
    public function test_detects_php_code_in_files()
    {
        // File with PHP code
        $filePath = $this->testFilesDir . '/malicious.jpg';
        file_put_contents($filePath, "<?php system('ls'); ?>");

        $this->assertTrue(FileValidator::containsPhpCode($filePath));

        // Clean file
        $cleanPath = $this->testFilesDir . '/clean.txt';
        file_put_contents($cleanPath, 'Just plain text');

        $this->assertFalse(FileValidator::containsPhpCode($cleanPath));
    }

    /**
     * Test: Detects polyglot files.
     */
    public function test_detects_polyglot_files()
    {
        // Create a fake "image" with PHP header
        $filePath = $this->testFilesDir . '/polyglot.jpg';
        $content = "<?php system('whoami'); ?>\xFF\xD8\xFF\xE0"; // PHP + JPEG header
        file_put_contents($filePath, $content);

        $detectedMime = FileValidator::detectMimeType($filePath);
        $isPolyglot = FileValidator::isPolyglot($filePath, $detectedMime ?: 'image/jpeg');

        $this->assertTrue($isPolyglot);
    }

    /**
     * Test: Validates MIME type mismatch.
     */
    public function test_validates_mime_type_mismatch()
    {
        // Create a text file but claim it's an image
        $filePath = $this->testFilesDir . '/fake_image.jpg';
        file_put_contents($filePath, 'This is just text, not an image');

        $result = FileValidator::validate($filePath, 'image/jpeg');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('MIME type mismatch', implode(', ', $result['errors']));
    }

    /**
     * Test: Rejects files with embedded PHP.
     */
    public function test_rejects_files_with_php()
    {
        $filePath = $this->testFilesDir . '/shell.jpg';
        file_put_contents($filePath, "<?php echo 'hacked'; ?>");

        $result = FileValidator::validate($filePath, 'image/jpeg');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('PHP code', implode(', ', $result['errors']));
    }

    /**
     * Test: Validates file size limits.
     */
    public function test_validates_file_size()
    {
        $filePath = $this->testFilesDir . '/large.txt';
        file_put_contents($filePath, str_repeat('A', 1024 * 1024 * 11)); // 11MB

        $result = FileValidator::validate($filePath, 'text/plain', [
            'max_size' => 10485760 // 10MB
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds maximum size', implode(', ', $result['errors']));
    }
}
