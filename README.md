# Nginx Log Importers for Various Databases (PHP Version)

This project provides a set of PHP scripts to read Nginx logs and import them into four different databases: ClickHouse, InfluxDB, Prometheus, and TimescaleDB. Each script is designed to measure and report the speed of data insertion, allowing for a performance comparison between these systems for handling log data.

## Project Structure

```
nginx_log_importers/
├── access.log                # Sample Nginx log file
├── composer.json             # PHP dependencies
├── .env.example              # Environment variable configuration
├── clickhouse_setup.sql      # SQL for creating the ClickHouse table
├── clickhouse_importer.php   # PHP script for ClickHouse
├── influxdb_setup.txt        # Instructions for setting up InfluxDB
├── influxdb_importer.php     # PHP script for InfluxDB
├── prometheus_setup.txt      # Instructions for setting up Prometheus
├── prometheus_importer.php   # PHP script for Prometheus
├── timescaledb_setup.sql     # SQL for creating the TimescaleDB hypertable
├── timescaledb_importer.php  # PHP script for TimescaleDB
├── src/                      # Helper classes
│   └── LogParser.php
└── README.md                 # This file
```

## 1. Installation

First, install the required PHP libraries using Composer:

```bash
composer install
```

## 2. Configuration

Before running the scripts, you need to create a `.env` file with your database credentials. You can do this by copying the example file:

```bash
cp .env.example .env
```

Then, edit the `.env` file and fill in the correct connection details for each database you want to test.

## 3. Database Setup and Usage

Below are the instructions for setting up each database and running the corresponding import script.

---

### A. ClickHouse

**1. Setup:**
- Make sure you have a running ClickHouse instance.
- Create the `nginx_logs` table using the provided SQL file. You can do this with the `clickhouse-client`:
  ```bash
  clickhouse-client --user <username> --password <password> --multiquery < clickhouse_setup.sql
  ```

**2. Run the Importer:**
- Make sure your ClickHouse credentials in the `.env` file are correct.
- Run the script:
  ```bash
  php clickhouse_importer.php
  ```

---

### B. InfluxDB

**1. Setup:**
- Make sure you have a running InfluxDB instance (v2.x).
- Follow the instructions in `influxdb_setup.txt` to create a bucket (e.g., `nginx_logs`), an organization, and an API token.

**2. Run the Importer:**
- Update the `.env` file with your InfluxDB URL, token, organization, and bucket name.
- Run the script:
  ```bash
  php influxdb_importer.php
  ```

---

### C. Prometheus

**1. Setup:**
- Prometheus is for metrics, not logs. This script will extract metrics from the logs and push them to a Pushgateway.
- Follow the instructions in `prometheus_setup.txt` to run a Pushgateway instance (e.g., using Docker) and configure Prometheus to scrape it.

**2. Run the Importer:**
- The script is pre-configured to connect to a Pushgateway at `http://localhost:9091`. You can change this in the `.env` file.
- Run the script to process the logs and push the metrics:
  ```bash
  php prometheus_importer.php
  ```

---

### D. TimescaleDB

**1. Setup:**
- Make sure you have a running PostgreSQL instance with the TimescaleDB extension enabled.
- Create the `nginx_logs` hypertable using the provided SQL file. You can do this with `psql`:
  ```bash
  psql -U <username> -d <database> -f timescaledb_setup.sql
  ```

**2. Run the Importer:**
- Update the `.env` file with your TimescaleDB/PostgreSQL connection details.
- Run the script:
  ```bash
  php timescaledb_importer.php
  ```
