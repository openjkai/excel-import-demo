#!/bin/bash

# Excel Import Application Test Script v2
# This script tests the complete import flow programmatically with proper CSRF handling

set -e

BASE_URL="http://localhost:8090"
COOKIE_FILE="/tmp/laravel_test_cookies.txt"
SAMPLE_FILE="public/sample_import.xlsx"

echo "=========================================="
echo "Excel Import Application Test"
echo "=========================================="
echo ""

# Clean up old cookie file
rm -f "$COOKIE_FILE"

# Step 1: Test the import page and get CSRF token
echo "Step 1: Testing /import page and extracting CSRF token..."
import_page=$(curl -s -c "$COOKIE_FILE" "$BASE_URL/import")
csrf_token=$(echo "$import_page" | grep -o 'name="_token" value="[^"]*"' | head -n 1 | sed 's/name="_token" value="//;s/"$//')

if [ -z "$csrf_token" ]; then
    echo "✗ Failed to extract CSRF token"
    exit 1
fi

echo "✓ Import page loaded successfully"
echo "  CSRF Token: ${csrf_token:0:20}..."
echo ""

# Step 2: Check sample file
echo "Step 2: Checking sample file..."
if [ -f "$SAMPLE_FILE" ]; then
    file_size=$(stat -f%z "$SAMPLE_FILE" 2>/dev/null || stat -c%s "$SAMPLE_FILE" 2>/dev/null)
    echo "✓ Sample file exists: $SAMPLE_FILE ($file_size bytes)"
else
    echo "✗ Sample file not found: $SAMPLE_FILE"
    exit 1
fi
echo ""

# Step 3: Upload the file
echo "Step 3: Uploading sample file..."
upload_response=$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
    -F "file=@$SAMPLE_FILE" \
    -F "_token=$csrf_token" \
    -w "\nHTTP_CODE:%{http_code}\nREDIRECT_URL:%{redirect_url}" \
    "$BASE_URL/import/upload" 2>&1)

upload_http_code=$(echo "$upload_response" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$upload_http_code" = "302" ]; then
    echo "✓ File uploaded successfully (HTTP $upload_http_code)"
else
    echo "✗ File upload failed (HTTP $upload_http_code)"
    echo "Response preview:"
    echo "$upload_response" | head -n 30
    exit 1
fi
echo ""

# Step 4: Get the batch ID from the database
echo "Step 4: Getting batch ID from database..."
batch_id=$(php artisan tinker --execute="echo App\Models\ImportBatch::latest()->first()->id ?? 'none';")
if [ "$batch_id" = "none" ] || [ -z "$batch_id" ]; then
    echo "✗ No batch found in database"
    exit 1
fi
echo "✓ Latest batch ID: $batch_id"
echo ""

# Step 5: Get the mapping page
echo "Step 5: Testing column mapping page..."
map_page=$(curl -s -b "$COOKIE_FILE" "$BASE_URL/import/$batch_id/map")
map_csrf_token=$(echo "$map_page" | grep -o 'name="_token" value="[^"]*"' | head -n 1 | sed 's/name="_token" value="//;s/"$//')

if [ -z "$map_csrf_token" ]; then
    echo "✗ Failed to load mapping page or extract CSRF token"
    exit 1
fi

echo "✓ Mapping page loaded successfully"

# Check for auto-detected fields
if echo "$map_page" | grep -q "Transaction Date"; then
    echo "  ✓ Transaction Date field detected"
fi
if echo "$map_page" | grep -q "Account Code"; then
    echo "  ✓ Account Code field detected"
fi
echo ""

# Step 6: Submit the mapping
echo "Step 6: Submitting column mapping with period dates..."
mapping_response=$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
    -d "_token=$map_csrf_token" \
    -d "period_start=2025-01-01" \
    -d "period_end=2025-01-31" \
    -d "mapping[transaction_date]=Transaction Date" \
    -d "mapping[account_code]=Account Code" \
    -d "mapping[description]=Description" \
    -d "mapping[debit]=Debit" \
    -d "mapping[credit]=Credit" \
    -d "mapping[reference]=Reference" \
    -d "mapping[department]=Department" \
    -w "\nHTTP_CODE:%{http_code}" \
    "$BASE_URL/import/$batch_id/map" 2>&1)

