<?php

namespace App\Http\Controllers\Reports;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PersonnelMasterListController extends Controller
{
    /**
     * Printable personnel master list: every active employee with the office,
     * position and employment status taken from their active primary
     * appointment.
     */
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Personnel::class);

        $officeId = $request->integer('office') ?: null;
        $status = EmploymentStatus::tryFrom((string) $request->string('status'));

        $personnel = Personnel::query()
            ->with(['activePrimaryAppointment.office', 'activePrimaryAppointment.position'])
            ->when($officeId, fn ($query) => $query->whereHas(
                'activePrimaryAppointment',
                fn ($query) => $query->where('office_id', $officeId),
            ))
            ->when($status, fn ($query) => $query->where('employment_status', $status))
            ->when(! $request->boolean('include_resigned'), fn ($query) => $query->active())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('reports.personnel-master-list', [
            'personnel' => $personnel,
            'office' => $officeId ? Office::find($officeId) : null,
            'status' => $status,
            'includesResigned' => $request->boolean('include_resigned'),
            'generatedAt' => now(),
        ]);
    }
}
