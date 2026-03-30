<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function markRead(Request $request, string $id)
    {
        return response()->json(['message' => 'Notification marked as read', 'id' => $id]);
    }
}
