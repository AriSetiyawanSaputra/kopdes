<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan SHU &amp; Rekap Transaksi Member KOPDES</title>
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
        .meta-label { font-weight: bold; color: #374151; width: 16%; white-space: nowrap; }
        .meta-value { color: #111827; width: 34%; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 -5px 8px -5px; }
        .kpi-card { background-color: #ffffff; border: 1px solid #d1d5db; border-top: 3px solid #b91c1c; padding: 5px 6px; vertical-align: top; }
        .kpi-card.blue { border-top-color: #1d4ed8; }
        .kpi-card.green { border-top-color: #047857; }
        .kpi-card.rose { border-top-color: #be123c; }
        .kpi-card.amber { border-top-color: #b45309; }
        .kpi-label { font-size: 6.8px; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .kpi-value { font-size: 9.5px; font-weight: bold; color: #111827; margin-top: 2px; }
        .kpi-sub { font-size: 6.8px; color: #6b7280; margin-top: 1px; }
        .kpi-value.blue { color: #1d4ed8; }
        .kpi-value.green { color: #047857; }
        .kpi-value.rose { color: #be123c; }
        .kpi-value.amber { color: #b45309; }
        .section-heading { font-size: 10px; font-weight: bold; color: #111827; background-color: #f3f4f6; border-left: 4px solid #b91c1c; padding: 5px 8px; margin: 9px 0 6px 0; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .data-table th { background-color: #1f2937; color: #ffffff; font-weight: bold; font-size: 7.3px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #374151; text-align: left; }
        .data-table td { padding: 4.5px 4px; border: 1px solid #d1d5db; font-size: 7.8px; vertical-align: top; }
        .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tfoot td { background-color: #f3f4f6; font-weight: bold; font-size: 7.8px; border-top: 2px solid #1f2937; border-bottom: 2px solid #1f2937; padding: 5px 4px; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono { font-family: "DejaVu Sans Mono", monospace; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #6b7280; font-size: 7px; }
        .text-blue { color: #1d4ed8; font-weight: bold; }
        .text-green { color: #047857; font-weight: bold; }
        .text-rose { color: #be123c; font-weight: bold; }
        .text-primary { color: #b91c1c; font-weight: bold; }
        .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-inactive { background-color: #fee2e2; color: #991b1b; }
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
        $grandTotalSpend = (float) ($summary['total_accumulated_spend'] ?? 0);
        $grossRev = (float) ($shuFinancials['gross_revenue'] ?? $shuFinancials['total_revenue'] ?? 0);
        $totalRet = (float) ($shuFinancials['total_returns'] ?? 0);
        $netRev = (float) ($shuFinancials['net_revenue'] ?? $shuFinancials['total_revenue'] ?? 0);
        $netCogs = (float) ($shuFinancials['net_cogs'] ?? $shuFinancials['total_cogs'] ?? 0);
        $damagedCogs = (float) ($shuFinancials['damaged_cogs'] ?? 0);
        $netProfit = (float) ($shuFinancials['net_profit'] ?? 0);
        $memberDiscount = (float) ($shuFinancials['total_member_discount'] ?? 0);
        $totalEstimatedShu = array_sum(array_column($members, 'estimated_shu_share'));
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
                    <p class="brand-subtitle">Laporan Sisa Hasil Usaha (SHU) Pasca-Retur &amp; Partisipasi Belanja Anggota</p>
                </td>
                <td class="doc-badge" style="width: 45%;">
                    <p class="doc-title">Laporan SHU &amp; Rekap Member</p>
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
                <td class="meta-label">Cakupan KOPDES</td>
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

    <!-- Komponen Dasar SHU (Laba Bersih Pasca-Retur) -->
    <div class="section-heading">1. Rincian Komponen Dasar SHU (Omzet Bruto &minus; Retur &rarr; Omzet Bersih &minus; HPP Bersih &rarr; Laba Kotor &minus; Biaya Ops &rarr; Dasar SHU)</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 16.66%;">
                <div class="kpi-label">Omzet Bruto</div>
                <div class="kpi-value">{{ $fmtRp($grossRev) }}</div>
                <div class="kpi-sub">Retur: {{ $fmtRp($totalRet) }}</div>
            </td>
            <td class="kpi-card" style="width: 16.66%;">
                <div class="kpi-label">(=) Omzet Bersih</div>
                <div class="kpi-value">{{ $fmtRp($netRev) }}</div>
                <div class="kpi-sub">Hemat Mbr: {{ $fmtRp($memberDiscount) }}</div>
            </td>
            <td class="kpi-card" style="width: 16.66%;">
                <div class="kpi-label">(-) HPP Bersih</div>
                <div class="kpi-value">{{ $fmtRp($netCogs) }}</div>
                <div class="kpi-sub">Rusak: {{ $fmtRp($damagedCogs) }}</div>
            </td>
            <td class="kpi-card green" style="width: 16.66%;">
                <div class="kpi-label">(=) Laba Kotor</div>
                <div class="kpi-value green">{{ $fmtRp($shuFinancials['gross_profit'] ?? 0) }}</div>
            </td>
            <td class="kpi-card rose" style="width: 16.66%;">
                <div class="kpi-label">(-) Biaya Ops</div>
                <div class="kpi-value rose">{{ $fmtRp($shuFinancials['total_operational_expenses'] ?? 0) }}</div>
            </td>
            <td class="kpi-card {{ $netProfit >= 0 ? 'green' : 'rose' }}" style="width: 16.66%;">
                <div class="kpi-label">(=) Dasar SHU</div>
                <div class="kpi-value {{ $netProfit >= 0 ? 'green' : 'rose' }}">{{ $fmtRp($netProfit) }}</div>
                <div class="kpi-sub">Margin {{ $fmtPct($shuFinancials['net_margin_percentage'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <!-- Ringkasan Partisipasi Belanja Member -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Anggota Bertransaksi (Paid)</div>
                <div class="kpi-value">{{ $fmtInt($summary['active_shopping_members']) }} / {{ $fmtInt($summary['total_members']) }}</div>
                <div class="kpi-sub">Total {{ $fmtInt($summary['total_transactions']) }} transaksi lunas</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Belanja Bersih KOPDES Asal</div>
                <div class="kpi-value">{{ $fmtRp($summary['total_home_branch_spend']) }}</div>
            </td>
            <td class="kpi-card blue" style="width: 25%;">
                <div class="kpi-label">Belanja Bersih Lintas Cabang</div>
                <div class="kpi-value blue">{{ $fmtRp($summary['total_other_branch_spend']) }}</div>
            </td>
            <td class="kpi-card green" style="width: 25%;">
                <div class="kpi-label">Total Akumulasi Belanja Bersih</div>
                <div class="kpi-value green">{{ $fmtRp($summary['total_accumulated_spend']) }}</div>
                <div class="kpi-sub">Potongan Retur Mbr: {{ $fmtRp($summary['total_member_returns'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <!-- Tabel Rekap Member -->
    <div class="section-heading">2. Daftar Partisipasi Belanja Bersih Anggota (Pasca-Retur) &amp; Estimasi Porsi SHU</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 3%;">No</th>
                <th style="width: 13%;">Kode Member</th>
                <th style="width: 14%;">Nama Anggota</th>
                <th class="text-center" style="width: 6%;">Status</th>
                <th style="width: 14%;">KOPDES Asal</th>
                <th class="text-center" style="width: 4%;">Trx</th>
                <th class="text-right" style="width: 9%;">KOPDES Asal</th>
                <th class="text-right" style="width: 9%;">KOPDES Lain</th>
                <th class="text-right" style="width: 8%;">Retur</th>
                <th class="text-right" style="width: 9%;">Belanja Bersih</th>
                <th class="text-center" style="width: 4%;">Porsi</th>
                <th class="text-right" style="width: 7%;">Est. SHU</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $idx => $m)
                @php
                    $homeBranch = $m['home_branch'] ?? null;
                    $homeName = is_array($homeBranch) ? ($homeBranch['name'] ?? '-') : ($homeBranch->name ?? '-');
                    $homeCode = is_array($homeBranch) ? ($homeBranch['code'] ?? '') : ($homeBranch->code ?? '');
                    $memberSpend = (float) ($m['total_spend'] ?? 0);
                    $sharePct = (float) ($m['spend_share_percent'] ?? ($grandTotalSpend > 0 ? ($memberSpend / $grandTotalSpend) * 100 : 0));
                    $estShu = (float) ($m['estimated_shu_share'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-mono font-bold">{{ $m['member_code'] }}</td>
                    <td class="font-bold">{{ $m['name'] }}</td>
                    <td class="text-center">
                        <span class="badge {{ ($m['status'] ?? 'active') === 'active' ? 'badge-active' : 'badge-inactive' }}">
                            {{ ($m['status'] ?? 'active') === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td>
                        <div class="font-bold">{{ $homeName }}</div>
                        <div class="font-mono text-muted">{{ $homeCode }}</div>
                    </td>
                    <td class="text-center font-bold">{{ $fmtInt($m['paid_transactions_count']) }}</td>
                    <td class="text-right">{{ $fmtRp($m['home_branch_spend']) }}</td>
                    <td class="text-right text-blue">{{ $fmtRp($m['other_branch_spend']) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($m['returned_spend'] ?? 0) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($memberSpend) }}</td>
                    <td class="text-center">{{ $fmtPct($sharePct) }}</td>
                    <td class="text-right text-primary">{{ $fmtRp($estShu) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center text-muted" style="padding: 14px;">
                        Belum ada data anggota pada periode dan filter wilayah terpilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($members))
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">TOTAL KESELURUHAN ({{ $fmtInt($summary['total_members']) }} ANGGOTA)</td>
                    <td class="text-center">{{ $fmtInt($summary['total_transactions']) }}</td>
                    <td class="text-right">{{ $fmtRp($summary['total_home_branch_spend']) }}</td>
                    <td class="text-right text-blue">{{ $fmtRp($summary['total_other_branch_spend']) }}</td>
                    <td class="text-right text-rose">{{ $fmtRp($summary['total_member_returns'] ?? 0) }}</td>
                    <td class="text-right text-green">{{ $fmtRp($summary['total_accumulated_spend']) }}</td>
                    <td class="text-center">{{ $grandTotalSpend > 0 ? '100,00%' : '0,00%' }}</td>
                    <td class="text-right text-primary">{{ $fmtRp($totalEstimatedShu) }}</td>
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
        * Catatan Audit SHU: Dasar SHU dihitung dari Laba Bersih pasca-retur (Omzet Bersih &minus; HPP Bersih &minus; Biaya Operasional Cabang). Seluruh akumulasi belanja anggota (KOPDES Asal &amp; Lintas Cabang) telah dikurangi nilai retur anggota terkait pada periode yang sama.
    </div>
</body>
</html>
