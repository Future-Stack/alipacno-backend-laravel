<?php

namespace App\Exports;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StaffExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    ShouldAutoSize
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Query staff for export.
     */
    public function query(): Builder
    {
        $query = Staff::query()
            ->with(['branch', 'role']);

        // Filter by branch
        if ($this->request->filled('branch_id')) {
            $query->where(
                'branch_id',
                $this->request->branch_id
            );
        }

        // Filter by role
        if ($this->request->filled('role_id')) {
            $query->where(
                'role_id',
                $this->request->role_id
            );
        }

        // Filter by status
        if ($this->request->filled('status')) {
            $query->where(
                'status',
                $this->request->status
            );
        }

        // Filter by shift
        if ($this->request->filled('shift')) {
            $query->where(
                'shift',
                $this->request->shift
            );
        }

        // Search by employee ID, name, email or phone
        if ($this->request->filled('search')) {
            $search = $this->request->search;

            $query->where(function ($q) use ($search) {
                $q->where(
                    'employee_id',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'name',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'email',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'phone',
                    'like',
                    "%{$search}%"
                );
            });
        }

        return $query->latest();
    }

    /**
     * Excel headings.
     */
    public function headings(): array
    {
        return [
            'Employee ID',
            'Name',
            'Email',
            'Phone',
            'Branch',
            'Role',
            'Shift',
            'Time In',
            'Time Out',
            'Salary',
            'Commission (%)',
            'Hire Date',
            'Status',
        ];
    }

    /**
     * Map staff data to Excel row.
     */
    public function map($staff): array
    {
        return [
            $staff->employee_id,
            $staff->name,
            $staff->email ?? '',
            $staff->phone,
            $staff->branch?->name ?? '',
            $staff->role?->name ?? '',
            $staff->shift ?? '',
            $staff->time_in ?? '',
            $staff->time_out ?? '',
            $staff->salary ?? '',
            $staff->commission ?? '',
            $staff->hire_date ?? '',
            $staff->status,
        ];
    }
}