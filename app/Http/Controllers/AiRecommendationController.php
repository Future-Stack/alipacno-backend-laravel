<?php

namespace App\Http\Controllers;

use App\Models\AiRecommendation;
use Illuminate\Http\Request;

class AiRecommendationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(AiRecommendation::paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Add validation rules as needed
        ]);

        $record = AiRecommendation::create($request->all());

        return response()->json($record, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AiRecommendation $model)
    {
        return response()->json($model);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AiRecommendation $model)
    {
        $model->update($request->all());

        return response()->json($model);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AiRecommendation $model)
    {
        $model->delete();

        return response()->json(null, 204);
    }
}