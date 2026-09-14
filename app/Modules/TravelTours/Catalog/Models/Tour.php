<?php

/**
 * Core tour product aggregate for content, schedule, pricing, and booking.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourDifficulty;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Core tour product aggregate for content, schedule, pricing, and booking. */
final class Tour extends TravelToursModel implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'travel_tours';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TourType::class, 'status' => PublicationStatus::class,
            'difficulty' => TourDifficulty::class, 'booking_mode' => BookingMode::class,
            'duration_days' => 'integer', 'duration_nights' => 'integer', 'minimum_age' => 'integer',
            'minimum_participants' => 'integer', 'maximum_participants' => 'integer',
            'languages' => 'array', 'meeting_latitude' => 'decimal:7', 'meeting_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7', 'end_longitude' => 'decimal:7',
            'is_featured' => 'boolean', 'sort_order' => 'integer', 'published_at' => 'datetime',
        ];
    }

    /** Declare allowed collections, MIME types and storage visibility for this resource. */
    public function registerMediaCollections(): void
    {
        $images = ['image/jpeg', 'image/png', 'image/webp'];
        $documents = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $this->addMediaCollection('tour_cover')->singleFile()->acceptsMimeTypes($images)->useDisk('public');
        $this->addMediaCollection('tour_gallery')->acceptsMimeTypes($images)->useDisk('public');
        $this->addMediaCollection('tour_documents')->acceptsMimeTypes($documents)->useDisk('public');
    }

    /** Define bounded image derivatives for the declared public image collections. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $collections = ['tour_cover', 'tour_gallery'];
        $this->addMediaConversion('thumb')->fit(Fit::Max, 360, 240)->performOnCollections(...$collections);
        $this->addMediaConversion('card')->fit(Fit::Max, 960, 640)->performOnCollections(...$collections);
        $this->addMediaConversion('hero')->fit(Fit::Max, 1920, 1080)->performOnCollections(...$collections);
    }

    /**
     * Resolve the associated User record through created_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the associated User record through updated_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Query related ItineraryDay records.
     *
     * @return HasMany<ItineraryDay, $this>
     */
    public function itineraryDays(): HasMany
    {
        return $this->hasMany(ItineraryDay::class)->orderBy('day_number');
    }

    /**
     * Query related TourContentItem records.
     *
     * @return HasMany<TourContentItem, $this>
     */
    public function contentItems(): HasMany
    {
        return $this->hasMany(TourContentItem::class)->orderBy('sort_order');
    }

    /**
     * Query related TourFaq records.
     *
     * @return HasMany<TourFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(TourFaq::class)->orderBy('sort_order');
    }

    /**
     * Query related TourExtra records.
     *
     * @return HasMany<TourExtra, $this>
     */
    public function extras(): HasMany
    {
        return $this->hasMany(TourExtra::class)->orderBy('sort_order');
    }

    /**
     * Query related TourRatePlan records.
     *
     * @return HasMany<TourRatePlan, $this>
     */
    public function ratePlans(): HasMany
    {
        return $this->hasMany(TourRatePlan::class)->orderBy('display_order');
    }

    /**
     * Query related TourDeparture records.
     *
     * @return HasMany<TourDeparture, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class)->orderBy('starts_at');
    }

    /**
     * Query related TourBooking records.
     *
     * @return HasMany<TourBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class);
    }

    /**
     * Query related TourInquiry records.
     *
     * @return HasMany<TourInquiry, $this>
     */
    public function inquiries(): HasMany
    {
        return $this->hasMany(TourInquiry::class);
    }

    /**
     * Query related TourCategory records through travel_tour_category.
     *
     * @return BelongsToMany<TourCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(TourCategory::class, 'travel_tour_category', 'tour_id', 'category_id')
            ->using(TourCategoryAssignment::class)->withPivot(['ulid', 'is_primary', 'sort_order'])->withTimestamps();
    }

    /**
     * Query related Destination records through travel_tour_destination.
     *
     * @return BelongsToMany<Destination, $this>
     */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'travel_tour_destination', 'tour_id', 'destination_id')
            ->using(TourDestinationAssignment::class)->withPivot(['ulid', 'role', 'sequence', 'is_overnight'])->withTimestamps();
    }

    /**
     * Query related Promotion records through travel_promotion_tour.
     *
     * @return BelongsToMany<Promotion, $this>
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'travel_promotion_tour')->withPivot('is_exclusion')->withTimestamps();
    }

    /**
     * Apply the published selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published->value)
            ->where(fn (Builder $published): Builder => $published->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
