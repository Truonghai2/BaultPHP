<?php

namespace Core\Security;

/**
 * Template Security Auditor.
 * 
 * Audits Blade templates for security vulnerabilities:
 * - Unescaped output {!! !!}
 * - @php blocks with dangerous functions
 * - eval() calls
 * - Dynamic includes
 */
class TemplateSecurityAuditor
{
    protected array $dangerousPatterns = [
        'eval' => '/\beval\s*\(/i',
        'exec' => '/\b(exec|shell_exec|system|passthru|proc_open)\s*\(/i',
        'file_operations' => '/\b(unlink|rmdir|file_put_contents|fwrite)\s*\(/i',
        'include_dynamic' => '/@include\s*\(\s*\$/',
        'unescaped_user_input' => '/\{!!\s*\$request|@php.*\$request/is',
    ];

    /**
     * Audit a template file.
     * 
     * @param string $templatePath Path to template file
     * @return array ['safe' => bool, 'issues' => array]
     */
    public function audit(string $templatePath): array
    {
        if (!file_exists($templatePath)) {
            return ['safe' => false, 'issues' => ['Template file not found']];
        }

        $content = file_get_contents($templatePath);
        $issues = [];

        // Check for dangerous patterns
        foreach ($this->dangerousPatterns as $name => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $issues[] = [
                    'type' => $name,
                    'severity' => 'high',
                    'message' => "Dangerous pattern detected: {$name}",
                    'match' => $matches[0] ?? '',
                ];
            }
        }

        // Check for @php blocks
        if (preg_match_all('/@php(.*?)@endphp/s', $content, $phpBlocks)) {
            foreach ($phpBlocks[1] as $block) {
                $blockIssues = $this->auditPhpBlock($block);
                $issues = array_merge($issues, $blockIssues);
            }
        }

        // Check for unescaped output
        if (preg_match_all('/\{!!\s*(.*?)\s*!!\}/s', $content, $unescapedOutputs)) {
            foreach ($unescapedOutputs[1] as $output) {
                if ($this->isUserInput($output)) {
                    $issues[] = [
                        'type' => 'unescaped_user_input',
                        'severity' => 'high',
                        'message' => 'Unescaped user input detected',
                        'match' => $output,
                    ];
                }
            }
        }

        return [
            'safe' => empty($issues),
            'issues' => $issues,
            'scanned_file' => $templatePath,
        ];
    }

    /**
     * Audit @php block for dangerous code.
     */
    protected function auditPhpBlock(string $code): array
    {
        $issues = [];

        // Check for dangerous functions
        $dangerousFunctions = [
            'eval', 'exec', 'shell_exec', 'system', 'passthru',
            'proc_open', 'popen', 'curl_exec', 'curl_multi_exec',
            'parse_str', 'create_function', 'assert',
        ];

        foreach ($dangerousFunctions as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $code)) {
                $issues[] = [
                    'type' => 'dangerous_function',
                    'severity' => 'critical',
                    'message' => "Dangerous function in @php block: {$func}()",
                    'match' => $func,
                ];
            }
        }

        return $issues;
    }

    /**
     * Check if expression contains user input.
     */
    protected function isUserInput(string $expression): bool
    {
        $userInputPatterns = [
            '/\$request/',
            '/\$_GET/',
            '/\$_POST/',
            '/\$_REQUEST/',
            '/\$_COOKIE/',
            '/\$_SERVER/',
            '/request\(\)/',
            '/input\(\)/',
        ];

        foreach ($userInputPatterns as $pattern) {
            if (preg_match($pattern, $expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Audit all templates in a directory.
     * 
     * @param string $directory Path to templates directory
     * @return array Audit results
     */
    public function auditDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return ['error' => 'Directory not found'];
        }

        $templates = glob($directory . '/**/*.blade.php', GLOB_BRACE);
        $results = [
            'total' => 0,
            'safe' => 0,
            'unsafe' => 0,
            'issues' => [],
        ];

        foreach ($templates as $template) {
            $results['total']++;
            $audit = $this->audit($template);

            if ($audit['safe']) {
                $results['safe']++;
            } else {
                $results['unsafe']++;
                $results['issues'][$template] = $audit['issues'];
            }
        }

        return $results;
    }
}
