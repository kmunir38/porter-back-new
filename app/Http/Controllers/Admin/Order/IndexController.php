<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Order;
use Auth;

class IndexController extends Controller
{
    public function index()
    {
    	$records = Order::with('OrderItem')->latest()->get();
    	return view('admin.orders.index', compact('records'));
    }

    public function show($id)
    {
    	$record = Order::with('ItemsOrder')->find($id);
    	return view('admin.orders.details', compact('record'));
    }
}
