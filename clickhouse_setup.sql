CREATE TABLE nginx_logs (
    ip_address String,
    timestamp DateTime,
    method String,
    url String,
    status_code UInt16,
    response_size UInt32,
    referrer String,
    user_agent String
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (timestamp, ip_address);
