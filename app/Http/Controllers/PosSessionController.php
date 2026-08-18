<?php

namespace App\Http\Controllers;

use App\Models\PosSession;
use Illuminate\Http\Request;

class PosSessionController extends Controller
{
    /**
     * Display a listing of POS sessions.
     */
    public function index(Request $request)
    {
        $query = PosSession::with(['user', 'branch']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->latest();

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Open a new POS session.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'opening_cash' => 'required|numeric|min:0',
        ]);

        $user = $request->user();

        PosSession::where(function($q) use ($user) {
                $q->where('staff_id', $user?->id)
                ->orWhere('user_id', $user?->id);
            })
            ->where('branch_id', $validated['branch_id'])
            ->where('status', 'open')
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $session = PosSession::create([
            'branch_id'    => $validated['branch_id'],
            'user_id'      => $user?->id,
            'staff_id'     => $user?->id,
            'opening_cash' => $validated['opening_cash'],
            'total_sales'  => 0,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        return response()->json($session->load(['staff', 'branch']), 201);
    }

    /**
     * Display the specified POS session.
     */
    public function show(PosSession $posSession)
    {
        return response()->json($posSession->load(['user', 'branch']));
    }

    /**
     * Close or update POS session.
     */
    public function update(Request $request, PosSession $posSession)
    {
        $validated = $request->validate([
            'closing_balance' => 'nullable|numeric|min:0',
            'cash_sales' => 'nullable|numeric|min:0',
            'card_sales' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:open,closed',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'closed') {
            $validated['closed_at'] = now();
        }

        $posSession->update($validated);

        return response()->json($posSession->load(['user', 'branch']));
    }

    /**
     * Delete POS session.
     */
    public function destroy(PosSession $posSession)
    {
        $posSession->delete();

        return response()->json(null, 204);
    }
}