<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * Menampilkan daftar semua rumah (Katalog)
     */
    public function index()
    {
        // Mengambil data rumah yang statusnya 'available'
        // dan memanggil semua gambarnya agar fitur carousel di card berfungsi
        $properties = Product::with('images')
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

    /**
     * Menyimpan produk baru (Hanya Admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric',
            'description' => 'required|string',
            'bedrooms' => 'required|string|max:20',
            'bathrooms' => 'required|string|max:20',
            'land_area' => 'required|integer',
            'building_area' => 'required|integer',
            'property_type' => 'nullable|string|max:50',
            'address' => 'required|string',
            'location' => 'required|string|max:100',
            'electricity' => 'nullable|string|max:50',
            'certificate' => 'nullable|string|max:50',
            'facing' => 'nullable|string|max:50',
            'furnish' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Auto-generate Slug
        $slug = Str::slug($request->title) . '-' . uniqid();

        // Auto-generate Listing ID: KPR-XXX-DDMMYY
        $dateStr = now()->format('dmy'); // DDMMYY
        $latestProduct = Product::latest('id')->first();
        $nextId = $latestProduct ? $latestProduct->id + 1 : 1;
        $paddedId = str_pad($nextId, 3, '0', STR_PAD_LEFT);
        $listingId = "KPR-{$paddedId}-{$dateStr}";

        $product = Product::create([
            'user_id' => Auth::id(),
            'listing_id' => $listingId,
            'title' => $request->title,
            'slug' => $slug,
            'price' => $request->price,
            'description' => $request->description,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'land_area' => $request->land_area,
            'building_area' => $request->building_area,
            'property_type' => $request->property_type ?? 'Rumah',
            'address' => $request->address,
            'location' => $request->location,
            'electricity' => $request->electricity,
            'certificate' => $request->certificate,
            'facing' => $request->facing,
            'furnish' => $request->furnish,
            'note' => $request->note,
            'status' => 'available',
        ]);

        // Handle Image Uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0 ? true : false, // Gambar pertama jadi primary
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product->load('images')
        ], 201);
    }

    /**
     * Memperbarui data produk
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric',
            'description' => 'sometimes|required|string',
            'bedrooms' => 'sometimes|required|string|max:20',
            'bathrooms' => 'sometimes|required|string|max:20',
            'land_area' => 'sometimes|required|integer',
            'building_area' => 'sometimes|required|integer',
            'property_type' => 'nullable|string|max:50',
            'address' => 'sometimes|required|string',
            'location' => 'sometimes|required|string|max:100',
            'electricity' => 'nullable|string|max:50',
            'certificate' => 'nullable|string|max:50',
            'facing' => 'nullable|string|max:50',
            'furnish' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'status' => 'sometimes|in:available,sold',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->has('title')) {
            $product->title = $request->title;
            $product->slug = Str::slug($request->title) . '-' . uniqid();
        }

        $product->update($request->except(['images', 'title']));

        // Handle Image Replacement
        if ($request->hasFile('images')) {
            // Hapus gambar lama dari storage dan database
            $oldImages = $product->images;
            foreach ($oldImages as $oldImg) {
                Storage::disk('public')->delete($oldImg->image_path);
                $oldImg->delete();
            }

            // Upload gambar baru
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0 ? true : false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diupdate',
            'data' => $product->load('images')
        ]);
    }

    /**
     * Menghapus produk beserta foto fisiknya
     */
    public function destroy($id)
    {
        $product = Product::with('images')->find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
        }

        // Hapus file fisik gambar dari storage
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        // Database akan otomatis menghapus dari tabel product_images karena onDelete('cascade')
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk dan gambar berhasil dihapus'
        ]);
    }
}