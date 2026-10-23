<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrganizationMedia extends Model
{
    protected $table = 'organization_media';

    protected $fillable = [
        'organization_id',
        'path',
        'caption',
        'sort_order',
        'is_cover',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (OrganizationMedia $media): void {
            if ($media->path && Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function publicUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        // Serve through the API (same pattern as profile avatars). Direct /storage/*
        // URLs break on some shared hosts (e.g. Hostinger image pipeline → 422).
        $url = url('/api/organization-media/'.$this->id.'/file');
        $version = $this->updated_at?->getTimestamp();
        if ($version) {
            $url .= '?v='.$version;
        }

        return $url;
    }
}
