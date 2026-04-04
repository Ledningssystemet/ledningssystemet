<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ItemStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'type' => 'required|regex:/^[A-Z][a-zA-Z]+[a-z]$/|max:255',
            'id' => 'sometimes|integer|min:1',
            'department_id' => 'sometimes|integer|exists:departments,id',
        ]);

        // Derive classname
        $className = 'App\\Models\\' . $validated['type'];

        // Validate that the class exists
        if (! class_exists($className)) {
            return response()->json(['message' => 'Invalid type'], 400);
        }

        // If an id is provided, find the item
        $item = isset($validated['id']) ? $className::findOrFail($validated['id']) : null;

        // If a department_id is provided, find the department
        $department = isset($validated['department_id']) ? \App\Models\Department::findOrFail($validated['department_id']) : null;

        // If both an item and a department is provided, then throw 400
        if($item && $department) {
            return response()->json(['message' => 'Cannot specify both id and department_id'], 400);
        }

        // Return the item status given that the user has permission to view it
        if($item) {
            $this->authorize('view', $item);

            return response()->json($this->getStatus($item));
        }
        else {
            $this->authorize('viewAny', $className);

            return response()->json($this->getStatuses($className, $department));
        }
    }

    protected function getStatus($item) {
        return [];
    }

    protected function getStatuses($className, $department = null) {
        return [];
    }
}
