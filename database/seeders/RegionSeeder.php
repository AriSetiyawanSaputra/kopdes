<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Seed reference administrative region data (2/4/7/10 digit codes).
     */
    public function run(): void
    {
        Province::upsert([
            ['id' => '35', 'name' => 'JAWA TIMUR'],
            ['id' => '33', 'name' => 'JAWA TENGAH'],
        ], ['id'], ['name']);

        Regency::upsert([
            ['id' => '3502', 'province_id' => '35', 'name' => 'KABUPATEN PONOROGO'],
            ['id' => '3519', 'province_id' => '35', 'name' => 'KABUPATEN MADIUN'],
            ['id' => '3312', 'province_id' => '33', 'name' => 'KABUPATEN WONOGIRI'],
        ], ['id'], ['province_id', 'name']);

        District::upsert([
            ['id' => '3502090', 'regency_id' => '3502', 'name' => 'BABADAN'],
            ['id' => '3502100', 'regency_id' => '3502', 'name' => 'PONOROGO'],
            ['id' => '3502110', 'regency_id' => '3502', 'name' => 'JENANGAN'],
            ['id' => '3519010', 'regency_id' => '3519', 'name' => 'KEBONSARI'],
            ['id' => '3519020', 'regency_id' => '3519', 'name' => 'GEGER'],
            ['id' => '3312010', 'regency_id' => '3312', 'name' => 'PURWANTORO'],
        ], ['id'], ['regency_id', 'name']);

        Village::upsert([
            ['id' => '3502090001', 'district_id' => '3502090', 'name' => 'NGUNUT'],
            ['id' => '3502090002', 'district_id' => '3502090', 'name' => 'BABADAN'],
            ['id' => '3502090003', 'district_id' => '3502090', 'name' => 'CEKOK'],
            ['id' => '3502090004', 'district_id' => '3502090', 'name' => 'SUKOSARI'],
            ['id' => '3502100001', 'district_id' => '3502100', 'name' => 'MANGKUJAYAN'],
            ['id' => '3502100002', 'district_id' => '3502100', 'name' => 'TONATAN'],
            ['id' => '3502110001', 'district_id' => '3502110', 'name' => 'JENANGAN'],
            ['id' => '3502110002', 'district_id' => '3502110', 'name' => 'JIMBE'],
            ['id' => '3519010001', 'district_id' => '3519010', 'name' => 'BALEREJO'],
            ['id' => '3519010002', 'district_id' => '3519010', 'name' => 'KEBONSARI'],
            ['id' => '3519020001', 'district_id' => '3519020', 'name' => 'PAGOTAN'],
            ['id' => '3312010001', 'district_id' => '3312010', 'name' => 'BITING'],
        ], ['id'], ['district_id', 'name']);
    }
}
