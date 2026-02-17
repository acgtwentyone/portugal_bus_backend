<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

class BusLineController extends Controller
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
