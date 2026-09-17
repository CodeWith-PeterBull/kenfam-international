<?php

/** Validates traveler-facing FAQ content. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\TourFaqData;
use App\Modules\TravelTours\Catalog\Models\TourFaq;
use Livewire\Form;

/** Collect one answer and its visibility state. */
final class TourFaqForm extends Form
{
    public string $question = '';

    public string $answer = '';

    public bool $isActive = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:320'],
            'answer' => ['required', 'string', 'max:50000'],
            'isActive' => ['boolean'],
        ];
    }

    /** Load an authorized FAQ for editing. */
    public function fillFromFaq(TourFaq $faq): void
    {
        $this->question = $faq->question;
        $this->answer = $faq->answer;
        $this->isActive = $faq->is_active;
    }

    /** Restore the default FAQ state. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->isActive = true;
    }

    /** Convert validated input to immutable FAQ data. */
    public function toData(): TourFaqData
    {
        return new TourFaqData($this->question, $this->answer, $this->isActive);
    }
}
