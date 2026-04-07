<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileAvatarController extends Controller
{
    public function show(User $user): StreamedResponse|Response
    {
        $profile = $user->profile ?? [];
        $avatarPath = $profile['avatarPath'] ?? null;

        if (! is_string($avatarPath) || $avatarPath === '' || ! Storage::disk('public')->exists($avatarPath)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('public')->response($avatarPath, null, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $profile = $user->profile ?? [];
        $previousAvatarPath = $profile['avatarPath'] ?? null;

        $path = $data['avatar']->store("avatars/{$user->id}", 'public');
        $profile['avatarPath'] = $path;
        $profile['avatarUrl'] = Storage::disk('public')->url($path);

        $user->profile = $profile;
        $user->save();

        if (is_string($previousAvatarPath) && $previousAvatarPath !== '' && $previousAvatarPath !== $path) {
            Storage::disk('public')->delete($previousAvatarPath);
        }

        $freshUser = $user->fresh([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        $auth = app(AuthController::class);

        return response()->json([
            'message' => 'Profile picture uploaded successfully.',
            'user' => $auth->formatUserProfile($freshUser),
        ], Response::HTTP_CREATED);
    }
}
