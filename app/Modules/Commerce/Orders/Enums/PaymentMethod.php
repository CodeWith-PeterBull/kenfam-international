<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Enums;

/**
 * Supported manual storefront preferences and POS tender methods.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case CashOnDelivery = 'cash_on_delivery';

    /**
     * Return the human-readable method label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::MobileMoney => 'Mobile money',
            self::Card => 'Card',
            self::BankTransfer => 'Bank transfer',
            self::CashOnDelivery => 'Cash on delivery',
        };
    }
}
