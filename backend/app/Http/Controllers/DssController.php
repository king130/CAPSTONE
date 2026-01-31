<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DssController extends Controller
{
    public function match(Request $request)
    {
        // Compute ranked internships and scores
        return response()->json(['data' => []]);
    }
}
