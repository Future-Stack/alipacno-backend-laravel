<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faq;
use Exception;

class FaqController extends Controller
{
    // 🔵 1. List all FAQs
    public function index()
    {
        try {
            $faqs = Faq::where('status', 1)->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $faqs
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 🟢 2. Store FAQ
    public function store(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:255',
                'answers'  => 'required|string',
            ]);

            $faq = Faq::create([
                'question' => $request->question,
                'answers'  => $request->answers,
                'status'   => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FAQ created successfully',
                'data' => $faq
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 🟡 3. Show single FAQ
    public function show($id)
    {
        try {
            $faq = Faq::find($id);

            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $faq
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 🟠 4. Update FAQ
    public function update(Request $request, $id)
    {
        try {
            $faq = Faq::find($id);

            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $faq->update([
                'question' => $request->question ?? $faq->question,
                'answers'  => $request->answers ?? $faq->answers,
                'status'   => $request->status ?? $faq->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FAQ updated successfully',
                'data' => $faq
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 🔴 5. Delete FAQ (Soft Delete)
    public function destroy($id)
    {
        try {
            $faq = Faq::find($id);

            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $faq->delete();

            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}