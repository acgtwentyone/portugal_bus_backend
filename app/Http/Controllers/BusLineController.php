<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusLineRequest;
use App\Http\Requests\UpdateBusLineRequest;
use App\Models\BusLine;
use Illuminate\Support\Facades\Artisan;

class BusLineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // TODO: List all bus lines
    }

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
