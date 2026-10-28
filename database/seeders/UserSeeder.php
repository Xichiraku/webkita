<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Hash bcrypt sama dengan supabase/seed.sql, jadi kredensial demo tidak berubah.
     */
    public function run(): void
    {
        $rows = [
            ['demo@webkita.local', 'demo', 'Demo', 'WebKita', 2, Role::User,
                '$2b$12$DUbJCLLpUDiRrXENY5mjTuzhMOnyyqwvXXdoEB/cIcspTds8p1CTK'],
            ['admin@webkita.local', 'admin-demo', 'Admin', 'Demo', 1, Role::Admin,
                '$2b$12$5VEqeutpPKTOIi0tAv2kv.UK06ds/xZpSVm57qsVMGUpiJo6A2wve'],
        ];

        foreach ($rows as [$email, $username, $first, $last, $group, $role, $hash]) {
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'username' => $username,
                'first_name' => $first,
                'last_name' => $last,
                'group_id' => $group,
                'role' => $role,
                'is_aktif' => 'Y',
                'email_verified_at' => now(),
            ]);
            // Prefiks $2b$ (node bcrypt) identik dengan $2y$ PHP, tetapi BcryptHasher Laravel hanya menerima $2y$.
            if (str_starts_with($hash, '$2b$')) {
                $hash = '$2y$'.substr($hash, 4);
            }
            // Lewati cast 'hashed' agar hash lama tidak di-hash ulang atau ditolak karena beda cost.
            $user->setRawAttributes([...$user->getAttributes(), 'password' => $hash]);
            $user->save();
        }
    }
}
