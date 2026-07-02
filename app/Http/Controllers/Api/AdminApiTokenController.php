<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdminApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PersonalAccessToken::class);

        $query = PersonalAccessToken::query()
            ->where('tokenable_type', User::class);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $sort = (string) $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');

        $sortableColumns = ['id', 'name', 'tokenable_id', 'last_used_at', 'expires_at', 'created_at'];
        if ($sortField === 'user_id') {
            $sortField = 'tokenable_id';
        }
        if (! in_array($sortField, $sortableColumns, true)) {
            $sortField = 'created_at';
            $direction = 'desc';
        }

        $query->orderBy($sortField, $direction);

        $paginate = $request->boolean('paginate', true);
        if ($paginate) {
            $perPage = max(1, min(100, $request->integer('per_page', 25)));
            $paginator = $query->paginate($perPage)->appends($request->query());
            $paginator->setCollection($paginator->getCollection()->map(fn (PersonalAccessToken $token): array => $this->serializeToken($token)));

            return response()->json($paginator);
        }

        return response()->json(
            $query->get()->map(fn (PersonalAccessToken $token): array => $this->serializeToken($token))->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', PersonalAccessToken::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);
        $tokenResult = $user->createToken($validated['name']);
        $token = $tokenResult->accessToken->fresh();

        if (! $token instanceof PersonalAccessToken) {
            $token = PersonalAccessToken::query()->findOrFail($tokenResult->accessToken->id);
        }

        return response()->json([
            ...$this->serializeToken($token),
            'plain_text_token' => $tokenResult->plainTextToken,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $tokenId): JsonResponse
    {
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->findOrFail($tokenId);

        Gate::authorize('update', $token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token->name = $validated['name'];
        $token->save();

        return response()->json($this->serializeToken($token->fresh()));
    }

    public function destroy(int $tokenId): JsonResponse
    {
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->findOrFail($tokenId);

        Gate::authorize('delete', $token);
        $token->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeToken(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'user_id' => $token->tokenable_id,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'created_at' => $token->created_at,
            'updated_at' => $token->updated_at,
        ];
    }
}

