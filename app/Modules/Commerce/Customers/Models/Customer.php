<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Customers\Models;

use App\Models\User;
use App\Modules\Commerce\Database\Factories\CustomerFactory;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable customer identity and default address shared by storefront and POS.
 */
final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'company',
        'tax_identifier',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country_code',
    ];

    /**
     * Create a module-local customer factory instance.
     */
    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /**
     * Optional authenticated account associated with the customer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Staff user who originally created the customer record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Staff user who most recently updated the customer record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Historical orders linked to this reusable customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Expose the customer's display name.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }
}
