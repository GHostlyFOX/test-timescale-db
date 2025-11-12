-- First, create a standard PostgreSQL table to store the Nginx log data.
CREATE TABLE nginx_logs (
    ip_address TEXT,
    timestamp TIMESTAMPTZ NOT NULL,
    method TEXT,
    url TEXT,
    status_code SMALLINT,
    response_size INTEGER,
    referrer TEXT,
    user_agent TEXT
);

-- Then, convert this table into a TimescaleDB hypertable.
-- This command will automatically partition the data by the 'timestamp' column.
SELECT create_hypertable('nginx_logs', 'timestamp');

-- Optional: You can also create indexes to speed up queries on other columns.
CREATE INDEX ON nginx_logs (ip_address, timestamp DESC);
CREATE INDEX ON nginx_logs (status_code, timestamp DESC);
