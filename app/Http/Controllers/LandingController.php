<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LandingController extends Controller
{
    private function setLocale(Request $request)
    {
        $lang = $request->query('lang');
        if (in_array($lang, ['en', 'pt', 'es'])) {
            app()->setLocale($lang);
        }
    }

    public function privacy(Request $request)
    {
        $this->setLocale($request);
        return view('legal.privacy');
    }

    public function terms(Request $request)
    {
        $this->setLocale($request);
        return view('legal.terms');
    }

    public function privacyPolicy(Request $request)
    {
        $this->setLocale($request);
        return view('legal.privacy_policy');
    }
}
