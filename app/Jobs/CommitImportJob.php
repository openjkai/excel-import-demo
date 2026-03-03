<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\FinancialRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommitImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public ImportBatch $batch
    ) {}

    public function handle(): void
    {
        try {
            DB::transaction(function () {
                FinancialRecord::whereBetween('transaction_date', [
                    $this->batch->period_start,
                    $this->batch->period_end,
                ])->delete();

                $validRows = $this->batch->rows()->where('status', 'valid')->get();
                $records = [];

                foreach ($validRows as $row) {
                    $mapped = $row->mapped_data;
                    $records[] = [
                        'transaction_date' => $this->parseDate($mapped['transaction_date'] ?? ''),
                        'account_code'     => $mapped['account_code'] ?? '',
                        'description'      => $mapped['description'] ?? '',
                        'debit'            => (float) ($mapped['debit'] ?? 0),
                        'credit'           => (float) ($mapped['credit'] ?? 0),
                        'reference'        => $mapped['reference'] ?? null,
                        'department'       => $mapped['department'] ?? null,
                        'import_batch_id'  => $this->batch->id,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];

                    $row->update(['status' => 'committed']);
                }

                foreach (array_chunk($records, 500) as $chunk) {
                    FinancialRecord::insert($chunk);
                }

                $this->batch->update([
                    'status' => 'committed',
                    'committed_at' => now(),
                ]);
            });

            Log::info("Import batch {$this->batch->id} committed successfully. {$this->batch->valid_rows} records inserted.");
        } catch (\Exception $e) {
            $this->batch->update(['status' => 'failed']);
            Log::error("Import batch {$this->batch->id} failed: {$e->getMessage()}");
            throw $e;
        }
    }

    private function parseDate($value): string
    {
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return now()->format('Y-m-d');
            }
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    }
}
