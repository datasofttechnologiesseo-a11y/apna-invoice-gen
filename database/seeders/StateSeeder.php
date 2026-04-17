<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['code' => 'JK', 'name' => 'Jammu and Kashmir', 'gst_code' => '01', 'is_union_territory' => true],
            ['code' => 'HP', 'name' => 'Himachal Pradesh', 'gst_code' => '02', 'is_union_territory' => false],
            ['code' => 'PB', 'name' => 'Punjab', 'gst_code' => '03', 'is_union_territory' => false],
            ['code' => 'CH', 'name' => 'Chandigarh', 'gst_code' => '04', 'is_union_territory' => true],
            ['code' => 'UK', 'name' => 'Uttarakhand', 'gst_code' => '05', 'is_union_territory' => false],
            ['code' => 'HR', 'name' => 'Haryana', 'gst_code' => '06', 'is_union_territory' => false],
            ['code' => 'DL', 'name' => 'Delhi', 'gst_code' => '07', 'is_union_territory' => true],
            ['code' => 'RJ', 'name' => 'Rajasthan', 'gst_code' => '08', 'is_union_territory' => false],
            ['code' => 'UP', 'name' => 'Uttar Pradesh', 'gst_code' => '09', 'is_union_territory' => false],
            ['code' => 'BR', 'name' => 'Bihar', 'gst_code' => '10', 'is_union_territory' => false],
            ['code' => 'SK', 'name' => 'Sikkim', 'gst_code' => '11', 'is_union_territory' => false],
            ['code' => 'AR', 'name' => 'Arunachal Pradesh', 'gst_code' => '12', 'is_union_territory' => false],
            ['code' => 'NL', 'name' => 'Nagaland', 'gst_code' => '13', 'is_union_territory' => false],
            ['code' => 'MN', 'name' => 'Manipur', 'gst_code' => '14', 'is_union_territory' => false],
            ['code' => 'MZ', 'name' => 'Mizoram', 'gst_code' => '15', 'is_union_territory' => false],
            ['code' => 'TR', 'name' => 'Tripura', 'gst_code' => '16', 'is_union_territory' => false],
            ['code' => 'ML', 'name' => 'Meghalaya', 'gst_code' => '17', 'is_union_territory' => false],
            ['code' => 'AS', 'name' => 'Assam', 'gst_code' => '18', 'is_union_territory' => false],
            ['code' => 'WB', 'name' => 'West Bengal', 'gst_code' => '19', 'is_union_territory' => false],
            ['code' => 'JH', 'name' => 'Jharkhand', 'gst_code' => '20', 'is_union_territory' => false],
            ['code' => 'OR', 'name' => 'Odisha', 'gst_code' => '21', 'is_union_territory' => false],
            ['code' => 'CG', 'name' => 'Chhattisgarh', 'gst_code' => '22', 'is_union_territory' => false],
            ['code' => 'MP', 'name' => 'Madhya Pradesh', 'gst_code' => '23', 'is_union_territory' => false],
            ['code' => 'GJ', 'name' => 'Gujarat', 'gst_code' => '24', 'is_union_territory' => false],
            ['code' => 'DD', 'name' => 'Dadra and Nagar Haveli and Daman and Diu', 'gst_code' => '26', 'is_union_territory' => true],
            ['code' => 'MH', 'name' => 'Maharashtra', 'gst_code' => '27', 'is_union_territory' => false],
            ['code' => 'AP', 'name' => 'Andhra Pradesh', 'gst_code' => '37', 'is_union_territory' => false],
            ['code' => 'KA', 'name' => 'Karnataka', 'gst_code' => '29', 'is_union_territory' => false],
            ['code' => 'GA', 'name' => 'Goa', 'gst_code' => '30', 'is_union_territory' => false],
            ['code' => 'LD', 'name' => 'Lakshadweep', 'gst_code' => '31', 'is_union_territory' => true],
            ['code' => 'KL', 'name' => 'Kerala', 'gst_code' => '32', 'is_union_territory' => false],
            ['code' => 'TN', 'name' => 'Tamil Nadu', 'gst_code' => '33', 'is_union_territory' => false],
            ['code' => 'PY', 'name' => 'Puducherry', 'gst_code' => '34', 'is_union_territory' => true],
            ['code' => 'AN', 'name' => 'Andaman and Nicobar Islands', 'gst_code' => '35', 'is_union_territory' => true],
            ['code' => 'TS', 'name' => 'Telangana', 'gst_code' => '36', 'is_union_territory' => false],
            ['code' => 'LA', 'name' => 'Ladakh', 'gst_code' => '38', 'is_union_territory' => true],
        ];

        $now = now();
        foreach ($states as &$s) {
            $s['created_at'] = $now;
            $s['updated_at'] = $now;
        }

        DB::table('states')->upsert($states, ['code'], ['name', 'gst_code', 'is_union_territory', 'updated_at']);
    }
}
