<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TodoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** I7: the "Perlu kamu kerjakan" list as JSON for the bell in the header, loaded after the page is shown. */
class TodoController extends Controller
{
    public function __invoke(Request $request, TodoService $todo): JsonResponse
    {
        return response()->json(['items' => $todo->for($request->user())]);
    }
}
