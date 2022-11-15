<?php
namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use Carbon\Carbon;
use DB;
use App\Order;
class DashboardController extends Controller
{
	public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    { 
    	$data['monthly'] = Order::where( 'created_at', '>', Carbon::now()->subDays(30))->sum('grand_total');
    	$data['weekly'] = Order::where( 'created_at', '>', Carbon::now()->subDays(7))->sum('grand_total');
    	$data['daily'] = Order::where( 'created_at', '>', Carbon::now()->subDays(1))->sum('grand_total');
    	$data['total_customers'] = Order::count( 'customer_id');
                                
    	return view ('admin.dashboard.index', $data);
    }
}
        
