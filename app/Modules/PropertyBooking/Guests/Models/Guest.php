<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingGuest;
use App\Modules\PropertyBooking\Database\Factories\GuestFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Reusable guest profile with service-controlled protected identity values. */
final class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $table = 'property_booking_guests';

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'title', 'first_name', 'middle_name', 'last_name', 'email', 'phone',
        'alternate_phone', 'date_of_birth', 'nationality_country_code', 'identity_type',
        'identity_country_code', 'address_line_1', 'address_line_2', 'city', 'region',
        'postal_code', 'country_code', 'emergency_contact_name', 'emergency_contact_phone', 'note',
    ];

    /** @var list<string> */
    protected $hidden = ['identity_number_ciphertext', 'identity_number_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): GuestFactory
    {
        return GuestFactory::new();
    }

    /** Get the user relationship. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Get the creator relationship. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Get the updater relationship. */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Get the primary bookings relationship. */
    public function primaryBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'primary_guest_id');
    }

    /** Get the booking assignments relationship. */
    public function bookingAssignments(): HasMany
    {
        return $this->hasMany(BookingGuest::class, 'guest_id');
    }

    /** Return a display name without exposing protected identity values. */
    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }
}
