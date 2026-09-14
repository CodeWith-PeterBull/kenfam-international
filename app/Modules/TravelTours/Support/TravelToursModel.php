<?php

/**
 * Shared persistence behavior for Travel and Tours domain models.
 *
 * Domain services must use validated DTOs and explicitly assign system-owned
 * fields. This class supplies only cross-cutting ULID and factory behavior.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

use App\Modules\TravelTours\Support\Concerns\HasTravelToursFactory;
use App\Modules\TravelTours\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

/** Shared model foundation for module-owned ULID resources and factories. */
abstract class TravelToursModel extends Model
{
    use HasTravelToursFactory;
    use HasUlid;

    /**
     * Protect persistence identifiers and transaction-owned controls.
     *
     * Service methods may use forceFill() only after validation and inside the
     * documented transaction boundary. Request input must never be force-filled.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'ulid',
        'operation_key',
        'created_by',
        'updated_by',
        'assigned_by',
        'actor_id',
        'received_by',
        'requested_by',
        'processed_by',
        'reconciled_by',
        'converted_booking_id',
        'register_open_guard',
        'operator_open_guard',
    ];
}
