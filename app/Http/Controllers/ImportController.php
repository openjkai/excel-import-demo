<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\FinancialRecord;
use App\Jobs\CommitImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SpreadsheetHeadingImport;

class ImportController extends Controller
{
    public const INTERNAL_FIELDS = [
        'transaction_date' => ['label' => 'Transaction Date', 'required' => true, 'type' => 'date'],
        'account_code'     => ['label' => 'Account Code', 'required' => true, 'type' => 'string'],
        'description'      => ['label' => 'Description', 'required' => true, 'type' => 'string'],
        'debit'            => ['label' => 'Debit', 'required' => false, 'type' => 'numeric'],
        'credit'           => ['label' => 'Credit', 'required' => false, 'type' => 'numeric'],
        'reference'        => ['label' => 'Reference', 'required' => false, 'type' => 'string'],
        'department'       => ['label' => 'Department', 'required' => false, 'type' => 'string'],
    ];

    public function index()
    {
        $batches = ImportBatch::orderByDesc('created_at')->limit(20)->get();
        return view('import.index', compact('batches'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('imports', $filename);

        $data = Excel::toArray(new SpreadsheetHeadingImport, $file);
        $sheet = $data[0] ?? [];

        if (empty($sheet)) {
            Storage::delete($path);
            return back()->withErrors(['file' => 'The file appears to be empty.']);
        }

        $headers = array_keys($sheet[0] ?? []);

        $batch = ImportBatch::create([
            'filename' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'uploaded',
            'detected_headers' => $headers,
            'total_rows' => count($sheet),
        ]);

        foreach ($sheet as $index => $row) {
            ImportRow::create([
                'import_batch_id' => $batch->id,
                'row_number' => $index + 2,
                'raw_data' => $row,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('import.map', $batch);
    }

    public function map(ImportBatch $batch)
    {
        $headers = $batch->detected_headers ?? [];
        $internalFields = self::INTERNAL_FIELDS;
        $autoMapping = $this->autoDetectMapping($headers);

        return view('import.map', compact('batch', 'headers', 'internalFields', 'autoMapping'));
    }

    public function saveMapping(Request $request, ImportBatch $batch)
    {
        $request->validate([
            'mapping' => 'required|array',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $mapping = array_filter($request->input('mapping'), fn($v) => $v !== '' && $v !== null);

        $requiredFields = collect(self::INTERNAL_FIELDS)
            ->filter(fn($f) => $f['required'])
            ->keys()
            ->toArray();

        $missingRequired = array_diff($requiredFields, array_keys($mapping));
        if (!empty($missingRequired)) {
            $labels = collect($missingRequired)->map(fn($f) => self::INTERNAL_FIELDS[$f]['label'])->join(', ');
            return back()->withErrors(['mapping' => "Required fields not mapped: {$labels}"])->withInput();
        }

        $batch->update([
            'column_mapping' => $mapping,
            'period_start' => $request->input('period_start'),
            'period_end' => $request->input('period_end'),
            'status' => 'mapped',
        ]);

        $this->applyMappingToRows($batch, $mapping);

        return $this->runValidation($batch);
    }

    public function validateBatch(ImportBatch $batch)
    {
        return $this->runValidation($batch);
    }

    private function runValidation(ImportBatch $batch)
    {
        $mapping = $batch->column_mapping;
        $errors = [];
        $validCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;

        $seenKeys = [];

        $rows = $batch->rows()->get();

        foreach ($rows as $row) {
            $rowErrors = [];
            $mapped = $row->mapped_data ?? [];

            foreach (self::INTERNAL_FIELDS as $field => $config) {
                if ($config['required'] && empty($mapped[$field])) {
                    $rowErrors[] = "Missing required field: {$config['label']}";
                }

                if ($config['type'] === 'numeric' && !empty($mapped[$field]) && !is_numeric($mapped[$field])) {
                    $rowErrors[] = "{$config['label']} must be numeric";
                }

                if ($config['type'] === 'date' && !empty($mapped[$field])) {
                    $parsed = $this->parseDate($mapped[$field]);
                    if (!$parsed) {
                        $rowErrors[] = "{$config['label']} is not a valid date";
                    }
                }
            }

            $compositeKey = ($mapped['transaction_date'] ?? '') . '|' . ($mapped['account_code'] ?? '') . '|' . ($mapped['reference'] ?? '');
            if (isset($seenKeys[$compositeKey]) && !empty($mapped['account_code'])) {
                $rowErrors[] = "Duplicate of row {$seenKeys[$compositeKey]}";
                $duplicateCount++;
                $row->update(['status' => 'duplicate', 'errors' => $rowErrors]);
                $errorCount++;
                continue;
            }
            $seenKeys[$compositeKey] = $row->row_number;

            if (!empty($rowErrors)) {
                $row->update(['status' => 'invalid', 'errors' => $rowErrors]);
                $errorCount++;
                $errors["Row {$row->row_number}"] = $rowErrors;
            } else {
                $row->update(['status' => 'valid', 'errors' => null]);
                $validCount++;
            }
        }

        $batch->update([
            'status' => 'validated',
            'valid_rows' => $validCount,
            'error_rows' => $errorCount,
            'duplicate_rows' => $duplicateCount,
            'validation_errors' => $errors,
        ]);

        return redirect()->route('import.preview', $batch);
    }

    public function preview(ImportBatch $batch)
    {
        $validRows = $batch->rows()->where('status', 'valid')->limit(100)->get();
        $errorRows = $batch->rows()->whereIn('status', ['invalid', 'duplicate'])->get();
        $internalFields = self::INTERNAL_FIELDS;

        $existingCount = FinancialRecord::whereBetween('transaction_date', [
            $batch->period_start, $batch->period_end
        ])->count();

        return view('import.preview', compact('batch', 'validRows', 'errorRows', 'internalFields', 'existingCount'));
    }

    public function commit(ImportBatch $batch)
    {
        if ($batch->status !== 'validated') {
            return back()->withErrors(['batch' => 'Batch must be validated before committing.']);
        }

        $batch->update(['status' => 'committing']);

        CommitImportJob::dispatch($batch);

        return redirect()->route('import.status', $batch);
    }

    public function status(ImportBatch $batch)
    {
        return view('import.status', compact('batch'));
    }

    public function statusJson(ImportBatch $batch)
    {
        return response()->json([
            'status' => $batch->fresh()->status,
            'valid_rows' => $batch->valid_rows,
            'committed_at' => $batch->committed_at?->toDateTimeString(),
        ]);
    }

    public function records(Request $request)
    {
        $query = FinancialRecord::query();

        if ($request->filled('from')) {
            $query->where('transaction_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('transaction_date', '<=', $request->to);
        }

        $records = $query->orderBy('transaction_date')->orderBy('account_code')->paginate(50);

        return view('import.records', compact('records'));
    }

    private function autoDetectMapping(array $headers): array
    {
        $map = [];
        $aliases = [
            'transaction_date' => ['date', 'transaction_date', 'trans_date', 'txn_date', 'transaction date', 'posting_date'],
            'account_code'     => ['account', 'account_code', 'acct', 'account code', 'gl_account', 'account_number'],
            'description'      => ['description', 'desc', 'memo', 'narration', 'detail', 'particulars'],
            'debit'            => ['debit', 'debit_amount', 'dr', 'debit amount'],
            'credit'           => ['credit', 'credit_amount', 'cr', 'credit amount'],
            'reference'        => ['reference', 'ref', 'ref_no', 'invoice', 'voucher', 'reference number'],
            'department'       => ['department', 'dept', 'cost_center', 'division', 'segment'],
        ];

        foreach ($aliases as $field => $possibleNames) {
            foreach ($headers as $header) {
                $normalized = strtolower(trim(str_replace([' ', '-'], '_', $header)));
                if (in_array($normalized, $possibleNames)) {
                    $map[$field] = $header;
                    break;
                }
            }
        }

        return $map;
    }

    private function applyMappingToRows(ImportBatch $batch, array $mapping): void
    {
        foreach ($batch->rows()->cursor() as $row) {
            $mapped = [];
            foreach ($mapping as $internalField => $sourceHeader) {
                $mapped[$internalField] = $row->raw_data[$sourceHeader] ?? null;
            }
            $row->update(['mapped_data' => $mapped]);
        }
    }

    private function parseDate($value): ?string
    {
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
