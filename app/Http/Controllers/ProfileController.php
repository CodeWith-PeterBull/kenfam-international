<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\UserManagementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UserManagementService $users): RedirectResponse
    {
        $validated = $request->validated();
        $users->updateOwnProfile(
            user: $request->user(),
            accountAttributes: [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ],
            profileAttributes: [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'identification_type' => $validated['identification_type'] ?? null,
                'identification_number' => $validated['identification_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'bio' => $validated['bio'] ?? null,
            ],
            profilePhoto: $request->file('profile_photo'),
            removeProfilePhoto: $request->boolean('remove_profile_photo'),
        );

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, UserManagementService $users): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        try {
            $users->assertOwnAccountDeletionIsSafe($user);
        } catch (DomainException $exception) {
            return Redirect::route('profile.edit')->withErrors([
                'password' => $exception->getMessage(),
            ], 'userDeletion');
        }

        Auth::logout();
        $users->deleteOwnAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
