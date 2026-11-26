<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan &amp; Laba Rugi KOPDES</title>
    <style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.35; margin: 0; padding: 0; }
        .letterhead { border-bottom: 3px solid #b91c1c; padding-bottom: 8px; margin-bottom: 10px; }
        .letterhead-table { width: 100%; border-collapse: collapse; }
        .brand-title { font-size: 15px; font-weight: bold; color: #b91c1c; letter-spacing: 0.4px; margin: 0; }
        .brand-subtitle { font-size: 9px; color: #4b5563; margin: 2px 0 0 0; }
        .doc-badge { text-align: right; vertical-align: top; }
        .doc-title { font-size: 12px; font-weight: bold; color: #111827; text-transform: uppercase; margin: 0; }
        .doc-code { font-size: 8px; color: #6b7280; margin-top: 2px; }
        .meta-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 7px 10px; margin-bottom: 10px; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 2px 4px; vertical-align: top; font-size: 8.5px; }
        .meta-label { font-weight: bold; color: #374151; width: 15%; white-space: nowrap; }
        .meta-value { color: #111827; width: 35%; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 -5px 8px -5px; }
        .kpi-card { background-color: #ffffff; border: 1px solid #d1d5db; border-top: 3px solid #b91c1c; padding: 6px 7px; vertical-align: top; }
        .kpi-card.green { border-top-color: #047857; }
        .kpi-card.amber { border-top-color: #b45309; }
        .kpi-card.rose { border-top-color: #be123c; }
        .kpi-card.blue { border-top-color: #1d4ed8; }
        .kpi-label { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .kpi-value { font-size: 9.5px; font-weight: bold; color: #111827; margin-top: 3px; }
        .kpi-sub { font-size: 7px; color: #6b7280; margin-top: 2px; }
        .kpi-value.green { color: #047857; }
        .kpi-value.amber { color: #b45309; }
        .kpi-value.rose { color: #be123c; }
        .kpi-value.blue { color: #1d4ed8; }
        .section-heading { font-size: 10px; font-weight: bold; color: #111827; background-color: #f3f4f6; border-left: 4px solid #b91c1c; padding: 5px 8px; margin: 10px 0 6px 0; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .data-table th { background-color: #1f2937; color: #ffffff; font-weight: bold; font-size: 7.2px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #374151; text-align: left; }
        .data-table td { padding: 4.5px 4px; border: 1px solid #d1d5db; font-size: 7.8px; vertical-align: top; }
        .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tfoot td { background-color: #f3f4f6; font-weight: bold; font-size: 7.8px; border-top: 2px solid #1f2937; border-bottom: 2px solid #1f2937; padding: 5px 4px; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono { font-family: "DejaVu Sans Mono", monospace; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #6b7280; font-size: 7px; }
        .text-green { color: #047857; font-weight: bold; }
        .text-amber { color: #b45309; font-weight: bold; }
        .text-rose { color: #be123c; font-weight: bold; }
        .text-blue { color: #1d4ed8; font-weight: bold; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .badge-cash { background-color: #dcfce7; color: #166534; }
        .badge-midtrans { background-color: #dbeafe; color: #1e40af; }
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
        $fmtPct = fn ($val) => number_format((float) ($val ?? 0), 2, ',', '.') . '%';
        $grossRevenue = (float) ($summary['gross_revenue'] ?? $summary['total_revenue'] ?? 0);
        $totalReturns = (float) ($summary['total_returns'] ?? 0);
        $netRevenue = (float) ($summary['net_revenue'] ?? $summary['total_revenue'] ?? 0);
        $netCogs = (float) ($summary['net_cogs'] ?? $summary['total_cogs'] ?? 0);
        $damagedCogs = (float) ($summary['damaged_cogs'] ?? 0);
        $netProfit = (float) ($summary['net_profit'] ?? 0);
        $memberDiscount = (float) ($summary['total_member_discount'] ?? 0);
    @endphp

    <!-- Kop Laporan -->
    <div class="letterhead">
        <table class="letterhead-table">
            <tr>
                <td style="width: 55%;">
                    @if (! empty($meta['institution_logo_data_uri']))
                        <img src="{{ $meta['institution_logo_data_uri'] }}" alt="Logo" style="height: 28px; float: left; margin-right: 8px;" />
                    @endif
                    <p class="brand-title">{{ strtoupper($meta['institution_name'] ?? 'KOPERASI DESA MERAH PUTIH (KOPDES)') }}</p>
                    <p class="brand-subtitle">Sistem Kasir Ritel Terpusat, Retur, Biaya Operasional &amp; Pelaporan Laba Rugi Berjenjang</p>
                </td>
                <td class="doc-badge" style="width: 45%;">
                    <p class="doc-title">Laporan Penjualan &amp; Laba Rugi</p>
                    <div class="doc-code">No. Dokumen: {{ $meta['document_code'] }} | Dicetak: {{ $meta['generated_at'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Metadata Filter -->
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Periode Laporan</td>
                <td class="meta-value">: {{ $meta['period_label'] }}</td>
                <td class="meta-label">Dicetak Oleh</td>
                <td class="meta-value">: {{ $meta['generated_by'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">Cakupan Wilayah</td>
                <td class="meta-value">: {{ $meta['region_label'] }}</td>
                <td class="meta-label">Metode Bayar</td>
                <td class="meta-value">: {{ $meta['payment_method_label'] }}</td>
            </tr>
            @if ($meta['search_keyword'] !== '')
                <tr>
                    <td class="meta-label">Filter Pencarian</td>
                    <td class="meta-value" colspan="3">: "{{ $meta['search_keyword'] }}"</td>
                </tr>
            @endif
        </table>
    </div>

    <!-- KPI Ringkasan Laba Rugi Pasca-Retur (Baris 1: Omzet -> HPP) -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Omzet Bruto (Paid)</div>
                <div class="kpi-value">{{ $fmtRp($grossRevenue) }}</div>
                <div class="kpi-sub">{{ $fmtInt($summary['total_transactions']) }} transaksi lunas</div>
            </td>
            <td class="kpi-card rose" style="width: 25%;">
                <div class="kpi-label">(-) Total Retur &amp; Void</div>
                <div class="kpi-value rose">{{ $fmtRp($totalReturns) }}</div>
                <div class="kpi-sub">Kerugian Rusak: {{ $fmtRp($damagedCogs) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">(=) Omzet Bersih</div>
                <div class="kpi-value">{{ $fmtRp($netRevenue) }}</div>
                <div class="kpi-sub">Hemat Member: {{ $fmtRp($memberDiscount) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">(-) HPP Bersih</div>
                <div class="kpi-value">{{ $fmtRp($netCogs) }}</div>
                <div class="kpi-sub">Restock: {{ $fmtRp($summary['restocked_cogs'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <!-- KPI Ringkasan Laba Rugi Pasca-Retur (Baris 2: Laba Kotor -> Laba Bersih) -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card green" style="width: 25%;">
                <div class="kpi-label">(=) Laba Kotor</div>
                <div class="kpi-value green">{{ $fmtRp($summary['gross_profit']) }}</div>
                <div class="kpi-sub">Margin Kotor {{ $fmtPct($summary['gross_margin_percent']) }}</div>
            </td>
            <td class="kpi-card rose" style="width: 25%;">
                <div class="kpi-label">(-) Biaya Operasional</div>
                <div class="kpi-value rose">{{ $fmtRp($summary['total_operational_expenses'] ?? 0) }}</div>
                <div class="kpi-sub">Beban cabang aktif periode</div>
            </td>
            <td class="kpi-card {{ $netProfit >= 0 ? 'green' : 'rose' }}" style="width: 25%;">
                <div class="kpi-label">(=) Laba Bersih / Dasar SHU</div>
                <div class="kpi-value {{ $netProfit >= 0 ? 'green' : 'rose' }}">{{ $fmtRp($netProfit) }}</div>
                <div class="kpi-sub">Laba Kotor &minus; Biaya Ops</div>
            </td>
            <td class="kpi-card amber" style="width: 25%;">
                <div class="kpi-label">Margin Bersih</div>
                <div class="kpi-value amber">{{ $fmtPct($summary['net_margin_percentage'] ?? 0) }}</div>
                <div class="kpi-sub">Laba Bersih / Omzet Bersih</div>
            </td>
        </tr>
    </table>

    <!-- Tabel 1: Agregasi per Cabang -->
    <div class="section-heading">1. Agregasi Laba Rugi Pasca-Retur per Cabang KOPDES</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 3%;">No</th>
                <th style="width: 17%;">Cabang KOPDES</th>
                <th class="text-center" style="width: 5%;">Trx</th>
                <th class="text-right" style="width: 9%;">Omzet Bruto</th>
                <th class="text-right" style="width: 8%;">Retur</th>
                <th class="text-right" style="width: 9%;">Omzet Bersih</th>
                <th class="text-right" style="width: 9%;">HPP Bersih</th>
                <th class="text-right" style="width: 8%;">HPP Rusak</th>
                <th class="text-right" style="width: 9%;">Laba Kotor</th>
                <th class="text-right" style="width: 9%;">Biaya Ops</th>
                <th class="text-right" style="width: 9%;">Laba Bersih</th>
                <th class="text-center" style="width: 5%;">Net %</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($branchSummaries as $idx => $b)
                @php
                    $branchNet = (float) ($b['net_profit'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-bold">{{ $b['branch_name'] }}</div>
                        <div class="font-mono text-muted">{{ $b['branch_code'] }} &bull; {{ $b['regency_name'] ?? '-' }}</div>
                    </td>
                    <td class="text-center font-bold">{{ $fmtInt($b['total_transactions']) }}</td>
                    <td class="text-right">{{ $fmtRp($b['gross_revenue'] ?? $b['total_revenue']) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($b['total_returns'] ?? 0) }}</td>
                    <td class="text-right font-bold">{{ $fmtRp($b['net_revenue'] ?? $b['total_revenue']) }}</td>
                    <td class="text-right">{{ $fmtRp($b['net_cogs'] ?? $b['total_cogs']) }}</td>
                    <td class="text-right text-amber">{{ $fmtRp($b['damaged_cogs'] ?? 0) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($b['gross_profit']) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($b['operational_expenses'] ?? 0) }}</td>
                    <td class="text-right {{ $branchNet >= 0 ? 'text-green' : 'text-rose' }}">{{ $fmtRp($branchNet) }}</td>
                    <td class="text-center text-amber">{{ $fmtPct($b['net_margin_percentage'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center text-muted" style="padding: 12px;">
                        Tidak ada data cabang pada periode dan filter wilayah terpilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($branchSummaries))
            @php
                $sumTrx = array_sum(array_column($branchSummaries, 'total_transactions'));
                $sumGrossRev = array_sum(array_map(fn ($b) => (float) ($b['gross_revenue'] ?? $b['total_revenue'] ?? 0), $branchSummaries));
                $sumRet = array_sum(array_map(fn ($b) => (float) ($b['total_returns'] ?? 0), $branchSummaries));
                $sumNetRev = array_sum(array_map(fn ($b) => (float) ($b['net_revenue'] ?? $b['total_revenue'] ?? 0), $branchSummaries));
                $sumNetCogs = array_sum(array_map(fn ($b) => (float) ($b['net_cogs'] ?? $b['total_cogs'] ?? 0), $branchSummaries));
                $sumDmgCogs = array_sum(array_map(fn ($b) => (float) ($b['damaged_cogs'] ?? 0), $branchSummaries));
                $sumGross = array_sum(array_column($branchSummaries, 'gross_profit'));
                $sumOpex = array_sum(array_column($branchSummaries, 'operational_expenses'));
                $sumNet = array_sum(array_column($branchSummaries, 'net_profit'));
                $sumNetMargin = $sumNetRev > 0 ? ($sumNet / $sumNetRev) * 100 : 0;
            @endphp
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right">TOTAL KESELURUHAN CABANG</td>
                    <td class="text-center">{{ $fmtInt($sumTrx) }}</td>
                    <td class="text-right">{{ $fmtRp($sumGrossRev) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($sumRet) }}</td>
                    <td class="text-right">{{ $fmtRp($sumNetRev) }}</td>
                    <td class="text-right">{{ $fmtRp($sumNetCogs) }}</td>
                    <td class="text-right text-amber">{{ $fmtRp($sumDmgCogs) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($sumGross) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($sumOpex) }}</td>
                    <td class="text-right {{ $sumNet >= 0 ? 'text-green' : 'text-rose' }}">{{ $fmtRp($sumNet) }}</td>
                    <td class="text-center text-amber">{{ $fmtPct($sumNetMargin) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <!-- Tabel 2: Rincian Transaksi Lunas -->
    <div class="section-heading">2. Daftar Rincian Transaksi Lunas (Paid) Berbasis Snapshot HPP &amp; Diskon Member</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 3%;">No</th>
                <th style="width: 18%;">No. Invoice &amp; Waktu Lunas</th>
                <th style="width: 16%;">Cabang &amp; Kasir</th>
                <th style="width: 16%;">Pembeli / Anggota</th>
                <th class="text-center" style="width: 8%;">Metode</th>
                <th class="text-center" style="width: 4%;">Item</th>
                <th class="text-right" style="width: 8%;">Hemat Member</th>
                <th class="text-right" style="width: 9%;">Omzet Paid</th>
                <th class="text-right" style="width: 9%;">HPP Snapshot</th>
                <th class="text-right" style="width: 9%;">Laba Kotor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $idx => $tx)
                @php
                    $branch = $tx['branch'] ?? null;
                    $cashier = $tx['cashier'] ?? null;
                    $member = $tx['member'] ?? null;
                    $branchName = is_array($branch) ? ($branch['name'] ?? '-') : ($branch->name ?? '-');
                    $cashierName = is_array($cashier) ? ($cashier['name'] ?? '-') : ($cashier->name ?? '-');
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-mono font-bold">{{ $tx['invoice_number'] }}</div>
                        <div class="text-muted">
                            {{ $tx['paid_at'] }}
                            @if (($tx['return_status'] ?? 'none') === 'partial')
                                <span class="badge badge-partial">Retur Parsial</span>
                            @elseif (($tx['return_status'] ?? 'none') === 'full')
                                <span class="badge badge-full">Full Void</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="font-bold">{{ $branchName }}</div>
                        <div class="text-muted">Kasir: {{ $cashierName }}</div>
                    </td>
                    <td>
                        @if ($member)
                            <div class="font-bold">{{ $member['name'] }}</div>
                            <div class="text-muted">{{ $member['member_code'] }} — {{ $member['home_branch_name'] ?? '-' }}</div>
                        @else
                            <span class="text-muted">Pembeli Umum (Non-Member)</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $tx['payment_method'] === 'cash' ? 'badge-cash' : 'badge-midtrans' }}">
                            {{ strtoupper($tx['payment_method']) }}
                        </span>
                    </td>
                    <td class="text-center">{{ $fmtInt($tx['items_count']) }}</td>
                    <td class="text-right text-blue">{{ $fmtRp($tx['member_discount_total'] ?? 0) }}</td>
                    <td class="text-right font-bold">{{ $fmtRp($tx['total_amount']) }}</td>
                    <td class="text-right">{{ $fmtRp($tx['total_cogs']) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($tx['gross_profit']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted" style="padding: 14px;">
                        Belum ada transaksi berstatus lunas (paid) pada periode dan filter ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($transactions))
            @php
                $txItems = array_sum(array_column($transactions, 'items_count'));
                $txDisc = array_sum(array_map(fn ($t) => (float) ($t['member_discount_total'] ?? 0), $transactions));
                $txRev = array_sum(array_column($transactions, 'total_amount'));
                $txCogs = array_sum(array_column($transactions, 'total_cogs'));
                $txProfit = array_sum(array_column($transactions, 'gross_profit'));
            @endphp
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">TOTAL RINCIAN TRANSAKSI ({{ $fmtInt(count($transactions)) }} TRX)</td>
                    <td class="text-center">{{ $fmtInt($txItems) }}</td>
                    <td class="text-right text-blue">{{ $fmtRp($txDisc) }}</td>
                    <td class="text-right">{{ $fmtRp($txRev) }}</td>
                    <td class="text-right">{{ $fmtRp($txCogs) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($txProfit) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <!-- Blok Pengesahan -->
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
        * Catatan Audit: Omzet Bersih = Omzet Bruto (Paid) &minus; Total Retur. HPP Bersih = HPP Bruto &minus; HPP Restock (item retur layak jual); HPP barang rusak tetap membebani Laba Kotor. Laba Bersih (Dasar SHU) = Laba Kotor &minus; Biaya Operasional aktif (mutasi kas laci shift tidak termasuk Biaya Operasional).
    </div>
</body>
</html>
