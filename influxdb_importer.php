<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use NginxLogImporters\LogParser;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Configuration ---
$logFilePath = 'access.log';
$batchSize = 5000;
$measurement = 'nginx_log';

// --- InfluxDB v3 Guzzle Client Setup ---
$client = new GuzzleClient([
    'base_uri' => $_ENV['INFLUXDB_URL'],
    'headers' => [
        'Authorization' => 'Token ' . $_ENV['INFLUXDB_TOKEN'],
        'Content-Type' => 'text/plain',
    ],
    'timeout' => 10,
]);

function main(GuzzleClient $client, string $logFilePath, int $batchSize, string $measurement): void
{
    echo "Starting Nginx log import to InfluxDB v3 (via v2 API)...\n";

    $handle = fopen($logFilePath, 'r');
    if (!$handle) {
        echo "Error: Log file not found at '$logFilePath'\n";
        return;
    }

    $linesBuffer = [];
    $totalInserted = 0;
    $startTime = microtime(true);

    while (($line = fgets($handle)) !== false) {
        $parsedData = LogParser::parse($line);
        if ($parsedData) {
            // Convert parsed data to InfluxDB Line Protocol
            $tags = [
                'ip_address=' . $parsedData['ip_address'],
                'method=' . $parsedData['method'],
                'url=' . str_replace(' ', '\ ', $parsedData['url']), // Escape spaces in URL
                'status_code=' . $parsedData['status_code'],
            ];
            $fields = [
                'response_size=' . $parsedData['response_size'],
                'referrer="' . addslashes($parsedData['referrer']) . '"',
                'user_agent="' . addslashes($parsedData['user_agent']) . '"',
            ];
            $timestamp = $parsedData['timestamp']->getTimestamp();

            $lpLine = sprintf(
                '%s,%s %s %d',
                $measurement,
                implode(',', $tags),
                implode(',', $fields),
                $timestamp
            );
            $linesBuffer[] = $lpLine;
        }

        if (count($linesBuffer) >= $batchSize) {
            sendBatch($client, $linesBuffer);
            $totalInserted += count($linesBuffer);
            $linesBuffer = [];
        }
    }

    if (!empty($linesBuffer)) {
        sendBatch($client, $linesBuffer);
        $totalInserted += count($linesBuffer);
    }

    fclose($handle);

    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $speed = $duration > 0 ? $totalInserted / $duration : NAN;

    echo "\n--- InfluxDB v3 Import Summary ---\n";
    echo "Total records inserted: $totalInserted\n";
    echo "Total time taken: " . number_format($duration, 4) . " seconds\n";
    echo "Insertion speed: " . number_format($speed, 2) . " records/second\n";
}

function sendBatch(GuzzleClient $client, array $linesBuffer): void
{
    try {
        $body = implode("\n", $linesBuffer);
        $client->post('/api/v2/write', [
            'query' => [
                'bucket' => $_ENV['INFLUXDB_DATABASE'], // In v3, the database name is used as the bucket for v2 API
                'org' => $_ENV['INFLUXDB_ORG'],
                'precision' => 's',
            ],
            'body' => $body,
        ]);
    } catch (RequestException $e) {
        echo "An error occurred while sending a batch: " . $e->getMessage() . "\n";
        if ($e->hasResponse()) {
            echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
        }
    }
}

try {
    main($client, $logFilePath, $batchSize, $measurement);
} catch (Exception $e) {
    echo "A critical error occurred: " . $e->getMessage() . "\n";
}
