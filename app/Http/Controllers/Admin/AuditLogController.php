<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $audits = $this->filteredQuery($request)
            ->with('user')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.audits.index', [
            'audits' => $audits,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['user_id', 'event', 'model', 'from', 'to']),
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->validate([
            'format' => ['required', 'in:csv,pdf'],
        ])['format'];

        $audits = $this->filteredQuery($request)->with('user')->latest()->limit(500)->get();

        if ($format === 'pdf') {
            return Pdf::loadView('admin.audits.pdf', ['audits' => $audits])
                ->download('audit-trail.pdf');
        }

        return new StreamedResponse(function () use ($audits): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Event', 'Model', 'Record ID', 'Old Values', 'New Values', 'URL', 'Method', 'Created At']);

            foreach ($audits as $audit) {
                fputcsv($handle, [
                    $audit->id,
                    $audit->user?->name,
                    $audit->event,
                    class_basename((string) $audit->auditable_type),
                    $audit->auditable_id,
                    json_encode($audit->old_values),
                    json_encode($audit->new_values),
                    $audit->url,
                    $audit->method,
                    optional($audit->created_at)->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-trail.csv"',
        ]);
    }

    protected function filteredQuery(Request $request)
    {
        return Audit::query()
            ->when($request->integer('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->query('event'), fn ($query, $event) => $query->where('event', $event))
            ->when($request->query('model'), fn ($query, $model) => $query->where('auditable_type', 'like', '%'.$model))
            ->when($request->query('from'), fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->query('to'), fn ($query, $value) => $query->whereDate('created_at', '<=', $value));
    }
}
