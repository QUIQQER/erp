<?php

namespace QUITests\ERP\Output;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BackendEndpointsTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function endpoints(): iterable
    {
        foreach (['download.php', 'print.php', 'printStream.php'] as $endpoint) {
            yield $endpoint . ' denied' => [$endpoint, false];
            yield $endpoint . ' allowed' => [$endpoint, true];
        }
    }

    #[DataProvider('endpoints')]
    public function testBackendContextAndAccessCheck(string $endpoint, bool $allowed): void
    {
        $package = dirname(__DIR__, 4);
        $directory = sys_get_temp_dir() . '/erp-output-' . bin2hex(random_bytes(8));
        $endpointDirectory = $directory . '/quiqqer/erp/bin/output/backend';
        mkdir($endpointDirectory, 0700, true);
        copy($package . '/bin/output/backend/' . $endpoint, $endpointDirectory . '/' . $endpoint);
        copy(__DIR__ . '/Fixtures/backend-bootstrap.php', $directory . '/header.php');
        $environment = getenv();
        $environment['ERP_OUTPUT_TEST_AUTOLOAD'] = dirname($package, 2) . '/autoload.php';
        $environment['ERP_OUTPUT_TEST_RESPONSE'] = $package . '/src/QUI/ERP/Output/BackendOutputResponse.php';
        $environment['ERP_OUTPUT_TEST_ALLOWED'] = $allowed ? '1' : '0';

        try {
            $process = proc_open(
                [PHP_BINARY, $endpointDirectory . '/' . $endpoint],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $directory,
                $environment
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string)$errors);
            self::assertIsString($output);

            if ($allowed) {
                self::assertSame('Authorized output reached', $output);
            } else {
                self::assertSame('HTTP_STATUS=403', $errors);
                self::assertStringContainsString('Permission denied', $output);
                self::assertStringContainsString('Messages.addError(', $output);
                self::assertStringNotContainsString('Authorized output reached', $output);
            }
        } finally {
            unlink($endpointDirectory . '/' . $endpoint);
            unlink($directory . '/header.php');

            for ($path = $endpointDirectory; $path !== dirname($directory); $path = dirname($path)) {
                rmdir($path);
            }
        }
    }
}
