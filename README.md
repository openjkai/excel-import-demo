# Excel / CSV Import — Laravel Demo

A Phase 1 MVP for importing Excel and CSV files with manual column mapping, validation, and controlled commit. Built with Laravel 12, MySQL 8, and Docker.

## Features

- **Upload** — Excel (.xlsx, .xls) or CSV via drag-and-drop
- **Column mapping** — Auto-detect headers and map to internal fields
- **Validation** — Required fields, type checking, duplicate key detection
- **Preview** — Summary and per-row errors before commit
- **Controlled commit** — Period-based overwrite via Laravel queue job
- **Staging pattern** — `import_batches` + `import_rows` tables before writing to target

## Requirements

- Docker & Docker Compose

## Quick Start

```bash
# Clone and enter the project
git clone https://github.com/openjkai/excel-import-demo.git
cd excel-import-demo

# Copy environment file
cp .env.example .env

# Start containers (PHP, Nginx, MySQL, Queue worker)
docker compose up -d

# Generate app key and run migrations
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# (Optional) Create sample Excel file for testing
docker compose exec app php create_sample.php
# Then copy to public: cp storage/app/sample_import.xlsx public/

# Open in browser
# http://localhost:8090
```

## Docker Services

| Service | Port | Description |
|---------|------|-------------|
| nginx   | 8090 | Web server |
| app     | 9000 | PHP-FPM (internal) |
| mysql   | 3307 | MySQL 8.0 (host port) |
| queue   | —    | Laravel queue worker |

## Flow

1. **Upload** → File stored in `storage/app/imports/`, headers parsed with [maatwebsite/excel](https://github.com/SpartnerNL/Laravel-Excel)
2. **Map** → Select internal fields for each spreadsheet column, set import period
3. **Validate** → Required fields, numeric/date types, composite-key duplicate check
4. **Preview** → Valid / invalid / duplicate counts, error details per row
5. **Commit** → `CommitImportJob` runs in queue: DELETE existing records in period, bulk INSERT valid rows

## Database Schema

- `import_batches` — Batch metadata, column mapping, status, period, row counts
- `import_rows` — Raw + mapped data per row, validation status, errors
- `financial_records` — Target table (transaction_date, account_code, description, debit, credit, reference, department)

## Tech Stack

- Laravel 12
- PHP 8.2
- MySQL 8.0
- maatwebsite/excel (PhpSpreadsheet)
- Tailwind CSS + Alpine.js
- Docker

## License

This project is open-sourced software licensed under the [MIT License](LICENSE).
