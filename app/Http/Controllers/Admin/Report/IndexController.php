<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Order;
use App\User;
use Auth;

class IndexController extends Controller
{
    public function index()
    {
    	$vendors = User::role(['restaurant', 'grocery', 'shopper'])->latest()->pluck('name', 'id');
    	
    	$records = Order::with('OrderItem')->latest()->get();
    	return view('admin.reports.index', compact('records', 'vendors'));
    }

    public function filter(Request $request)
    {
    	$vendors = User::role(['restaurant', 'grocery', 'shopper'])->pluck('name', 'id');
    	$records = Order::query();

    	if($request->vendor){
    		$records = Order::whereHas('grocery', function($q) use($request){

    			$q->where('id',  $request->vendor);

    		})->orWhereHas('restaurant', function($q) use($request){

    			$q->where('id',  $request->vendor);

    		})->orWhereHas('shopper', function($q) use($request){

    			$q->where('id',  $request->vendor);

    		});
    	}    	

    	if($request->status){
    		$records = Order::where('order_status', $request->status);    		
    	}

    	if($request->to_date && $request->from_date){
    		$records = Order::whereBetween('created_at', [$request->to_date, $request->from_date]);    		
    	}

    	$records = $records->latest()->get();
    	// return $records;
    	return view('admin.reports.filter', compact('records', 'vendors'));
    }
}
