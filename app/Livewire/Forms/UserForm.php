<?php

namespace App\Livewire\Forms;

use App\Enums\IdentificationType;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    #[Validate]
    public string $name = '';

    #[Validate]
    public string $email = '';

    #[Validate]
    public string $password = '';

    public string $password_confirmation = '';

    #[Validate]
    public string $userType = 'viewer';

    #[Validate]
    public bool $isActive = true;

    #[Validate]
    public bool $twoFactorEnabled = true;

    #[Validate]
    public string $firstName = '';

    public string $middleName = '';

    #[Validate]
    public string $lastName = '';

    #[Validate]
    public string $dateOfBirth = '';

    #[Validate]
    public string $identificationType = '';

    #[Validate]
    public string $identificationNumber = '';

    #[Validate]
    public string $phone = '';

    #[Validate]
    public string $jobTitle = '';

    #[Validate]
    public string $bio = '';

    /** @var list<string> */
    #[Validate]
    public array $additionalRoles = [];

    public bool $removeProfilePhoto = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('users', 'name')->ignore($this->user),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user),
            ],
            'password' => $this->user
                ? ['nullable', 'confirmed', Password::defaults()]
                : ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['nullable', 'string'],
            'userType' => ['required', Rule::enum(UserType::class)],
            'isActive' => ['boolean'],
            'twoFactorEnabled' => ['boolean'],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'identificationType' => ['nullable', 'required_with:identificationNumber', Rule::enum(IdentificationType::class)],
            'identificationNumber' => [
                'nullable',
                'required_with:identificationType',
                'string',
                'max:100',
                Rule::unique('user_profiles', 'identification_number')->ignore($this->user?->profile),
            ],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() .-]+$/'],
            'jobTitle' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'additionalRoles' => ['array', 'max:20'],
            'additionalRoles.*' => [
                'string',
                'distinct',
                Rule::notIn(UserType::values()),
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'removeProfilePhoto' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'username',
            'userType' => 'user type',
            'twoFactorEnabled' => 'two-factor authentication',
            'firstName' => 'first name',
            'middleName' => 'middle name',
            'lastName' => 'last name',
            'dateOfBirth' => 'date of birth',
            'identificationType' => 'identification type',
            'identificationNumber' => 'identification number',
            'jobTitle' => 'job title',
            'additionalRoles' => 'additional roles',
            'additionalRoles.*' => 'selected role',
        ];
    }

    public function fillFromUser(User $user): void
    {
        $user->loadMissing(['profile', 'roles', 'media']);
        $profile = $user->profile;
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->userType = $user->user_type->value;
        $this->isActive = $user->is_active;
        $this->twoFactorEnabled = $user->two_factor_enabled;
        $this->firstName = (string) $profile?->first_name;
        $this->middleName = (string) $profile?->middle_name;
        $this->lastName = (string) $profile?->last_name;
        $this->dateOfBirth = $profile?->date_of_birth?->format('Y-m-d') ?? '';
        $this->identificationType = $profile?->identification_type?->value ?? '';
        $this->identificationNumber = (string) $profile?->identification_number;
        $this->phone = (string) $profile?->phone;
        $this->jobTitle = (string) $profile?->job_title;
        $this->bio = (string) $profile?->bio;
        $this->additionalRoles = $user->getRoleNames()
            ->reject(static fn (string $role): bool => in_array($role, UserType::values(), true))
            ->values()
            ->all();
        $this->removeProfilePhoto = false;
    }

    public function resetForCreate(): void
    {
        $this->reset();
        $this->user = null;
        $this->userType = UserType::Viewer->value;
        $this->isActive = true;
        $this->twoFactorEnabled = true;
        $this->additionalRoles = [];
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function accountPayload(): array
    {
        $payload = [
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'user_type' => $this->userType,
            'is_active' => $this->isActive,
            'two_factor_enabled' => $this->twoFactorEnabled,
        ];

        if ($this->password !== '') {
            $payload['password'] = $this->password;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public function profilePayload(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'identification_type' => $this->identificationType,
            'identification_number' => $this->identificationNumber,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'bio' => $this->bio,
        ];
    }
}
