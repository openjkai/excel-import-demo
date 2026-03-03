#!/bin/bash

# Excel Import Application Test Script
# This script tests the complete import flow programmatically

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

# Step 1: Test the import page
echo "Step 1: Testing /import page..."
response=$(curl -s -c "$COOKIE_FILE" -w "\n%{http_code}" "$BASE_URL/import")
http_code=$(echo "$response" | tail -n 1)
if [ "$http_code" = "200" ]; then
    echo "✓ Import page loaded successfully (HTTP $http_code)"
else
    echo "✗ Import page failed (HTTP $http_code)"
    exit 1
fi
echo ""

# Step 2: Download sample file (verify it exists)
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
    -F "_token=$(grep XSRF-TOKEN "$COOKIE_FILE" | awk '{print $7}' | sed 's/%3D/=/g')" \
    -w "\n%{http_code}\n%{redirect_url}" \
    "$BASE_URL/import/upload")

upload_http_code=$(echo "$upload_response" | tail -n 2 | head -n 1)
redirect_url=$(echo "$upload_response" | tail -n 1)

if [ "$upload_http_code" = "302" ] || [ "$upload_http_code" = "200" ]; then
    echo "✓ File uploaded successfully (HTTP $upload_http_code)"
    if [ -n "$redirect_url" ]; then
        echo "  Redirect URL: $redirect_url"
    fi
else
    echo "✗ File upload failed (HTTP $upload_http_code)"
    echo "$upload_response" | head -n 20
    exit 1
fi
echo ""

# Step 4: Get the batch ID from the redirect or database
echo "Step 4: Getting batch ID..."
batch_id=$(php artisan tinker --execute="echo App\Models\ImportBatch::latest()->first()->id;")
echo "✓ Latest batch ID: $batch_id"
echo ""

# Step 5: Test the mapping page
echo "Step 5: Testing column mapping page..."
map_response=$(curl -s -b "$COOKIE_FILE" -w "\n%{http_code}" "$BASE_URL/import/$batch_id/map")
map_http_code=$(echo "$map_response" | tail -n 1)
if [ "$map_http_code" = "200" ]; then
    echo "✓ Mapping page loaded successfully (HTTP $map_http_code)"
    # Check if auto-mapping detected the columns
    if echo "$map_response" | grep -q "Transaction Date"; then
        echo "  ✓ Transaction Date field found"
    fi
    if echo "$map_response" | grep -q "Account Code"; then
        echo "  ✓ Account Code field found"
    fi
else
    echo "✗ Mapping page failed (HTTP $map_http_code)"
    exit 1
fi
echo ""

# Step 6: Submit the mapping with period dates
echo "Step 6: Submitting column mapping..."

# Extract CSRF token from the mapping page
csrf_token=$(echo "$map_response" | grep -o 'name="_token" value="[^"]*"' | sed 's/name="_token" value="//;s/"$//' | head -n 1)

mapping_response=$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
    -d "_token=$csrf_token" \
    -d "period_start=2025-01-01" \
    -d "period_end=2025-01-31" \
    -d "mapping[transaction_date]=Transaction Date" \
    -d "mapping[account_code]=Account Code" \
    -d "mapping[description]=Description" \
    -d "mapping[debit]=Debit" \
    -d "mapping[credit]=Credit" \
    -d "mapping[reference]=Reference" \
    -d "mapping[department]=Department" \
    -w "\n%{http_code}\n%{redirect_url}" \
    "$BASE_URL/import/$batch_id/map")

mapping_http_code=$(echo "$mapping_response" | tail -n 2 | head -n 1)
if [ "$mapping_http_code" = "302" ] || [ "$mapping_http_code" = "200" ]; then
    echo "✓ Mapping submitted successfully (HTTP $mapping_http_code)"
else
    echo "✗ Mapping submission failed (HTTP $mapping_http_code)"
    echo "$mapping_response" | head -n 20
    exit 1
fi
echo ""

# Step 7: Wait a moment for validation to complete
echo "Step 7: Waiting for validation..."
sleep 2
echo ""

