<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Riwayat Retur &amp; Void Transaksi KOPDES</title>
    <style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8.5px; color: #1f2937; line-height: 1.35; margin: 0; padding: 0; }
        .letterhead { border-bottom: 3px solid #b91c1c; padding-bottom: 8px; margin-bottom: 10px; }
        .letterhead-table { width: 100%; border-collapse: collapse; }
        .brand-title { font-size: 15px; font-weight: bold; color: #b91c1c; letter-spacing: 0.4px; margin: 0; }
        .brand-subtitle { font-size: 9px; color: #4b5563; margin: 2px 0 0 0; }
        .doc-badge { text-align: right; vertical-align: top; }
        .doc-title { font-size: 12px; font-weight: bold; color: #111827; text-transform: uppercase; margin: 0; }
        .doc-code { font-size: 8px; color: #6b7280; margin-top: 2px; }
        .meta-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 7px 10px; margin-bottom: 10px; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 2px 4px; vertical-align: top; font-size: 8px; }
        .meta-label { font-weight: bold; color: #374151; width: 15%; white-space: nowrap; }
        .meta-value { color: #111827; width: 35%; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 -5px 10px -5px; }
        .kpi-card { background-color: #ffffff; border: 1px solid #d1d5db; border-top: 3px solid #b91c1c; padding: 6px 8px; width: 25%; vertical-align: top; }
        .kpi-card.green { border-top-color: #047857; }
        .kpi-card.rose { border-top-color: #be123c; }
        .kpi-card.amber { border-top-color: #b45309; }
        .kpi-label { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .kpi-value { font-size: 10.5px; font-weight: bold; color: #111827; margin-top: 3px; }
        .kpi-value.green { color: #047857; }
        .kpi-value.rose { color: #be123c; }
        .kpi-value.amber { color: #b45309; }
        .section-heading { font-size: 10px; font-weight: bold; color: #111827; background-color: #f3f4f6; border-left: 4px solid #b91c1c; padding: 5px 8px; margin: 10px 0 6px 0; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .data-table th { background-color: #1f2937; color: #ffffff; font-weight: bold; font-size: 7.2px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #374151; text-align: left; }
        .data-table td { padding: 4.5px 4px; border: 1px solid #d1d5db; font-size: 7.6px; vertical-align: top; }
        .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tfoot td { background-color: #f3f4f6; font-weight: bold; font-size: 7.6px; border-top: 2px solid #1f2937; border-bottom: 2px solid #1f2937; padding: 5px 4px; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono { font-family: "DejaVu Sans Mono", monospace; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #6b7280; font-size: 6.8px; }
        .text-green { color: #047857; font-weight: bold; }
        .text-amber { color: #b45309; font-weight: bold; }
        .text-rose { color: #be123c; font-weight: bold; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 6.8px; font-weight: bold; text-transform: uppercase; }
        .badge-partial { background-color: #fef3c7; color: #92400e; }
        .badge-full { background-color: #fee2e2; color: #991b1b; }
        .footnote { font-size: 7.5px; color: #6b7280; font-style: italic; border-top: 1px dashed #d1d5db; padding-top: 6px; margin-top: 6px; }
        .signature-table { width: 100%; border-collapse: collapse; margin-top: 16px; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; text-align: center; vertical-align: top; font-size: 8.5px; }
        .signature-space { height: 40px; }
        .signature-line { display: inline-block; border-top: 1px solid #374151; width: 145px; padding-top: 3px; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $fmtRp = function ($val) {
            $num = (float) ($val ?? 0);
            $prefix = $num < 0 ? '-Rp ' : 'Rp ';
            return $prefix . number_format(abs($num), 0, ',', '.');
        };
        $fmtInt = fn ($val) => number_format((int) ($val ?? 0), 0, ',', '.');
    @endphp

    <div class="letterhead">
        <table class="letterhead-table">
            <tr>
                <td style="width: 55%;">
                    <p class="brand-title">KOPERASI DESA MERAH PUTIH (KOPDES)</p>
                    <p class="brand-subtitle">Audit Immutable Retur Parsial, Full Void, Restock &amp; Kerugian Barang Rusak</p>
                </td>
                <td class="doc-badge" style="width: 45%;">
                    <p class="doc-title">Laporan Riwayat Retur &amp; Void</p>
                    <div class="doc-code">No. Dokumen: {{ $meta['document_code'] }} | Dicetak: {{ $meta['generated_at'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Periode Retur</td>
                <td class="meta-value">: {{ $meta['period_label'] }}</td>
                <td class="meta-label">Dicetak Oleh</td>
                <td class="meta-value">: {{ $meta['generated_by'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">Cakupan Wilayah</td>
                <td class="meta-value">: {{ $meta['region_label'] }}</td>
                <td class="meta-label">Metode Refund</td>
                <td class="meta-value">: {{ $meta['refund_method_label'] }}</td>
            </tr>
            @if ($meta['search_keyword'] !== '')
                <tr>
                    <td class="meta-label">Filter Pencarian</td>
                    <td class="meta-value" colspan="3">: "{{ $meta['search_keyword'] }}"</td>
                </tr>
            @endif
        </table>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Total Dokumen Retur</div>
                <div class="kpi-value">{{ $fmtInt($summary['total_returns_count'] ?? 0) }} Dokumen</div>
            </td>
            <td class="kpi-card rose">
                <div class="kpi-label">Total Nilai Refund</div>
                <div class="kpi-value rose">{{ $fmtRp($summary['total_refund_amount'] ?? 0) }}</div>
            </td>
            <td class="kpi-card green">
                <div class="kpi-label">HPP Restock (Layak Jual)</div>
                <div class="kpi-value green">{{ $fmtRp($summary['total_restocked_cogs'] ?? 0) }}</div>
            </td>
            <td class="kpi-card amber">
                <div class="kpi-label">Kerugian Barang Rusak</div>
                <div class="kpi-value amber">{{ $fmtRp($summary['total_damaged_cogs'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-heading">Daftar Dokumen Retur Parsial &amp; Full Void Transaksi</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 3%;">No</th>
                <th style="width: 13%;">No. Retur &amp; Waktu</th>
                <th style="width: 13%;">No. Invoice Asal</th>
                <th style="width: 12%;">Cabang KOPDES</th>
                <th style="width: 10%;">Admin Pengeksekusi</th>
                <th class="text-center" style="width: 6%;">Jenis</th>
                <th style="width: 11%;">Metode Refund</th>
                <th class="text-right" style="width: 8%;">Nilai Refund</th>
                <th class="text-right" style="width: 7%;">HPP Restock</th>
                <th class="text-right" style="width: 7%;">HPP Rusak</th>
                <th style="width: 10%;">Alasan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($returns as $idx => $ret)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-mono font-bold">{{ $ret['return_number'] }}</div>
                        <div class="text-muted">{{ $ret['returned_at'] ?? '-' }}</div>
                    </td>
                    <td>
                        <div class="font-mono font-bold">{{ $ret['invoice_number'] ?? '-' }}</div>
                        <div class="text-muted">{{ $ret['member_name'] ?? 'Pembeli Umum' }}</div>
                    </td>
                    <td>
                        <div class="font-bold">{{ $ret['branch']['name'] ?? '-' }}</div>
                        <div class="font-mono text-muted">{{ $ret['branch']['code'] ?? '-' }}</div>
                    </td>
                    <td>{{ $ret['processed_by'] ?? '-' }}</td>
                    <td class="text-center">
                        <span class="badge {{ ($ret['return_type'] ?? 'partial') === 'full' ? 'badge-full' : 'badge-partial' }}">
                            {{ ($ret['return_type'] ?? 'partial') === 'full' ? 'Full Void' : 'Parsial' }}
                        </span>
                    </td>
                    <td>
                        @if (($ret['refund_method'] ?? '') === 'cash_refund')
                            <div class="font-bold">Tunai</div>
                            <div class="text-muted">
                                {{ ($ret['cash_source'] ?? '') === 'active_shift' ? 'Laci Shift (' . ($ret['refund_shift_code'] ?? '-') . ')' : 'Kas Cabang' }}
                            </div>
                        @else
                            <div class="font-bold">Transfer Manual</div>
                            <div class="font-mono text-muted">{{ $ret['refund_reference'] ?? '-' }}</div>
                        @endif
                    </td>
                    <td class="text-right text-rose">{{ $fmtRp($ret['total_refund_amount'] ?? 0) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($ret['total_restocked_cogs'] ?? 0) }}</td>
                    <td class="text-right text-amber">{{ $fmtRp($ret['total_damaged_cogs'] ?? 0) }}</td>
                    <td>{{ $ret['reason'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-muted" style="padding: 12px;">
                        Belum ada data retur atau pembatalan transaksi pada periode dan filter ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($returns))
            <tfoot>
                <tr>
                    <td colspan="7" class="text-right">TOTAL KESELURUHAN ({{ $fmtInt(count($returns)) }} DOKUMEN)</td>
                    <td class="text-right text-rose">{{ $fmtRp($summary['total_refund_amount'] ?? 0) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($summary['total_restocked_cogs'] ?? 0) }}</td>
                    <td class="text-right text-amber">{{ $fmtRp($summary['total_damaged_cogs'] ?? 0) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div>Dibuat Oleh,</div>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $meta['generated_by'] }}</div>
            </td>
            <td>
                <div>Diperiksa Oleh,</div>
                <div class="signature-space"></div>
                <div class="signature-line">Pengawas / Bendahara KOPDES</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div class="signature-space"></div>
                <div class="signature-line">Ketua Pengurus KOPDES</div>
            </td>
        </tr>
    </table>

    <div class="footnote">
        * Catatan Audit Retur: Item berkondisi layak jual (restockable) mengembalikan stok cabang dan menurunkan HPP Bersih. Item berkondisi rusak (damaged) tidak mengembalikan stok dan tercatat sebagai kerugian barang rusak yang membebani Laba Kotor.
    </div>
</body>
</html>
