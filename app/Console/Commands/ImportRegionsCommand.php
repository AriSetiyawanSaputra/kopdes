<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRegionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kopdes:import-regions
        {file : Path file CSV wilayah baku}
        {--type= : Jenis wilayah (provinces, regencies, districts, villages)}';

    /**
     * The console command description.
     */
    protected $description = 'Impor data wilayah administratif resmi (Provinsi, Kabupaten, Kecamatan, Desa) dari CSV secara streaming & chunked upsert (idempotent).';

    private const CHUNK_SIZE = 1000;

    /**
     * @var array<string, array{columns: list<string>, update: list<string>}>
     */
    private const SCHEMAS = [
        'provinces' => [
            'columns' => ['id', 'name'],
            'update' => ['name'],
        ],
        'regencies' => [
            'columns' => ['id', 'province_id', 'name'],
            'update' => ['province_id', 'name'],
        ],
        'districts' => [
            'columns' => ['id', 'regency_id', 'name'],
            'update' => ['regency_id', 'name'],
        ],
        'villages' => [
            'columns' => ['id', 'district_id', 'name'],
            'update' => ['district_id', 'name'],
        ],
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $type = strtolower(trim((string) $this->option('type')));

        if (! array_key_exists($type, self::SCHEMAS)) {
            $this->error('Opsi --type wajib salah satu dari: provinces, regencies, districts, villages.');

            return self::FAILURE;
        }

        if (! is_file($file) || ! is_readable($file)) {
            $this->error("File CSV tidak ditemukan atau tidak dapat dibaca: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            $this->error("Gagal membuka file CSV: {$file}");

            return self::FAILURE;
        }

        $startedAt = microtime(true);
        $schema = self::SCHEMAS[$type];
        $requiredColumns = $schema['columns'];

        try {
            $rawHeader = fgetcsv($handle);
            if ($rawHeader === false || empty($rawHeader)) {
                $this->error('File CSV kosong atau header tidak ditemukan.');

                return self::FAILURE;
            }

            $headers = array_map(function ($col) {
                $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $col);

                return strtolower(trim((string) $clean));
            }, $rawHeader);

            $colIndexMap = [];
            foreach ($requiredColumns as $reqCol) {
                $idx = array_search($reqCol, $headers, true);
                if ($idx === false) {
                    $this->error(
                        "Header CSV tidak sesuai untuk --type={$type}. Kolom wajib: ".implode(', ', $requiredColumns)
                    );

                    return self::FAILURE;
                }
                $colIndexMap[$reqCol] = $idx;
            }

            $chunk = [];
            $totalProcessed = 0;
            $chunksCommitted = 0;

            $progressBar = $this->output->createProgressBar();
            $progressBar->start();

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                $record = [];
                $missingValue = false;
                foreach ($requiredColumns as $col) {
                    $val = trim((string) ($row[$colIndexMap[$col]] ?? ''));
                    if ($val === '') {
                        $missingValue = true;
                        break;
                    }
                    $record[$col] = $val;
                }

                if ($missingValue) {
                    continue;
                }

                $chunk[] = $record;
                $totalProcessed++;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    DB::table($type)->upsert($chunk, ['id'], $schema['update']);
                    $chunksCommitted++;
                    $progressBar->advance(count($chunk));
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                DB::table($type)->upsert($chunk, ['id'], $schema['update']);
                $chunksCommitted++;
                $progressBar->advance(count($chunk));
            }

            $progressBar->finish();
            $this->newLine(2);

            $elapsed = round(microtime(true) - $startedAt, 2);
            $this->info("Impor wilayah '{$type}' selesai: {$totalProcessed} baris diproses dalam {$chunksCommitted} chunk ({$elapsed}s).");

            return self::SUCCESS;
        } finally {
            fclose($handle);
        }
    }
}