mapping_http_code=$(echo "$mapping_response" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$mapping_http_code" = "302" ]; then
    echo "✓ Mapping submitted successfully (HTTP $mapping_http_code)"
else
    echo "✗ Mapping submission failed (HTTP $mapping_http_code)"
    exit 1
fi
echo ""

# Step 7: Wait for validation to complete
echo "Step 7: Waiting for validation to complete..."
sleep 3
echo ""

# Step 8: Check batch status in database
echo "Step 8: Checking batch status in database..."
php artisan tinker --execute="
\$batch = App\Models\ImportBatch::find($batch_id);
echo 'Status: ' . \$batch->status . PHP_EOL;
echo 'Total Rows: ' . \$batch->total_rows . PHP_EOL;
echo 'Valid Rows: ' . \$batch->valid_rows . PHP_EOL;
echo 'Error Rows: ' . \$batch->error_rows . PHP_EOL;
echo 'Duplicate Rows: ' . \$batch->duplicate_rows . PHP_EOL;
"
echo ""

# Step 9: Get the preview page
echo "Step 9: Testing preview page..."
preview_page=$(curl -s -b "$COOKIE_FILE" "$BASE_URL/import/$batch_id/preview")
preview_csrf_token=$(echo "$preview_page" | grep -o 'name="_token" value="[^"]*"' | head -n 1 | sed 's/name="_token" value="//;s/"$//')

if [ -z "$preview_csrf_token" ]; then
    echo "✗ Failed to load preview page"
    exit 1
fi

echo "✓ Preview page loaded successfully"

# Check for statistics
if echo "$preview_page" | grep -q "Valid Rows"; then
    echo "  ✓ Valid rows section displayed"
fi
if echo "$preview_page" | grep -q "Error Rows\|Errors"; then
    echo "  ✓ Error rows section displayed"
fi
echo ""

# Step 10: Commit the import
echo "Step 10: Committing import to database..."
commit_response=$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
    -d "_token=$preview_csrf_token" \
    -w "\nHTTP_CODE:%{http_code}" \
    "$BASE_URL/import/$batch_id/commit" 2>&1)

commit_http_code=$(echo "$commit_response" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$commit_http_code" = "302" ]; then
    echo "✓ Import committed successfully (HTTP $commit_http_code)"
    echo "  Job dispatched to queue"
else
    echo "✗ Import commit failed (HTTP $commit_http_code)"
    exit 1
fi
echo ""

# Step 11: Wait for the job to process
echo "Step 11: Waiting for commit job to process..."
echo "  (Running queue worker...)"

# Run the queue worker in the background for a few seconds
timeout 10 php artisan queue:work --stop-when-empty --tries=1 > /dev/null 2>&1 &
sleep 5
echo ""

# Step 12: Check the status page
echo "Step 12: Checking import status page..."
status_page=$(curl -s -b "$COOKIE_FILE" "$BASE_URL/import/$batch_id/status")

if echo "$status_page" | grep -q "Import Status\|Status"; then
    echo "✓ Status page loaded successfully"
fi

# Check status via JSON endpoint
status_json=$(curl -s -b "$COOKIE_FILE" "$BASE_URL/import/$batch_id/status-json")
echo "  Status JSON: $status_json"
echo ""

# Step 13: Check the records page
echo "Step 13: Checking financial records page..."
records_page=$(curl -s -b "$COOKIE_FILE" "$BASE_URL/records")

if echo "$records_page" | grep -q "Financial Records"; then
    echo "✓ Records page loaded successfully"
fi

# Check if actual data is displayed
if echo "$records_page" | grep -q "Office Supplies\|SaaS\|Payroll"; then
    echo "  ✓ Financial records are displayed on the page"
fi
echo ""

# Step 14: Get final statistics from database
echo "Step 14: Final statistics from database..."
echo "=========================================="
php artisan tinker --execute="
\$batch = App\Models\ImportBatch::find($batch_id);
echo 'BATCH INFORMATION:' . PHP_EOL;
echo '  ID: ' . \$batch->id . PHP_EOL;
echo '  Filename: ' . \$batch->original_filename . PHP_EOL;
echo '  Status: ' . \$batch->status . PHP_EOL;
echo '  Total Rows: ' . \$batch->total_rows . PHP_EOL;
echo '  Valid Rows: ' . \$batch->valid_rows . PHP_EOL;
echo '  Error Rows: ' . \$batch->error_rows . PHP_EOL;
echo '  Duplicate Rows: ' . \$batch->duplicate_rows . PHP_EOL;
echo '  Period: ' . \$batch->period_start . ' to ' . \$batch->period_end . PHP_EOL;
echo PHP_EOL;

\$recordCount = App\Models\FinancialRecord::count();
echo 'FINANCIAL RECORDS:' . PHP_EOL;
echo '  Total Count: ' . \$recordCount . PHP_EOL;
echo PHP_EOL;

if (\$recordCount > 0) {
    echo 'SAMPLE RECORDS (first 5):' . PHP_EOL;
    \$records = App\Models\FinancialRecord::orderBy('transaction_date')->limit(5)->get();
    foreach (\$records as \$record) {
        echo '  - ' . \$record->transaction_date . ' | ' . \$record->account_code . ' | ' . \$record->description . ' | Debit: ' . \$record->debit . ' | Credit: ' . \$record->credit . PHP_EOL;
    }
}
"
echo "=========================================="
echo ""

echo "=========================================="
echo "✓ All tests completed successfully!"
echo "=========================================="

# Clean up
rm -f "$COOKIE_FILE"
