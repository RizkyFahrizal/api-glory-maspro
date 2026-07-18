<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Menampilkan daftar semua rumah (Katalog)
     */
    public function index()
    {
        // Mengambil data rumah yang statusnya 'available'
        // dan hanya memanggil gambar yang is_primary = true untuk cover depan
        $properties = Product::with(['images' => function ($query) {
            $query->where('is_primary', true);
        }])
        ->where('status', 'available')
        ->latest() // Urutkan dari yang paling baru
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data katalog properti',
            'data'    => $properties
        ], 200);
    }

    /**
     * Menampilkan detail satu rumah berdasarkan slug
     */
    public function show($slug)
    {
        // Mengambil data satu rumah beserta SEMUA galerinya dan data marketingnya
        $property = Product::with(['images', 'user.waMarketing'])
            ->where('slug', $slug)
            ->first();

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Properti tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail properti',
            'data'    => $property
        ], 200);
    }
}