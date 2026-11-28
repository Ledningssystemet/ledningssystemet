<?php

namespace Ledningssystemet\Ledningssystemet\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;
use Ledningssystemet\Ledningssystemet\Support\Modules;
use Ledningssystemet\Ledningssystemet\Support\SystemSettings;

class SystemSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'modules' => collect(Modules::LABELS)->map(fn ($label, $module) => [
                'key' => $module,
                'label' => __($label),
                'enabled' => Modules::active($module),
            ])->values()->all(),
            'contact' => collect(SystemSettings::CONTACT_FIELDS)
                ->map(fn ($label, $key) => SystemSettings::value($key))->all(),
            'assessment' => collect(SystemSettings::ASSESSMENT_FIELDS)
                ->map(fn ($label, $key) => SystemSettings::integerValue($key))->all(),
            'contactFields' => collect(SystemSettings::CONTACT_FIELDS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => __($label), 'email' => $key === 'data_privacy_contact_email'])->values()->all(),
            'assessmentFields' => collect(SystemSettings::ASSESSMENT_FIELDS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => __($label)])->values()->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('systemadministrator.edit');

        $rules = [
            'modules' => ['sometimes', 'array:'.implode(',', array_keys(Modules::LABELS))],
            'data_privacy_contact_name' => ['present', 'nullable', 'string', 'max:255'],
            'data_privacy_contact_email' => ['present', 'nullable', 'string', 'email', 'max:255'],
            'control_status_reassessment_days' => ['required', 'integer', 'min:1'],
        ];
        foreach (Modules::LABELS as $module => $label) {
            $rules['modules.'.$module] = ['required_with:modules', 'boolean'];
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['modules'] ?? [] as $module => $enabled) {
                Feature::store('ledningssystemet')->for(null)->activate($module, (bool) $enabled);
            }
            foreach (array_keys(SystemSettings::CONTACT_FIELDS) as $name) {
                Feature::store('ledningssystemet')->for(null)->activate($name, $validated[$name] ?? '');
            }
            foreach (array_keys(SystemSettings::ASSESSMENT_FIELDS) as $name) {
                Feature::store('ledningssystemet')->for(null)->activate($name, (int) $validated[$name]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('System settings saved.'),
                'contact' => collect(SystemSettings::CONTACT_FIELDS)->map(fn ($label, $key) => $validated[$key] ?? '')->all(),
                'assessment' => collect(SystemSettings::ASSESSMENT_FIELDS)->map(fn ($label, $key) => (int) $validated[$key])->all(),
            ]);
        }

        return redirect('/systemadmin/settings')->with('status', __('System settings saved.'));
    }

    public function updateModule(Request $request, string $module): JsonResponse
    {
        Gate::authorize('systemadministrator.edit');

        $validated = validator([
            'module' => $module,
            'enabled' => $request->input('enabled'),
        ], [
            'module' => ['required', Rule::in(array_keys(Modules::LABELS))],
            'enabled' => ['required', 'boolean'],
        ])->validate();

        Feature::store('ledningssystemet')->for(null)->activate($module, (bool) $validated['enabled']);

        return response()->json(['module' => $module, 'enabled' => (bool) $validated['enabled']]);
    }
}
