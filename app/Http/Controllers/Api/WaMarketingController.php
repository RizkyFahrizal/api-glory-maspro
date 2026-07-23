<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WaMarketing;

class WaMarketingController extends Controller
{
    // GET /api/wa-marketing/next
    public function next(Request $request)
    {
        // 1. Cari marketing yang ditandai sebagai giliran saat ini (is_next_in_queue = true)
        $marketing = WaMarketing::whereHas('user', function($q) {
                $q->where('role', 'marketing');
            })
            ->where('is_active', true)
            ->where('is_next_in_queue', true)
            ->first();

        // 2. Jika tidak ada yang ditandai, ambil saja yang posisinya paling atas (queue_order terkecil)
        if (!$marketing) {
            $marketing = WaMarketing::whereHas('user', function($q) {
                $q->where('role', 'marketing');
            })
            ->where('is_active', true)
            ->orderBy('queue_order', 'asc')
            ->first();
        }

        if (!$marketing) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tim marketing yang aktif saat ini.',
            ], 404);
        }

        // Catat waktu pemberian prospek dan hilangkan tanda gilirannya
        $marketing->last_assigned_at = now();
        $marketing->is_next_in_queue = false;
        $marketing->save();

        // Cari marketing berikutnya (yang urutannya di bawah marketing saat ini)
        $nextMarketing = WaMarketing::whereHas('user', function($q) {
                $q->where('role', 'marketing');
            })
            ->where('is_active', true)
            ->where('queue_order', '>', $marketing->queue_order)
            ->orderBy('queue_order', 'asc')
            ->first();

        // Jika tidak ada di bawahnya (berarti dia yang terakhir), putar kembali ke urutan paling atas
        if (!$nextMarketing) {
            $nextMarketing = WaMarketing::whereHas('user', function($q) {
                    $q->where('role', 'marketing');
                })
                ->where('is_active', true)
                ->orderBy('queue_order', 'asc')
                ->first();
        }

        // Tandai dia sebagai penerima giliran berikutnya
        if ($nextMarketing) {
            $nextMarketing->is_next_in_queue = true;
            $nextMarketing->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Giliran marketing selanjutnya berhasil diambil.',
            'data' => [
                'phone_number' => $marketing->phone_number,
                'user_name' => $marketing->user->name ?? 'Tim Marketing'
            ]
        ]);
    }

    // POST /api/wa-marketing/reorder
    public function reorder(Request $request)
    {
        $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'exists:wa_marketings,id'
        ]);

        foreach ($request->ordered_ids as $index => $id) {
            WaMarketing::where('id', $id)->update(['queue_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Urutan antrean berhasil disimpan permanen.'
        ]);
    }
}
