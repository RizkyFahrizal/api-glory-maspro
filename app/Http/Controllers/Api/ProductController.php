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
        $query = Product::with('images');

        // Filter berdasarkan Pencarian Teks (Search)
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%')
                  ->orWhere('location', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter berdasarkan Lokasi
        if ($request->has('location') && !empty($request->location)) {
            $query->where('location', $request->location);
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
            ->orWhere('id', $slug)
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
            'images' => 'required|array|max:7',
            'images.*' => 'file|mimes:jpeg,png,jpg,webp,webm,mp4|max:15360',
        ]);

        // Cek maksimal 1 video
        $videoCount = 0;
        foreach ($request->file('images') as $file) {
            if (str_starts_with($file->getClientMimeType(), 'video/')) {
                $videoCount++;
            }
        }
        if ($videoCount > 1) {
            return response()->json(['success' => false, 'message' => 'Maksimal hanya diperbolehkan 1 video untuk setiap produk.'], 422);
        }

        // Auto-generate Slug
        $slug = Str::slug($request->title) . '-' . uniqid();

        // Auto-generate Listing ID: KPR-{id}{ddmmyy}
        $dateStr = now()->format('dmy'); // DDMMYY
        $latestProduct = Product::latest('id')->first();
        $nextId = $latestProduct ? $latestProduct->id + 1 : 1;
        $listingId = "KPR-{$nextId}{$dateStr}";

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

        // Handle Image & Video Uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $file) {
                $isPrimary = $index === 0 ? true : false; // Gambar pertama jadi primary
                $mime = $file->getClientMimeType();

                if (str_starts_with($mime, 'video/')) {
                    // Simpan video langsung tanpa konversi
                    $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('products', $filename, 'public');
                } else {
                    // Konversi gambar ke webp (kualitas 80)
                    $filename = uniqid() . '.webp';
                    $path = 'products/' . $filename;
                    $img = Image::make($file)->encode('webp', 80);
                    Storage::disk('public')->put($path, (string) $img);
                }

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $isPrimary,
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
            'images' => 'nullable|array|max:7',
            'images.*' => 'file|mimes:jpeg,png,jpg,webp,webm,mp4|max:15360',
        ]);

        if ($request->hasFile('images')) {
            // Cek maksimal 1 video gabungan dari yang lama dan yang baru
            $newVideoCount = 0;
            foreach ($request->file('images') as $file) {
                if (str_starts_with($file->getClientMimeType(), 'video/')) {
                    $newVideoCount++;
                }
            }
            
            $existingVideoCount = 0;
            foreach ($product->images as $img) {
                if (str_ends_with($img->image_path, '.mp4') || str_ends_with($img->image_path, '.webm')) {
                    $existingVideoCount++;
                }
            }
            
            if ($newVideoCount + $existingVideoCount > 1) {
                return response()->json(['success' => false, 'message' => 'Maksimal hanya diperbolehkan 1 video untuk setiap produk.'], 422);
            }
        }

        if ($request->has('title')) {
            $product->title = $request->title;
            $product->slug = Str::slug($request->title) . '-' . uniqid();
        }

        $product->update($request->except(['images', 'title']));

        // Handle Image Replacement
        // Handle Image Additive Upload
        if ($request->hasFile('images')) {
            $hasExistingPrimary = $product->images()->where('is_primary', true)->exists();
            $isFirstNewImage = true;

            // Upload gambar/video baru secara aditif
            foreach ($request->file('images') as $index => $file) {
                $isPrimary = (!$hasExistingPrimary && $isFirstNewImage) ? true : false;
                $isFirstNewImage = false;
                
                $mime = $file->getClientMimeType();

                if (str_starts_with($mime, 'video/')) {
                    // Simpan video langsung tanpa konversi
                    $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('products', $filename, 'public');
                } else {
                    // Konversi gambar ke webp dengan orientate EXIF
                    $filename = uniqid() . '.webp';
                    $path = 'products/' . $filename;
                    $img = Image::make($file)->orientate()->encode('webp', 80);
                    Storage::disk('public')->put($path, (string) $img);
                }

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $isPrimary,
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
        foreach ($product->images as $oldImg) {
            Storage::disk('public')->delete($oldImg->getRawOriginal('image_path'));
            $oldImg->delete();
        }

        // Database akan otomatis menghapus dari tabel product_images karena onDelete('cascade')
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus'
        ]);
    }

    /**
     * Delete a specific product image
     */
    public function deleteImage($imageId)
    {
        $image = ProductImage::find($imageId);
        if (!$image) {
            return response()->json(['success' => false, 'message' => 'Media tidak ditemukan'], 404);
        }

        // Cek kepemilikan
        $product = $image->product;
        if (Auth::user()->role === 'marketing' && $product->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        Storage::disk('public')->delete($image->getRawOriginal('image_path'));
        
        $wasPrimary = $image->is_primary;
        $image->delete();

        // Jika yang dihapus adalah cover, jadikan gambar (bukan video) lain sebagai cover
        if ($wasPrimary) {
            $nextImage = $product->images()->where('image_path', 'not like', '%.mp4')->where('image_path', 'not like', '%.webm')->first();
            if ($nextImage) {
                $nextImage->is_primary = true;
                $nextImage->save();
            } else {
                // Fallback ke media apapun jika tidak ada gambar
                $anyMedia = $product->images()->first();
                if ($anyMedia) {
                    $anyMedia->is_primary = true;
                    $anyMedia->save();
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Media berhasil dihapus']);
    }

    /**
     * Get distinct property types
     */
    public function getTypes()
    {
        $types = Product::whereNotNull('property_type')
            ->where('property_type', '!=', '')
            ->distinct()
            ->pluck('property_type')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $types
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