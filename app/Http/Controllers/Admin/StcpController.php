<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Controller;

class StcpController extends Controller
{
    public function forceSync()
    {
        try {
            Artisan::call('app:sync-stcp-lines', ['--force' => true]);

            return redirect()->back()->with('success', 'Sync successfully!');
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
