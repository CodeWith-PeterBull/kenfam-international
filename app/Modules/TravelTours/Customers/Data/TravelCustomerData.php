<?php

/**
 * Validated customer identity and contact input for TravelTours services.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Customers\Data;

use InvalidArgumentException;

/** Carry normalized-profile candidates without persistence-owned attributes. */
final readonly class TravelCustomerData
{
    /** Validate the minimum customer identity and supported country code shape. */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $title = null,
        public ?string $middleName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $whatsappPhone = null,
        public ?string $nationalityCode = null,
    ) {
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new InvalidArgumentException('Customer first and last names are required.');
        }

        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Customer email address is invalid.');
        }

        if ($this->nationalityCode !== null && preg_match('/^[A-Za-z]{2}$/', $this->nationalityCode) !== 1) {
            throw new InvalidArgumentException('Customer nationality must be an ISO alpha-2 code.');
        }
    }
}
