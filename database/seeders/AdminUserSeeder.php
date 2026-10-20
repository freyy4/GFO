<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('GFO_ADMIN_PASSWORD');

        if (blank($password)) {
            throw new RuntimeException(
                'GFO_ADMIN_PASSWORD belum diatur pada file .env'
            );
        }

        DB::transaction(function () use ($password) {
            $organization = Organization::updateOrCreate(
                [
                    'code' => 'GFO',
                ],
                [
                    'name' => 'PT Garuta Food Indonesia',
                    'legal_name' => 'PT Garuta Food Indonesia',
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                    'status' => 'ACTIVE',
                ]
            );

            $role = Role::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'SUPER_ADMIN',
                ],
                [
                    'name' => 'Super Administrator',
                    'scope_type' => 'ORGANIZATION',
                    'is_system' => true,
                ]
            );

            $user = User::updateOrCreate(
                [
                    'email' => env(
                        'GFO_ADMIN_EMAIL',
                        'admin@garutafood.co.id'
                    ),
                ],
                [
                    'organization_id' => $organization->id,
                    'name' => env(
                        'GFO_ADMIN_NAME',
                        'Administrator GFO'
                    ),
                    'phone' => env('GFO_ADMIN_PHONE'),
                    'password' => Hash::make($password),
                    'status' => 'ACTIVE',
                    'mfa_enabled' => false,
                    'email_verified_at' => now(),
                ]
            );

            DB::table('role_user')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'user_id' => $user->id,
                    'operational_unit_id' => null,
                ],
                [
                    'starts_at' => now(),
                    'ends_at' => null,
                    'granted_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }
}