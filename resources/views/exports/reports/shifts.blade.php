<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Riwayat Shift Kasir KOPDES</title>
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
        .kpi-card.blue { border-top-color: #1d4ed8; }
        .kpi-card.green { border-top-color: #047857; }
        .kpi-card.rose { border-top-color: #be123c; }
        .kpi-card.amber { border-top-color: #b45309; }
        .kpi-label { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .kpi-value { font-size: 10.5px; font-weight: bold; color: #111827; margin-top: 3px; }
        .kpi-value.blue { color: #1d4ed8; }
        .kpi-value.green { color: #047857; }
        .kpi-value.rose { color: #be123c; }
        .kpi-value.amber { color: #b45309; }
        .section-heading { font-size: 10px; font-weight: bold; color: #111827; background-color: #f3f4f6; border-left: 4px solid #b91c1c; padding: 5px 8px; margin: 10px 0 6px 0; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .data-table th { background-color: #1f2937; color: #ffffff; font-weight: bold; font-size: 6.8px; text-transform: uppercase; padding: 5px 3px; border: 1px solid #374151; text-align: left; }
        .data-table td { padding: 4px 3px; border: 1px solid #d1d5db; font-size: 7.2px; vertical-align: top; }
        .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tfoot td { background-color: #f3f4f6; font-weight: bold; font-size: 7.2px; border-top: 2px solid #1f2937; border-bottom: 2px solid #1f2937; padding: 5px 3px; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono { font-family: "DejaVu Sans Mono", monospace; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #6b7280; font-size: 6.5px; }
        .text-green { color: #047857; font-weight: bold; }
        .text-amber { color: #b45309; font-weight: bold; }
        .text-rose { color: #be123c; font-weight: bold; }
        .text-blue { color: #1d4ed8; font-weight: bold; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 6.5px; font-weight: bold; text-transform: uppercase; }
        .badge-open { background-color: #dbeafe; color: #1e40af; }
        .badge-closed { background-color: #dcfce7; color: #166534; }
        .badge-force { background-color: #fef3c7; color: #92400e; }
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
        $totalVar = (float) ($summary['total_variance'] ?? 0);
    @endphp

    <div class="letterhead">
        <table class="letterhead-table">
            <tr>
                <td style="width: 55%;">
                    @if (! empty($meta['institution_logo_data_uri']))
                        <img src="{{ $meta['institution_logo_data_uri'] }}" alt="Logo" style="height: 28px; float: left; margin-right: 8px;" />
                    @endif
                    <p class="brand-title">{{ strtoupper($meta['institution_name'] ?? 'KOPERASI DESA MERAH PUTIH (KOPDES)') }}</p>
                    <p class="brand-subtitle">Audit Rekonsiliasi Laci Kasir, Blind Count &amp; Pengawasan Shift POS</p>
                </td>
                <td class="doc-badge" style="width: 45%;">
                    <p class="doc-title">Laporan Riwayat Shift Kasir</p>
                    <div class="doc-code">No. Dokumen: {{ $meta['document_code'] }} | Dicetak: {{ $meta['generated_at'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Periode Shift</td>
                <td class="meta-value">: {{ $meta['period_label'] }}</td>
                <td class="meta-label">Dicetak Oleh</td>
                <td class="meta-value">: {{ $meta['generated_by'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">Cakupan Wilayah</td>
                <td class="meta-value">: {{ $meta['region_label'] }}</td>
                <td class="meta-label">Status Shift</td>
                <td class="meta-value">: {{ $meta['shift_status_label'] }}</td>
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
                <div class="kpi-label">Total Shift</div>
                <div class="kpi-value">{{ $fmtInt($summary['total_shifts'] ?? 0) }} Shift</div>
            </td>
            <td class="kpi-card blue">
                <div class="kpi-label">Shift Aktif (Open)</div>
                <div class="kpi-value blue">{{ $fmtInt($summary['open_shifts'] ?? 0) }} Shift</div>
            </td>
            <td class="kpi-card green">
                <div class="kpi-label">Shift Selesai (Closed)</div>
                <div class="kpi-value green">{{ $fmtInt($summary['closed_shifts'] ?? 0) }} Shift</div>
            </td>
            <td class="kpi-card {{ $totalVar < 0 ? 'rose' : ($totalVar > 0 ? 'amber' : 'green') }}">
                <div class="kpi-label">Akumulasi Selisih Kas</div>
                <div class="kpi-value {{ $totalVar < 0 ? 'rose' : ($totalVar > 0 ? 'amber' : 'green') }}">
                    {{ $totalVar > 0 ? '+' : '' }}{{ $fmtRp($totalVar) }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section-heading">Daftar Riwayat &amp; Rekonsiliasi Shift Kasir</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 3%;">No</th>
                <th style="width: 12%;">Kode Shift &amp; Waktu</th>
                <th style="width: 12%;">Cabang &amp; Kasir</th>
                <th class="text-center" style="width: 7%;">Status</th>
                <th class="text-right" style="width: 7%;">Modal Awal</th>
                <th class="text-right" style="width: 8%;">Penj. Tunai</th>
                <th class="text-right" style="width: 8%;">Midtrans</th>
                <th class="text-right" style="width: 7%;">Kas Masuk</th>
                <th class="text-right" style="width: 7%;">Kas Keluar</th>
                <th class="text-right" style="width: 7%;">Refund Laci</th>
                <th class="text-right" style="width: 8%;">Ekspektasi</th>
                <th class="text-right" style="width: 7%;">Fisik Aktual</th>
                <th class="text-right" style="width: 7%;">Selisih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shifts as $idx => $s)
                @php
                    $isClosed = ($s['status'] ?? '') === 'closed';
                    $varVal = (float) ($s['variance'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-mono font-bold">{{ $s['shift_code'] }}</div>
                        <div class="text-muted">Buka: {{ $s['opened_at'] ?? '-' }}</div>
                        @if (! empty($s['closed_at']))
                            <div class="text-muted">Tutup: {{ $s['closed_at'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="font-bold">{{ $s['cashier']['name'] ?? '-' }}</div>
                        <div class="text-muted">{{ $s['branch']['name'] ?? '-' }} ({{ $s['branch']['code'] ?? '-' }})</div>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $isClosed ? 'badge-closed' : 'badge-open' }}">
                            {{ $isClosed ? 'Closed' : 'Open' }}
                        </span>
                        @if (! empty($s['is_force_closed']))
                            <div style="margin-top: 2px;"><span class="badge badge-force">Force-Close</span></div>
                        @endif
                    </td>
                    <td class="text-right">{{ $fmtRp($s['opening_cash'] ?? 0) }}</td>
                    <td class="text-right">{{ $isClosed ? $fmtRp($s['cash_sales_total'] ?? 0) : '-' }}</td>
                    <td class="text-right text-blue">{{ $isClosed ? $fmtRp($s['midtrans_sales_total'] ?? 0) : '-' }}</td>
                    <td class="text-right text-green">{{ $isClosed ? $fmtRp($s['cash_in_total'] ?? 0) : '-' }}</td>
                    <td class="text-right text-rose">{{ $isClosed ? $fmtRp($s['cash_out_total'] ?? 0) : '-' }}</td>
                    <td class="text-right text-rose">{{ $isClosed ? $fmtRp($s['cash_refund_total'] ?? 0) : '-' }}</td>
                    <td class="text-right font-bold">{{ $isClosed ? $fmtRp($s['expected_closing_cash'] ?? 0) : '-' }}</td>
                    <td class="text-right font-bold">{{ $isClosed ? $fmtRp($s['actual_closing_cash'] ?? 0) : '-' }}</td>
                    <td class="text-right {{ ! $isClosed ? '' : ($varVal < 0 ? 'text-rose' : ($varVal > 0 ? 'text-amber' : 'text-green')) }}">
                        @if ($isClosed)
                            {{ $varVal > 0 ? '+' : '' }}{{ $fmtRp($varVal) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center text-muted" style="padding: 12px;">
                        Belum ada data riwayat shift kasir pada periode dan filter ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($shifts))
            @php
                $closedList = array_filter($shifts, fn ($s) => ($s['status'] ?? '') === 'closed');
                $sumOpenCash = array_sum(array_column($shifts, 'opening_cash'));
                $sumCashSales = array_sum(array_column($closedList, 'cash_sales_total'));
                $sumMidtrans = array_sum(array_column($closedList, 'midtrans_sales_total'));
                $sumCashIn = array_sum(array_column($closedList, 'cash_in_total'));
                $sumCashOut = array_sum(array_column($closedList, 'cash_out_total'));
                $sumRefund = array_sum(array_column($closedList, 'cash_refund_total'));
                $sumExp = array_sum(array_map(fn ($s) => (float) ($s['expected_closing_cash'] ?? 0), $closedList));
                $sumAct = array_sum(array_map(fn ($s) => (float) ($s['actual_closing_cash'] ?? 0), $closedList));
                $sumVar = array_sum(array_map(fn ($s) => (float) ($s['variance'] ?? 0), $closedList));
            @endphp
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">TOTAL KESELURUHAN ({{ $fmtInt(count($shifts)) }} SHIFT)</td>
                    <td class="text-right">{{ $fmtRp($sumOpenCash) }}</td>
                    <td class="text-right">{{ $fmtRp($sumCashSales) }}</td>
                    <td class="text-right text-blue">{{ $fmtRp($sumMidtrans) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($sumCashIn) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($sumCashOut) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($sumRefund) }}</td>
                    <td class="text-right">{{ $fmtRp($sumExp) }}</td>
                    <td class="text-right">{{ $fmtRp($sumAct) }}</td>
                    <td class="text-right {{ $sumVar < 0 ? 'text-rose' : ($sumVar > 0 ? 'text-amber' : 'text-green') }}">
                        {{ $sumVar > 0 ? '+' : '' }}{{ $fmtRp($sumVar) }}
                    </td>
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
        * Catatan Audit Shift: Ekspektasi Kas = Modal Awal + Penjualan Tunai + Kas Masuk &minus; Kas Keluar &minus; Refund Tunai Laci Shift. Penjualan Midtrans ditampilkan sebagai informasi omzet non-tunai dan tidak menambah ekspektasi laci fisik. Mutasi kas laci dicatat terpisah dari Biaya Operasional.
    </div>
</body>
</html>
