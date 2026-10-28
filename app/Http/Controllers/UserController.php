<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use RespondsWithJson;

    private const PUBLIC_FIELDS = [
        'id', 'username', 'first_name', 'last_name', 'email', 'phone', 'group_id', 'role',
        'is_aktif', 'subscribe_newsletter', 'email_verified_at', 'created_at', 'updated_at',
    ];

    /** Daftar user (admin/developer): berisi data pribadi, jadi dijaga middleware role di route. */
    public function index(): JsonResponse
    {
        return $this->ok(User::query()->latest()->get(self::PUBLIC_FIELDS));
    }

    /** Hapus (soft delete) user berdasarkan username: hanya admin. */
    public function destroy(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            throw new ApiException('User tidak ditemukan', 404);
        }
        if ($user->is($request->user())) {
            throw new ApiException('Anda tidak dapat menghapus akun sendiri', 409);
        }

        $user->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return $this->ok(null, 'User berhasil dihapus');
    }
}
