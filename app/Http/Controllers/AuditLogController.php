<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user');

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('action', 'like', "%{$s}%")
                    ->orWhere('module', 'like', "%{$s}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$s}%"));
            });
        }

        $logs = $query->latest()->paginate(20)->withQueryString();
        $modules = AuditLog::select('module')->distinct()->pluck('module');

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'modules' => $modules,
            'filters' => $request->only(['module', 'action', 'search']),
        ]);
    }
}
