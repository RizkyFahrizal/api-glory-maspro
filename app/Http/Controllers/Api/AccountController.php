<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\WaMarketing;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    // GET /api/accounts - Bisa diakses Admin dan Marketing
    public function index()
    {
        $users = User::all();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    // POST /api/accounts - Hanya Admin (dilindungi route middleware)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,marketing',
            'phone_number' => 'nullable|string|max:20',
            'coverage_area' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        if ($request->role === 'marketing' && $request->has('phone_number')) {
            WaMarketing::create([
                'user_id' => $user->id,
                'phone_number' => $request->phone_number,
                'coverage_area' => $request->coverage_area ?? 'Semua Area',
                'is_active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat.',
            'data' => $user
        ], 201);
    }

    // PUT/PATCH /api/accounts/{id} - Admin bisa update semua, Marketing hanya bisa update miliknya
    public function update(Request $request, $id)
    {
        $userToUpdate = User::find($id);
        
        if (!$userToUpdate) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        $currentUser = Auth::user();

        // Cek permission: Jika user saat ini adalah marketing, pastikan id yang diupdate adalah miliknya sendiri
        if ($currentUser->role === 'marketing' && $currentUser->id != $userToUpdate->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Forbidden. Anda hanya bisa mengubah akun Anda sendiri.'
            ], 403);
        }

        // Validasi
        $rules = [
            'name' => 'sometimes|required|string|max:255|unique:users,name,' . $id,
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|in:admin,marketing',
            'phone_number' => 'nullable|string|max:20',
            'coverage_area' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];

        // Marketing tidak boleh mengubah role-nya sendiri menjadi admin
        if ($currentUser->role === 'marketing' && $request->has('role') && $request->role !== 'marketing') {
            return response()->json([
                'success' => false, 
                'message' => 'Forbidden. Anda tidak bisa mengubah role Anda.'
            ], 403);
        }

        // Proteksi Akun Super Admin
        if ($userToUpdate->name === 'Super Admin') {
            if ($request->has('name') && $request->name !== 'Super Admin') {
                return response()->json(['success' => false, 'message' => 'Nama Super Admin tidak boleh diubah.'], 403);
            }
            if ($request->has('role') && $request->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Role Super Admin tidak boleh diubah.'], 403);
            }
        }

        $request->validate($rules);

        $userToUpdate->name = $request->name ?? $userToUpdate->name;
        $userToUpdate->email = $request->email ?? $userToUpdate->email;
        
        if ($request->has('role')) {
            $userToUpdate->role = $request->role;
        }

        if ($request->has('password') && !empty($request->password)) {
            $userToUpdate->password = Hash::make($request->password);
        }

        $userToUpdate->save();

        // Update WaMarketing if role is marketing
        if ($userToUpdate->role === 'marketing') {
            $waData = [];
            if ($request->has('phone_number')) $waData['phone_number'] = $request->phone_number;
            if ($request->has('coverage_area')) $waData['coverage_area'] = $request->coverage_area;
            if ($request->has('is_active')) $waData['is_active'] = $request->is_active;

            if (!empty($waData)) {
                $waMarketing = WaMarketing::firstOrCreate(
                    ['user_id' => $userToUpdate->id],
                    ['coverage_area' => 'Semua Area', 'phone_number' => '']
                );
                $waMarketing->update($waData);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diupdate.',
            'data' => $userToUpdate->load('waMarketing')
        ]);
    }

    // DELETE /api/accounts/{id} - Hanya Admin (dilindungi route middleware)
    public function destroy($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        // Proteksi Super Admin
        if ($user->name === 'Super Admin') {
            return response()->json(['success' => false, 'message' => 'Akun Super Admin tidak boleh dihapus.'], 403);
        }

        // Pindahkan produk ke Super Admin jika ada
        $superAdmin = User::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            Product::where('user_id', $user->id)->update(['user_id' => $superAdmin->id]);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus.'
        ]);
    }
}
