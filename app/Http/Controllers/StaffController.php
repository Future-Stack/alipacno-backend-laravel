<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /**
     * Display a listing of staff members.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['branch', 'role']);

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by shift
        if ($request->filled('shift')) {
            $query->where('shift', $request->shift);
        }

        // Search by employee ID, name, email or phone
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        // Get all staff
        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->get(),
            ]);
        }

        // Paginated staff
        return response()->json(
            $query->paginate(
                $request->input('per_page', 15)
            )
        );
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'string',
                'max:255',
                'unique:staff,employee_id',
            ],

            'branch_id' => [
                'required',
                'exists:branches,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:50',
            ],

            'role_id' => [
                'nullable',
                'exists:roles,id',
            ],

            'shift' => [
                'nullable',
                'string',
                'max:255',
            ],

            'time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'salary' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'commission' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'hire_date' => [
                'nullable',
                'date',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'on_leave',
                    'on_break',
                    'off_duty',
                    'absent',
                ]),
            ],
        ]);

        // Validate time in / time out
        $this->validateAttendanceTime(
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null
        );

        // Upload staff image
        if ($request->hasFile('image')) {
            $validated['image'] = $request
                ->file('image')
                ->store('staff', 'public');
        }

        $staff = Staff::create($validated);

        $staff->load(['branch', 'role']);

        return response()->json([
            'message' => 'Staff created successfully.',
            'data' => $staff,
        ], 201);
    }

    /**
     * Display the specified staff member.
     */
    public function show(Staff $staff)
    {
        $staff->load([
            'branch',
            'role',
            'attendances',
        ]);

        return response()->json([
            'data' => $staff,
        ]);
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'employee_id' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('staff', 'employee_id')
                    ->ignore($staff->id),
            ],

            'branch_id' => [
                'sometimes',
                'required',
                'exists:branches,id',
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],

            'role_id' => [
                'nullable',
                'exists:roles,id',
            ],

            'shift' => [
                'nullable',
                'string',
                'max:255',
            ],

            'time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'salary' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'commission' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'hire_date' => [
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'on_leave',
                    'on_break',
                    'off_duty',
                    'absent',
                ]),
            ],
        ]);

        // Get final time values
        $timeIn = $validated['time_in'] ?? $staff->time_in;
        $timeOut = $validated['time_out'] ?? $staff->time_out;

        // Validate time in / time out
        $this->validateAttendanceTime(
            $timeIn,
            $timeOut
        );

        // Replace old image if a new image is uploaded
        if ($request->hasFile('image')) {
            if ($staff->image) {
                Storage::disk('public')->delete($staff->image);
            }

            $validated['image'] = $request
                ->file('image')
                ->store('staff', 'public');
        }

        $staff->update($validated);

        $staff->refresh();
        $staff->load(['branch', 'role']);

        return response()->json([
            'message' => 'Staff updated successfully.',
            'data' => $staff,
        ]);
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(Staff $staff)
    {
        // Soft delete
        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully.',
        ]);
    }

    /**
     * Validate attendance time.
     *
     * Normal shift:
     * 08:00 -> 17:00
     *
     * Overnight shift:
     * 20:00 -> 04:00
     *
     * Same time is not allowed.
     */
    private function validateAttendanceTime(
        ?string $timeIn,
        ?string $timeOut
    ): void {
        if (!$timeIn || !$timeOut) {
            return;
        }

        if ($timeIn === $timeOut) {
            abort(response()->json([
                'message' => 'Time out must be different from time in.',
                'errors' => [
                    'time_out' => [
                        'Time out must be different from time in.',
                    ],
                ],
            ], 422));
        }
    }
}