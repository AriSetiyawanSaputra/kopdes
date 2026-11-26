<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateService
{
    /**
     * @var array<string, array{
     *     filename: string,
     *     title: string,
     *     headers: list<string>,
     *     example: list<string|int>,
     *     widths: array<string, int>,
     *     notes: list<string>
     * }>
     */
    private const TEMPLATES = [
        'products' => [
            'filename' => 'template-import-produk-kopdes.xlsx',
            'title' => 'Template Import Produk',
            'headers' => ['category_name', 'sku', 'barcode', 'name', 'unit', 'cost_price', 'selling_price', 'member_price'],
            'example' => ['Sembako', 'SKU-BRS-001', '8991234567890', 'Beras Premium 5kg', 'karung', 60000, 72000, 68000],
            'widths' => ['A' => 20, 'B' => 18, 'C' => 20, 'D' => 28, 'E' => 12, 'F' => 14, 'G' => 14, 'H' => 14],
            'notes' => [
                'category_name: Wajib sesuai nama kategori yang sudah terdaftar di Master Kategori.',
                'sku & barcode: Wajib unik (tidak boleh duplikat di file maupun di database).',
                'cost_price & selling_price: Angka >= 0 tanpa simbol Rp atau titik pemisah ribuan.',
                'member_price: Opsional (kosongkan jika tidak ada harga khusus member). Jika diisi, tidak boleh melebihi selling_price.',
            ],
        ],
        'stocks' => [
            'filename' => 'template-import-stok-awal-kopdes.xlsx',
            'title' => 'Template Import Stok Awal',
            'headers' => ['barcode', 'qty_add', 'min_stock', 'notes'],
            'example' => ['8991234567890', 50, 10, 'Stok awal pembukaan cabang'],
            'widths' => ['A' => 22, 'B' => 14, 'C' => 14, 'D' => 36],
            'notes' => [
                'barcode: Wajib sesuai barcode produk aktif pada Katalog Global dan unik di dalam file.',
                'qty_add: Jumlah stok masuk awal (bilangan bulat >= 1).',
                'min_stock: Opsional (bilangan bulat >= 0) untuk batas peringatan stok menipis.',
                'notes: Opsional, default "Import Stok Awal" pada audit log pergerakan stok.',
            ],
        ],
        'members' => [
            'filename' => 'template-import-member-kopdes.xlsx',
            'title' => 'Template Import Member',
            'headers' => ['name', 'phone', 'nik', 'card_uid', 'pin', 'joined_at'],
            'example' => ['Siti Aminah', '081234567899', '3502094501950001', '', '123456', '2026-01-15'],
            'widths' => ['A' => 26, 'B' => 18, 'C' => 22, 'D' => 20, 'E' => 12, 'F' => 16],
            'notes' => [
                'name: Wajib diisi (nama lengkap anggota).',
                'phone & nik: Opsional. Jika NIK diisi, harus unik (tidak boleh duplikat).',
                'card_uid: Opsional. Kosongkan jika kartu RFID fisik belum dibagikan (bisa di-binding nanti). Jika diisi, harus unik lintas Kasir & Member.',
                'pin: Opsional. Jika diisi untuk akses Portal Member, wajib tepat 6 digit angka (contoh: 123456).',
                'joined_at: Opsional format YYYY-MM-DD (default tanggal hari ini).',
            ],
        ],
    ];

    public function download(string $type): StreamedResponse
    {
        $spec = self::TEMPLATES[$type] ?? abort(404);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        foreach ($spec['headers'] as $idx => $header) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$col}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($spec['headers']));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B91C1C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '991B1B']],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        foreach ($spec['widths'] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');

        // Sheet 2: Contoh & Panduan Pengisian
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Contoh & Panduan');
        $guide->setCellValue('A1', strtoupper($spec['title']).' — PANDUAN PENGISIAN');
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('B91C1C');

        foreach ($spec['headers'] as $idx => $header) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $guide->setCellValue("{$col}3", $header);
            $guide->setCellValue("{$col}4", $spec['example'][$idx] ?? '');
        }
        $guide->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
        ]);
        $guide->getStyle("A4:{$lastCol}4")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        $noteRow = 6;
        $guide->setCellValue("A{$noteRow}", 'ATURAN VALIDASI:');
        $guide->getStyle("A{$noteRow}")->getFont()->setBold(true);
        $noteRow++;
        foreach ($spec['notes'] as $note) {
            $guide->setCellValue("A{$noteRow}", '- '.$note);
            $noteRow++;
        }

        foreach ($spec['widths'] as $col => $width) {
            $guide->getColumnDimension($col)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $spec['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
