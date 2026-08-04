<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    /**
     * Display a listing of recipes.
     */
    public function index(Request $request)
    {
        $query = Recipe::with(['menuItem', 'branch', 'ingredients.inventoryItem']);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('menu_item_id')) {
            $query->forMenuItem($request->menu_item_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'name', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created recipe in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'menu_item_id' => 'required|exists:menu_items,id',
            'branch_id' => 'required|exists:branches,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.inventory_item_id' => 'required_with:ingredients|exists:inventory_items,id',
            'ingredients.*.quantity' => 'required_with:ingredients|numeric|min:0.01',
            'ingredients.*.unit' => 'required_with:ingredients|string|max:50',
        ]);

        $recipe = DB::transaction(function () use ($validated) {
            $recipe = Recipe::create([
                'name' => $validated['name'],
                'menu_item_id' => $validated['menu_item_id'],
                'branch_id' => $validated['branch_id'],
            ]);

            if (!empty($validated['ingredients'])) {
                foreach ($validated['ingredients'] as $ing) {
                    $recipe->ingredients()->create([
                        'inventory_item_id' => $ing['inventory_item_id'],
                        'quantity' => $ing['quantity'],
                        'unit' => $ing['unit'],
                    ]);
                }
            }

            return $recipe;
        });

        return response()->json($recipe->load(['menuItem', 'branch', 'ingredients.inventoryItem']), 201);
    }

    /**
     * Display the specified recipe.
     */
    public function show(Recipe $recipe)
    {
        return response()->json($recipe->load(['menuItem', 'branch', 'ingredients.inventoryItem']));
    }

    /**
     * Update the specified recipe in storage.
     */
    public function update(Request $request, Recipe $recipe)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'menu_item_id' => 'sometimes|required|exists:menu_items,id',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.inventory_item_id' => 'required_with:ingredients|exists:inventory_items,id',
            'ingredients.*.quantity' => 'required_with:ingredients|numeric|min:0.01',
            'ingredients.*.unit' => 'required_with:ingredients|string|max:50',
        ]);

        DB::transaction(function () use ($request, $validated, $recipe) {
            $recipe->update(array_intersect_key($validated, array_flip(['name', 'menu_item_id', 'branch_id'])));

            if ($request->has('ingredients')) {
                $recipe->ingredients()->delete();
                if (!empty($validated['ingredients'])) {
                    foreach ($validated['ingredients'] as $ing) {
                        $recipe->ingredients()->create([
                            'inventory_item_id' => $ing['inventory_item_id'],
                            'quantity' => $ing['quantity'],
                            'unit' => $ing['unit'],
                        ]);
                    }
                }
            }
        });

        return response()->json($recipe->load(['menuItem', 'branch', 'ingredients.inventoryItem']));
    }

    /**
     * Remove the specified recipe from storage.
     */
    public function destroy(Recipe $recipe)
    {
        DB::transaction(function () use ($recipe) {
            $recipe->ingredients()->delete();
            $recipe->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Add a single ingredient to an existing recipe.
     */
    public function addIngredient(Request $request, Recipe $recipe)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
        ]);

        $ingredient = $recipe->ingredients()->updateOrCreate(
            ['inventory_item_id' => $validated['inventory_item_id']],
            ['quantity' => $validated['quantity'], 'unit' => $validated['unit']]
        );

        return response()->json($ingredient->load('inventoryItem'), 201);
    }

    /**
     * Remove a single ingredient from a recipe.
     */
    public function removeIngredient(Recipe $recipe, RecipeIngredient $ingredient)
    {
        if ($ingredient->recipe_id !== $recipe->id) {
            return response()->json(['message' => 'Ingredient does not belong to this recipe.'], 422);
        }

        $ingredient->delete();

        return response()->json(null, 204);
    }
}