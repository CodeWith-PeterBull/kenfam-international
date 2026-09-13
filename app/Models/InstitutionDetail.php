<?php

namespace App\Models;

use Database\Factories\InstitutionDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InstitutionDetail extends Model implements HasMedia
{
    /** @use HasFactory<InstitutionDetailFactory> */
    use HasFactory, InteractsWithMedia;

    public const PRIMARY_ID = 1;

    protected $fillable = [
        'name',
        'short_name',
        'descriptor',
        'primary_email',
        'secondary_email',
        'primary_phone',
        'secondary_phone',
        'website',
        'physical_address',
        'city',
        'county',
        'postal_code',
        'postal_address',
        'postal_city',
        'social_media',
    ];

    protected function casts(): array
    {
        return [
            'social_media' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $acceptedMimeTypes = ['image/png', 'image/jpeg'];

        $this->addMediaCollection('main_logo')
            ->singleFile()
            ->acceptsMimeTypes($acceptedMimeTypes)
            ->useDisk('public');

        $this->addMediaCollection('logo_icon')
            ->singleFile()
            ->acceptsMimeTypes($acceptedMimeTypes)
            ->useDisk('public');
    }

    public function mainLogo(): ?Media
    {
        return $this->getFirstMedia('main_logo');
    }

    public function logoIcon(): ?Media
    {
        return $this->getFirstMedia('logo_icon');
    }
}
