<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Support\InternshipDocumentRequirements;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $category = strtolower(trim((string) ($data['category'] ?? 'general')));
        $isEndorsement = in_array($category, InternshipDocumentRequirements::endorsementDocumentCategories(), true);

        $documents = [];
        foreach ($request->file('files', []) as $file) {
            if ($isEndorsement) {
                $documents[] = $this->storePrivateEndorsement($user, $file, $category);
            } else {
                $path = $file->store("documents/{$user->id}", 'public');
                $document = Document::query()->create([
                    'owner_id' => $user->id,
                    'owner_type' => (string) $user->effectiveAppRole(),
                    'category' => $category !== '' ? $category : 'general',
                    'file_name' => $this->safeFileName((string) $file->getClientOriginalName()),
                    'file_url' => Storage::disk('public')->url($path),
                    'file_path' => $path,
                    'disk' => 'public',
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'storage_provider' => 'local_public',
                    'verified_at' => null,
                ]);
                $documents[] = $this->transform($document);
            }
        }

        return response()->json([
            'message' => 'Documents uploaded successfully.',
            'data' => $documents,
        ], Response::HTTP_CREATED);
    }

    public function download(Request $request, Document $document)
    {
        $user = $request->user();
        if (! $this->canAccessDocument($user, $document)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $disk = $document->disk ?: 'public';
        $path = $document->file_path;
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return response()->json(['message' => 'Document file not found.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk($disk)->download($path, $document->file_name);
    }

    public function destroy(Request $request, Document $document)
    {
        $user = $request->user();
        if ((int) $document->owner_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $disk = $document->disk ?: 'public';
        if ($document->file_path && Storage::disk($disk)->exists($document->file_path)) {
            Storage::disk($disk)->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    private function storePrivateEndorsement(User $user, $file, string $category): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'files' => ['Endorsement must be PDF, JPG, or PNG.'],
            ]);
        }

        $realMime = $this->detectMimeFromContent((string) $file->getRealPath());
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (! in_array($realMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'files' => ['Endorsement file content must be a real PDF, JPG, or PNG.'],
            ]);
        }

        $randomName = Str::uuid()->toString().'.'.$extension;
        $directory = 'application-documents/'.$user->id.'/endorsements';
        $path = $file->storeAs($directory, $randomName, 'local');

        $document = Document::query()->create([
            'owner_id' => $user->id,
            'owner_type' => (string) $user->effectiveAppRole(),
            'category' => $category,
            'file_name' => $this->safeFileName((string) $file->getClientOriginalName()),
            'file_url' => '/api/documents/pending/file', // replaced after insert
            'file_path' => $path,
            'disk' => 'local',
            'file_type' => $realMime,
            'file_size' => $file->getSize() ?: 0,
            'storage_provider' => 'local_private',
            'verified_at' => now(), // student-owned endorsement is usable immediately for apply
        ]);

        $downloadUrl = '/api/documents/'.$document->id.'/file';
        $document->update(['file_url' => $downloadUrl]);

        return $this->transform($document->fresh());
    }

    private function detectMimeFromContent(string $absolutePath): string
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($absolutePath);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function canAccessDocument(User $user, Document $document): bool
    {
        if ($user->isPlatformAdmin() || $user->effectiveAppRole() === 'admin') {
            return true;
        }

        if ((int) $document->owner_id === (int) $user->id) {
            return true;
        }

        // Hosts / schools that share an application with this document URL may download.
        $url = trim((string) $document->file_url);
        $pathUrl = '/api/documents/'.$document->id.'/file';
        $appRole = $user->effectiveAppRole();
        if (! in_array($appRole, ['company', 'school', 'municipal_hall'], true)) {
            return false;
        }

        $query = \App\Models\Application::query()
            ->where(function ($q) use ($url, $pathUrl) {
                $q->where('resume_url', $url)
                    ->orWhere('resume_url', $pathUrl)
                    ->orWhere('documents', 'like', '%'.$pathUrl.'%');
                if ($url !== '') {
                    $q->orWhere('documents', 'like', '%'.addcslashes($url, '%_').'%');
                }
            });

        if ($appRole === 'school') {
            $school = $user->organizationSchool() ?? $user->school;
            if (! $school) {
                return false;
            }

            return $query->whereHas('student', fn ($s) => $s->where('school_id', $school->id))->exists();
        }

        $company = $user->organizationCompany() ?? $user->company;
        if (! $company) {
            return false;
        }

        return $query->where(function ($q) use ($company) {
            $q->where('company_id', $company->id)
                ->orWhereHas('internship', fn ($i) => $i->where('company_id', $company->id));
        })->exists();
    }

    private function safeFileName(string $name): string
    {
        $base = trim(str_replace(['\\', '/'], '', $name));
        $base = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $base) ?: 'document';

        return mb_substr($base, 0, 180);
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
            'disk' => $document->disk,
            'status' => $document->verified_at ? 'approved' : 'pending',
            'createdAt' => $document->created_at?->toIso8601String(),
            'updatedAt' => $document->updated_at?->toIso8601String(),
        ];
    }
}
