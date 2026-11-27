<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\OperationalExpense;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    private const COLOR_PRIMARY = 'B91C1C';      // Merah KOPDES

    private const COLOR_PRIMARY_SOFT = 'FEF2F2'; // Latar merah lembut

    private const COLOR_HEADER_DARK = '1F2937';  // Slate gelap header tabel

    private const COLOR_ZEBRA = 'F9FAFB';        // Baris zebra halus

    private const COLOR_TOTAL_BG = 'F3F4F6';     // Latar baris total

    private const COLOR_BORDER = 'D1D5DB';       // Garis tepi halus

    private const COLOR_ACCENT_GREEN = '047857'; // Hijau laba / SHU

    private const COLOR_ACCENT_BLUE = '1D4ED8';  // Biru lintas cabang

    private const COLOR_ACCENT_ROSE = 'BE123C';  // Merah biaya operasional / rugi

    private const FORMAT_RUPIAH = '"Rp "#,##0;-"Rp "#,##0;"Rp "0';

    private const FORMAT_INTEGER = '#,##0';

    private const FORMAT_PERCENT = '0.00"%"';

    /**
     * Generate a professional A4 Landscape PDF response for Sales & Profit/Loss Report
     * (Omzet -> HPP Snapshot -> Laba Kotor -> Biaya Operasional -> Laba Bersih / SHU Bruto).
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportSalesPdf(User $actor, array $report, array $filters): Response
    {
        $meta = $this->buildReportMetadata($actor, $filters);

        $html = view('exports.reports.sales', [
            'summary' => $report['summary'],
            'branchSummaries' => $report['branch_summaries'],
            'transactions' => $report['transactions'],
            'meta' => $meta,
        ])->render();

        $filename = sprintf(
            'laporan-laba-rugi-kopdes-%s-sd-%s.pdf',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->renderPdfResponse($html, $filename, 'landscape');
    }

    /**
     * Generate a professional A4 Landscape PDF response for Branch Operational Expenses Report.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportExpensesPdf(User $actor, array $report, array $filters): Response
    {
        $meta = $this->buildReportMetadata($actor, $filters);

        $html = view('exports.reports.expenses', [
            'summary' => $report['summary'],
            'categorySummaries' => $report['category_summaries'],
            'expenses' => $report['expenses'],
            'meta' => $meta,
        ])->render();

        $filename = sprintf(
            'laporan-biaya-operasional-kopdes-%s-sd-%s.pdf',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->renderPdfResponse($html, $filename, 'landscape');
    }

    /**
     * Generate a professional A4 Landscape PDF response for Member Spending Recap & SHU Report.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportMembersPdf(User $actor, array $report, array $filters): Response
    {
        $meta = $this->buildReportMetadata($actor, $filters);

        $html = view('exports.reports.members', [
            'summary' => $report['summary'],
            'shuFinancials' => $report['shu_financials'] ?? $report['summary'],
            'members' => $report['members'],
            'meta' => $meta,
        ])->render();

        $filename = sprintf(
            'laporan-shu-member-kopdes-%s-sd-%s.pdf',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->renderPdfResponse($html, $filename, 'landscape');
    }

    /**
     * Generate a professional A4 Landscape PDF response for Cashier Shifts History Report.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportShiftsPdf(User $actor, array $report, array $filters): Response
    {
        $meta = $this->buildReportMetadata($actor, $filters);

        $html = view('exports.reports.shifts', [
            'summary' => $report['summary'],
            'shifts' => $report['shifts'],
            'meta' => $meta,
        ])->render();

        $filename = sprintf(
            'laporan-shift-kasir-kopdes-%s-sd-%s.pdf',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->renderPdfResponse($html, $filename, 'landscape');
    }

    /**
     * Generate a professional A4 Landscape PDF response for Transaction Returns & Void History Report.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportReturnsPdf(User $actor, array $report, array $filters): Response
    {
        $meta = $this->buildReportMetadata($actor, $filters);

        $html = view('exports.reports.returns', [
            'summary' => $report['summary'],
            'returns' => $report['returns'],
            'meta' => $meta,
        ])->render();

        $filename = sprintf(
            'laporan-retur-void-kopdes-%s-sd-%s.pdf',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->renderPdfResponse($html, $filename, 'landscape');
    }

    /**
     * Generate a professional 2-sheet Excel (.xlsx) workbook for Sales & Profit/Loss Report.
     * Sheet names remain ['Ringkasan per Cabang', 'Rincian Transaksi'] for backward compatibility.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportSalesExcel(User $actor, array $report, array $filters): StreamedResponse
    {
        $meta = $this->buildReportMetadata($actor, $filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $spreadsheet->getProperties()
            ->setCreator($actor->name)
            ->setTitle('Laporan Penjualan & Laba Rugi KOPDES')
            ->setSubject('Laporan Keuangan Berbasis Snapshot HPP & Biaya Operasional')
            ->setCompany('Koperasi Desa Merah Putih (KOPDES)');

        // ============================================================
        // SHEET 1: Ringkasan per Cabang (Laba Rugi Lengkap Pasca-Retur)
        // ============================================================
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Ringkasan per Cabang');

        $colCount1 = 14;
        $lastCol1 = Coordinate::stringFromColumnIndex($colCount1); // N

        $this->applyHeaderBanner(
            $sheet1,
            strtoupper($meta['institution_name']),
            'LAPORAN PENJUALAN & LABA RUGI PASCA-RETUR PER CABANG (OMZET BERSIH, HPP BERSIH, BIAYA OPS & LABA BERSIH)',
            $colCount1
        );

        $row = $this->writeMetadataBlock($sheet1, $meta, $colCount1, startRow: 4);
        $row++; // Blank spacer row

        // Executive Summary KPI Strip (2 baris: 10 metrik P&L Pasca-Retur + Hemat Member)
        $summary = $report['summary'];
        $kpiStart = $row;
        $sheet1->setCellValue("A{$row}", 'RINGKASAN EKSEKUTIF LABA RUGI PASCA-RETUR PERIODE TERPILIH');
        $sheet1->mergeCells("A{$row}:{$lastCol1}{$row}");
        $this->styleSectionTitle($sheet1, "A{$row}:{$lastCol1}{$row}");
        $row++;

        $kpiRow1Blocks = [
            ['A', 'B', 'Transaksi (Paid)', (int) ($summary['total_transactions'] ?? 0), self::FORMAT_INTEGER, '111827'],
            ['C', 'E', 'Omzet Bruto (Rp)', (float) ($summary['gross_revenue'] ?? $summary['total_revenue'] ?? 0), self::FORMAT_RUPIAH, '111827'],
            ['F', 'H', '(-) Total Retur (Rp)', (float) ($summary['total_returns'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_ROSE],
            ['I', 'K', '(=) Omzet Bersih (Rp)', (float) ($summary['net_revenue'] ?? $summary['total_revenue'] ?? 0), self::FORMAT_RUPIAH, '111827'],
            ['L', 'N', 'Total Hemat Member (Rp)', (float) ($summary['total_member_discount'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_BLUE],
        ];
        $valRow1 = $row + 1;
        foreach ($kpiRow1Blocks as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            if ($startCol !== $endCol) {
                $sheet1->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
                $sheet1->mergeCells("{$startCol}{$valRow1}:{$endCol}{$valRow1}");
            }
            $sheet1->setCellValue("{$startCol}{$row}", $label);
            $sheet1->setCellValue("{$startCol}{$valRow1}", $value);
            $sheet1->getStyle("{$startCol}{$valRow1}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet1->getStyle("{$startCol}{$valRow1}")->getFont()->getColor()->setRGB($colorRgb);
        }
        $sheet1->getStyle("A{$row}:{$lastCol1}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet1->getRowDimension($row)->setRowHeight(20);
        $sheet1->getStyle("A{$valRow1}:{$lastCol1}{$valRow1}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10.5],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet1->getRowDimension($valRow1)->setRowHeight(24);

        $row2Kpi = $valRow1 + 1;
        $valRow2 = $row2Kpi + 1;
        $kpiRow2Blocks = [
            ['A', 'C', '(-) HPP Bersih (Rp)', (float) ($summary['net_cogs'] ?? $summary['total_cogs'] ?? 0), self::FORMAT_RUPIAH, '374151'],
            ['D', 'E', 'Info HPP Rusak (Rp)', (float) ($summary['damaged_cogs'] ?? 0), self::FORMAT_RUPIAH, 'B45309'],
            ['F', 'H', '(=) Laba Kotor (Rp)', (float) ($summary['gross_profit'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_GREEN],
            ['I', 'J', '(-) Biaya Ops (Rp)', (float) ($summary['total_operational_expenses'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_ROSE],
            ['K', 'M', '(=) Laba Bersih / Dasar SHU (Rp)', (float) ($summary['net_profit'] ?? 0), self::FORMAT_RUPIAH, ((float) ($summary['net_profit'] ?? 0) >= 0 ? self::COLOR_ACCENT_GREEN : self::COLOR_ACCENT_ROSE)],
            ['N', 'N', 'Net %', (float) ($summary['net_margin_percentage'] ?? 0), self::FORMAT_PERCENT, 'B45309'],
        ];
        foreach ($kpiRow2Blocks as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            if ($startCol !== $endCol) {
                $sheet1->mergeCells("{$startCol}{$row2Kpi}:{$endCol}{$row2Kpi}");
                $sheet1->mergeCells("{$startCol}{$valRow2}:{$endCol}{$valRow2}");
            }
            $sheet1->setCellValue("{$startCol}{$row2Kpi}", $label);
            $sheet1->setCellValue("{$startCol}{$valRow2}", $value);
            $sheet1->getStyle("{$startCol}{$valRow2}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet1->getStyle("{$startCol}{$valRow2}")->getFont()->getColor()->setRGB($colorRgb);
        }
        $sheet1->getStyle("A{$row2Kpi}:{$lastCol1}{$row2Kpi}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet1->getRowDimension($row2Kpi)->setRowHeight(20);
        $sheet1->getStyle("A{$valRow2}:{$lastCol1}{$valRow2}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10.5],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet1->getRowDimension($valRow2)->setRowHeight(24);
        $this->applyThinBorders($sheet1, "A{$kpiStart}:{$lastCol1}{$valRow2}");

        $row = $valRow2 + 2;

        // Table 1: Branch Aggregation
        $sheet1->setCellValue("A{$row}", '1. AGREGASI LABA RUGI PASCA-RETUR PER CABANG KOPDES');
        $sheet1->mergeCells("A{$row}:{$lastCol1}{$row}");
        $this->styleSectionTitle($sheet1, "A{$row}:{$lastCol1}{$row}");
        $row++;

        $branchHeaders = [
            'No',
            'Kode Cabang',
            'Nama Cabang KOPDES',
            'Kabupaten / Kota',
            'Jml Trx',
            'Omzet Bruto (Rp)',
            'Total Retur (Rp)',
            'Omzet Bersih (Rp)',
            'HPP Bersih (Rp)',
            'HPP Rusak (Rp)',
            'Laba Kotor (Rp)',
            'Biaya Operasional (Rp)',
            'Laba Bersih (Rp)',
            'Margin Bersih (%)',
        ];
        $headerRow1 = $row;
        $this->writeTableHeaders($sheet1, $branchHeaders, $headerRow1);
        $row++;

        $dataStart1 = $row;
        $branches = $report['branch_summaries'];

        if (empty($branches)) {
            $sheet1->setCellValue("A{$row}", 'Tidak ada data cabang pada filter wilayah dan periode ini.');
            $sheet1->mergeCells("A{$row}:{$lastCol1}{$row}");
            $sheet1->getStyle("A{$row}:{$lastCol1}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
            $this->applyThinBorders($sheet1, "A{$row}:{$lastCol1}{$row}");
            $row++;
        } else {
            foreach ($branches as $index => $b) {
                $netProfitVal = (float) ($b['net_profit'] ?? 0);

                $sheet1->setCellValue("A{$row}", $index + 1);
                $this->setSafeTextCell($sheet1, "B{$row}", (string) ($b['branch_code'] ?? '-'));
                $this->setSafeTextCell($sheet1, "C{$row}", (string) ($b['branch_name'] ?? '-'));
                $this->setSafeTextCell($sheet1, "D{$row}", (string) ($b['regency_name'] ?? '-'));
                $sheet1->setCellValue("E{$row}", (int) ($b['total_transactions'] ?? 0));
                $sheet1->setCellValue("F{$row}", (float) ($b['gross_revenue'] ?? $b['total_revenue'] ?? 0));
                $sheet1->setCellValue("G{$row}", (float) ($b['total_returns'] ?? 0));
                $sheet1->setCellValue("H{$row}", (float) ($b['net_revenue'] ?? $b['total_revenue'] ?? 0));
                $sheet1->setCellValue("I{$row}", (float) ($b['net_cogs'] ?? $b['total_cogs'] ?? 0));
                $sheet1->setCellValue("J{$row}", (float) ($b['damaged_cogs'] ?? 0));
                $sheet1->setCellValue("K{$row}", (float) ($b['gross_profit'] ?? 0));
                $sheet1->setCellValue("L{$row}", (float) ($b['operational_expenses'] ?? 0));
                $sheet1->setCellValue("M{$row}", $netProfitVal);
                $sheet1->setCellValue("N{$row}", (float) ($b['net_margin_percentage'] ?? 0));

                $this->styleDataRow($sheet1, $row, $colCount1, isAlt: $index % 2 === 1);
                $sheet1->getStyle("A{$row}:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet1->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet1->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
                $sheet1->getStyle("F{$row}:M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet1->getStyle("F{$row}:M{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet1->getStyle("G{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
                $sheet1->getStyle("H{$row}")->getFont()->setBold(true);
                $sheet1->getStyle("J{$row}")->getFont()->getColor()->setRGB('B45309');
                $sheet1->getStyle("K{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
                $sheet1->getStyle("L{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
                $sheet1->getStyle("M{$row}")->getFont()->setBold(true)->getColor()->setRGB(
                    $netProfitVal >= 0 ? self::COLOR_ACCENT_GREEN : self::COLOR_ACCENT_ROSE
                );
                $sheet1->getStyle("N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet1->getStyle("N{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);
                $row++;
            }

            $dataEnd1 = $row - 1;
            $sheet1->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
            $sheet1->mergeCells("A{$row}:D{$row}");
            $sheet1->setCellValue("E{$row}", "=SUM(E{$dataStart1}:E{$dataEnd1})");
            $sheet1->setCellValue("F{$row}", "=SUM(F{$dataStart1}:F{$dataEnd1})");
            $sheet1->setCellValue("G{$row}", "=SUM(G{$dataStart1}:G{$dataEnd1})");
            $sheet1->setCellValue("H{$row}", "=SUM(H{$dataStart1}:H{$dataEnd1})");
            $sheet1->setCellValue("I{$row}", "=SUM(I{$dataStart1}:I{$dataEnd1})");
            $sheet1->setCellValue("J{$row}", "=SUM(J{$dataStart1}:J{$dataEnd1})");
            $sheet1->setCellValue("K{$row}", "=SUM(K{$dataStart1}:K{$dataEnd1})");
            $sheet1->setCellValue("L{$row}", "=SUM(L{$dataStart1}:L{$dataEnd1})");
            $sheet1->setCellValue("M{$row}", "=SUM(M{$dataStart1}:M{$dataEnd1})");
            $sheet1->setCellValue("N{$row}", "=IF(H{$row}>0,(M{$row}/H{$row})*100,0)");

            $this->styleTotalRow($sheet1, $row, $colCount1);
            $sheet1->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
            $sheet1->getStyle("F{$row}:M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("F{$row}:M{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet1->getStyle("G{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
            $sheet1->getStyle("J{$row}")->getFont()->getColor()->setRGB('B45309');
            $sheet1->getStyle("K{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
            $sheet1->getStyle("L{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
            $sheet1->getStyle("M{$row}")->getFont()->getColor()->setRGB(
                ((float) ($summary['net_profit'] ?? 0)) >= 0 ? self::COLOR_ACCENT_GREEN : self::COLOR_ACCENT_ROSE
            );
            $sheet1->getStyle("N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("N{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);
            $row++;
        }

        $this->finalizeSheetLayout($sheet1, $colCount1, $headerRow1, [
            'A' => 6,
            'B' => 14,
            'C' => 24,
            'D' => 18,
            'E' => 10,
            'F' => 17,
            'G' => 16,
            'H' => 17,
            'I' => 17,
            'J' => 16,
            'K' => 17,
            'L' => 18,
            'M' => 18,
            'N' => 14,
        ]);

        // ============================================================
        // SHEET 2: Rincian Transaksi Lunas (Paid)
        // ============================================================
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Rincian Transaksi');

        $this->applyHeaderBanner(
            $sheet2,
            strtoupper($meta['institution_name']),
            'DAFTAR RINCIAN TRANSAKSI LUNAS (PAID) BERBASIS SNAPSHOT HPP & HEMAT MEMBER',
            15
        );

        $row2 = $this->writeMetadataBlock($sheet2, $meta, 15, startRow: 4);
        $row2++;

        $txHeaders = [
            'No',
            'No. Invoice',
            'Waktu Lunas',
            'Kode Cabang',
            'Nama Cabang',
            'Kasir',
            'Kode Member',
            'Nama Pembeli',
            'KOPDES Asal Member',
            'Metode Bayar',
            'Jml Item',
            'Hemat Member (Rp)',
            'Omzet (Rp)',
            'HPP Snapshot (Rp)',
            'Laba Kotor (Rp)',
        ];
        $headerRow2 = $row2;
        $this->writeTableHeaders($sheet2, $txHeaders, $headerRow2);
        $row2++;

        $dataStart2 = $row2;
        $transactions = $report['transactions'];

        if (empty($transactions)) {
            $sheet2->setCellValue("A{$row2}", 'Belum ada transaksi berstatus lunas (paid) pada periode dan filter ini.');
            $sheet2->mergeCells("A{$row2}:O{$row2}");
            $sheet2->getStyle("A{$row2}:O{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("A{$row2}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
            $this->applyThinBorders($sheet2, "A{$row2}:O{$row2}");
            $row2++;
        } else {
            foreach ($transactions as $index => $tx) {
                $branch = $tx['branch'] ?? null;
                $cashier = $tx['cashier'] ?? null;
                $member = $tx['member'] ?? null;

                $branchCode = is_array($branch) ? ($branch['code'] ?? '-') : ($branch->code ?? '-');
                $branchName = is_array($branch) ? ($branch['name'] ?? '-') : ($branch->name ?? '-');
                $cashierName = is_array($cashier) ? ($cashier['name'] ?? '-') : ($cashier->name ?? '-');

                $paymentLabel = strtoupper((string) ($tx['payment_method'] ?? '-'));
                if (! empty($tx['midtrans_payment_type'])) {
                    $paymentLabel .= ' ('.strtoupper((string) $tx['midtrans_payment_type']).')';
                }

                $sheet2->setCellValue("A{$row2}", $index + 1);
                $this->setSafeTextCell($sheet2, "B{$row2}", (string) ($tx['invoice_number'] ?? '-'));
                $this->setSafeTextCell($sheet2, "C{$row2}", (string) ($tx['paid_at'] ?? '-'));
                $this->setSafeTextCell($sheet2, "D{$row2}", (string) $branchCode);
                $this->setSafeTextCell($sheet2, "E{$row2}", (string) $branchName);
                $this->setSafeTextCell($sheet2, "F{$row2}", (string) $cashierName);
                $this->setSafeTextCell($sheet2, "G{$row2}", $member ? (string) ($member['member_code'] ?? '-') : '-');
                $this->setSafeTextCell($sheet2, "H{$row2}", $member ? (string) ($member['name'] ?? '-') : 'Pembeli Umum');
                $this->setSafeTextCell($sheet2, "I{$row2}", $member ? (string) ($member['home_branch_name'] ?? '-') : '-');
                $this->setSafeTextCell($sheet2, "J{$row2}", $paymentLabel);
                $sheet2->setCellValue("K{$row2}", (int) ($tx['items_count'] ?? 0));
                $sheet2->setCellValue("L{$row2}", (float) ($tx['member_discount_total'] ?? 0));
                $sheet2->setCellValue("M{$row2}", (float) ($tx['total_amount'] ?? 0));
                $sheet2->setCellValue("N{$row2}", (float) ($tx['total_cogs'] ?? 0));
                $sheet2->setCellValue("O{$row2}", (float) ($tx['gross_profit'] ?? 0));

                $this->styleDataRow($sheet2, $row2, 15, isAlt: $index % 2 === 1);
                $sheet2->getStyle("A{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet2->getStyle("C{$row2}:D{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet2->getStyle("J{$row2}:K{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet2->getStyle("K{$row2}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
                $sheet2->getStyle("L{$row2}:O{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet2->getStyle("L{$row2}:O{$row2}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet2->getStyle("L{$row2}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_BLUE);
                $sheet2->getStyle("O{$row2}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
                $row2++;
            }

            $dataEnd2 = $row2 - 1;
            $sheet2->setCellValue("A{$row2}", 'TOTAL RINCIAN TRANSAKSI');
            $sheet2->mergeCells("A{$row2}:J{$row2}");
            $sheet2->setCellValue("K{$row2}", "=SUM(K{$dataStart2}:K{$dataEnd2})");
            $sheet2->setCellValue("L{$row2}", "=SUM(L{$dataStart2}:L{$dataEnd2})");
            $sheet2->setCellValue("M{$row2}", "=SUM(M{$dataStart2}:M{$dataEnd2})");
            $sheet2->setCellValue("N{$row2}", "=SUM(N{$dataStart2}:N{$dataEnd2})");
            $sheet2->setCellValue("O{$row2}", "=SUM(O{$dataStart2}:O{$dataEnd2})");

            $this->styleTotalRow($sheet2, $row2, 15);
            $sheet2->getStyle("A{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("K{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("K{$row2}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
            $sheet2->getStyle("L{$row2}:O{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("L{$row2}:O{$row2}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet2->getStyle("L{$row2}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_BLUE);
            $sheet2->getStyle("O{$row2}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
            $row2++;
        }

        $this->finalizeSheetLayout($sheet2, 15, $headerRow2, [
            'A' => 6,
            'B' => 28,
            'C' => 20,
            'D' => 15,
            'E' => 24,
            'F' => 18,
            'G' => 22,
            'H' => 22,
            'I' => 24,
            'J' => 16,
            'K' => 10,
            'L' => 17,
            'M' => 18,
            'N' => 18,
            'O' => 18,
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = sprintf(
            'laporan-laba-rugi-kopdes-%s-sd-%s.xlsx',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->streamExcelResponse($spreadsheet, $filename);
    }

    /**
     * Generate a professional 2-sheet Excel (.xlsx) workbook for Branch Operational Expenses Report:
     * Sheet 1: Rekap per Kategori, Sheet 2: Rincian Biaya Operasional.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportExpensesExcel(User $actor, array $report, array $filters): StreamedResponse
    {
        $meta = $this->buildReportMetadata($actor, $filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $spreadsheet->getProperties()
            ->setCreator($actor->name)
            ->setTitle('Laporan Biaya Operasional Cabang KOPDES')
            ->setSubject('Rekap Pengeluaran Operasional KOPDES')
            ->setCompany('Koperasi Desa Merah Putih (KOPDES)');

        // ============================================================
        // SHEET 1: Rekap per Kategori
        // ============================================================
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Rekap Kategori');

        $this->applyHeaderBanner(
            $sheet1,
            strtoupper($meta['institution_name']),
            'LAPORAN BIAYA OPERASIONAL CABANG — REKAPITULASI SUBTOTAL PER KATEGORI',
            9
        );

        $row = $this->writeMetadataBlock($sheet1, $meta, 9, startRow: 4, isExpenseReport: true);
        $row++;

        $summary = $report['summary'];
        $kpiStart = $row;
        $sheet1->setCellValue("A{$row}", 'RINGKASAN BIAYA OPERASIONAL PERIODE TERPILIH');
        $sheet1->mergeCells("A{$row}:I{$row}");
        $this->styleSectionTitle($sheet1, "A{$row}:I{$row}");
        $row++;

        $valRow = $row + 1;
        $kpiBlocks = [
            ['A', 'B', 'Jumlah Catatan Biaya', (int) ($summary['total_count'] ?? 0), self::FORMAT_INTEGER, '111827'],
            ['C', 'E', 'Total Biaya Operasional (Rp)', (float) ($summary['total_amount'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_ROSE],
            ['F', 'G', 'Rata-rata / Catatan (Rp)', (float) ($summary['average_amount'] ?? 0), self::FORMAT_RUPIAH, '374151'],
            ['H', 'I', 'Kategori Aktif', (int) ($summary['active_categories_count'] ?? 0), self::FORMAT_INTEGER, '1D4ED8'],
        ];
        foreach ($kpiBlocks as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            $sheet1->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
            $sheet1->mergeCells("{$startCol}{$valRow}:{$endCol}{$valRow}");
            $sheet1->setCellValue("{$startCol}{$row}", $label);
            $sheet1->setCellValue("{$startCol}{$valRow}", $value);
            $sheet1->getStyle("{$startCol}{$valRow}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet1->getStyle("{$startCol}{$valRow}")->getFont()->getColor()->setRGB($colorRgb);
        }

        $sheet1->getStyle("A{$row}:I{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet1->getRowDimension($row)->setRowHeight(20);
        $sheet1->getStyle("A{$valRow}:I{$valRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet1->getRowDimension($valRow)->setRowHeight(26);
        $this->applyThinBorders($sheet1, "A{$kpiStart}:I{$valRow}");

        $row = $valRow + 2;

        $sheet1->setCellValue("A{$row}", '1. REKAPITULASI SUBTOTAL PER KATEGORI BIAYA');
        $sheet1->mergeCells("A{$row}:I{$row}");
        $this->styleSectionTitle($sheet1, "A{$row}:I{$row}");
        $row++;

        $catHeaders = [
            'No',
            'Kode Kategori',
            'Nama Kategori Biaya',
            'Jumlah Catatan',
            'Total Nominal (Rp)',
            'Porsi (%)',
        ];
        $headerRow1 = $row;
        $this->writeTableHeaders($sheet1, $catHeaders, $headerRow1);
        $row++;

        $dataStart1 = $row;
        $categories = $report['category_summaries'];
        $totalRowNum1 = $dataStart1 + count($categories);

        foreach ($categories as $idx => $cat) {
            $sheet1->setCellValue("A{$row}", $idx + 1);
            $this->setSafeTextCell($sheet1, "B{$row}", (string) ($cat['category'] ?? '-'));
            $this->setSafeTextCell($sheet1, "C{$row}", (string) ($cat['label'] ?? '-'));
            $sheet1->setCellValue("D{$row}", (int) ($cat['count'] ?? 0));
            $sheet1->setCellValue("E{$row}", (float) ($cat['total_amount'] ?? 0));
            $sheet1->setCellValue("F{$row}", "=IF(E{$totalRowNum1}>0,(E{$row}/E{$totalRowNum1})*100,0)");

            $this->styleDataRow($sheet1, $row, 6, isAlt: $idx % 2 === 1);
            $sheet1->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
            $sheet1->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet1->getStyle("E{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
            $sheet1->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);
            $row++;
        }

        $dataEnd1 = $row - 1;
        $sheet1->setCellValue("A{$row}", 'TOTAL BIAYA OPERASIONAL');
        $sheet1->mergeCells("A{$row}:C{$row}");
        $sheet1->setCellValue("D{$row}", "=SUM(D{$dataStart1}:D{$dataEnd1})");
        $sheet1->setCellValue("E{$row}", "=SUM(E{$dataStart1}:E{$dataEnd1})");
        $sheet1->setCellValue("F{$row}", "=IF(E{$row}>0,100,0)");
        $this->styleTotalRow($sheet1, $row, 6);
        $sheet1->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
        $sheet1->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $sheet1->getStyle("E{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
        $sheet1->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);

        $this->finalizeSheetLayout($sheet1, 9, $headerRow1, [
            'A' => 6,
            'B' => 20,
            'C' => 30,
            'D' => 16,
            'E' => 22,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 14,
        ]);

        // ============================================================
        // SHEET 2: Rincian Biaya Operasional
        // ============================================================
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Rincian Biaya Operasional');

        $this->applyHeaderBanner(
            $sheet2,
            strtoupper($meta['institution_name']),
            'DAFTAR RINCIAN TRANSAKSI BIAYA OPERASIONAL CABANG',
            9
        );

        $row2 = $this->writeMetadataBlock($sheet2, $meta, 9, startRow: 4, isExpenseReport: true);
        $row2++;

        $expHeaders = [
            'No',
            'Tanggal',
            'Kode Cabang',
            'Nama Cabang KOPDES',
            'Kategori',
            'Deskripsi Pengeluaran',
            'Catatan',
            'Pencatat',
            'Nominal (Rp)',
        ];
        $headerRow2 = $row2;
        $this->writeTableHeaders($sheet2, $expHeaders, $headerRow2);
        $row2++;

        $dataStart2 = $row2;
        $expenses = $report['expenses'];

        if (empty($expenses)) {
            $sheet2->setCellValue("A{$row2}", 'Belum ada catatan biaya operasional pada periode dan filter ini.');
            $sheet2->mergeCells("A{$row2}:I{$row2}");
            $sheet2->getStyle("A{$row2}:I{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("A{$row2}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
            $this->applyThinBorders($sheet2, "A{$row2}:I{$row2}");
            $row2++;
        } else {
            foreach ($expenses as $idx => $exp) {
                $branch = $exp['branch'] ?? null;
                $recorder = $exp['user'] ?? null;

                $sheet2->setCellValue("A{$row2}", $idx + 1);
                $this->setSafeTextCell($sheet2, "B{$row2}", (string) ($exp['expense_date'] ?? '-'));
                $this->setSafeTextCell($sheet2, "C{$row2}", (string) ($branch['code'] ?? '-'));
                $this->setSafeTextCell($sheet2, "D{$row2}", (string) ($branch['name'] ?? '-'));
                $this->setSafeTextCell($sheet2, "E{$row2}", (string) ($exp['category_label'] ?? '-'));
                $this->setSafeTextCell($sheet2, "F{$row2}", (string) ($exp['description'] ?? '-'));
                $this->setSafeTextCell($sheet2, "G{$row2}", (string) ($exp['notes'] ?? '-'));
                $this->setSafeTextCell($sheet2, "H{$row2}", (string) ($recorder['name'] ?? '-'));
                $sheet2->setCellValue("I{$row2}", (float) ($exp['amount'] ?? 0));

                $this->styleDataRow($sheet2, $row2, 9, isAlt: $idx % 2 === 1);
                $sheet2->getStyle("A{$row2}:C{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet2->getStyle("I{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet2->getStyle("I{$row2}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet2->getStyle("I{$row2}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
                $row2++;
            }

            $dataEnd2 = $row2 - 1;
            $sheet2->setCellValue("A{$row2}", 'TOTAL BIAYA OPERASIONAL');
            $sheet2->mergeCells("A{$row2}:H{$row2}");
            $sheet2->setCellValue("I{$row2}", "=SUM(I{$dataStart2}:I{$dataEnd2})");

            $this->styleTotalRow($sheet2, $row2, 9);
            $sheet2->getStyle("A{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("I{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("I{$row2}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet2->getStyle("I{$row2}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
            $row2++;
        }

        $this->finalizeSheetLayout($sheet2, 9, $headerRow2, [
            'A' => 6,
            'B' => 14,
            'C' => 15,
            'D' => 25,
            'E' => 22,
            'F' => 34,
            'G' => 26,
            'H' => 20,
            'I' => 20,
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = sprintf(
            'laporan-biaya-operasional-kopdes-%s-sd-%s.xlsx',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->streamExcelResponse($spreadsheet, $filename);
    }

    /**
     * Generate a professional Excel (.xlsx) workbook for Member Spending Recap & SHU Report.
     * Sheet name remains ['Rekap Member (SHU)'] for backward compatibility.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportMembersExcel(User $actor, array $report, array $filters): StreamedResponse
    {
        $meta = $this->buildReportMetadata($actor, $filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $spreadsheet->getProperties()
            ->setCreator($actor->name)
            ->setTitle('Laporan SHU & Rekap Transaksi Member KOPDES')
            ->setSubject('Perhitungan Dasar SHU (Laba Bersih) & Partisipasi Belanja Anggota')
            ->setCompany('Koperasi Desa Merah Putih (KOPDES)');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Member (SHU)');

        $colCount = 12;
        $lastCol = Coordinate::stringFromColumnIndex($colCount); // L

        $this->applyHeaderBanner(
            $sheet,
            strtoupper($meta['institution_name']),
            'LAPORAN SISA HASIL USAHA (SHU) & REKAP BELANJA ANGGOTA (BASIS LABA BERSIH)',
            $colCount
        );

        $row = $this->writeMetadataBlock($sheet, $meta, $colCount, startRow: 4);
        $row++;

        $summary = $report['summary'];
        $shuFinancials = $report['shu_financials'] ?? $summary;
        $shuBasis = (float) ($shuFinancials['shu_basis'] ?? max(0.0, (float) ($shuFinancials['net_profit'] ?? 0)));

        // ============================================================
        // BLOK 1: Rincian Komponen Dasar SHU (Laba Rugi Pasca-Retur)
        // ============================================================
        $shuStart = $row;
        $sheet->setCellValue("A{$row}", '1. RINCIAN KOMPONEN DASAR SHU PASCA-RETUR (OMZET BRUTO - RETUR -> OMZET BERSIH - HPP BERSIH -> LABA KOTOR - BIAYA OPS -> DASAR SHU)');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->styleSectionTitle($sheet, "A{$row}:{$lastCol}{$row}");
        $row++;

        $shuValRow1 = $row + 1;
        $shuBlocksRow1 = [
            ['A', 'B', 'Omzet Bruto (Paid)', (float) ($shuFinancials['gross_revenue'] ?? $shuFinancials['total_revenue'] ?? 0), self::FORMAT_RUPIAH, '111827'],
            ['C', 'D', '(-) Total Retur', (float) ($shuFinancials['total_returns'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_ROSE],
            ['E', 'F', '(=) Omzet Bersih', (float) ($shuFinancials['net_revenue'] ?? $shuFinancials['total_revenue'] ?? 0), self::FORMAT_RUPIAH, '111827'],
            ['G', 'H', '(-) HPP Bersih', (float) ($shuFinancials['net_cogs'] ?? $shuFinancials['total_cogs'] ?? 0), self::FORMAT_RUPIAH, '374151'],
            ['I', 'J', 'Info HPP Rusak Retur', (float) ($shuFinancials['damaged_cogs'] ?? 0), self::FORMAT_RUPIAH, 'B45309'],
            ['K', 'L', 'Total Hemat Member', (float) ($shuFinancials['total_member_discount'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_BLUE],
        ];
        foreach ($shuBlocksRow1 as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            $sheet->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
            $sheet->mergeCells("{$startCol}{$shuValRow1}:{$endCol}{$shuValRow1}");
            $sheet->setCellValue("{$startCol}{$row}", $label);
            $sheet->setCellValue("{$startCol}{$shuValRow1}", $value);
            $sheet->getStyle("{$startCol}{$shuValRow1}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet->getStyle("{$startCol}{$shuValRow1}")->getFont()->getColor()->setRGB($colorRgb);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle("A{$shuValRow1}:{$lastCol}{$shuValRow1}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10.5],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($shuValRow1)->setRowHeight(24);

        $shuRow2 = $shuValRow1 + 1;
        $shuValRow = $shuRow2 + 1;
        $shuBlocksRow2 = [
            ['A', 'C', '(=) Laba Kotor', (float) ($shuFinancials['gross_profit'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_GREEN],
            ['D', 'F', '(-) Biaya Operasional', (float) ($shuFinancials['total_operational_expenses'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_ROSE],
            ['G', 'J', '(=) Laba Bersih / Dasar SHU', (float) ($shuFinancials['net_profit'] ?? 0), self::FORMAT_RUPIAH, ((float) ($shuFinancials['net_profit'] ?? 0) >= 0 ? self::COLOR_ACCENT_GREEN : self::COLOR_ACCENT_ROSE)],
            ['K', 'L', 'Margin Bersih (%)', (float) ($shuFinancials['net_margin_percentage'] ?? 0), self::FORMAT_PERCENT, 'B45309'],
        ];
        foreach ($shuBlocksRow2 as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            $sheet->mergeCells("{$startCol}{$shuRow2}:{$endCol}{$shuRow2}");
            $sheet->mergeCells("{$startCol}{$shuValRow}:{$endCol}{$shuValRow}");
            $sheet->setCellValue("{$startCol}{$shuRow2}", $label);
            $sheet->setCellValue("{$startCol}{$shuValRow}", $value);
            $sheet->getStyle("{$startCol}{$shuValRow}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet->getStyle("{$startCol}{$shuValRow}")->getFont()->getColor()->setRGB($colorRgb);
        }

        $sheet->getStyle("A{$shuRow2}:{$lastCol}{$shuRow2}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($shuRow2)->setRowHeight(20);
        $sheet->getStyle("A{$shuValRow}:{$lastCol}{$shuValRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($shuValRow)->setRowHeight(26);
        $this->applyThinBorders($sheet, "A{$shuStart}:{$lastCol}{$shuValRow}");

        $row = $shuValRow + 2;

        // ============================================================
        // BLOK 2: Ringkasan Partisipasi Belanja Anggota
        // ============================================================
        $kpiStart = $row;
        $sheet->setCellValue("A{$row}", '2. RINGKASAN PARTISIPASI BELANJA ANGGOTA (KOPDES ASAL VS LINTAS CABANG)');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->styleSectionTitle($sheet, "A{$row}:{$lastCol}{$row}");
        $row++;

        $kpiMerges = [
            ['A', 'C'],
            ['D', 'E'],
            ['F', 'G'],
            ['H', 'I'],
            ['J', 'L'],
        ];
        foreach ($kpiMerges as [$startCol, $endCol]) {
            $sheet->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
            $sheet->mergeCells("{$startCol}".($row + 1).":{$endCol}".($row + 1));
        }

        $sheet->setCellValue("A{$row}", 'Anggota Aktif Belanja / Terdaftar');
        $sheet->setCellValue("D{$row}", 'Total Transaksi (Paid)');
        $sheet->setCellValue("F{$row}", 'Belanja KOPDES Asal (Rp)');
        $sheet->setCellValue("H{$row}", 'Belanja Lintas Cabang (Rp)');
        $sheet->setCellValue("J{$row}", 'Total Akumulasi Belanja Member (Rp)');

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);

        $valRow = $row + 1;
        $sheet->setCellValue(
            "A{$valRow}",
            sprintf(
                '%s / %s Anggota',
                number_format((int) ($summary['active_shopping_members'] ?? 0), 0, ',', '.'),
                number_format((int) ($summary['total_members'] ?? 0), 0, ',', '.')
            )
        );
        $sheet->setCellValue("D{$valRow}", (int) ($summary['total_transactions'] ?? 0));
        $sheet->setCellValue("F{$valRow}", (float) ($summary['total_home_branch_spend'] ?? 0));
        $sheet->setCellValue("H{$valRow}", (float) ($summary['total_other_branch_spend'] ?? 0));
        $sheet->setCellValue("J{$valRow}", (float) ($summary['total_accumulated_spend'] ?? 0));

        $sheet->getStyle("D{$valRow}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
        $sheet->getStyle("F{$valRow}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $sheet->getStyle("H{$valRow}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $sheet->getStyle("J{$valRow}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);

        $sheet->getStyle("A{$valRow}:{$lastCol}{$valRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '111827']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("H{$valRow}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_BLUE);
        $sheet->getStyle("J{$valRow}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
        $sheet->getRowDimension($valRow)->setRowHeight(26);
        $this->applyThinBorders($sheet, "A{$kpiStart}:{$lastCol}{$valRow}");

        $row = $valRow + 2;

        // Table Header
        $headers = [
            'No',
            'Kode Member',
            'Nama Anggota',
            'Status Kartu',
            'Kode KOPDES Asal',
            'Nama KOPDES Asal',
            'Jml Transaksi (Paid)',
            'Belanja di KOPDES Asal (Rp)',
            'Belanja di KOPDES Lain (Rp)',
            'Total Akumulasi Belanja (Rp)',
            'Porsi Belanja (%)',
            'Estimasi Porsi SHU (Rp)',
        ];
        $headerRow = $row;
        $this->writeTableHeaders($sheet, $headers, $headerRow);
        $row++;

        $dataStart = $row;
        $members = $report['members'];

        if (empty($members)) {
            $sheet->setCellValue("A{$row}", 'Belum ada data anggota pada filter wilayah dan periode ini.');
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
            $this->applyThinBorders($sheet, "A{$row}:{$lastCol}{$row}");
            $row++;
        } else {
            $totalRowNum = $dataStart + count($members);

            foreach ($members as $index => $m) {
                $homeBranch = $m['home_branch'] ?? null;
                $homeCode = is_array($homeBranch) ? ($homeBranch['code'] ?? '-') : ($homeBranch->code ?? '-');
                $homeName = is_array($homeBranch) ? ($homeBranch['name'] ?? '-') : ($homeBranch->name ?? '-');
                $statusLabel = ($m['status'] ?? 'active') === 'active' ? 'Aktif' : 'Nonaktif';

                $sheet->setCellValue("A{$row}", $index + 1);
                $this->setSafeTextCell($sheet, "B{$row}", (string) ($m['member_code'] ?? '-'));
                $this->setSafeTextCell($sheet, "C{$row}", (string) ($m['name'] ?? '-'));
                $this->setSafeTextCell($sheet, "D{$row}", $statusLabel);
                $this->setSafeTextCell($sheet, "E{$row}", (string) $homeCode);
                $this->setSafeTextCell($sheet, "F{$row}", (string) $homeName);
                $sheet->setCellValue("G{$row}", (int) ($m['paid_transactions_count'] ?? 0));
                $sheet->setCellValue("H{$row}", (float) ($m['home_branch_spend'] ?? 0));
                $sheet->setCellValue("I{$row}", (float) ($m['other_branch_spend'] ?? 0));
                $sheet->setCellValue("J{$row}", (float) ($m['total_spend'] ?? 0));
                $sheet->setCellValue("K{$row}", "=IF(J{$totalRowNum}>0,(J{$row}/J{$totalRowNum})*100,0)");
                $sheet->setCellValue("L{$row}", (float) ($m['estimated_shu_share'] ?? 0));

                $this->styleDataRow($sheet, $row, $colCount, isAlt: $index % 2 === 1);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
                $sheet->getStyle("H{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("H{$row}:J{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet->getStyle("I{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_BLUE);
                $sheet->getStyle("J{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
                $sheet->getStyle("K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);
                $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet->getStyle("L{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_PRIMARY);
                $row++;
            }

            $dataEnd = $row - 1;
            $sheet->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("G{$row}", "=SUM(G{$dataStart}:G{$dataEnd})");
            $sheet->setCellValue("H{$row}", "=SUM(H{$dataStart}:H{$dataEnd})");
            $sheet->setCellValue("I{$row}", "=SUM(I{$dataStart}:I{$dataEnd})");
            $sheet->setCellValue("J{$row}", "=SUM(J{$dataStart}:J{$dataEnd})");
            $sheet->setCellValue("K{$row}", "=IF(J{$row}>0,100,0)");
            $sheet->setCellValue("L{$row}", "=SUM(L{$dataStart}:L{$dataEnd})");

            $this->styleTotalRow($sheet, $row, $colCount);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
            $sheet->getStyle("H{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$row}:J{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet->getStyle("I{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_BLUE);
            $sheet->getStyle("J{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
            $sheet->getStyle("K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);
            $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet->getStyle("L{$row}")->getFont()->getColor()->setRGB(self::COLOR_PRIMARY);
            $row++;
        }

        $this->finalizeSheetLayout($sheet, $colCount, $headerRow, [
            'A' => 6,
            'B' => 24,
            'C' => 22,
            'D' => 12,
            'E' => 15,
            'F' => 24,
            'G' => 14,
            'H' => 20,
            'I' => 20,
            'J' => 22,
            'K' => 14,
            'L' => 22,
        ]);

        $filename = sprintf(
            'laporan-shu-member-kopdes-%s-sd-%s.xlsx',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->streamExcelResponse($spreadsheet, $filename);
    }

    /**
     * Generate a professional Excel (.xlsx) workbook for Cashier Shifts History Report.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportShiftsExcel(User $actor, array $report, array $filters): StreamedResponse
    {
        $meta = $this->buildReportMetadata($actor, $filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $spreadsheet->getProperties()
            ->setCreator($actor->name)
            ->setTitle('Laporan Riwayat Shift Kasir KOPDES')
            ->setSubject('Rekonsiliasi Laci Kasir, Blind Count & Selisih Shift')
            ->setCompany('Koperasi Desa Merah Putih (KOPDES)');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Riwayat Shift Kasir');

        $colCount = 17;
        $lastCol = Coordinate::stringFromColumnIndex($colCount); // Q

        $this->applyHeaderBanner(
            $sheet,
            strtoupper($meta['institution_name']),
            'LAPORAN RIWAYAT & REKONSILIASI SHIFT KASIR POS (BLIND COUNT)',
            $colCount
        );

        $row = $this->writeMetadataBlock($sheet, $meta, $colCount, startRow: 4, filterMode: 'shift');
        $row++;

        $summary = $report['summary'];
        $kpiStart = $row;
        $sheet->setCellValue("A{$row}", 'RINGKASAN SHIFT KASIR PERIODE TERPILIH');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->styleSectionTitle($sheet, "A{$row}:{$lastCol}{$row}");
        $row++;

        $valRow = $row + 1;
        $totalVar = (float) ($summary['total_variance'] ?? 0);
        $kpiBlocks = [
            ['A', 'D', 'Total Shift', (int) ($summary['total_shifts'] ?? 0), self::FORMAT_INTEGER, '111827'],
            ['E', 'H', 'Shift Aktif (Open)', (int) ($summary['open_shifts'] ?? 0), self::FORMAT_INTEGER, self::COLOR_ACCENT_BLUE],
            ['I', 'L', 'Shift Selesai (Closed)', (int) ($summary['closed_shifts'] ?? 0), self::FORMAT_INTEGER, self::COLOR_ACCENT_GREEN],
            ['M', 'Q', 'Akumulasi Selisih Kas (Rp)', $totalVar, self::FORMAT_RUPIAH, ($totalVar < 0 ? self::COLOR_ACCENT_ROSE : ($totalVar > 0 ? 'B45309' : self::COLOR_ACCENT_GREEN))],
        ];
        foreach ($kpiBlocks as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            $sheet->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
            $sheet->mergeCells("{$startCol}{$valRow}:{$endCol}{$valRow}");
            $sheet->setCellValue("{$startCol}{$row}", $label);
            $sheet->setCellValue("{$startCol}{$valRow}", $value);
            $sheet->getStyle("{$startCol}{$valRow}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet->getStyle("{$startCol}{$valRow}")->getFont()->getColor()->setRGB($colorRgb);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle("A{$valRow}:{$lastCol}{$valRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($valRow)->setRowHeight(26);
        $this->applyThinBorders($sheet, "A{$kpiStart}:{$lastCol}{$valRow}");

        $row = $valRow + 2;

        $headers = [
            'No',
            'Kode Shift',
            'Cabang KOPDES',
            'Kasir',
            'Waktu Buka',
            'Waktu Tutup',
            'Status',
            'Force-Close',
            'Modal Awal (Rp)',
            'Penjualan Tunai (Rp)',
            'Penjualan Midtrans (Rp)',
            'Kas Masuk (Rp)',
            'Kas Keluar (Rp)',
            'Refund Laci (Rp)',
            'Ekspektasi Tunai (Rp)',
            'Fisik Aktual (Rp)',
            'Selisih Kas (Rp)',
        ];

        $headerRow = $row;
        $this->writeTableHeaders($sheet, $headers, $headerRow);
        $row++;

        $dataStart = $row;
        $shifts = $report['shifts'];

        if (empty($shifts)) {
            $sheet->setCellValue("A{$row}", 'Belum ada riwayat shift kasir pada periode dan filter ini.');
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
            $this->applyThinBorders($sheet, "A{$row}:{$lastCol}{$row}");
            $row++;
        } else {
            foreach ($shifts as $idx => $s) {
                $isClosed = ($s['status'] ?? '') === 'closed';
                $varVal = (float) ($s['variance'] ?? 0);

                $sheet->setCellValue("A{$row}", $idx + 1);
                $this->setSafeTextCell($sheet, "B{$row}", (string) ($s['shift_code'] ?? '-'));
                $this->setSafeTextCell($sheet, "C{$row}", (string) (($s['branch']['name'] ?? '-').' ('.($s['branch']['code'] ?? '-').')'));
                $this->setSafeTextCell($sheet, "D{$row}", (string) ($s['cashier']['name'] ?? '-'));
                $this->setSafeTextCell($sheet, "E{$row}", (string) ($s['opened_at'] ?? '-'));
                $this->setSafeTextCell($sheet, "F{$row}", (string) ($s['closed_at'] ?? '-'));
                $this->setSafeTextCell($sheet, "G{$row}", $isClosed ? 'Closed' : 'Open');
                $this->setSafeTextCell($sheet, "H{$row}", ! empty($s['is_force_closed']) ? 'Ya' : 'Tidak');
                $sheet->setCellValue("I{$row}", (float) ($s['opening_cash'] ?? 0));
                $sheet->setCellValue("J{$row}", $isClosed ? (float) ($s['cash_sales_total'] ?? 0) : 0);
                $sheet->setCellValue("K{$row}", $isClosed ? (float) ($s['midtrans_sales_total'] ?? 0) : 0);
                $sheet->setCellValue("L{$row}", $isClosed ? (float) ($s['cash_in_total'] ?? 0) : 0);
                $sheet->setCellValue("M{$row}", $isClosed ? (float) ($s['cash_out_total'] ?? 0) : 0);
                $sheet->setCellValue("N{$row}", $isClosed ? (float) ($s['cash_refund_total'] ?? 0) : 0);
                $sheet->setCellValue("O{$row}", $isClosed ? (float) ($s['expected_closing_cash'] ?? 0) : 0);
                $sheet->setCellValue("P{$row}", $isClosed ? (float) ($s['actual_closing_cash'] ?? 0) : 0);
                $sheet->setCellValue("Q{$row}", $isClosed ? $varVal : 0);

                $this->styleDataRow($sheet, $row, $colCount, isAlt: $idx % 2 === 1);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I{$row}:Q{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("I{$row}:Q{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet->getStyle("K{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_BLUE);
                $sheet->getStyle("Q{$row}")->getFont()->setBold(true)->getColor()->setRGB(
                    $varVal < 0 ? self::COLOR_ACCENT_ROSE : ($varVal > 0 ? 'B45309' : self::COLOR_ACCENT_GREEN)
                );
                $row++;
            }

            $dataEnd = $row - 1;
            $sheet->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
            $sheet->mergeCells("A{$row}:H{$row}");
            foreach (['I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'] as $c) {
                $sheet->setCellValue("{$c}{$row}", "=SUM({$c}{$dataStart}:{$c}{$dataEnd})");
            }
            $this->styleTotalRow($sheet, $row, $colCount);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$row}:Q{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$row}:Q{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $row++;
        }

        $this->finalizeSheetLayout($sheet, $colCount, $headerRow, [
            'A' => 6,
            'B' => 24,
            'C' => 26,
            'D' => 18,
            'E' => 19,
            'F' => 19,
            'G' => 10,
            'H' => 12,
            'I' => 16,
            'J' => 17,
            'K' => 17,
            'L' => 15,
            'M' => 15,
            'N' => 15,
            'O' => 17,
            'P' => 17,
            'Q' => 16,
        ]);

        $filename = sprintf(
            'laporan-shift-kasir-kopdes-%s-sd-%s.xlsx',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->streamExcelResponse($spreadsheet, $filename);
    }

    /**
     * Generate a professional Excel (.xlsx) workbook for Transaction Returns & Void History Report.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     */
    public function exportReturnsExcel(User $actor, array $report, array $filters): StreamedResponse
    {
        $meta = $this->buildReportMetadata($actor, $filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $spreadsheet->getProperties()
            ->setCreator($actor->name)
            ->setTitle('Laporan Riwayat Retur & Void Transaksi KOPDES')
            ->setSubject('Audit Immutable Retur Parsial, Full Void, Restock & Barang Rusak')
            ->setCompany('Koperasi Desa Merah Putih (KOPDES)');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Riwayat Retur & Void');

        $colCount = 13;
        $lastCol = Coordinate::stringFromColumnIndex($colCount); // M

        $this->applyHeaderBanner(
            $sheet,
            strtoupper($meta['institution_name']),
            'LAPORAN RIWAYAT RETUR PARSIAL & FULL VOID TRANSAKSI CABANG',
            $colCount
        );

        $row = $this->writeMetadataBlock($sheet, $meta, $colCount, startRow: 4, filterMode: 'return');
        $row++;

        $summary = $report['summary'];
        $kpiStart = $row;
        $sheet->setCellValue("A{$row}", 'RINGKASAN RETUR & VOID PERIODE TERPILIH');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->styleSectionTitle($sheet, "A{$row}:{$lastCol}{$row}");
        $row++;

        $valRow = $row + 1;
        $kpiBlocks = [
            ['A', 'C', 'Total Dokumen Retur', (int) ($summary['total_returns_count'] ?? 0), self::FORMAT_INTEGER, '111827'],
            ['D', 'F', 'Total Nilai Refund (Rp)', (float) ($summary['total_refund_amount'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_ROSE],
            ['G', 'I', 'HPP Restock Layak Jual (Rp)', (float) ($summary['total_restocked_cogs'] ?? 0), self::FORMAT_RUPIAH, self::COLOR_ACCENT_GREEN],
            ['J', 'M', 'Kerugian Barang Rusak (Rp)', (float) ($summary['total_damaged_cogs'] ?? 0), self::FORMAT_RUPIAH, 'B45309'],
        ];
        foreach ($kpiBlocks as [$startCol, $endCol, $label, $value, $numFormat, $colorRgb]) {
            $sheet->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
            $sheet->mergeCells("{$startCol}{$valRow}:{$endCol}{$valRow}");
            $sheet->setCellValue("{$startCol}{$row}", $label);
            $sheet->setCellValue("{$startCol}{$valRow}", $value);
            $sheet->getStyle("{$startCol}{$valRow}")->getNumberFormat()->setFormatCode($numFormat);
            $sheet->getStyle("{$startCol}{$valRow}")->getFont()->getColor()->setRGB($colorRgb);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle("A{$valRow}:{$lastCol}{$valRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($valRow)->setRowHeight(26);
        $this->applyThinBorders($sheet, "A{$kpiStart}:{$lastCol}{$valRow}");

        $row = $valRow + 2;

        $headers = [
            'No',
            'Nomor Retur',
            'Waktu Retur',
            'Nomor Invoice',
            'Cabang KOPDES',
            'Admin Pengeksekusi',
            'Jenis Retur',
            'Metode Refund',
            'Sumber Kas / Ref',
            'Nilai Refund (Rp)',
            'HPP Restock (Rp)',
            'HPP Rusak (Rp)',
            'Alasan Retur',
        ];
        $headerRow = $row;
        $this->writeTableHeaders($sheet, $headers, $headerRow);
        $row++;

        $dataStart = $row;
        $returns = $report['returns'];

        if (empty($returns)) {
            $sheet->setCellValue("A{$row}", 'Belum ada riwayat retur atau pembatalan transaksi pada periode dan filter ini.');
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
            $this->applyThinBorders($sheet, "A{$row}:{$lastCol}{$row}");
            $row++;
        } else {
            foreach ($returns as $idx => $ret) {
                $isCash = ($ret['refund_method'] ?? '') === 'cash_refund';
                $sourceOrRef = $isCash
                    ? (($ret['cash_source'] ?? '') === 'active_shift'
                        ? 'Laci Shift ('.($ret['refund_shift_code'] ?? '-').')'
                        : 'Kas Cabang')
                    : (string) ($ret['refund_reference'] ?? '-');

                $sheet->setCellValue("A{$row}", $idx + 1);
                $this->setSafeTextCell($sheet, "B{$row}", (string) ($ret['return_number'] ?? '-'));
                $this->setSafeTextCell($sheet, "C{$row}", (string) ($ret['returned_at'] ?? '-'));
                $this->setSafeTextCell($sheet, "D{$row}", (string) ($ret['invoice_number'] ?? '-'));
                $this->setSafeTextCell($sheet, "E{$row}", (string) (($ret['branch']['name'] ?? '-').' ('.($ret['branch']['code'] ?? '-').')'));
                $this->setSafeTextCell($sheet, "F{$row}", (string) ($ret['processed_by'] ?? '-'));
                $this->setSafeTextCell($sheet, "G{$row}", ($ret['return_type'] ?? 'partial') === 'full' ? 'Full Void' : 'Parsial');
                $this->setSafeTextCell($sheet, "H{$row}", $isCash ? 'Tunai' : 'Transfer Manual');
                $this->setSafeTextCell($sheet, "I{$row}", $sourceOrRef);
                $sheet->setCellValue("J{$row}", (float) ($ret['total_refund_amount'] ?? 0));
                $sheet->setCellValue("K{$row}", (float) ($ret['total_restocked_cogs'] ?? 0));
                $sheet->setCellValue("L{$row}", (float) ($ret['total_damaged_cogs'] ?? 0));
                $this->setSafeTextCell($sheet, "M{$row}", (string) ($ret['reason'] ?? '-'));

                $this->styleDataRow($sheet, $row, $colCount, isAlt: $idx % 2 === 1);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
                $sheet->getStyle("J{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
                $sheet->getStyle("K{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
                $sheet->getStyle("L{$row}")->getFont()->getColor()->setRGB('B45309');
                $row++;
            }

            $dataEnd = $row - 1;
            $sheet->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->setCellValue("J{$row}", "=SUM(J{$dataStart}:J{$dataEnd})");
            $sheet->setCellValue("K{$row}", "=SUM(K{$dataStart}:K{$dataEnd})");
            $sheet->setCellValue("L{$row}", "=SUM(L{$dataStart}:L{$dataEnd})");
            $sheet->setCellValue("M{$row}", '');
            $this->styleTotalRow($sheet, $row, $colCount);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet->getStyle("J{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_ROSE);
            $sheet->getStyle("K{$row}")->getFont()->getColor()->setRGB(self::COLOR_ACCENT_GREEN);
            $sheet->getStyle("L{$row}")->getFont()->getColor()->setRGB('B45309');
            $row++;
        }

        $this->finalizeSheetLayout($sheet, $colCount, $headerRow, [
            'A' => 6,
            'B' => 24,
            'C' => 19,
            'D' => 26,
            'E' => 26,
            'F' => 20,
            'G' => 12,
            'H' => 16,
            'I' => 22,
            'J' => 17,
            'K' => 17,
            'L' => 17,
            'M' => 30,
        ]);

        $filename = sprintf(
            'laporan-retur-void-kopdes-%s-sd-%s.xlsx',
            $filters['start_date'],
            $filters['end_date']
        );

        return $this->streamExcelResponse($spreadsheet, $filename);
    }

    /**
     * Build human-readable report header metadata (period, region scope, payment method/category, actor).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function buildReportMetadata(User $actor, array $filters): array
    {
        $startFormatted = Carbon::parse($filters['start_date'])->translatedFormat('d M Y');
        $endFormatted = Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

        $regionLabel = 'Nasional (Seluruh Indonesia)';
        if (! empty($filters['branch_id'])) {
            $branch = Branch::with(['regency:id,name', 'province:id,name'])->find((int) $filters['branch_id']);
            if ($branch) {
                $regionLabel = "{$branch->name} ({$branch->code}) — {$branch->regency?->name}, {$branch->province?->name}";
            }
        } elseif (! empty($filters['regency_id'])) {
            $regency = Regency::with('province:id,name')->find((string) $filters['regency_id']);
            if ($regency) {
                $regionLabel = "{$regency->name}, {$regency->province?->name}";
            }
        } elseif (! empty($filters['province_id'])) {
            $province = Province::find((string) $filters['province_id']);
            if ($province) {
                $regionLabel = "Provinsi {$province->name}";
            }
        }

        $paymentMethodLabel = match ($filters['payment_method'] ?? 'all') {
            'cash' => 'Tunai (Cash)',
            'midtrans' => 'Non-Tunai (Midtrans)',
            default => 'Semua Metode (Tunai & Midtrans)',
        };

        $categoryKey = (string) ($filters['category'] ?? 'all');
        $categoryLabel = $categoryKey === 'all' || $categoryKey === ''
            ? 'Semua Kategori Biaya'
            : (OperationalExpense::CATEGORIES[$categoryKey] ?? ucfirst($categoryKey));

        $shiftStatusLabel = match ($filters['status'] ?? 'all') {
            'open' => 'Aktif (Open)',
            'closed' => 'Selesai (Closed)',
            default => 'Semua Status Shift',
        };

        $refundMethodLabel = match ($filters['refund_method'] ?? 'all') {
            'cash_refund' => 'Tunai (Cash Refund)',
            'manual_transfer' => 'Transfer Manual',
            default => 'Semua Metode Refund',
        };

        $roleLabel = match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => 'Admin Nasional',
            User::ROLE_ADMIN_PROVINSI => 'Admin Provinsi',
            User::ROLE_ADMIN_KABUPATEN => 'Admin Kabupaten',
            User::ROLE_ADMIN_CABANG => 'Admin Cabang',
            default => ucfirst(str_replace('_', ' ', (string) $actor->role)),
        };

        $publicSettings = app(AppSettingService::class)->publicConfig();
        $logoDataUri = app(AppSettingService::class)->logoDataUri();

        return [
            'institution_name' => $publicSettings['institution_name'],
            'institution_logo_data_uri' => $logoDataUri,
            'receipt_footer' => $publicSettings['receipt_footer'],
            'period_label' => "{$startFormatted} s.d. {$endFormatted}",
            'region_label' => $regionLabel,
            'payment_method_label' => $paymentMethodLabel,
            'category_label' => $categoryLabel,
            'shift_status_label' => $shiftStatusLabel,
            'refund_method_label' => $refundMethodLabel,
            'search_keyword' => trim((string) ($filters['search'] ?? '')),
            'generated_by' => "{$actor->name} ({$roleLabel})",
            'generated_at' => now()->format('d/m/Y H:i:s').' WIB',
            'document_code' => 'DOC-KOPDES-'.now()->format('YmdHis'),
        ];
    }

    /**
     * Render HTML into an A4 PDF download response via DomPDF with embedded page numbering.
     */
    private function renderPdfResponse(string $html, string $filename, string $orientation = 'landscape'): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pageWidth = $canvas->get_width();
        $pageHeight = $canvas->get_height();

        $canvas->page_text(
            $pageWidth - 135,
            $pageHeight - 24,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $font,
            7.5,
            [0.35, 0.39, 0.45]
        );

        $canvas->page_text(
            28,
            $pageHeight - 24,
            'Sistem Kasir & Manajemen KOPDES Merah Putih — Dokumen Resmi',
            $font,
            7.5,
            [0.35, 0.39, 0.45]
        );

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Stream a PhpSpreadsheet workbook as a downloadable .xlsx file.
     */
    private function streamExcelResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Neutralize spreadsheet formula injection characters (=, +, -, @, \t, \r) and store as explicit string (SEC-03).
     */
    private function sanitizeSpreadsheetText(?string $value): string
    {
        $text = (string) ($value ?? '');
        if ($text === '') {
            return '';
        }

        $trimmed = ltrim($text, " \t\r\n");
        $firstChar = $trimmed !== '' ? $trimmed[0] : '';
        $rawFirstChar = $text[0];

        if (in_array($firstChar, ['=', '+', '-', '@'], true)
            || in_array($rawFirstChar, ['=', '+', '-', '@', "\t", "\r"], true)
        ) {
            return "'".$text;
        }

        return $text;
    }

    private function setSafeTextCell(Worksheet $sheet, string $coordinate, ?string $value): void
    {
        $sheet->setCellValueExplicit(
            $coordinate,
            $this->sanitizeSpreadsheetText($value),
            DataType::TYPE_STRING
        );
    }

    private function applyHeaderBanner(Worksheet $sheet, string $orgTitle, string $reportTitle, int $colCount): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        $this->setSafeTextCell($sheet, 'A1', $orgTitle);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $this->setSafeTextCell($sheet, 'A2', $reportTitle);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COLOR_PRIMARY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY_SOFT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_PRIMARY]],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function writeMetadataBlock(
        Worksheet $sheet,
        array $meta,
        int $colCount,
        int $startRow,
        bool $isExpenseReport = false,
        ?string $filterMode = null
    ): int {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $mode = $filterMode ?? ($isExpenseReport ? 'expense' : 'payment');
        [$thirdLeftLabel, $thirdLeftValue, $basisLabel] = match ($mode) {
            'expense' => ['Kategori Biaya', $meta['category_label'], 'Catatan Biaya Operasional Aktif'],
            'shift' => ['Status Shift', $meta['shift_status_label'], 'Riwayat Shift Kasir & Rekonsiliasi Blind Count'],
            'return' => ['Metode Refund', $meta['refund_method_label'], 'Audit Retur Parsial & Full Void Transaksi'],
            default => ['Metode Pembayaran', $meta['payment_method_label'], 'Transaksi Lunas (paid), Retur, HPP Snapshot & Biaya Operasional'],
        };

        $rows = [
            ['Periode Laporan', $meta['period_label'], 'Dicetak Oleh', $meta['generated_by']],
            ['Cakupan Wilayah / Cabang', $meta['region_label'], 'Waktu Cetak', $meta['generated_at']],
            [$thirdLeftLabel, $thirdLeftValue, 'No. Dokumen', $meta['document_code']],
        ];

        if ($meta['search_keyword'] !== '') {
            $rows[] = [
                'Kata Kunci Pencarian',
                $meta['search_keyword'],
                'Basis Data',
                $basisLabel,
            ];
        }

        $row = $startRow;
        foreach ($rows as [$leftLabel, $leftVal, $rightLabel, $rightVal]) {
            $this->setSafeTextCell($sheet, "A{$row}", $leftLabel);
            $sheet->mergeCells("A{$row}:B{$row}");
            $this->setSafeTextCell($sheet, "C{$row}", ': '.$this->sanitizeSpreadsheetText($leftVal));
            $sheet->mergeCells("C{$row}:F{$row}");

            $this->setSafeTextCell($sheet, "G{$row}", $rightLabel);
            $sheet->mergeCells("G{$row}:H{$row}");
            $this->setSafeTextCell($sheet, "I{$row}", ': '.$this->sanitizeSpreadsheetText($rightVal));
            if ($colCount > 9) {
                $sheet->mergeCells("I{$row}:{$lastCol}{$row}");
            }

            $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('374151');
            $sheet->getStyle("G{$row}:H{$row}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('374151');
            $sheet->getStyle("C{$row}:F{$row}")->getFont()->setSize(9)->getColor()->setRGB('111827');
            $sheet->getStyle("I{$row}:{$lastCol}{$row}")->getFont()->setSize(9)->getColor()->setRGB('111827');
            $sheet->getRowDimension($row)->setRowHeight(17);
            $row++;
        }

        return $row;
    }

    private function styleSectionTitle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '111827']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $rowNum = (int) preg_replace('/[^0-9]/', '', explode(':', $range)[0]);
        $sheet->getRowDimension($rowNum)->setRowHeight(21);
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function writeTableHeaders(Worksheet $sheet, array $headers, int $row): void
    {
        foreach ($headers as $idx => $title) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$col}{$row}", $title);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9.5, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_DARK]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '374151']],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(26);
    }

    private function styleDataRow(Worksheet $sheet, int $row, int $colCount, bool $isAlt): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $style = [
            'font' => ['size' => 9.5, 'color' => ['rgb' => '1F2937']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]],
            ],
        ];

        if ($isAlt) {
            $style['fill'] = [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_ZEBRA],
            ];
        }

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($style);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function styleTotalRow(Worksheet $sheet, int $row, int $colCount): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '111827']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_BG]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_HEADER_DARK]],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => self::COLOR_HEADER_DARK]],
                'left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]],
                'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]],
                'inside' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    private function applyThinBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB(self::COLOR_BORDER);
    }

    /**
     * @param  array<string, int|float>  $columnWidths
     */
    private function finalizeSheetLayout(Worksheet $sheet, int $colCount, int $freezeAfterRow, array $columnWidths): void
    {
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->freezePane('A'.($freezeAfterRow + 1));

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $sheet->setShowGridlines(true);
    }
}
