<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends Controller
{
    /**
     * GET: Retrieve a list of all equipment.
     * URL: /api/equipment
     */
    public function index()
    {
        // Retrieve all equipment records
        $equipment = Equipment::all();
        // Return them as a JSON response
        return response()->json($equipment, 200);
    }

    /**
     * POST: Store a newly created equipment record.
     * URL: /api/equipment
     */
    public function store(Request $request)
    {
        // 1. Define Validation Rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            // Rule for image: optional, is a file, max size 2MB, must be an image type (jpeg, png, bmp, gif, svg)
            'image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // 422 Unprocessable Entity
        }

        // 2. Handle Image Upload (if present)
        $data = $request->except('image_file');
        $data['image'] = null; // Default to null

        if ($request->hasFile('image_file')) {
            // Store the file in the 'public/equipment_images' folder
            // and get the public path (e.g., equipment_images/kdgs83.jpg)
            $path = $request->file('image_file')->store('equipment_images', 'public');
            $data['image'] = Storage::url($path); // Get the URL path for the database
        }

        // 3. Create the Equipment record
        $equipment = Equipment::create($data);

        return response()->json([
            'message' => 'Equipment created successfully',
            'data' => $equipment
        ], 201); // 201 Created
    }

    /**
     * GET: Display the specified equipment record.
     * URL: /api/equipment/{equipment}
     */
    public function show(Equipment $equipment)
    {
        // Route model binding handles the 404 check automatically
        return response()->json($equipment, 200);
    }

    /**
     * PUT/PATCH: Update the specified equipment record.
     * URL: /api/equipment/{equipment}
     */
    public function update(Request $request, Equipment $equipment)
    {
        // 1. Define Validation Rules
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'description' => 'sometimes|required|string',
            'image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Handle Image Update/Removal
        $data = $request->except('image_file');
        
        if ($request->hasFile('image_file')) {
            // Delete old image if it exists
            if ($equipment->image) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $equipment->image));
            }
            // Store new image
            $path = $request->file('image_file')->store('equipment_images', 'public');
            $data['image'] = Storage::url($path);
        } elseif ($request->input('remove_image')) {
            // If frontend explicitly asks to remove the image
            if ($equipment->image) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $equipment->image));
            }
            $data['image'] = null;
        }

        // 3. Update the Equipment record
        $equipment->update($data);

        return response()->json([
            'message' => 'Equipment updated successfully',
            'data' => $equipment
        ], 200);
    }

    /**
     * DELETE: Remove the specified equipment record.
     * URL: /api/equipment/{equipment}
     */
    public function destroy(Equipment $equipment)
    {
        // Delete the associated image file from storage
        if ($equipment->image) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $equipment->image));
        }
        
        $equipment->delete();

        return response()->json(['message' => 'Equipment deleted successfully'], 204); // 204 No Content
    }
}