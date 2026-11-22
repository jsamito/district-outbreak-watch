<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $full = $request->user()->can('view audit log');
        $since = now()->subDays(30);
        $summary = AuditLog::where('created_at', '>=', $since)->selectRaw('category, action, COUNT(*) AS n')->groupBy('category', 'action')->orderByDesc('n')->get();

        $logs = null;
        if ($full) {
            $q = AuditLog::with('user')->latest('id');
            foreach (['category', 'action'] as $f) {
                if ($request->filled($f)) {
                    $q->where($f, $request->input($f));
                }
            }
            if ($request->filled('user')) {
                $q->where('user_id', $request->integer('user'));
            }
            $logs = $q->paginate(50)->withQueryString();
        }

        return view('admin.audit', ['title' => 'Audit log', 'full' => $full, 'summary' => $summary, 'logs' => $logs,
            'categories' => AuditLog::select('category')->distinct()->pluck('category'), 'f' => $request->all(),
            'topUsers' => AuditLog::where('created_at', '>=', $since)->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->orderByDesc('n')->limit(8)->with('user')->get()]);
    }
}
