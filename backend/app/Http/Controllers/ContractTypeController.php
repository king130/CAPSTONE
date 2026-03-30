<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\Contracts\ContractTypeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        $types = $this->resolver->forOrganizations($requesterOrganization, $partnerOrganization);

        return response()->json([
            'data' => $types->map(fn ($type) => [
                'id' => (string) $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'scope' => $type->scope,
                'organizationId' => $type->organization_id ? (string) $type->organization_id : null,
                'organizationType' => $type->organization_type,
                'fieldsSchema' => $type->fields_schema ?? [],
                'defaultValues' => $type->default_values ?? [],
                'settings' => $type->settings ?? [],
            ])->values(),
        ], Response::HTTP_OK);
    }
}
