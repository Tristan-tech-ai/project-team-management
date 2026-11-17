<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Services\ProjectTemplateService;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** U4: the code field is filled in while the name is typed; this answers the exact unique code the record would get. */
class CodeSuggestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['for' => ['required', 'in:companies,clients,projects'], 'name' => ['nullable', 'string', 'max:200']]);
        $user = $request->user();
        abort_unless(match ($data['for']) {
            'companies' => $user->can('companies.manage'),
            'clients' => $user->can('clients.manage'),
            'projects' => $user->can('create', Project::class),
        }, 403);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return response()->json(['code' => '']);
        }
        $code = match ($data['for']) {
            'companies' => CodeGenerator::unique($name, fn (string $c) => Company::withTrashed()->where('code', $c)->exists()),
            'clients' => CodeGenerator::unique($name, fn (string $c) => Client::withTrashed()->where('code', $c)->exists()),
            'projects' => ProjectTemplateService::codeFor($name),
        };

        return response()->json(['code' => $code]);
    }
}
