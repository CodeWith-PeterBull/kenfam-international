<?php

namespace Database\Seeders;

use App\Models\InstitutionDetail;
use Illuminate\Database\Seeder;

class InstitutionDetailsSeeder extends Seeder
{
    public function run(): void
    {
        InstitutionDetail::query()->updateOrCreate(
            ['id' => InstitutionDetail::PRIMARY_ID],
            [
                'name' => config('institution.defaults.name'),
                'short_name' => config('institution.defaults.short_name'),
                'descriptor' => config('institution.defaults.descriptor'),
                'primary_email' => config('institution.defaults.primary_email'),
                'secondary_email' => config('institution.defaults.secondary_email'),
                'primary_phone' => config('institution.defaults.primary_phone'),
                'secondary_phone' => config('institution.defaults.secondary_phone'),
                'website' => config('institution.defaults.website'),
                'physical_address' => config('institution.defaults.physical_address'),
                'city' => config('institution.defaults.city'),
                'county' => config('institution.defaults.county'),
                'postal_code' => config('institution.defaults.postal_code'),
                'postal_address' => config('institution.defaults.postal_address'),
                'postal_city' => config('institution.defaults.postal_city'),
                'social_media' => config('institution.defaults.social_media'),
            ],
        );
    }
}
