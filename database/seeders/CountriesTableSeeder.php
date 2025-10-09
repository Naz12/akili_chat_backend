<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CountriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table('countries')->insert([
            ['name' => 'Ethiopia', 'iso_code' => 'ET', 'currency' => 'ETB', 'region' => 'local'],
            ['name' => 'Kenya', 'iso_code' => 'KE', 'currency' => 'KES', 'region' => 'local'],
            ['name' => 'United States', 'iso_code' => 'US', 'currency' => 'USD', 'region' => 'intl'],
            ['name' => 'Germany', 'iso_code' => 'DE', 'currency' => 'EUR', 'region' => 'intl'],
        ]);
        
    }
}