<?php

use App\Modules\Commerce\CommerceServiceProvider;
use App\Modules\PropertyBooking\PropertyBookingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CommerceServiceProvider::class,
    PropertyBookingServiceProvider::class,
];
