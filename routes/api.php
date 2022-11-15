<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Route::get('moov', function() {
//     $url = "https://196.28.245.227/tlcfzc_gw/api/gateway/3pp/transaction/process?command-id=transfer-api-transaction";
//     $curl = curl_init($url);
//         curl_setopt($curl, CURLOPT_URL, $url);
//         curl_setopt($curl, CURLOPT_POST, true);
//         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
//         $headers = array(
//           "Authorization: Basic U01JTDowN1Npc2ZhejAwNmo=",
//           "command-id:   transfer-api-transaction",
//         );
//         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//         $resp = curl_exec($curl);
//         curl_close($curl);
//         var_dump($resp);die();
// });

Route::group(['prefix' => 'v1', 'namespace' => 'Api'], function() {
    // Route::get('app', 'Coupon\IndexController@index');
	// Customer Auth Start Created By MYTECH MAESTRO
	Route::group(['prefix' => 'customer'], function() {
        Route::get('converter/{amount}', 'Order\IndexController@converter');
        Route::get('orange/{order_id}', 'Order\IndexController@orangeMoney');
        Route::get('orange-wallet', 'Order\IndexController@orangeMoneyWallet');
        Route::get('ligdi/{order_id}', 'Order\IndexController@ligdiCash');
	    Route::get('ligdi-callback', 'Order\IndexController@ligdiCallback');
        Route::get('ligdi-wallet/{customer_id}/{amount}', 'Order\IndexController@ligdiCashWallet');
	    Route::get('ligdi-wallet-callback', 'Order\IndexController@ligdiCallbackWallet');
	    Route::get('payment-success', function() {
	        return 'Success';
	    })->name('callback-success');
	    
	    Route::get('payment-failure', function() {
	        return 'Failed';
	    })->name('callback-failure');

        Route::post('login', 'AuthController@customer_login');
        Route::post('register', 'AuthController@register');
        Route::post('verify-otp', 'AuthController@verifyOtp');
        Route::post('resend-otp', 'AuthController@resendOtp');
        Route::post('forgot-password', 'AuthController@forgetPassword');
        Route::post('reset-password', 'AuthController@resetPassword');

        Route::group(['prefix' => 'social'], function() 
        {
            Route::post('/google', 'AuthController@googleSignIn');
            Route::post('/facebook', 'AuthController@facebookSignIn');
            Route::post('/apple', 'AuthController@appleSignIn');
        });

        Route::group(['middleware' => 'auth:api'], function() 
        {
            Route::get('/getProfile', 'AuthController@getProfile');
            Route::get('/user-profile', 'AuthController@userProfile');
            Route::post('change-password', 'AuthController@changePassword');
            Route::post('/update-profile', 'AuthController@UpdateProfile');
            Route::post('saveUserDeviceToken', 'AuthController@saveUserDeviceToken');
            Route::get('sign-out', 'AuthController@signOut');
        
        Route::group(['namespace' => 'Customer'], function()
        {
            Route::get('address', 'AddressController@index');
            Route::post('address/add', 'AddressController@create');
            Route::post('address/update', 'AddressController@update');
            Route::get('order-history', 'IndexController@orderHistory');
            Route::get('desserts', 'IndexController@desserts');
            Route::get('order-details', 'IndexController@orderDetails');
            Route::get('home', 'IndexController@home');
            Route::post('delivery-cost', 'IndexController@getDeliveryCost');
        });        
            Route::post('addRemove', 'AuthController@AddRemove');  
      });
    });

    Route::group(['prefix' => 'rider'], function() {

        Route::post('login', 'AuthController@rider_login');
        Route::post('register', 'AuthController@riderSignup');
        Route::post('verify-otp', 'AuthController@verifyOtp');
        Route::post('resend-otp', 'AuthController@resendOtp');
        Route::get('forgot-password', 'AuthController@forgetPassword');
        Route::post('reset-password', 'AuthController@resetPassword');

        Route::group(['prefix' => 'social'], function() 
        {
            Route::post('/google', 'AuthController@googleSignIn');
            Route::post('/facebook', 'AuthController@facebookSignIn');
            Route::post('/apple', 'AuthController@appleSignIn');
        });

        Route::group(['middleware' => 'auth:api'], function() 
        {
            Route::get('/getProfile', 'AuthController@riderProfile');
            Route::get('/user-profile', 'AuthController@userProfile');
            Route::post('change-password', 'AuthController@changePassword');
            Route::post('/update-profile', 'AuthController@UpdateProfile');
            Route::post('saveUserDeviceToken', 'AuthController@saveUserDeviceToken');
            Route::post('updateCoordinate', 'AuthController@updateCoordinate');
            Route::get('getCoordinate', 'AuthController@getCoordinate'); 
            Route::get('sign-out', 'AuthController@signOut');

        Route::group(['namespace' => 'Rider'], function()
        {
            Route::get('order-history', 'IndexController@orderHistory');
            Route::post('bank-info', 'IndexController@bankInfo');
            Route::post('add-vehicle', 'IndexController@addVehicle');
            Route::get('balance-page', 'IndexController@balancePage');
            Route::get('cash-OrderHistory', 'IndexController@cashOrders');
            Route::get('card-OrderHistory', 'IndexController@cardOrders');
            Route::get('new-order', 'IndexController@newOrder');
            Route::get('countcashorders', 'IndexController@countCashOrders'); 
            Route::post('update-locations', 'IndexController@updateRiderLocation'); 
            Route::get('get-wallet', 'IndexController@getWalletBalance'); 
            Route::post('withdraw', 'IndexController@getWithdraw'); 
        });
      });
    });

    Route::group(['prefix' => 'vendor'], function() {

        Route::post('login', 'AuthController@login');
        Route::post('register', 'AuthController@register');
        Route::post('verify-otp', 'AuthController@verifyOtp');
        Route::post('resend-otp', 'AuthController@resendOtp');
        Route::get('forgot-password', 'AuthController@forgetPassword');
        Route::post('reset-password', 'AuthController@resetPassword');

        Route::group(['prefix' => 'social'], function() 
        {
            Route::post('/google', 'AuthController@googleSignIn');
            Route::post('/facebook', 'AuthController@facebookSignIn');
            Route::post('/apple', 'AuthController@appleSignIn');
        });

        Route::group(['middleware' => 'auth:api'], function() 
        {
            Route::get('/getProfile', 'AuthController@getProfile');
            Route::get('/getRestProfile', 'AuthController@getRestProfile');
            Route::post('change-password', 'AuthController@changePassword');
            Route::post('/update-profile', 'AuthController@UpdateProfile');
            Route::post('saveUserDeviceToken', 'AuthController@saveUserDeviceToken');
            Route::get('sign-out', 'AuthController@signOut');
            Route::group(['namespace' => 'Vendor'], function()
            {
                Route::get('order-history', 'IndexController@orderHistory');
                Route::get('profile', 'IndexController@profile');
                Route::get('all', 'IndexController@getAllRestaurants');
            });      
        });
    });

    Route::group(['prefix' => 'coupons', 'namespace' => 'Coupon'], function()
    {
        Route::get('/', 'IndexController@index');
        Route::post('/store', 'IndexController@store');
        Route::post('/update', 'IndexController@update');
        Route::post('/delete', 'IndexController@destroy');
        Route::get('/verify-coupon', 'IndexController@verifyPromo');
    });

    Route::group(['prefix' => 'shopper'], function() {

        Route::post('login', 'AuthController@shopper_login');
        Route::post('register', 'AuthController@register');
        Route::post('verify-otp', 'AuthController@verifyOtp');
        Route::post('resend-otp', 'AuthController@resendOtp');
        Route::post('forgot-password', 'AuthController@forgetPassword');
        Route::post('reset-password', 'AuthController@resetPassword');

        Route::group(['prefix' => 'social'], function() 
        {
            Route::post('/google', 'AuthController@googleSignIn');
            Route::post('/facebook', 'AuthController@facebookSignIn');
            Route::post('/apple', 'AuthController@appleSignIn');
        });

        Route::group(['middleware' => 'auth:api'], function() 
        {
            Route::get('/getProfile', 'AuthController@getShopperProfile');
            Route::post('change-password', 'AuthController@changePassword');
            Route::post('/update-profile', 'AuthController@UpdateProfile');
            Route::post('saveUserDeviceToken', 'AuthController@saveUserDeviceToken');
            Route::get('sign-out', 'AuthController@signOut');
            Route::group(['namespace' => 'Shopper'], function()
            {
                Route::get('order-history', 'IndexController@orderHistory');
            });            
        });
    }); 

    Route::group(['prefix' => 'grocery'], function() {
        Route::post('login', 'AuthController@login');
        Route::post('register', 'AuthController@register');
        Route::post('verify-otp', 'AuthController@verifyOtp');
        Route::post('resend-otp', 'AuthController@resendOtp');
        Route::get('forgot-password', 'AuthController@forgetPassword');
        Route::post('reset-password', 'AuthController@resetPassword');

        Route::group(['prefix' => 'social'], function() 
        {
            Route::post('/google', 'AuthController@googleSignIn');
            Route::post('/facebook', 'AuthController@facebookSignIn');
            Route::post('/apple', 'AuthController@appleSignIn');
        });

        Route::group(['middleware' => 'auth:api'], function() {
            // Route::get('/getProfile', 'AuthController@getProfile');
            Route::post('change-password', 'AuthController@changePassword');
            Route::post('/update-profile', 'AuthController@UpdateProfile');
            Route::post('saveUserDeviceToken', 'AuthController@saveUserDeviceToken');
            Route::get('sign-out', 'AuthController@signOut');
            Route::group(['namespace' => 'Grocery'], function()
            {
                Route::get('/getProfile', 'IndexController@Profile');
                Route::get('order-history', 'IndexController@orderHistory');
            });            
        });
    }); 

    Route::group(['middleware' => 'auth:api'], function(){

        Route::group(['prefix' => 'items', 'namespace' => 'Items', ], function(){
            Route::get('/', 'IndexController@index');
            Route::post('/create', 'IndexController@store');
            Route::post('/update', 'IndexController@update');
            Route::get('/view', 'IndexController@singleItem');
            Route::post('/delete', 'IndexController@destroy');
            Route::post('/search-filter', 'IndexController@searchFilter');
            Route::get('/latest-offers', 'IndexController@latestOffers');
            Route::get('/getByCategory', 'IndexController@getItemsbyCategory');
            Route::get('/getByUser', 'IndexController@getItemsbyUser');
            Route::get('/popular', 'IndexController@popularItems');
            Route::post('/searchFood', 'IndexController@searchItem');
            Route::get('/getRestaurantCategories', 'IndexController@getRestaurantCategories');
            Route::get('/getGroceryCategories', 'IndexController@getGroceryCategories');
            Route::get('/getRecentItems', 'IndexController@getRecentItems');
            Route::get('/getAllDiscounted', 'IndexController@getAllDiscounted');
            Route::get('/getItemsByExpertise', 'IndexController@getItemsByExpertise');
            Route::get('/getAllExpertise', 'IndexController@getAllExpertise');
            Route::get('/get-filters', 'IndexController@getFilters');
            Route::get('/getAllCusineTypes', 'IndexController@getAllCusineTypes');
            Route::get('/getPriceWithVat', 'IndexController@getPriceWithVat');
        });        

        Route::group(['prefix' => 'orders', 'namespace' => 'Order'], function() {
            Route::post('place', 'IndexController@store');
            Route::post('accept', 'IndexController@acceptOrder');
            Route::post('reject', 'IndexController@rejectOrder');
            Route::get('past-orders', 'IndexController@pastOrders');
            Route::get('new-orders', 'IndexController@newOrders');
            Route::get('getitems', 'IndexController@getAllOrderItems');
            Route::get('view-order', 'IndexController@singleOrder');        
            Route::post('complete-order', 'IndexController@completeOrder');        
            Route::post('ready-order', 'IndexController@readyOrder');        
            Route::get('getOrderView', 'IndexController@getOrderView');        
            Route::post('assign', 'IndexController@assignOrder');
            Route::get('getOrderDetails', 'IndexController@orderDetailToRider');                   
            Route::post('pickup-order', 'IndexController@riderPickOrder');                   
            Route::get('get-paymentDetails', 'IndexController@getOrderPaymentDetails');  
            Route::post('payment-proceeding', 'IndexController@paymentProceed');
            Route::get('getItemByQrCode', 'IndexController@getItemByQrCode'); 
        });

        Route::group(['namespace' => 'Notification', 'prefix' => 'notification'], function() {
            Route::get('/', 'IndexController@index'); 
            Route::post('/delete', 'IndexController@destroy'); 
        });

        Route::group(['namespace' => 'Card', 'prefix' => 'cards'], function() {
            Route::get('/', 'IndexController@index');   
            Route::post('/add', 'IndexController@create');
            Route::post('/update', 'IndexController@updateStatus'); 
        });

        Route::group(['prefix' => 'saveDeviceToken'], function() {
            Route::post('/', 'AuthController@saveDeviceToken');              
        });  
        
        Route::group(['namespace' => 'Rating', 'prefix' => 'rating'], function() {
            Route::post('/add-rating', 'IndexController@addRatings');              
        });
        
        Route::group(['prefix' => 'deactivate', ], function(){
            Route::post('/', 'AuthController@deactivateAccount');
        });
    }); 

    Route::group(['prefix' => 'contents', 'namespace' => 'Content'], function() {
            Route::get('/', 'IndexController@index');        
    });  
}); 