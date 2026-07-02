<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomProperty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CustomPropertyCrudController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = [
        'id',
        'name',
        'description',
        'type',
        'options',
        'ordinal',
        'display_on_card',
        'user_editable',
        'required',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request, string $context): JsonResponse
    {
        Gate::authorize('viewAny', CustomProperty::class);

        $resolvedContext = rawurldecode($context);
        $query = CustomProperty::query()->where('context', $resolvedContext);

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('options', 'like', "%{$search}%");
            });
        }

        $sort = trim((string) $request->query('sort', 'ordinal'));
        $direction = 'asc';

        if ($sort !== '' && str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        }

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'ordinal';
        }

        $query->orderBy($sort, $direction);

        $selectedColumns = $this->parseSelectedColumns($request);
        if ($selectedColumns !== []) {
            if (! in_array('id', $selectedColumns, true)) {
                $selectedColumns[] = 'id';
            }

            $query->select($selectedColumns);
        }

        $paginate = $request->has('paginate') ? $request->boolean('paginate') : true;

        if ($paginate) {
            $perPage = max(1, min(100, $request->integer('per_page', 25)));

            return response()->json($query->paginate($perPage)->appends($request->query()));
        }

        return response()->json($query->get());
    }

    public function store(Request $request, string $context): JsonResponse
    {
        Gate::authorize('create', CustomProperty::class);

        $resolvedContext = rawurldecode($context);
        $payload = $request->all();
        $payload['context'] = $resolvedContext;

        if (! array_key_exists('ordinal', $payload) || $payload['ordinal'] === '' || $payload['ordinal'] === null) {
            $payload['ordinal'] = ((int) CustomProperty::query()->where('context', $resolvedContext)->max('ordinal')) + 1;
        }

        Validator::make($payload, CustomProperty::validationRules())->validate();

        $model = new CustomProperty();
        $model->fill($payload);
        $model->save();

        return response()->json($model->fresh(), Response::HTTP_CREATED);
    }

    public function update(Request $request, string $context, string $id): JsonResponse
    {
        $resolvedContext = rawurldecode($context);
        $model = CustomProperty::query()
            ->where('context', $resolvedContext)
            ->findOrFail($id);

        Gate::authorize('update', $model);

        $rules = $this->updateValidationRules();
        $data = $request->validate($rules);
        unset($data['context']);

        $model->fill($data);
        $model->save();

        return response()->json($model->fresh());
    }

    public function destroy(string $context, string $id): JsonResponse
    {
        $resolvedContext = rawurldecode($context);
        $model = CustomProperty::query()
            ->where('context', $resolvedContext)
            ->findOrFail($id);

        Gate::authorize('delete', $model);
        $model->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<int, string>
     */
    private function parseSelectedColumns(Request $request): array
    {
        $raw = $request->query('$select', $request->query('select', ''));

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $allowed = array_fill_keys(self::SORTABLE_COLUMNS, true);
        $allowed['context'] = true;

        $columns = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return array_values(array_unique(array_filter(
            $columns,
            static fn (string $column): bool => isset($allowed[$column])
        )));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateValidationRules(): array
    {
        $rules = CustomProperty::validationRules();

        foreach ($rules as $field => $fieldRules) {
            $filtered = array_values(array_filter($fieldRules, static fn (mixed $rule): bool => $rule !== 'required'));

            if (! in_array('sometimes', $filtered, true)) {
                array_unshift($filtered, 'sometimes');
            }

            $rules[$field] = $filtered;
        }

        return $rules;
    }
}

