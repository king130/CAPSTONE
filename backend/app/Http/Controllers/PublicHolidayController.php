<?php

namespace App\Http\Controllers;

use App\Models\PublicHoliday;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicHolidayController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $query = PublicHoliday::query()->orderBy('date');
        if ($request->filled('year')) {
            $year = (int) $request->integer('year');
            $query->whereBetween('date', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (PublicHoliday $h) => [
                'id' => (string) $h->id,
                'date' => $h->date?->toDateString(),
                'name' => $h->name,
                'type' => $h->type,
                'scope' => $h->scope,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
            'scope' => ['nullable', 'string', 'max:255'],
        ]);

        $holiday = PublicHoliday::query()->create([
            'date' => $data['date'],
            'name' => $data['name'],
            'type' => $data['type'] ?? 'national',
            'scope' => $data['scope'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => (string) $holiday->id,
                'date' => $holiday->date?->toDateString(),
                'name' => $holiday->name,
                'type' => $holiday->type,
                'scope' => $holiday->scope,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, PublicHoliday $publicHoliday)
    {
        if ($request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
            'scope' => ['nullable', 'string', 'max:255'],
        ]);

        $publicHoliday->fill($data)->save();

        return response()->json([
            'data' => [
                'id' => (string) $publicHoliday->id,
                'date' => $publicHoliday->date?->toDateString(),
                'name' => $publicHoliday->name,
                'type' => $publicHoliday->type,
                'scope' => $publicHoliday->scope,
            ],
        ]);
    }

    public function destroy(Request $request, PublicHoliday $publicHoliday)
    {
        if ($request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $publicHoliday->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
