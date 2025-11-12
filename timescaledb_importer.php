<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use NginxLogImporters\LogParser;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Configuration ---
$logFilePath = 'access.log';
$batchSize = 1000;

// --- TimescaleDB Connection ---
$connString = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s",
    $_ENV['TIMESCALE_HOST'],
    $_ENV['TIMESCALE_PORT'],
    $_ENV['TIMESCALE_DATABASE'],
    $_ENV['TIMESCALE_USER'],
    $_ENV['TIMESCALE_PASSWORD']
);

$conn = pg_connect($connString);

function main($conn, string $logFilePath, int $batchSize): void
{
    if (!$conn) {
        echo "Error: Unable to connect to TimescaleDB.\n";
        return;
    }

    echo "Starting Nginx log import to TimescaleDB...\n";

    $handle = fopen($logFilePath, 'r');
    if (!$handle) {
        echo "Error: Log file not found at '$logFilePath'\n";
        return;
    }

    $records = [];
    $totalInserted = 0;
    $startTime = microtime(true);

    pg_query($conn, "PREPARE insert_log AS INSERT INTO nginx_logs (ip_address, timestamp, method, url, status_code, response_size, referrer, user_agent) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)");

    while (($line = fgets($handle)) !== false) {
        $parsedData = LogParser::parse($line);
        if ($parsedData) {
            $records[] = [
                $parsedData['ip_address'],
                $parsedData['timestamp']->format('Y-m-d H:i:s P'),
                $parsedData['method'],
                $parsedData['url'],
                $parsedData['status_code'],
                $parsedData['response_size'],
                $parsedData['referrer'],
                $parsedData['user_agent'],
            ];
        }

        if (count($records) >= $batchSize) {
            pg_query($conn, "BEGIN");
            foreach ($records as $record) {
                pg_execute($conn, "insert_log", $record);
            }
            pg_query($conn, "COMMIT");
            $totalInserted += count($records);
            $records = [];
        }
    }

    if (!empty($records)) {
        pg_query($conn, "BEGIN");
        foreach ($records as $record) {
            pg_execute($conn, "insert_log", $record);
        }
        pg_query($conn, "COMMIT");
        $totalInserted += count($records);
    }

    fclose($handle);

    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $speed = $duration > 0 ? $totalInserted / $duration : NAN;

    echo "\n--- TimescaleDB Import Summary ---\n";
    echo "Total records inserted: $totalInserted\n";
    echo "Total time taken: " . number_format($duration, 4) . " seconds\n";
    echo "Insertion speed: " . number_format($speed, 2) . " records/second\n";
}

try {
    main($conn, $logFilePath, $batchSize);
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
} finally {
    if ($conn) {
        pg_close($conn);
    }
}
