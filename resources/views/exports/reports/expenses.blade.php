<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Biaya Operasional Cabang KOPDES</title>
    <style>
        @page {
            margin: 14mm 10mm 16mm 10mm;
        }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        .letterhead {
            border-bottom: 3px solid #b91c1c;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .letterhead-table { width: 100%; border-collapse: collapse; }
        .brand-title { font-size: 15px; font-weight: bold; color: #b91c1c; letter-spacing: 0.4px; margin: 0; }
        .brand-subtitle { font-size: 9px; color: #4b5563; margin: 2px 0 0 0; }
        .doc-badge { text-align: right; vertical-align: top; }
        .doc-title { font-size: 12px; font-weight: bold; color: #111827; text-transform: uppercase; margin: 0; }
        .doc-code { font-size: 8px; color: #6b7280; margin-top: 2px; }

        .meta-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 7px 10px;
            margin-bottom: 10px;
        }

        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 2px 4px; vertical-align: top; font-size: 8.5px; }
        .meta-label { font-weight: bold; color: #374151; width: 16%; white-space: nowrap; }
        .meta-value { color: #111827; width: 34%; }

        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 12px -6px;
        }

        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            border-top: 3px solid #b91c1c;
            padding: 6px 8px;
            width: 25%;
        }

        .kpi-card.rose { border-top-color: #be123c; }
        .kpi-card.blue { border-top-color: #1d4ed8; }
        .kpi-label { font-size: 7.5px; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .kpi-value { font-size: 11px; font-weight: bold; color: #111827; margin-top: 3px; }
        .kpi-value.rose { color: #be123c; }
        .kpi-value.blue { color: #1d4ed8; }

        .section-heading {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
            background-color: #f3f4f6;
            border-left: 4px solid #b91c1c;
            padding: 5px 8px;
            margin: 10px 0 6px 0;
            text-transform: uppercase;
        }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .data-table th {
            background-color: #1f2937;
            color: #ffffff;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            padding: 6px 5px;
            border: 1px solid #374151;
            text-align: left;
        }
        .data-table td {
            padding: 5px;
            border: 1px solid #d1d5db;
            font-size: 8.5px;
            vertical-align: top;
        }
        .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tfoot td {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 8.5px;
            border-top: 2px solid #1f2937;
            border-bottom: 2px solid #1f2937;
            padding: 6px 5px;
        }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono { font-family: "DejaVu Sans Mono", monospace; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #6b7280; font-size: 7.5px; }
        .text-rose { color: #be123c; font-weight: bold; }

        .footnote {
            font-size: 7.5px;
            color: #6b7280;
            font-style: italic;
            border-top: 1px dashed #d1d5db;
            padding-top: 6px;
            margin-top: 6px;
        }

        .signature-table { width: 100%; border-collapse: collapse; margin-top: 18px; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; text-align: center; vertical-align: top; font-size: 8.5px; }
        .signature-space { height: 42px; }
        .signature-line {
            display: inline-block;
            border-top: 1px solid #374151;
            width: 145px;
            padding-top: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @php
        $fmtRp = fn ($val) => 'Rp ' . number_format((float) ($val ?? 0), 0, ',', '.');
        $fmtInt = fn ($val) => number_format((int) ($val ?? 0), 0, ',', '.');
        $fmtPct = fn ($val) => number_format((float) ($val ?? 0), 2, ',', '.') . '%';
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
                    <p class="brand-subtitle">Pencatatan &amp; Audit Biaya Operasional Cabang KOPDES</p>
                </td>
                <td class="doc-badge" style="width: 45%;">
                    <p class="doc-title">Laporan Biaya Operasional</p>
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
                <td class="meta-label">Filter Kategori</td>
                <td class="meta-value">: {{ $meta['category_label'] }}</td>
            </tr>
            @if ($meta['search_keyword'] !== '')
                <tr>
                    <td class="meta-label">Kata Kunci</td>
                    <td class="meta-value" colspan="3">: "{{ $meta['search_keyword'] }}"</td>
                </tr>
            @endif
        </table>
    </div>

    <!-- KPI Ringkasan -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card rose">
                <div class="kpi-label">Total Biaya Operasional</div>
                <div class="kpi-value rose">{{ $fmtRp($summary['total_amount']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Jumlah Catatan Biaya</div>
                <div class="kpi-value">{{ $fmtInt($summary['total_count']) }} Catatan</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Rata-rata per Catatan</div>
                <div class="kpi-value">{{ $fmtRp($summary['average_amount']) }}</div>
            </td>
            <td class="kpi-card blue">
                <div class="kpi-label">Kategori Aktif</div>
                <div class="kpi-value blue">{{ $fmtInt($summary['active_categories_count']) }} Kategori</div>
            </td>
        </tr>
    </table>

    <!-- Tabel 1: Rekap Subtotal per Kategori -->
    <div class="section-heading">1. Rekapitulasi Subtotal per Kategori Biaya Operasional</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 45%;">Kategori Biaya</th>
                <th class="text-center" style="width: 15%;">Jumlah Catatan</th>
                <th class="text-right" style="width: 22%;">Total Nominal</th>
                <th class="text-center" style="width: 13%;">Porsi (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categorySummaries as $idx => $cat)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold">{{ $cat['label'] }}</td>
                    <td class="text-center font-mono">{{ $fmtInt($cat['count']) }}</td>
                    <td class="text-right font-mono text-rose">{{ $fmtRp($cat['total_amount']) }}</td>
                    <td class="text-center font-mono">{{ $fmtPct($cat['percentage']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right">TOTAL BIAYA OPERASIONAL</td>
                <td class="text-center font-mono">{{ $fmtInt($summary['total_count']) }}</td>
                <td class="text-right font-mono text-rose">{{ $fmtRp($summary['total_amount']) }}</td>
                <td class="text-center font-mono">{{ (float) ($summary['total_amount'] ?? 0) > 0 ? '100,00%' : '0,00%' }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Tabel 2: Rincian Transaksi Pengeluaran -->
    <div class="section-heading">2. Daftar Rincian Transaksi Biaya Operasional</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 4%;">No</th>
                <th class="text-center" style="width: 10%;">Tanggal</th>
                <th style="width: 18%;">Cabang KOPDES</th>
                <th style="width: 15%;">Kategori</th>
                <th style="width: 27%;">Deskripsi &amp; Catatan</th>
                <th style="width: 13%;">Pencatat</th>
                <th class="text-right" style="width: 13%;">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $idx => $exp)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center font-mono font-bold">{{ $exp['expense_date'] }}</td>
                    <td>
                        <div class="font-bold">{{ $exp['branch']['name'] ?? '-' }}</div>
                        <div class="font-mono text-muted">{{ $exp['branch']['code'] ?? '' }}</div>
                    </td>
                    <td class="font-bold">{{ $exp['category_label'] }}</td>
                    <td>
                        <div class="font-bold">{{ $exp['description'] }}</div>
                        @if (! empty($exp['notes']))
                            <div class="text-muted">{{ $exp['notes'] }}</div>
                        @endif
                    </td>
                    <td>{{ $exp['user']['name'] ?? '-' }}</td>
                    <td class="text-right font-mono text-rose">{{ $fmtRp($exp['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding: 14px;">
                        Belum ada catatan biaya operasional pada periode dan filter terpilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($expenses))
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right">TOTAL KESELURUHAN ({{ $fmtInt(count($expenses)) }} CATATAN)</td>
                    <td class="text-right font-mono text-rose">{{ $fmtRp($summary['total_amount']) }}</td>
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
        * Catatan Audit: Laporan ini hanya mencakup catatan biaya operasional aktif (catatan yang telah dihapus secara soft-delete tidak dihitung) dan menjadi pengurang langsung Laba Kotor pada Laporan Laba Rugi &amp; Dasar SHU KOPDES.
    </div>
</body>
</html>
