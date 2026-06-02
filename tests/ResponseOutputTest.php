<?php
declare(strict_types=1);

namespace Sangia\Tests;

use PHPUnit\Framework\TestCase;

class ResponseOutputTest extends TestCase
{
    /**
     * Jalankan kode PHP pendek di proses CLI terpisah dan tangkap outputnya.
     */
    private function captureOutput(string $code): string
    {
        $command = sprintf(
            '%s -d display_errors=0 -r %s 2>NUL',
            escapeshellcmd(PHP_BINARY),
            escapeshellarg($code)
        );
        exec($command . ' 2>&1', $output, $exitCode);
        return implode("\n", $output);
    }

    /** @test */
    public function json_outputs_expected_structure(): void
    {
        $output = $this->captureOutput(
            "require 'library/autoload.php'; " .
            "\\Sangia\\Api\\Response::json(['key' => 'value'], 201);"
        );

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertSame(['key' => 'value'], $decoded);
    }

    /** @test */
    public function success_wraps_data_with_success_flag(): void
    {
        $output = $this->captureOutput(
            "require 'library/autoload.php'; " .
            "\\Sangia\\Api\\Response::success(['result' => 123], ['page' => 1]);"
        );

        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['success']);
        $this->assertSame(['result' => 123], $decoded['data']);
        $this->assertSame(['page' => 1], $decoded['meta']);
    }

    /** @test */
    public function error_returns_success_false_and_message(): void
    {
        $output = $this->captureOutput(
            "require 'library/autoload.php'; " .
            "\\Sangia\\Api\\Response::error('Bad request', 400);"
        );

        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
        $this->assertSame('Bad request', $decoded['message']);
    }

    /** @test */
    public function not_found_has_404_default_message(): void
    {
        $output = $this->captureOutput(
            "require 'library/autoload.php'; " .
            "\\Sangia\\Api\\Response::notFound();"
        );

        $decoded = json_decode($output, true);
        $this->assertStringContainsStringIgnoringCase('not found', $decoded['message']);
    }

    /** @test */
    public function unauthorized_has_401_default_message(): void
    {
        $output = $this->captureOutput(
            "require 'library/autoload.php'; " .
            "\\Sangia\\Api\\Response::unauthorized();"
        );

        $decoded = json_decode($output, true);
        $this->assertStringContainsStringIgnoringCase('unauthorized', $decoded['message']);
    }

    /** @test */
    public function server_error_has_500_default_message(): void
    {
        $output = $this->captureOutput(
            "require 'library/autoload.php'; " .
            "\\Sangia\\Api\\Response::serverError();"
        );

        $decoded = json_decode($output, true);
        $this->assertStringContainsStringIgnoringCase('server error', $decoded['message']);
    }
}