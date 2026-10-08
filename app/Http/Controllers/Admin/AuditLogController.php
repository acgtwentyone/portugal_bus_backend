<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('admin.actions.index', [
            'actions' => AdminAction::latest()->paginate(50),
        ]);
    }
}
