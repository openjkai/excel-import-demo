<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('GL Transactions');

$headers = ['Transaction Date', 'Account Code', 'Description', 'Debit', 'Credit', 'Reference', 'Department'];
$sheet->fromArray($headers, null, 'A1');

$data = [
    ['2025-01-05', '1000', 'Office Supplies Purchase', 450.00, 0, 'INV-2025-001', 'Admin'],
    ['2025-01-05', '2000', 'Accounts Payable - Office Supplies', 0, 450.00, 'INV-2025-001', 'Admin'],
    ['2025-01-10', '4000', 'Monthly SaaS Revenue - Client A', 0, 12500.00, 'INV-2025-002', 'Sales'],
    ['2025-01-10', '1100', 'Accounts Receivable - Client A', 12500.00, 0, 'INV-2025-002', 'Sales'],
    ['2025-01-12', '5000', 'Cloud Hosting - AWS EC2', 3200.00, 0, 'INV-2025-003', 'Engineering'],
    ['2025-01-12', '1000', 'Cash Payment - AWS', 0, 3200.00, 'INV-2025-003', 'Engineering'],
    ['2025-01-15', '5100', 'Employee Payroll - January', 45000.00, 0, 'PAY-2025-001', 'HR'],
    ['2025-01-15', '1000', 'Payroll Bank Transfer', 0, 45000.00, 'PAY-2025-001', 'HR'],
    ['2025-01-18', '4000', 'Consulting Revenue - Project X', 0, 8750.00, 'INV-2025-004', 'Sales'],
    ['2025-01-18', '1100', 'Accounts Receivable - Project X', 8750.00, 0, 'INV-2025-004', 'Sales'],
    ['2025-01-20', '5200', 'Marketing Campaign - Google Ads', 2100.00, 0, 'MKT-2025-001', 'Marketing'],
    ['2025-01-20', '1000', 'Marketing Payment', 0, 2100.00, 'MKT-2025-001', 'Marketing'],
    ['2025-01-22', '1200', 'Prepaid Insurance - Q1', 4800.00, 0, 'INS-2025-001', 'Admin'],
    ['2025-01-22', '1000', 'Insurance Payment', 0, 4800.00, 'INS-2025-001', 'Admin'],
    ['2025-01-25', '4100', 'Product License Revenue', 0, 6200.00, 'LIC-2025-001', 'Sales'],
    ['2025-01-25', '1100', 'Accounts Receivable - License', 6200.00, 0, 'LIC-2025-001', 'Sales'],
    ['2025-01-28', '5300', 'Legal Consulting Fees', 1500.00, 0, 'LEG-2025-001', 'Admin'],
    ['2025-01-28', '2000', 'Accounts Payable - Legal', 0, 1500.00, 'LEG-2025-001', 'Admin'],
    // Intentional row with missing data to demo validation
    ['2025-01-30', '', 'Mystery Transaction - No Account', 500.00, 0, '', 'Unknown'],
    // Duplicate row for demo
    ['2025-01-05', '1000', 'Office Supplies Purchase', 450.00, 0, 'INV-2025-001', 'Admin'],
];

$row = 2;
foreach ($data as $record) {
    $sheet->fromArray($record, null, "A{$row}");
    $row++;
}

foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$sheet->getStyle('A1:G1')->getFont()->setBold(true);
$sheet->getStyle('A1:G1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__ . '/storage/app/sample_import.xlsx');

echo "Sample file created at storage/app/sample_import.xlsx\n";
