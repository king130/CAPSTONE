<?php

namespace App\Http\Controllers;

use App\Models\ContractType;
use App\Models\Organization;
use App\Models\User;
use App\Services\Contracts\ContractTypeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractTypeController extends Controller
{
    public function __construct(
        private readonly ContractTypeResolver $resolver
    ) {
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'partnerUserId' => ['nullable', 'integer', 'exists:users,id'],
            'partnerOrganizationId' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $partnerUser = null;
        if (! empty($data['partnerUserId'])) {
            $partnerUser = User::query()
                ->with('organizationMemberships.organization')
                ->find($data['partnerUserId']);
        }

        $partnerOrganization = null;
        if (! empty($data['partnerOrganizationId'])) {
            $partnerOrganization = Organization::query()->find($data['partnerOrganizationId']);
        }

        $requesterOrganization = $user->activeOrganization();
        $partnerOrganization ??= $partnerUser?->activeOrganization();

        $types = $this->resolver->forUser($user, $requesterOrganization, $partnerOrganization);

        return response()->json([
            'data' => $types->map(fn ($type) => [
                'id' => (string) $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'scope' => $type->scope,
                'organizationId' => $type->organization_id ? (string) $type->organization_id : null,
                'organizationType' => $type->organization_type,
                'createdByUserId' => $type->created_by_user_id ? (string) $type->created_by_user_id : null,
                'fieldsSchema' => $type->fields_schema ?? [],
                'defaultValues' => $type->default_values ?? [],
                'settings' => $type->settings ?? [],
            ])->values(),
        ], Response::HTTP_OK);
    }

    public function manage(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        if (! $organization) {
            throw ValidationException::withMessages([
                'organization' => 'You need an active organization to manage contract types.',
            ]);
        }

        $customTypes = ContractType::query()
            ->where('organization_id', $organization->id)
            ->where('created_by_user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $globalTypes = ContractType::query()
            ->whereNull('organization_id')
            ->where('scope', 'global')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'customTypes' => $customTypes->map(fn ($type) => $this->serializeType($type))->values(),
                'globalTypes' => $globalTypes->map(fn ($type) => $this->serializeType($type))->values(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        if (! $organization) {
            throw ValidationException::withMessages([
                'organization' => 'You need an active organization to create a contract type.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'baseContractTypeId' => ['nullable', 'integer', 'exists:contract_types,id'],
            'fieldsSchema' => ['required', 'array', 'min:1'],
            'fieldsSchema.*.key' => ['required', 'string', 'max:100'],
            'fieldsSchema.*.label' => ['required', 'string', 'max:255'],
            'fieldsSchema.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'date', 'number', 'select', 'multiselect', 'email', 'checkbox'])],
            'fieldsSchema.*.required' => ['nullable', 'boolean'],
            'fieldsSchema.*.options' => ['nullable', 'array'],
            'fieldsSchema.*.options.*' => ['nullable', 'string', 'max:255'],
            'fieldsSchema.*.placeholder' => ['nullable', 'string', 'max:255'],
            'defaultValues' => ['nullable', 'array'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $slug = $this->uniqueSlugForOrganization($organization, (string) $data['name']);

        $type = ContractType::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'scope' => 'organization',
            'organization_id' => $organization->id,
            'organization_type' => $organization->type,
            'created_by_user_id' => $user->id,
            'base_contract_type_id' => $data['baseContractTypeId'] ?? null,
            'description' => $data['description'] ?? null,
            'fields_schema' => $data['fieldsSchema'],
            'default_values' => $data['defaultValues'] ?? [],
            'settings' => [],
            'is_active' => $data['isActive'] ?? true,
            'sort_order' => (ContractType::query()->where('organization_id', $organization->id)->max('sort_order') ?? 0) + 10,
        ]);

        return response()->json([
            'data' => $this->serializeType($type),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ContractType $contractType)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        $this->assertOwnsType($user, $organization, $contractType);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fieldsSchema' => ['required', 'array', 'min:1'],
            'fieldsSchema.*.key' => ['required', 'string', 'max:100'],
            'fieldsSchema.*.label' => ['required', 'string', 'max:255'],
            'fieldsSchema.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'date', 'number', 'select', 'multiselect', 'email', 'checkbox'])],
            'fieldsSchema.*.required' => ['nullable', 'boolean'],
            'fieldsSchema.*.options' => ['nullable', 'array'],
            'fieldsSchema.*.options.*' => ['nullable', 'string', 'max:255'],
            'fieldsSchema.*.placeholder' => ['nullable', 'string', 'max:255'],
            'defaultValues' => ['nullable', 'array'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $slug = $contractType->name !== $data['name']
            ? $this->uniqueSlugForOrganization($organization, (string) $data['name'], $contractType->id)
            : $contractType->slug;

        $contractType->update([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'fields_schema' => $data['fieldsSchema'],
            'default_values' => $data['defaultValues'] ?? [],
            'is_active' => $data['isActive'] ?? $contractType->is_active,
        ]);

        return response()->json([
            'data' => $this->serializeType($contractType->fresh()),
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, ContractType $contractType)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        $this->assertOwnsType($user, $organization, $contractType);

        if ($contractType->contracts()->exists()) {
            throw ValidationException::withMessages([
                'contractType' => 'This contract type is already used by submitted contracts and cannot be deleted.',
            ]);
        }

        $contractType->delete();

        return response()->json([
            'message' => 'Contract type deleted.',
        ], Response::HTTP_OK);
    }

    private function assertOwnsType(User $user, ?Organization $organization, ContractType $contractType): void
    {
        if (
            ! $organization
            || $contractType->organization_id !== $organization->id
            || $contractType->created_by_user_id !== $user->id
        ) {
            throw ValidationException::withMessages([
                'contractType' => 'You can only manage custom contract types that you created.',
            ]);
        }
    }

    private function uniqueSlugForOrganization(Organization $organization, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'contract-type';
        $suffix = 1;

        while (
            ContractType::query()
                ->where('organization_id', $organization->id)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function serializeType(ContractType $type): array
    {
        return [
            'id' => (string) $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
            'description' => $type->description,
            'scope' => $type->scope,
            'organizationId' => $type->organization_id ? (string) $type->organization_id : null,
            'organizationType' => $type->organization_type,
            'createdByUserId' => $type->created_by_user_id ? (string) $type->created_by_user_id : null,
            'baseContractTypeId' => $type->base_contract_type_id ? (string) $type->base_contract_type_id : null,
            'fieldsSchema' => $type->fields_schema ?? [],
            'defaultValues' => $type->default_values ?? [],
            'settings' => $type->settings ?? [],
            'isActive' => (bool) $type->is_active,
            'sortOrder' => (int) $type->sort_order,
        ];
    }
}
