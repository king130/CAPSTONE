<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Document::query()
            ->where('owner_id', $user->id)
            ->where('owner_type', (string) $user->effectiveAppRole())
            ->orderByDesc('created_at');

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Document $document) => $this->transform($document))->values()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['approved', 'pending', 'rejected'])],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $documents = [];
        foreach ($request->file('files', []) as $file) {
            $path = $file->store("documents/{$user->id}", 'public');
            $document = Document::query()->create([
                'owner_id' => $user->id,
                'owner_type' => (string) $user->effectiveAppRole(),
                'category' => $data['category'] ?? 'general',
                'file_name' => $file->getClientOriginalName(),
                'file_url' => Storage::disk('public')->url($path),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'storage_provider' => 'local_public',
                'verified_at' => ($data['status'] ?? 'pending') === 'approved' ? now() : null,
            ]);

            $documents[] = $this->transform($document);
        }

        return response()->json([
            'message' => 'Documents uploaded successfully.',
            'data' => $documents,
        ], Response::HTTP_CREATED);
    }

    private function transform(Document $document): array
    {
        return [
            'id' => (string) $document->id,
            'category' => $document->category,
            'fileName' => $document->file_name,
            'fileUrl' => $document->file_url,
            'fileType' => $document->file_type,
            'fileSize' => $document->file_size,
            'storageProvider' => $document->storage_provider,
            'status' => $document->verified_at ? 'approved' : 'pending',
            'createdAt' => $document->created_at?->toIso8601String(),
            'updatedAt' => $document->updated_at?->toIso8601String(),
        ];
    }
}
