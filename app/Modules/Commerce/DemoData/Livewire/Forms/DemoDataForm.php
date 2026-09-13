<?php

declare(strict_types=1);

namespace App\Modules\Commerce\DemoData\Livewire\Forms;

use App\Modules\Commerce\Database\Seeders\CatalogDemoSeeder;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates the only arguments accepted by the browser-facing demo command.
 */
final class DemoDataForm extends Form
{
    public string $context = CatalogDemoSeeder::DEFAULT_CONTEXT;

    public bool $archiveExisting = false;

    public bool $archiveConfirmed = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'context' => ['required', 'string', Rule::in(CatalogDemoSeeder::supportedContexts())],
            'archiveExisting' => ['boolean'],
            'archiveConfirmed' => [
                'boolean',
                Rule::requiredIf(fn (): bool => $this->archiveExisting),
                Rule::when($this->archiveExisting, ['accepted']),
            ],
        ];
    }

    /** @return array{context: string, archive_existing: bool} */
    public function commandArguments(): array
    {
        return [
            'context' => $this->context,
            'archive_existing' => $this->archiveExisting,
        ];
    }
}
