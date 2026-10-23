<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ProfileAvatar
{
    public static function publicUrl(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $profile = is_array($user->profile) ? $user->profile : [];
        $path = $profile['avatarPath'] ?? null;
        $prefix = 'avatars/'.$user->id.'/';

        if (! is_string($path) || $path === '' || ! str_starts_with($path, $prefix)) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $version = $user->updated_at?->getTimestamp() ?? time();

        return url('/api/profile/avatar/'.$user->id).'?v='.$version;
    }
}
