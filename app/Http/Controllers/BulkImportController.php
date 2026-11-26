<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkImportRequest;
use App\Services\BulkImportService;
use App\Services\ImportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkImportController extends Controller
{
    public function __construct(
        private readonly ImportTemplateService $templateService,
        private readonly BulkImportService $importService,
    ) {}

    /**
     * Unduh template Excel (.xlsx) resmi untuk Produk, Stok Awal, atau Member
     * (GET /imports/{type}/template).
     */
    public function downloadTemplate(Request $request, string $type): StreamedResponse
    {
        $actor = $request->user();

        if ($type === 'products' && ! $actor?->isAdminNasional()) {
            abort(403, 'Hanya Admin Nasional yang dapat mengunduh template import produk.');
        }

        if (in_array($type, ['stocks', 'members'], true) && ! ($actor?->isAdminNasional() || $actor?->isAdminCabang())) {
            abort(403, 'Anda tidak berwenang mengunduh template import ini.');
        }

        return $this->templateService->download($type);
    }

    /**
     * Proses upload & import massal Excel/CSV secara all-or-nothing
     * (POST /imports/{type}).
     */
    public function import(BulkImportRequest $request, string $type): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        $file = $request->file('file');
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        $result = match ($type) {
            'products' => $this->importService->importProducts($file, $actor),
            'stocks' => $this->importService->importInitialStocks($file, $actor, $branchId),
            'members' => $this->importService->importMembers($file, $actor, $branchId),
            default => abort(404),
        };

        $labelMap = [
            'products' => 'produk baru',
            'stocks' => 'baris stok awal cabang',
            'members' => 'anggota (member) baru',
        ];
        $message = "Import massal berhasil: {$result['imported_count']} ".($labelMap[$type] ?? 'baris').' telah disimpan.';

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'result' => $result,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }
}
