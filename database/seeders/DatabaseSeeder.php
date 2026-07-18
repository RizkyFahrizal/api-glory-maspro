<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\WaMarketing;
use App\Models\Product;
use App\Models\ProductImage;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Menggunakan format data Indonesia

        // 1. Buat Akun Admin
        User::create([
            'name' => 'Admin Utama',
            'email' => 'admin@glory.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // 2. Buat Akun Marketing Pertama (Mas Wahyu)
        $marketing1 = User::create([
            'name' => 'Wahyu Marketing',
            'email' => 'wahyu@glory.com',
            'password' => Hash::make('password123'),
            'role' => 'marketing',
        ]);
        WaMarketing::create([
            'user_id' => $marketing1->id,
            'coverage_area' => 'Surabaya Selatan',
            'phone_number' => '6281234567890',
            'is_active' => true,
        ]);

        // 3. Buat Akun Marketing Kedua (Budi)
        $marketing2 = User::create([
            'name' => 'Budi Sales',
            'email' => 'budi@glory.com',
            'password' => Hash::make('password123'),
            'role' => 'marketing',
        ]);
        WaMarketing::create([
            'user_id' => $marketing2->id,
            'coverage_area' => 'Sidoarjo',
            'phone_number' => '6289876543210',
            'is_active' => true,
        ]);

        // Array ID Marketing untuk dipilih acak saat buat rumah
        $marketingIds = [$marketing1->id, $marketing2->id];

        // 4. Generate 10 Data Rumah secara looping
        for ($i = 1; $i <= 10; $i++) {
            $title = 'Rumah ' . $faker->city . ' Tipe ' . $faker->numberBetween(36, 120);
            
            $product = Product::create([
                'user_id' => $faker->randomElement($marketingIds), // Assign ke marketing acak
                'listing_id' => 'KPR-' . date('ymd') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'title' => $title,
                'slug' => Str::slug($title) . '-' . Str::random(5),
                'price' => $faker->numberBetween(3, 15) * 100000000, // Harga 300 Juta - 1.5 Miliar
                'description' => $faker->paragraph(3),
                'bedrooms' => $faker->numberBetween(2, 4),
                'bathrooms' => $faker->numberBetween(1, 2),
                'land_area' => $faker->numberBetween(60, 150),
                'building_area' => $faker->numberBetween(36, 120),
                'property_type' => 'Rumah',
                'address' => $faker->address,
                'location' => $faker->city,
                'electricity' => $faker->randomElement(['1300W', '2200W']),
                'certificate' => $faker->randomElement(['SHM', 'HGB']),
                'facing' => $faker->randomElement(['Utara', 'Selatan', 'Timur', 'Barat']),
                'furnish' => $faker->randomElement(['Non-Furnished', 'Semi-Furnished']),
                'status' => $faker->randomElement(['available', 'sold']),
            ]);

            // 5. Generate 3 Foto untuk tiap rumah
            for ($j = 1; $j <= 3; $j++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    // Pake dummy image dari internet sementara
                    'image_path' => 'https://dummyimage.com/600x400/2c3e50/ecf0f1&text=Rumah+' . $i . '+Foto+' . $j,
                    'is_primary' => $j === 1 ? true : false, // Foto pertama jadikan cover utama
                ]);
            }
        }
    }
}