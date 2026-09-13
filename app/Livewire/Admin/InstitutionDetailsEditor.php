<?php

namespace App\Livewire\Admin;

use App\Contracts\ResolvesInstitutionProfile;
use App\Livewire\Forms\InstitutionDetailsForm;
use App\Services\InstitutionDetailsService;
use App\Support\CmsPermission;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class InstitutionDetailsEditor extends Component
{
    use WithFileUploads;

    public InstitutionDetailsForm $form;

    public $mainLogoUpload = null;

    public $logoIconUpload = null;

    protected InstitutionDetailsService $institutionDetails;

    protected ResolvesInstitutionProfile $profiles;

    public function boot(InstitutionDetailsService $institutionDetails, ResolvesInstitutionProfile $profiles): void
    {
        Gate::authorize(CmsPermission::VIEW_INSTITUTION_DETAILS);
        $this->institutionDetails = $institutionDetails;
        $this->profiles = $profiles;
    }

    public function mount(): void
    {
        $this->form->fillFromProfile($this->profiles->current());
    }

    #[Computed]
    public function profile()
    {
        return $this->profiles->current();
    }

    public function addSocialMedia(): void
    {
        Gate::authorize(CmsPermission::MANAGE_INSTITUTION_DETAILS);
        $this->form->socialMedia[] = ['platform' => '', 'handle' => '', 'url' => ''];
    }

    public function removeSocialMedia(int $index): void
    {
        Gate::authorize(CmsPermission::MANAGE_INSTITUTION_DETAILS);
        unset($this->form->socialMedia[$index]);
        $this->form->socialMedia = array_values($this->form->socialMedia);
    }

    public function save(): void
    {
        Gate::authorize(CmsPermission::MANAGE_INSTITUTION_DETAILS);
        $this->form->validate();
        $this->validate([
            'mainLogoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'logoIconUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ]);

        $this->institutionDetails->save(
            attributes: $this->form->payload(),
            mainLogo: $this->mainLogoUpload,
            logoIcon: $this->logoIconUpload,
            actor: auth()->user(),
        );

        $this->reset(['mainLogoUpload', 'logoIconUpload']);
        unset($this->profile);
        $this->form->fillFromProfile($this->profiles->current());
        session()->flash('success', 'Institution details saved successfully.');
    }

    public function removeLogo(string $collection): void
    {
        Gate::authorize(CmsPermission::MANAGE_INSTITUTION_DETAILS);
        $this->institutionDetails->removeLogo($collection, auth()->user());
        unset($this->profile);
        session()->flash('success', 'Institution logo removed successfully.');
    }

    public function render()
    {
        return view('livewire.admin.institution-details-editor');
    }
}
