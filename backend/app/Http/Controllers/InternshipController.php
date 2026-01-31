<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InternshipController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Internship created'], Response::HTTP_CREATED);
    }

    public function show($internshipId)
    {
        return response()->json(['data' => ['id' => $internshipId]]);
    }

    public function update(Request $request, $internshipId)
    {
        return response()->json(['message' => 'Internship updated']);
    }

    public function destroy($internshipId)
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