# Step 8: Test the preview page
echo "Step 8: Testing preview page..."
preview_response=$(curl -s -b "$COOKIE_FILE" -w "\n%{http_code}" "$BASE_URL/import/$batch_id/preview")
preview_http_code=$(echo "$preview_response" | tail -n 1)
if [ "$preview_http_code" = "200" ]; then
    echo "✓ Preview page loaded successfully (HTTP $preview_http_code)"
    
    # Extract statistics from the preview page
    if echo "$preview_response" | grep -q "Valid Rows"; then
        echo "  ✓ Valid rows count displayed"
    fi
    if echo "$preview_response" | grep -q "Error Rows"; then
        echo "  ✓ Error rows count displayed"
    fi
    if echo "$preview_response" | grep -q "Duplicate Rows"; then
        echo "  ✓ Duplicate rows count displayed"
    fi
else
    echo "✗ Preview page failed (HTTP $preview_http_code)"
    exit 1
fi
echo ""

# Step 9: Commit the import
echo "Step 9: Committing import..."

# Extract CSRF token from preview page
csrf_token=$(echo "$preview_response" | grep -o 'name="_token" value="[^"]*"' | sed 's/name="_token" value="//;s/"$//' | head -n 1)

commit_response=$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
    -d "_token=$csrf_token" \
    -w "\n%{http_code}\n%{redirect_url}" \
    "$BASE_URL/import/$batch_id/commit")

commit_http_code=$(echo "$commit_response" | tail -n 2 | head -n 1)
if [ "$commit_http_code" = "302" ] || [ "$commit_http_code" = "200" ]; then
    echo "✓ Import committed successfully (HTTP $commit_http_code)"
else
    echo "✗ Import commit failed (HTTP $commit_http_code)"
    exit 1
fi
echo ""

# Step 10: Check the status page
echo "Step 10: Checking import status..."
sleep 3  # Wait for the job to process

status_response=$(curl -s -b "$COOKIE_FILE" -w "\n%{http_code}" "$BASE_URL/import/$batch_id/status")
status_http_code=$(echo "$status_response" | tail -n 1)
if [ "$status_http_code" = "200" ]; then
    echo "✓ Status page loaded successfully (HTTP $status_http_code)"
    
    if echo "$status_response" | grep -q "committed\|committing"; then
        echo "  ✓ Import status is committed/committing"
    fi
else
    echo "✗ Status page failed (HTTP $status_http_code)"
    exit 1
fi
echo ""

# Step 11: Check the records page
echo "Step 11: Checking financial records page..."
records_response=$(curl -s -b "$COOKIE_FILE" -w "\n%{http_code}" "$BASE_URL/records")
records_http_code=$(echo "$records_response" | tail -n 1)
if [ "$records_http_code" = "200" ]; then
    echo "✓ Records page loaded successfully (HTTP $records_http_code)"
    
    # Check if records are displayed
    if echo "$records_response" | grep -q "Office Supplies\|SaaS Revenue"; then
        echo "  ✓ Financial records are displayed"
    fi
else
    echo "✗ Records page failed (HTTP $records_http_code)"
    exit 1
fi
echo ""

# Step 12: Get final statistics from database
echo "Step 12: Final statistics from database..."
echo "----------------------------------------"
php artisan tinker --execute="
\$batch = App\Models\ImportBatch::find($batch_id);
echo 'Batch Status: ' . \$batch->status . PHP_EOL;
echo 'Total Rows: ' . \$batch->total_rows . PHP_EOL;
echo 'Valid Rows: ' . \$batch->valid_rows . PHP_EOL;
echo 'Error Rows: ' . \$batch->error_rows . PHP_EOL;
echo 'Duplicate Rows: ' . \$batch->duplicate_rows . PHP_EOL;
echo 'Period: ' . \$batch->period_start . ' to ' . \$batch->period_end . PHP_EOL;
echo PHP_EOL;
echo 'Financial Records Count: ' . App\Models\FinancialRecord::count() . PHP_EOL;
"
echo ""

echo "=========================================="
echo "✓ All tests passed successfully!"
echo "=========================================="

# Clean up
rm -f "$COOKIE_FILE"
