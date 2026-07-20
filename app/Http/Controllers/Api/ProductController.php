<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\WaMarketing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;

class ProductController extends Controller
{
    /**
     * Menampilkan daftar semua rumah (Katalog)
     */
    public function index(Request $request)
    {
        $query = Product::with('images')->where('status', 'available');

        // Filter berdasarkan Lokasi
        if ($request->has('location') && !empty($request->location)) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        // Filter berdasarkan Tipe Properti
        if ($request->has('property_type') && !empty($request->property_type)) {
            $query->where('property_type', $request->property_type);
        }

        // Filter berdasarkan Kamar Tidur
        if ($request->has('bedrooms') && !empty($request->bedrooms)) {
            $query->where('bedrooms', $request->bedrooms);
        }

        // Filter berdasarkan Kamar Mandi
        if ($request->has('bathrooms') && !empty($request->bathrooms)) {
            $query->where('bathrooms', $request->bathrooms);
        }

        // Filter berdasarkan Range Harga (Start & End)
        // Logika: Produk masuk kriteria jika (price_start <= max_price) AND (price_end >= min_price)
        // atau untuk simplenya: cek apakah budget minimum lebih kecil dari harga maksimal properti, dst.
        if ($request->has('min_price') && is_numeric($request->min_price)) {
            $query->where('price_end', '>=', $request->min_price);
        }
        if ($request->has('max_price') && is_numeric($request->max_price)) {
            $query->where('price_start', '<=', $request->max_price);
        }

        $properties = $query->latest()->get();

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
        // Mengambil data satu rumah beserta galerinya
        $property = Product::with(['images', 'user'])
            ->where('slug', $slug)
            ->first();

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Properti tidak ditemukan'
            ], 404);
        }

        // Logika Round Robin untuk Marketing
        $activeMarketings = WaMarketing::with('user')->where('is_active', true)->orderBy('id')->get();
        
        if ($activeMarketings->count() > 0) {
            // Ambil ID terakhir yang dilayani dari Cache
            $lastServedId = Cache::get('last_served_marketing_id', 0);
            
            // Cari agen berikutnya
            $nextMarketing = $activeMarketings->firstWhere('id', '>', $lastServedId);
            
            // Jika tidak ada yang id-nya lebih besar, kembali ke agen pertama
            if (!$nextMarketing) {
                $nextMarketing = $activeMarketings->first();
            }
            
            // Simpan ID agen ini sebagai yang terakhir dilayani
            Cache::put('last_served_marketing_id', $nextMarketing->id);
            
            // Sisipkan data marketing terpilih ke respons (agar frontend bisa langsung pakai)
            $property->marketing = $nextMarketing;
        } else {
            $property->marketing = null;
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
            'price_start' => 'required|numeric',
            'price_end' => 'required|numeric',
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
            'price_start' => $request->price_start,
            'price_end' => $request->price_end,
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
                // Generate nama file unik dengan ekstensi .webp
                $filename = uniqid() . '.webp';
                $path = 'products/' . $filename;

                // Konversi gambar ke webp (kualitas 80)
                $img = Image::make($image)->encode('webp', 80);
                
                // Simpan ke storage public
                Storage::disk('public')->put($path, (string) $img);

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
            'price_start' => 'sometimes|required|numeric',
            'price_end' => 'sometimes|required|numeric',
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

            // Upload gambar baru dan konversi ke webp
            foreach ($request->file('images') as $index => $image) {
                $filename = uniqid() . '.webp';
                $path = 'products/' . $filename;

                $img = Image::make($image)->encode('webp', 80);
                Storage::disk('public')->put($path, (string) $img);

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

    /**
     * Mengambil daftar lokasi unik untuk combo box
     */
    public function locations()
    {
        $locations = Product::where('status', 'available')
            ->select('location')
            ->distinct()
            ->orderBy('location', 'asc')
            ->pluck('location');
            
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar lokasi',
            'data'    => $locations
        ], 200);
    }
}