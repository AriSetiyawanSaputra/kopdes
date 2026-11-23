<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Support\CardUidNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAndAdminSeeder extends Seeder
{
    /**
     * Seed initial KOPDES branches, hierarchical admin accounts, sample cashiers, and sample members.
     */
    public function run(): void
    {
        $branchNgunut = Branch::updateOrCreate(
            ['village_id' => '3502090001'],
            [
                'code' => 'KOP-3502-001',
                'name' => 'KOPDES Desa Ngunut',
                'district_id' => '3502090',
                'regency_id' => '3502',
                'province_id' => '35',
                'address' => 'Jl. Raya Ponorogo - Madiun KM 5, Desa Ngunut, Kec. Babadan, Kab. Ponorogo',
                'phone' => '0352-481001',
                'is_active' => true,
            ]
        );

        $branchCekok = Branch::updateOrCreate(
            ['village_id' => '3502090003'],
            [
                'code' => 'KOP-3502-002',
                'name' => 'KOPDES Desa Cekok',
                'district_id' => '3502090',
                'regency_id' => '3502',
                'province_id' => '35',
                'address' => 'Jl. Desa Cekok No. 12, Kec. Babadan, Kab. Ponorogo',
                'phone' => '0352-481002',
                'is_active' => true,
            ]
        );

        $defaultPassword = Hash::make('password');
        $defaultPinHash = Hash::make('123456');

        User::updateOrCreate(
            ['email' => 'admin.nasional@kopdes.id'],
            [
                'name' => 'Admin Nasional KOPDES',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN_NASIONAL,
                'province_id' => null,
                'regency_id' => null,
                'branch_id' => null,
                'card_uid' => null,
                'pin_hash' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin.jatim@kopdes.id'],
            [
                'name' => 'Admin Provinsi Jawa Timur',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN_PROVINSI,
                'province_id' => '35',
                'regency_id' => null,
                'branch_id' => null,
                'card_uid' => null,
                'pin_hash' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin.ponorogo@kopdes.id'],
            [
                'name' => 'Admin Kabupaten Ponorogo',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN_KABUPATEN,
                'province_id' => '35',
                'regency_id' => '3502',
                'branch_id' => null,
                'card_uid' => null,
                'pin_hash' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin.ngunut@kopdes.id'],
            [
                'name' => 'Admin Cabang KOPDES Desa Ngunut',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN_CABANG,
                'province_id' => '35',
                'regency_id' => '3502',
                'branch_id' => $branchNgunut->id,
                'card_uid' => null,
                'pin_hash' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin.cekok@kopdes.id'],
            [
                'name' => 'Admin Cabang KOPDES Desa Cekok',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN_CABANG,
                'province_id' => '35',
                'regency_id' => '3502',
                'branch_id' => $branchCekok->id,
                'card_uid' => null,
                'pin_hash' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['card_uid' => CardUidNormalizer::normalize('KSR3502001A')],
            [
                'name' => 'Kasir Ngunut 1',
                'email' => null,
                'password' => null,
                'role' => User::ROLE_KASIR,
                'province_id' => '35',
                'regency_id' => '3502',
                'branch_id' => $branchNgunut->id,
                'pin_hash' => $defaultPinHash,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['card_uid' => CardUidNormalizer::normalize('KSR3502002A')],
            [
                'name' => 'Kasir Cekok 1',
                'email' => null,
                'password' => null,
                'role' => User::ROLE_KASIR,
                'province_id' => '35',
                'regency_id' => '3502',
                'branch_id' => $branchCekok->id,
                'pin_hash' => $defaultPinHash,
                'is_active' => true,
            ]
        );

        Member::updateOrCreate(
            ['member_code' => 'MBR-KOP-3502-001-00001'],
            [
                'home_branch_id' => $branchNgunut->id,
                'card_uid' => CardUidNormalizer::normalize('MBR350200100001'),
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'nik' => '3502090101900001',
                'status' => 'active',
                'joined_at' => '2026-01-15',
            ]
        );

        Member::updateOrCreate(
            ['member_code' => 'MBR-KOP-3502-002-00001'],
            [
                'home_branch_id' => $branchCekok->id,
                'card_uid' => CardUidNormalizer::normalize('MBR350200200001'),
                'name' => 'Siti Aminah',
                'phone' => '081234567891',
                'nik' => '3502090202920002',
                'status' => 'active',
                'joined_at' => '2026-01-20',
            ]
        );
    }
}
