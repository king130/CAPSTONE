<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationController extends Controller
{
    public function store(Request $request)
    {
        return response()->json(['message' => 'Application submitted'], Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, $applicationId)
    {
        return response()->json(['message' => 'Application status updated']);
    }
}
