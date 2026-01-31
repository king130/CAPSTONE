<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function store(Request $request)
    {
        // Expect Firebase file_url + metadata from client
        return response()->json(['message' => 'Document stored'], Response::HTTP_CREATED);
    }
}
