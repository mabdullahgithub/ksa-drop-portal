<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['warehouses' => $warehouses]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'area' => 'nullable|string|max:255',
            'address' => 'required|string|max:500',
            'post_code' => 'nullable|string|max:20',
            'country_code' => 'string|max:5',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($validated);

        return response()->json(['warehouse' => $warehouse, 'message' => 'Warehouse created successfully.'], 201);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'area' => 'nullable|string|max:255',
            'address' => 'required|string|max:500',
            'post_code' => 'nullable|string|max:20',
            'country_code' => 'string|max:5',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Warehouse::where('id', '!=', $warehouse->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $warehouse->update($validated);

        return response()->json(['warehouse' => $warehouse, 'message' => 'Warehouse updated successfully.']);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return response()->json(['message' => 'Warehouse deleted successfully.']);
    }
}
