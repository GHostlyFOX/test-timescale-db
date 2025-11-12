<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use NginxLogImporters\LogParser;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use PrometheusPushGateway\PushGateway;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Configuration ---
$logFilePath = 'access.log';
$jobName = 'nginx_log_importer';

function main(string $logFilePath, string $jobName): void
{
    echo "Starting Nginx log processing for Prometheus...\n";

    $registry = new CollectorRegistry(new InMemory());
    $pushGateway = new PushGateway($_ENV['PUSHGATEWAY_URL']);

    $counter = $registry->getOrRegisterCounter(
        'nginx',
        'http_requests_total',
        'Total number of HTTP requests',
        ['ip_address', 'method', 'status_code']
    );

    $handle = fopen($logFilePath, 'r');
    if (!$handle) {
        echo "Error: Log file not found at '$logFilePath'\n";
        return;
    }

    $totalProcessed = 0;
    $startTime = microtime(true);

    while (($line = fgets($handle)) !== false) {
        $parsedData = LogParser::parse($line);
        if ($parsedData) {
            $counter->inc([
                $parsedData['ip_address'],
                $parsedData['method'],
                (string)$parsedData['status_code'],
            ]);
            $totalProcessed++;
        }
    }

    fclose($handle);

    $pushGateway->push($registry, $jobName);

    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $speed = $duration > 0 ? $totalProcessed / $duration : NAN;

    echo "\n--- Prometheus Processing Summary ---\n";
    echo "Total log entries processed: $totalProcessed\n";
    echo "Total time taken: " . number_format($duration, 4) . " seconds\n";
    echo "Processing speed: " . number_format($speed, 2) . " logs/second\n";
    echo "Metrics successfully pushed to " . $_ENV['PUSHGATEWAY_URL'] . "\n";
}

try {
    main($logFilePath, $jobName);
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}
