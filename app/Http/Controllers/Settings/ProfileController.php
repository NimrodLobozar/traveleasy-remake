<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\AccountEraser;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = CurrentUser::of($request);

        $user->fill($request->validated());

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Erase the signed-in user's own account (never another user's) through the shared AccountEraser (TE-047).
     *
     * Logout comes first: logging out a deleted user would save the model again (remember-token cycling).
     */
    public function destroy(ProfileDeleteRequest $request, AccountEraser $eraser): RedirectResponse
    {
        $user = CurrentUser::of($request);

        abort_if($eraser->isProtected($user), 403);

        Auth::logout();

        $eraser->erase($user, AccountEraser::ACTOR_SELF);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
