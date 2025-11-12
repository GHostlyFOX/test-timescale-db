<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use InfluxDB2\Client;
use InfluxDB2\Point;
use NginxLogImporters\LogParser;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Configuration ---
$logFilePath = 'access.log';
$batchSize = 5000;

// --- InfluxDB Client Setup ---
$client = new Client([
    "url" => $_ENV['INFLUXDB_URL'],
    "token" => $_ENV['INFLUXDB_TOKEN'],
    "bucket" => $_ENV['INFLUXDB_BUCKET'],
    "org" => $_ENV['INFLUXDB_ORG'],
    "precision" => InfluxDB2\Model\WritePrecision::S
]);

$writeApi = $client->createWriteApi();

function main($writeApi, string $logFilePath, int $batchSize): void
{
    echo "Starting Nginx log import to InfluxDB...\n";

    $handle = fopen($logFilePath, 'r');
    if (!$handle) {
        echo "Error: Log file not found at '$logFilePath'\n";
        return;
    }

    $points = [];
    $totalInserted = 0;
    $startTime = microtime(true);

    while (($line = fgets($handle)) !== false) {
        $parsedData = LogParser::parse($line);
        if ($parsedData) {
            $point = Point::measurement('nginx_log')
                ->addTag('ip_address', $parsedData['ip_address'])
                ->addTag('method', $parsedData['method'])
                ->addTag('url', $parsedData['url'])
                ->addTag('status_code', (string)$parsedData['status_code'])
                ->addField('response_size', $parsedData['response_size'])
                ->addField('referrer', $parsedData['referrer'])
                ->addField('user_agent', $parsedData['user_agent'])
                ->time($parsedData['timestamp']->getTimestamp());
            $points[] = $point;
        }

        if (count($points) >= $batchSize) {
            $writeApi->write($points);
            $totalInserted += count($points);
            $points = [];
        }
    }

    if (!empty($points)) {
        $writeApi->write($points);
        $totalInserted += count($points);
    }

    fclose($handle);

    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $speed = $duration > 0 ? $totalInserted / $duration : NAN;

    echo "\n--- InfluxDB Import Summary ---\n";
    echo "Total records inserted: $totalInserted\n";
    echo "Total time taken: " . number_format($duration, 4) . " seconds\n";
    echo "Insertion speed: " . number_format($speed, 2) . " records/second\n";
}

try {
    main($writeApi, $logFilePath, $batchSize);
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}
