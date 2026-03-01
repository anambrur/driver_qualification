<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function plans()
    {
        $plans = Plan::orderBy('id', 'asc')->where('is_active', 1)->get();
        return view('admin.plans.index', compact('plans'));
    }
}
