<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client as GuzzleClient;
use NginxLogImporters\LogParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Configuration ---
$logFilePath = 'access.log';
$batchSize = 1000;

// --- ClickHouse Client Setup ---
$psr17Factory = new Psr17Factory();
$guzzleClient = new GuzzleClient([
    'base_uri' => sprintf(
        'http://%s:%s',
        $_ENV['CLICKHOUSE_HOST'],
        $_ENV['CLICKHOUSE_PORT']
    ),
    'headers' => [
        'X-ClickHouse-User' => $_ENV['CLICKHOUSE_USER'],
        'X-ClickHouse-Key' => $_ENV['CLICKHOUSE_PASSWORD'],
    ],
]);

$clickHouseClient = new PsrClickHouseClient(
    $guzzleClient,
    new RequestFactory(
        $psr17Factory,
        $psr17Factory
    )
);

function main(PsrClickHouseClient $client, string $logFilePath, int $batchSize): void
{
    echo "Starting Nginx log import to ClickHouse...\n";

    $handle = fopen($logFilePath, 'r');
    if (!$handle) {
        echo "Error: Log file not found at '$logFilePath'\n";
        return;
    }

    $records = [];
    $totalInserted = 0;
    $startTime = microtime(true);

    while (($line = fgets($handle)) !== false) {
        $parsedData = LogParser::parse($line);
        if ($parsedData) {
            $records[] = [
                $parsedData['ip_address'],
                $parsedData['timestamp']->format('Y-m-d H:i:s'),
                $parsedData['method'],
                $parsedData['url'],
                $parsedData['status_code'],
                $parsedData['response_size'],
                $parsedData['referrer'],
                $parsedData['user_agent'],
            ];
        }

        if (count($records) >= $batchSize) {
            $client->insert('nginx_logs', $records);
            $totalInserted += count($records);
            $records = [];
        }
    }

    if (!empty($records)) {
        $client->insert('nginx_logs', $records);
        $totalInserted += count($records);
    }

    fclose($handle);

    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $speed = $duration > 0 ? $totalInserted / $duration : NAN;

    echo "\n--- ClickHouse Import Summary ---\n";
    echo "Total records inserted: $totalInserted\n";
    echo "Total time taken: " . number_format($duration, 4) . " seconds\n";
    echo "Insertion speed: " . number_format($speed, 2) . " records/second\n";
}

try {
    main($clickHouseClient, $logFilePath, $batchSize);
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}
