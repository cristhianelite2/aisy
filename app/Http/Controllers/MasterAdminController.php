<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterAdminController extends Controller
{
    public function index(Request $request): View
    {
        if (!$request->user()?->is_admin) {
            abort(403, 'Acceso exclusivo para la cuenta maestra.');
        }

        $businesses = Business::withCount(['operations', 'catalogItems', 'activities'])->latest()->get();
        $recentActivities = BusinessActivity::with('business')->latest()->limit(30)->get();
        $odooUrl = config('aisy.odoo.url', 'http://127.0.0.1:8069') . '/web/login';

        return view('master.index', compact('businesses', 'recentActivities', 'odooUrl'));
    }
}
