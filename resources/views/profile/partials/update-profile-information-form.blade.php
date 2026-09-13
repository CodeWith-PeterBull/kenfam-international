@php($profile = $user->profile)
<p class="aureon-muted">Maintain your account identity, contact details, and profile photo.</p>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
    @csrf
    @method('patch')

    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4">
        @if ($user->profilePhotoUrl())
            <img src="{{ $user->profilePhotoUrl() }}" alt="Current profile photo" class="aureon-avatar-img aureon-avatar-img--profile">
        @else
            <span class="aureon-user-avatar aureon-user-avatar--large">{{ $user->initials }}</span>
        @endif
        <div class="flex-grow-1">
            <label for="profile-photo" class="form-label">Profile photo</label>
            <input id="profile-photo" type="file" name="profile_photo" class="form-control @error('profile_photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
            @error('profile_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="aureon-muted">JPEG, PNG, or WebP up to 3 MB.</small>
            @if ($user->profilePhotoUrl())
                <div class="form-check mt-2"><input id="remove-profile-photo" type="checkbox" name="remove_profile_photo" value="1" class="form-check-input"><label for="remove-profile-photo" class="form-check-label">Remove current photo</label></div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6"><label for="name" class="form-label">Username <span class="text-danger">*</span></label><input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="username">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="first-name" class="form-label">First name <span class="text-danger">*</span></label><input id="first-name" type="text" name="first_name" value="{{ old('first_name', $profile?->first_name) }}" class="form-control @error('first_name') is-invalid @enderror" required autocomplete="given-name">@error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="middle-name" class="form-label">Middle name</label><input id="middle-name" type="text" name="middle_name" value="{{ old('middle_name', $profile?->middle_name) }}" class="form-control @error('middle_name') is-invalid @enderror" autocomplete="additional-name">@error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="last-name" class="form-label">Last name <span class="text-danger">*</span></label><input id="last-name" type="text" name="last_name" value="{{ old('last_name', $profile?->last_name) }}" class="form-control @error('last_name') is-invalid @enderror" required autocomplete="family-name">@error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="date-of-birth" class="form-label">Date of birth</label><input id="date-of-birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}" class="form-control @error('date_of_birth') is-invalid @enderror">@error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="phone" class="form-label">Phone</label><input id="phone" type="tel" name="phone" value="{{ old('phone', $profile?->phone) }}" class="form-control @error('phone') is-invalid @enderror" autocomplete="tel">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="job-title" class="form-label">Job title</label><input id="job-title" type="text" name="job_title" value="{{ old('job_title', $profile?->job_title) }}" class="form-control @error('job_title') is-invalid @enderror" autocomplete="organization-title">@error('job_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label for="identification-type" class="form-label">Identification type</label><select id="identification-type" name="identification_type" class="form-select @error('identification_type') is-invalid @enderror"><option value="">Not recorded</option>@foreach (\App\Enums\IdentificationType::cases() as $type)<option value="{{ $type->value }}" @selected(old('identification_type', $profile?->identification_type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select>@error('identification_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-8"><label for="identification-number" class="form-label">ID or passport number</label><input id="identification-number" type="text" name="identification_number" value="{{ old('identification_number', $profile?->identification_number) }}" class="form-control @error('identification_number') is-invalid @enderror">@error('identification_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label for="bio" class="form-label">Biography</label><textarea id="bio" name="bio" rows="4" class="form-control @error('bio') is-invalid @enderror">{{ old('bio', $profile?->bio) }}</textarea>@error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>

    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
        <div class="alert alert-warning mt-3 mb-0 fs-13" role="alert">Your email address is unverified. <button form="send-verification" class="btn btn-link aureon-auth-link p-0 fs-13 align-baseline">Re-send the verification email.</button></div>
        @if (session('status') === 'verification-link-sent')<div class="alert alert-success mt-2 mb-0 fs-13" role="alert">A new verification link has been sent.</div>@endif
    @endif

    <div class="d-flex align-items-center gap-3 mt-4"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-2"></i>Save profile</button>@if (session('status') === 'profile-updated')<span class="text-success fs-13"><i class="ti ti-check me-1"></i>Saved.</span>@endif</div>
</form>
