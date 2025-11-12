<?php

namespace NginxLogImporters;

use DateTime;

class LogParser
{
    private static string $logPattern = '/^(?P<ip>[\d\.]+) - - \[(?P<time>.*?)\] ' .
        '"(?P<method>.*?) (?P<url>.*?) .*" (?P<status>\d+) (?P<size>\d+) ' .
        '"(?P<referrer>.*?)" "(?P<user_agent>.*?)"$/';

    public static function parse(string $line): ?array
    {
        if (!preg_match(self::$logPattern, $line, $matches)) {
            return null;
        }

        try {
            $timestamp = new DateTime($matches['time']);
        } catch (\Exception $e) {
            return null;
        }

        return [
            'ip_address' => $matches['ip'],
            'timestamp' => $timestamp,
            'method' => $matches['method'],
            'url' => $matches['url'],
            'status_code' => (int) $matches['status'],
            'response_size' => (int) $matches['size'],
            'referrer' => $matches['referrer'],
            'user_agent' => $matches['user_agent'],
        ];
    }
}
