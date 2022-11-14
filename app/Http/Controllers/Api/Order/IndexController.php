<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Traits\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use App\Http\Resources\Frontend\Order\PastOrder as ViewPastOrder;
use App\Http\Resources\Frontend\Order\ViewOrder as ViewOrderDetail;
use App\Http\Resources\Frontend\Order\GetItems as GetOrderItems;
use App\Http\Resources\Frontend\Order\Getlatest as GetLatestOrder;
use App\Http\Resources\Frontend\Order\AcceptOrderRes as GetAcceptOrderRes;
use App\Http\Resources\Frontend\Order\GetDetails as GetPaymentDetails;
use App\Order;
use App\OrderItem;
use App\CancelOrder;
use App\Item;
use App\User;
use App\Notification;
use App\Setting;
use App\Address;
use App\Wallet;
use App\WalletItem;
use App\WalletTransactions;
use Auth;
use DB;
use Fasodev\Sdk\Config\TransactionData;
use Fasodev\Sdk\Exception\TransactionException;
use Fasodev\Sdk\OrangeMoneyAPI;
use Fasodev\Sdk\PaymentSDK;
use GuzzleHttp\Client;

class IndexController extends Controller
{
    use ApiResponse;
    
    public function store(Request $request)
    { 
        $validator = Validator::make($request->all(), [
            // 'restaurant_id'     => 'exists:users,id|required_if:grocery_id, ==, ""',
            // 'grocery_id'        => 'exists:users,id|required_if:restaurant_id, ==, ""',
            'card_id'           => 'nullable|exists:cards,id',
            'address_id'        => 'required|exists:addresses,id',
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }  

        $vat = Setting::where('name', 'vat')->first();  
        $order = new Order();
        $order->customer_id = \Auth::user()->id;
        $order->restaurant_id = $request->restaurant_id;
        $order->grocery_id = $request->grocery_id;
        $order->shopper_id = $request->shopper_id;
        $order->order_status = 'pending';
        $order->payment_method = $request->payment_method;
        $order->payment_status = 'pending';
        $order->card_id = $request->card_id;
        $order->address_id = $request->address_id;
        $order->discount = $request->discount;
        $order->vat_amount = $request->vat_amount;
        $order->vat = $vat->value;
        $order->delivery_cost = $request->delivery_cost;
        $order->distance = $request->distance;
        $order->note = $request->note;
        $order->tip = $request->tipe;
        $order->grand_total = 00;
        $order->save();
        
        $grand_total = 0;
        $total = 0;

        foreach($request->item as $indexx => $t) {

            $item = Item::where('id', $t)->first();
            if($item && $indexx == 0) {
                $order->restaurant_id = $request->restaurant_id;
                $order->grocery_id = $request->grocery_id;
                $order->save();
            }
            $discounted = $item->price / 100 * $item->discount;
            if($item->end_date < date(strtotime(now()))){
                $itemPrice = $item->price - $discounted;
            } else {
                $itemPrice = $item->price;
            }
            $total += $itemPrice * $t['qty'];
           
            if($order){
                $record             = new OrderItem();
                $record->item_id    = $t['id'];
                $record->order_id   = $order->id;
                $record->price      = $item->price;
                $record->sub_total  = $item->price * $t['qty'];                
                $record->qty        = $t['qty'];
                $record->save();
            }
            
            // $order->order_items()->attach('order_id', 
            //     [
            //       'item_id'      => $t, 
            //       'price'        => $item->price,
            //       'sub_total'    => $item->price * $request->qty
            //     ]);
        }
        $grand_total = $total + $request->delivery_cost + $request->vat_amount + $request->tipe;
        $order->sub_total   = $total;                   
        $order->grand_total = $grand_total;
        $order->save();
        
        if($request->payment_method == 'wallet' && Auth::user()->wallet < $grand_total) {
            $order->payment_status = 'failed';
            $order->order_status = 'cancelled';
            $order->save();
        } elseif($request->payment_method == 'wallet' && Auth::user()->wallet >= $grand_total) {
            $order->payment_status = 'completed';
			$order->save();
			
			$user = Auth::user();
			$user->wallet -= $grand_total;
			$user->save();
			
			if($order->restaurant_id){
                $vendorToken = User::find($order->restaurant_id);   
                $device_token = $vendorToken->device_token;
    
                // dd($shopper_token);
                if ($device_token) {            
                    $notification = new Notification;
                    $message2 = "You Have A New Order";
                    $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'new_order', $order->id);
                }
            }
            if($order->grocery_id){
                $shopperToken = User::where('assigned_grocery', $order->grocery_id)->first();                
                $groceryToken = User::where('id', $order->grocery_id)->first();
                
                $shopper_token = $shopperToken->device_token;
                $grocery_token = $groceryToken->device_token;
    
                if ($grocery_token) {            
                    $notification = new Notification;
                    $message2 = "You Have A New Order";
                    $notification->sendPushNotification($grocery_token, 'Porter', $message2, '', 'new_order', $order->id);
                }
    
                if ($shopper_token) {            
                    $notification = new Notification;
                    $message2 = "You Have A New Order";
                    $notification->sendPushNotification($shopper_token, 'Porter', $message2, '', 'new_order', $order->id);
                }
            }    
        }
        
        // if($request->restaurant_id){
        //     $vendorToken = User::find($order->restaurant_id);   
        //     $device_token = $vendorToken->device_token;

        //     // dd($shopper_token);
        //     if ($device_token) {            
        //         $notification = new Notification;
        //         $message2 = "You Have A New Order";
        //         $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'new_order', $order->id);
        //     }
        // }
        // if($request->grocery_id){
        //     $shopperToken = User::where('assigned_grocery', $order->grocery_id)->first();                
        //     $groceryToken = User::where('id', $order->grocery_id)->first();
            
        //     $shopper_token = $shopperToken->device_token;
        //     $grocery_token = $groceryToken->device_token;

        //     if ($grocery_token) {            
        //         $notification = new Notification;
        //         $message2 = "You Have A New Order";
        //         $notification->sendPushNotification($grocery_token, 'Porter', $message2, '', 'new_order', $order->id);
        //     }

        //     if ($shopper_token) {            
        //         $notification = new Notification;
        //         $message2 = "You Have A New Order";
        //         $notification->sendPushNotification($shopper_token, 'Porter', $message2, '', 'new_order', $order->id);
        //     }
        // }
        
        if ($order instanceof \App\Order) {
            return $this->apiSuccessMessageResponse('Success', $order);
        }
    }
    
    public function converter($amount)
    {
        // $req_url = 'https://api.exchangerate.host/latest?symbols=XOF&base=USD&amount='.$amount;
        // $response_json = file_get_contents($req_url);
        // if(false !== $response_json) {
        //     try {
        //         $response = json_decode($response_json);
        //         if($response->success === true) {
        //             return number_format($response->rates->XOF, 2, '.', '');
        //             // var_dump($response);
        //         }
        //     } catch(Exception $e) {
        //         // Handle JSON parse error...
        //     }
        // }
        return $amount;
    }

    public function sendOrangeResquest($url, $xml)
    {
        try {
            $ch = curl_init();
            // initialisation de l'url*
            if ($ch == false) {
                throw new Exception('échec d\initialisation');
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $str = curl_exec($ch);
            $content = curl_exec($ch);
            //  return value de curl_exec()
            if ($content == false) {
                throw new Exception(curl_error($ch), curl_errno($ch));
            }
            // Close curl handle
            curl_close($ch);
            return $str;
        } catch(Exception $e) {

            $message =trigger_error(sprintf(
                'Curl failed with error #%d: %s',
                $e->getCode(), $e->getMessage()),
                E_USER_ERROR);

            echo 'Echec de transaction!';

        }

    }


    public function orangeMoney($order_id, Request $request) // api a appele
    {
        $order = Order::find($order_id);
        $customerMsisdn = $request->cust_msisdn; // numero du client
        $otp = $request->otp;               // code otp generer
        $amount = $order->grand_total;        // le montant de la transaction
        $merchantMsisdn = '64926823';
        $apiUsername = 'SMILSARL';
        $apiPassword = 'Supreme@123';
        $provider = '101';
        $provider2 = '101';
        $payid = '12';
        $payid2 = '12';
        $reference = '789233';
        $extTxnId = '201500068544';

        $url = "https://testom.orange.bf:9008/payment";
        $xml ='<?xml version="1.0" encoding="UTF-8"?>
            <COMMAND>
                    <TYPE>OMPREQ</TYPE>
                    <customer_msisdn>'.$customerMsisdn.'</customer_msisdn>
                    <merchant_msisdn>'.$merchantMsisdn.'</merchant_msisdn>
                    <api_username>'.$apiUsername.'</api_username>
                    <api_password>'.$apiPassword.'</api_password>
                    <amount>'.$amount.'</amount>
                    <PROVIDER>'.$provider.'</PROVIDER>
                    <PROVIDER2>'.$provider2.'</PROVIDER2>
                    <PAYID>'.$payid.'</PAYID>
                    <PAYID2>'.$payid2.'</PAYID2>
                    <otp>'.$otp.'</otp>
                    <reference_number>'.$reference.'</reference_number>
                    <ext_txn_id>'.$extTxnId.'</ext_txn_id>
            </COMMAND>';
        //dd($url,$xml);
        $result = $this->sendOrangeResquest($url,$xml);
        //recuperation des resultats de la transaction
        if($result)
        {
            $val= '<xml>'.$result.'</xml>';
            $mxml = simplexml_load_string($val);
            $status = (string)$mxml->status;
            $message = (string)$mxml->message;
            //dd($result,$val,$mxml,$status,$message);
            if($status == 200)
            {
                if ($order && $order->payment_method == 'orange_money') {
                    if ($request->success) {
                        $order->payment_status = 'completed';
                        $order->save();
            
                        if ($order->restaurant_id) {
                            $vendorToken = User::find($order->restaurant_id);
                            $device_token = $vendorToken->device_token;
            
                            // dd($shopper_token);
                            if ($device_token) {
                                $notification = new Notification;
                                $message2 = "You Have A New Order";
                                $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'new_order', $order->id);
                            }
                        }
                        if ($order->grocery_id) {
                            $shopperToken = User::where('assigned_grocery', $order->grocery_id)->first();
                            $groceryToken = User::where('id', $order->grocery_id)->first();
            
                            $shopper_token = $shopperToken->device_token;
                            $grocery_token = $groceryToken->device_token;
            
                            if ($grocery_token) {
                                $notification = new Notification;
                                $message2 = "You Have A New Order";
                                $notification->sendPushNotification($grocery_token, 'Porter', $message2, '', 'new_order', $order->id);
                            }
            
                            if ($shopper_token) {
                                $notification = new Notification;
                                $message2 = "You Have A New Order";
                                $notification->sendPushNotification($shopper_token, 'Porter', $message2, '', 'new_order', $order->id);
                            }
                        }
                        return $this->apiSuccessMessageResponse('Payment Successfull');
                    } else {
                        $order->payment_status = 'failed';
                        $order->save();
                        return $this->apiErrorMessageResponse('Payment Failed');
                    }
                } else {
                    return $this->apiErrorMessageResponse('Invalid Order ID');
                }
            }
            return $this->apiErrorMessageResponse('Payment Failed');
        }
        return $this->apiErrorMessageResponse('Payment Failed');

    }

    public function orangeMoneyWallet($order_id, Request $request) // api a appele
    {
        $user = User::find($request->user_id);
        $customerMsisdn = $request->cust_msisdn; // numero du client
        $otp = $request->otp;               // code otp generer
        $amount = $order->grand_total;        // le montant de la transaction
        $merchantMsisdn = '64926823';
        $apiUsername = 'SMILSARL';
        $apiPassword = 'Supreme@123';
        $provider = '101';
        $provider2 = '101';
        $payid = '12';
        $payid2 = '12';
        $reference = '789233';
        $extTxnId = '201500068544';

        $url = "https://testom.orange.bf:9008/payment";
        $xml ='<?xml version="1.0" encoding="UTF-8"?>
            <COMMAND>
                    <TYPE>OMPREQ</TYPE>
                    <customer_msisdn>'.$customerMsisdn.'</customer_msisdn>
                    <merchant_msisdn>'.$merchantMsisdn.'</merchant_msisdn>
                    <api_username>'.$apiUsername.'</api_username>
                    <api_password>'.$apiPassword.'</api_password>
                    <amount>'.$amount.'</amount>
                    <PROVIDER>'.$provider.'</PROVIDER>
                    <PROVIDER2>'.$provider2.'</PROVIDER2>
                    <PAYID>'.$payid.'</PAYID>
                    <PAYID2>'.$payid2.'</PAYID2>
                    <otp>'.$otp.'</otp>
                    <reference_number>'.$reference.'</reference_number>
                    <ext_txn_id>'.$extTxnId.'</ext_txn_id>
            </COMMAND>';
        //dd($url,$xml);
        $result = $this->sendOrangeResquest($url,$xml);
        //recuperation des resultats de la transaction
        if($result)
        {
            $val= '<xml>'.$result.'</xml>';
            $mxml = simplexml_load_string($val);
            $status = (string)$mxml->status;
            $message = (string)$mxml->message;
            //dd($result,$val,$mxml,$status,$message);
            if($status == 200)
            {
                // $user = User::find(Auth::id());
                $amount = $request->amount;
               
                $user->wallet+= $amount;
               
                $transaction = new WalletTransactions();
                $transaction->user_id = $user->id;
                $transaction->amount = $amount;
                $transaction->status = 'paid';
                $transaction->save();
                    
                if($user){
                        // $vendorToken = User::find($order->restaurant_id);   
                    $device_token = $user->device_token;
            
                    // dd($shopper_token);
                    if ($device_token) {            
                    $notification = new Notification;
                    $message2 = "Your wallet has been credited successfully!";
                    $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'wallet_topup', $user->id);
                    }
                }
                return $this->apiSuccessMessageResponse('Topup successfull');
            }
            return $this->apiErrorMessageResponse('Payment Failed');
        }
        return $this->apiErrorMessageResponse('Payment Failed');

    }
    
    // public function orangeMoney($order_id, Request $request)
    // {
    //     $order = Order::find($order_id);
    //     $url = "https://apiom.orange.bf:9007/payment";//"https://testom.orange.bf:9008/payment";

    //     $curl = curl_init($url);
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_POST, true);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
    //     $headers = array(
    //        "Content-Type: application/xml",
    //        "Accept: application/xml",
    //     );
    //     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
    //     $data = "<COMMAND><TYPE>OMPREQ</TYPE><customer_msisdn>{$request->cust_msisdn}</customer_msisdn><merchant_msisdn>64926823</merchant_msisdn><api_username>SMILSARL</api_username><api_password>Orange@123</api_password><amount>{$order->grand_total}</amount><PROVIDER>101</PROVIDER><PROVIDER2>101</PROVIDER2><PAYID>12</PAYID><PAYID2>12</PAYID2><otp>{$request->otp}</otp><reference_number>789233</reference_number><ext_txn_id>201500068544</ext_txn_id></COMMAND>";
        
    //     curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        
    //     //for debug only!
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
    //     $resp = curl_exec($curl);
    //     curl_close($curl);
    //     \Storage::put('orange_result.txt', $resp);
    //     // var_dump($resp);die();
        
    //     preg_match('/<status>(.*?)<\/status>/s', $resp, $status);
	   //  preg_match('/<message>(.*?)<\/message>/s', $resp, $message);
        
        
    //    if (isset($status[1]) && $status[1] == "990417") {
    //         return response()->json([
    //         'status' => 'OTP failed',
    //         'message' => trans('response.' . substr($message[1], 8))
    //         ]);
    //     } elseif ($status[1] == "200") {
    //         if ($order && $order->payment_method == 'orange_money') {
    //             if ($request->success) {
    //                 $order->payment_status = 'completed';
    //                 $order->save();
        
    //                 if ($order->restaurant_id) {
    //                     $vendorToken = User::find($order->restaurant_id);
    //                     $device_token = $vendorToken->device_token;
        
    //                     // dd($shopper_token);
    //                     if ($device_token) {
    //                         $notification = new Notification;
    //                         $message2 = "You Have A New Order";
    //                         $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'new_order', $order->id);
    //                     }
    //                 }
    //                 if ($order->grocery_id) {
    //                     $shopperToken = User::where('assigned_grocery', $order->grocery_id)->first();
    //                     $groceryToken = User::where('id', $order->grocery_id)->first();
        
    //                     $shopper_token = $shopperToken->device_token;
    //                     $grocery_token = $groceryToken->device_token;
        
    //                     if ($grocery_token) {
    //                         $notification = new Notification;
    //                         $message2 = "You Have A New Order";
    //                         $notification->sendPushNotification($grocery_token, 'Porter', $message2, '', 'new_order', $order->id);
    //                     }
        
    //                     if ($shopper_token) {
    //                         $notification = new Notification;
    //                         $message2 = "You Have A New Order";
    //                         $notification->sendPushNotification($shopper_token, 'Porter', $message2, '', 'new_order', $order->id);
    //                     }
    //                 }
    //                 return $this->apiSuccessMessageResponse('Payment Successfull');
    //             } else {
    //                 $order->payment_status = 'failed';
    //                 $order->save();
    //                 return $this->apiErrorMessageResponse('Payment Failed');
    //             }
    //         } else {
    //             return $this->apiErrorMessageResponse('Invalid Order ID');
    //         }
    //     } else {
    //         return $this->apiErrorMessageResponse('Payment Failed');
    //     }
    // }
    
    // public function orangeMoneyWallet(Request $request)
    // {
        
    //     $user = User::find($request->user_id);
    //     $url = "https://testom.orange.bf:9007/payment";

    //     $curl = curl_init($url);
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_POST, true);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
    //     $headers = array(
    //        "Content-Type: application/xml",
    //        "Accept: application/xml",
    //     );
    //     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
    //     $data = "<COMMAND><TYPE>OMPREQ</TYPE><customer_msisdn>{$request->customer_msisdn}</customer_msisdn><merchant_msisdn>64926823</merchant_msisdn><api_username>SMILSARL</api_username><api_password>Orange@123</api_password><amount>{$request->amount}</amount><PROVIDER>101</PROVIDER><PROVIDER2>101</PROVIDER2><PAYID>12</PAYID><PAYID2>12</PAYID2><otp>{$request->otp}</otp><reference_number>789233</reference_number><ext_txn_id>201500068544</ext_txn_id></COMMAND>";
        
    //     curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        
    //     //for debug only!
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
    //     $resp = curl_exec($curl);
    //     curl_close($curl);
    //     \Storage::put('orange_result.txt', $resp);
    //     // var_dump($resp);die();
        
    //     preg_match('/<status>(.*?)<\/status>/s', $resp, $status);
	   //  preg_match('/<message>(.*?)<\/message>/s', $resp, $message);
        
        
    //    if ($status[1] == "990417") {
    //         return response()->json([
    //         'status' => 'OTP failed',
    //         'message' => trans('response.' . substr($message[1], 8))
    //         ]);
    //     } else if ($status[1] == "200") {
            
    //         // $user = User::find(Auth::id());
    //         $amount = $request->amount;
           
    //         $user->wallet+= $amount;
           
    //         $transaction = new WalletTransactions();
    // 	    $transaction->user_id = $user->id;
    // 		$transaction->amount = $amount;
    // 		$transaction->status = 'paid';
    // 		$transaction->save();
    			
    // 		if($user){
    //                 // $vendorToken = User::find($order->restaurant_id);   
    //             $device_token = $user->device_token;
        
    //             // dd($shopper_token);
    //             if ($device_token) {            
    //             $notification = new Notification;
    //             $message2 = "Your wallet has been credited successfully!";
    //             $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'wallet_topup', $user->id);
    //             }
    //         }
    //        return $this->apiSuccessMessageResponse('Topup successfull');
    //     }else{
    //         return $this->apiErrorMessageResponse('Payment Failed');
    //     }
        
        
        
    // //     if($request->success) {
    // //         $user = User::find($user_id);
    // //         $amount = $response->montant;
    // // 		if(trim($response->status)=="completed") {
    // // 			$user->wallet += $amount;
    // // 			$user->save();
    			
    // // 			$transaction = new WalletTransactions();
    // // 			$transaction->user_id = $user->id;
    // // 			$transaction->amount = $amount;
    // // 			$transaction->status = 'paid';
    // // 			$transaction->save();
    			
    // // 			if($user){
    // //                 // $vendorToken = User::find($order->restaurant_id);   
    // //                 $device_token = $user->device_token;
        
    // //                 // dd($shopper_token);
    // //                 if ($device_token) {            
    // //                     $notification = new Notification;
    // //                     $message2 = "Your wallet has been credited successfully!";
    // //                     $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'wallet_topup', $user->id);
    // //                 }
    // //             }
    // //             return $this->apiSuccessMessageResponse('Topup successfull');
    // // 		} else {
    // //             return $this->apiErrorMessageResponse('Payment Failed');
    // //         }
    // //     } else {
    // //         return $this->apiErrorMessageResponse('Payment Failed');
    // //     }
    // }
    
    public function ligdiCash($order_id)
    {
        if(env('LIVE_MODE') == 1) {
            $api_key = env('API_KEY');
            $authorization = env('AUTHORIZATION');
        } else {
            $api_key = env('TEST_API_KEY');
            $authorization = env('TEST_AUTHORIZATION');
        }

        /*
        * use "findOrFail" instead of "find"
        */
        $order = Order::findOrFail($order_id);        
        $amount = $this->converter($order->grand_total);

        if($amount < 100) {
            $amount = 6675;
        }
        
        $curl = curl_init();


        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://app.ligdicash.com/pay/v01/redirect/checkout-invoice/create",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_SSL_VERIFYHOST => false,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_HTTPHEADER => array(
            "Apikey: " . $api_key,
            "Authorization: Bearer " . $authorization,
            "Accept: application/json",
            "Content-Type: application/json"
          ),
        // CURLOPT_HTTPHEADER => array(
        //     "Apikey: YNYZ3BXIFWRBBPFQ2",
        //     "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZF9hcHAiOiI3NzQiLCJpZF9hYm9ubmUiOiI4OTk0MiIsImRhdGVjcmVhdGlvbl9hcHAiOiIyMDIxLTA4LTE4IDE4OjIwOjQyIn0.8rMinJMEDZeeoGNqcKxwD2VjXPC5t1__ilTJIOwFtQ4",
        //     "Accept: application/json",
        //     "Content-Type: application/json"
        //   ),
          CURLOPT_POSTFIELDS =>'
                              {
                              "commande": {
                                "invoice": {
                                  "items": [
                                    {
                                      "name": "Nom de article ou service ou produits",
                                      "description": "Description du service ou produits",
                                      "quantity": 1,
                                      "unit_price": "'.$amount.'",
                                      "total_price": "'.$amount.'"
                                    }
                                  ],
                                  "total_amount": "'.$amount.'",
                                  "devise": "XOF",
                                  "description": "Descrion de la commande des produits ou services",
                                  "customer": "",
                                  "customer_firstname":"Prenom du client",
                                  "customer_lastname":"Nom du client",
                                  "customer_email":"tester@ligdicash.com"
                                },
                                "store": {
                                  "name": "PORTER DELIVERY",
                                  "website_url": "https://api.porterdelivery.app/api/v1/customer/ligdi-callback"
                                },
                                "actions": {
                                  "cancel_url": "https://api.porterdelivery.app/api/v1/customer/payment-cancel",
                                  "return_url": "https://api.porterdelivery.app/api/v1/customer/ligdi-callback",
                                  "callback_url": "https://api.porterdelivery.app/api/v1/customer/ligdi-callback"
                                },
                                "custom_data": {
                                  "transaction_id": "'. $order_id .'" 
                                }
                              }
                            }',
        ));
        
        $response = json_decode(curl_exec($curl));
        
        curl_close($curl);
        
        return redirect($response->response_text);
    }
    
    public function ligdiCallback(Request $request)
    {
        if(env('LIVE_MODE') == 1) {
            $api_key = env('API_KEY');
            $authorization = env('AUTHORIZATION');
        } else {
            $api_key = env('TEST_API_KEY');
            $authorization = env('TEST_AUTHORIZATION');
        }
        
        $invoiceToken = $request->token; // Payment Token sent by ligdi cash on callback
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://app.ligdicash.com/pay/v01/redirect/checkout-invoice/confirm?invoiceToken=".$invoiceToken,
          CURLOPT_RETURNTRANSFER => true,
          // CURLOPT_SSL_VERIFYHOST => false,
          // CURLOPT_SSL_VERIFYPEER => false,
          // CURLOPT_ENCODING => "",
          // CURLOPT_MAXREDIRS => 10,
          // CURLOPT_TIMEOUT => 30,
          // CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER => array(
            "Apikey: " . $api_key,
            "Authorization: Bearer " . $authorization
          ),
        // CURLOPT_HTTPHEADER => array(
        //     "Apikey: YNYZ3BXIFWRBBPFQ2",
        //     "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZF9hcHAiOiI3NzQiLCJpZF9hYm9ubmUiOiI4OTk0MiIsImRhdGVjcmVhdGlvbl9hcHAiOiIyMDIxLTA4LTE4IDE4OjIwOjQyIn0.8rMinJMEDZeeoGNqcKxwD2VjXPC5t1__ilTJIOwFtQ4"
        //   ),
        ));
        $response = json_decode(curl_exec($curl));
        curl_close($curl);
        
        if(isset($response) && isset($response->external_id)) {
            $order = Order::find($response->external_id);
            if(trim($response->status)=="completed") {
                $order->payment_status = 'completed';
                $order->save();
                
                if($order->restaurant_id){
                    $vendorToken = User::find($order->restaurant_id);   
                    $device_token = $vendorToken->device_token;
        
                    // dd($shopper_token);
                    if ($device_token) {            
                        $notification = new Notification;
                        $message2 = "You Have A New Order";
                        $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'new_order', $order->id);
                    }
                }
                if($order->grocery_id){
                    $shopperToken = User::where('assigned_grocery', $order->grocery_id)->first();                
                    $groceryToken = User::where('id', $order->grocery_id)->first();
                    
                    $shopper_token = $shopperToken->device_token;
                    $grocery_token = $groceryToken->device_token;
        
                    if ($grocery_token) {            
                        $notification = new Notification;
                        $message2 = "You Have A New Order";
                        $notification->sendPushNotification($grocery_token, 'Porter', $message2, '', 'new_order', $order->id);
                    }
        
                    if ($shopper_token) {            
                        $notification = new Notification;
                        $message2 = "You Have A New Order";
                        $notification->sendPushNotification($shopper_token, 'Porter', $message2, '', 'new_order', $order->id);
                    }
                }
                return redirect()->route('callback-success');
            } else {
                $order->payment_status = 'failed';
                $order->save();
                
                return redirect()->route('callback-failure');
            }
        }else{
            return redirect()->route('callback-failure');
        }
    }
    
    public function ligdiCashWallet($user_id, $amount)
    {
        if(env('LIVE_MODE') == 1) {
            $api_key = env('API_KEY');
            $authorization = env('AUTHORIZATION');
        } else {
            $api_key = env('TEST_API_KEY');
            $authorization = env('TEST_AUTHORIZATION');
        }
        //add new
        
        $user = User::findOrFail($user_id);
        $amount = $this->converter($amount);
        
        if(!$user || $amount < 100) {
            abort(404);
        }
        // $amount = $order->grand_total;
        // $amount = 100;
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://app.ligdicash.com/pay/v01/redirect/checkout-invoice/create",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_SSL_VERIFYHOST => false,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_HTTPHEADER => array(
            "Apikey: " . $api_key,
            "Authorization: Bearer " . $authorization,
            "Accept: application/json",
            "Content-Type: application/json"
          ),
        // CURLOPT_HTTPHEADER => array(
        //     "Apikey: YNYZ3BXIFWRBBPFQ2",
        //     "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZF9hcHAiOiI3NzQiLCJpZF9hYm9ubmUiOiI4OTk0MiIsImRhdGVjcmVhdGlvbl9hcHAiOiIyMDIxLTA4LTE4IDE4OjIwOjQyIn0.8rMinJMEDZeeoGNqcKxwD2VjXPC5t1__ilTJIOwFtQ4",
        //     "Accept: application/json",
        //     "Content-Type: application/json"
        //   ),
          CURLOPT_POSTFIELDS =>'
                              {
                              "commande": {
                                "invoice": {
                                  "items": [
                                    {
                                      "name": "Nom de article ou service ou produits",
                                      "description": "Description du service ou produits",
                                      "quantity": 1,
                                      "unit_price": "'.$amount.'",
                                      "total_price": "'.$amount.'"
                                    }
                                  ],
                                  "total_amount": "'.$amount.'",
                                  "devise": "XOF",
                                  "description": "Descrion de la commande des produits ou services",
                                  "customer": "",
                                  "customer_firstname":"Prenom du client",
                                  "customer_lastname":"Nom du client",
                                  "customer_email":"tester@ligdicash.com"
                                },
                                "store": {
                                  "name": "PORTER DELIVERY",
                                  "website_url": "https://api.porterdelivery.app"
                                },
                                "actions": {
                                  "cancel_url": "https://api.porterdelivery.app/api/v1/customer/payment-cancel",
                                  "return_url": "https://api.porterdelivery.app/api/v1/customer/ligdi-wallet-callback",
                                  "callback_url": "https://api.porterdelivery.app/api/v1/customer/ligdi-wallet-callback"
                                },
                                "custom_data": {
                                  "transaction_id": "'. $user_id .'" 
                                }
                              }
                            }',
        ));
        
        $response = json_decode(curl_exec($curl));
        
        curl_close($curl);
        
        return redirect($response->response_text);
    }
    
    public function ligdiCallbackWallet(Request $request)
    {
        if(env('LIVE_MODE') == 1) {
            $api_key = env('API_KEY');
            $authorization = env('AUTHORIZATION');
        } else {
            $api_key = env('TEST_API_KEY');
            $authorization = env('TEST_AUTHORIZATION');
        }
        
        $invoiceToken = $request->token;
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://app.ligdicash.com/pay/v01/redirect/checkout-invoice/confirm/?invoiceToken=".$invoiceToken,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_SSL_VERIFYHOST => false,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER => array(
            "Apikey: " . $api_key,
            "Authorization: Bearer " . $authorization
          ),
        // CURLOPT_HTTPHEADER => array(
        //     "Apikey: YNYZ3BXIFWRBBPFQ2",
        //     "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZF9hcHAiOiI3NzQiLCJpZF9hYm9ubmUiOiI4OTk0MiIsImRhdGVjcmVhdGlvbl9hcHAiOiIyMDIxLTA4LTE4IDE4OjIwOjQyIn0.8rMinJMEDZeeoGNqcKxwD2VjXPC5t1__ilTJIOwFtQ4"
        //   ),
        ));
        $response = json_decode(curl_exec($curl));
        curl_close($curl);
        
        if(isset($response) && isset($response->external_id)) {
            // dd($response);
            $user = User::find($response->external_id);
            $amount = $response->montant;
            if(trim($response->status)=="completed") {
                $user->wallet += $amount;
                $user->save();
                
                $transaction = new WalletTransactions();
                $transaction->user_id = $user->id;
                $transaction->amount = $amount;
                $transaction->status = 'paid';
                $transaction->save();
                
                if($user){
                    // $vendorToken = User::find($order->restaurant_id);   
                    $device_token = $user->device_token;
        
                    // dd($shopper_token);
                    if ($device_token) {            
                        $notification = new Notification;
                        $message2 = "Your wallet has been credited successfully!";
                        $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'wallet_topup', $user->id);
                    }
                }
                return redirect()->route('callback-success');
            } else {
                return redirect()->route('callback-failure');
            }
        }else{
            return redirect()->route('callback-failure');
        }
    }

    public function assignOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orderID'     => 'required|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }  
        $radius = 5;   

        $order = Order::where('id', $request->orderID)->first();
        $vendor = User::where('id', $order->restaurant_id)
        ->orWhere('id', $order->grocery_id)
        ->orWhere('id', $order->shopper_id)->first();
         $rider = User::role('rider')->whereDoesnthave('cancel_orders')->where('onlineStatus', 1)->select('*')
            ->selectRaw('( 6371 * acos( cos( radians(?) ) *
                               cos( radians( latitude ) )
                               * cos( radians( longitude ) - radians(?)
                               ) + sin( radians(?) ) *
                               sin( radians( latitude ) ) )
                             ) AS distance', [$vendor->latitude, $vendor->longitude, $vendor->latitude])
            ->havingRaw("distance < ?", [$radius])
            ->first();
        if(!$rider){
             return response()->json([
            'status' => 0,
            'message' => 'No rider Available at this time',
            'data' => []
            ]);
        } 

        $order->order_status   = 'finding_rider';
        // $order->rider_id = $rider->id;
        $order->rider_id = 155;
        $order->save();
        $vendorToken = User::find($order->rider_id);
       
        $device_token = $vendorToken->device_token;
        if ($device_token) {            
            $notification = new Notification;
            $message2 = "You have a new order!";
            $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'new_order', $order->id);
        }
        if ($order instanceof \App\Order) {
            return $this->apiSuccessMessageResponse('Success', $order);
        }
    }

    public function acceptOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id'        => 'exists:orders,id|nullable',  
            'restaurant_id'   => 'exists:users,id|nullable',
            'shopper_id'      => 'exists:users,id|nullable',
            'rider_id'        => 'exists:users,id|nullable',     
            'grocery_id'      => 'exists:users,id|nullable'     
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }
        
        $order = Order::where('id', $request->order_id)->first();
        $user = User::where('id', $request->restaurant_id)->first();
        $address = Address::where('id', $order->address_id)->first();
        
        $id = $request->order_id;
        $data  = Order::find($id);

        
        $latitude   = $address->latitude;
        $longitude  = $address->longitude;
        
        
        $record = User::where('id', $data->restaurant_id)
        ->orWhere('id', $data->grocery_id)
        ->orWhere('id', $data->shopper_id)
        ->selectRaw('*, ( 6367 * acos( cos( radians( ? ) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians( ? ) ) + sin( radians( ? ) ) * sin( radians( latitude ) ) ) ) AS distance', [$latitude, $longitude, $latitude])
        ->having('distance', '<', 30)
        ->orderBy('distance')
        ->first();

        $cost = Setting::where('name', 'delivery_cost')->first();                 
        $result = $record->distance * $cost->value;
        if($request->restaurant_id){
        $data->restaurant_id    = $request->restaurant_id;
        $data->order_status     = 'preparing'; 
        }
        if($request->shopper_id){
        $data->shopper_id       = $request->shopper_id;
        $data->order_status     = 'preparing'; 
        }
        if($request->rider_id){
        $data->rider_id         = $request->rider_id;
        $data->order_status     = 'rider_accepted'; 

        }
        if($request->grocery_id){
        $data->grocery_id       = $request->grocery_id;
        $data->order_status     = 'preparing'; 
        }

        // $data->delivery_cost    = round($result,2);
        if($request->restaurant_id){
            $data->accepted_at_vendor = now();
        }
        if($request->restaurant_id){
            $data->accepted_at_rider = now();
        }

        $data->save();

        $customerToken = User::find($order->customer_id); 
        if($order->rider_id){
            if($order->restaurant_id){
                $vendorToken = User::find($order->restaurant_id);   
            }
            if($order->grocery_id){
                // $vendorToken = User::find($order->grocery_id);   
                $vendorToken = User::where('assigned_grocery',$order->grocery_id)->first();   
            }
            $d_token = $vendorToken->device_token;
            if ($d_token) {            
                $notification = new Notification;
                $message2 = "Rider found and on the way!";
                $notification->sendPushNotification($d_token, 'Porter', $message2, '', 'order_accepted', $order->id);
            }
        } else {   
            $device_token = $customerToken->device_token;
            if ($device_token) {
                
                $notification = new Notification;
                $message2 = "Your Order has been accepted and in process";
                $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'order_accepted', $order->id);
            }
        }
        

        if ($data instanceof \App\Order) {
            return $this->apiSuccessMessageResponse('Success', $data);
        }
    }

    public function readyOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id'   => 'exists:users,id|nullable',            
            'order_id'        => 'required|exists:orders,id|nullable',            
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }

        $id = $request->order_id;
        $data  = Order::find($id);

        $data->order_status   = 'ready';
        $data->completed_at_vendor   = now();
        $data->save();
        
        if($data->restaurant_id){
            $customerToken = User::find($data->customer_id);   
            $device_token = $customerToken->device_token;
            if ($device_token) {
                
                $notification = new Notification;
                $message2 = "Your Order has been prepared and searching for rider";
                $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'order_ready', $data->id);
            }
        }
        if ($data instanceof \App\Order) {
            return $this->apiSuccessMessageResponse('Success', $data);
        }
    }
    
    public function riderPickOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [                        
            'order_id'        => 'required|exists:orders,id|nullable',            
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }

        $id = $request->order_id;
        $data  = Order::find($id);

        $data->order_status  = 'picked';
        $data->save();
        
        $customerToken = User::find($data->customer_id);
        $device_token = $customerToken->device_token;
        
        if ($device_token) {            
            $notification = new Notification;
            $message2 = "Rider has picked up your order and on the way!";
            $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'order_picked', $data->id);
        }

        if ($data instanceof \App\Order) {
            return $this->apiSuccessMessageResponse('Success', $data);
        }
    }
    
    public function paymentProceed(Request $request)
    {
        $validator = Validator::make($request->all(), [                        
            'orderID'        => 'required|exists:orders,id'            
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }

        $data  = Order::find($request->orderID);
        if($request->type == 'shopper'){
            $data->confirm_payment_proceed = 0;
            $data->save();
        } elseif($request->type == 'grocery'){
            $data->confirm_payment_proceed = 1;
            $data->save();
        }
        if($request->type == 'shopper'){
            
            $vendorToken = User::find($data->grocery_id);        
            $device_token = $vendorToken->device_token;
            if($device_token) {
                $notification = new Notification();
                $message2 = "Please confirm payment for Order No. ". $data->id . "";
                $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'payment_confirmation', $data->id);
            }    
        }

        if($request->type == 'grocery'){
            $grocery = User::find($data->grocery_id);
            $vendorToken = User::where('assigned_grocery', $grocery->id)->first(); 

            $device_token = $vendorToken->device_token;
            if($device_token) {
                $notification = new Notification();
                $message2 = "Payment confirmed for Order No. ". $data->id . ". Please proceed to continue";
                $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'payment_confirmation', $data->id);
            }    
        }
    }

    public function completeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id'   => 'exists:users,id|nullable',
            'shopper_id'      => 'exists:users,id|nullable',
            'rider_id'        => 'exists:users,id|nullable',     
            'grocery_id'      => 'exists:users,id|nullable'     
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }

        $setting = Setting::where('name', 'commission')->first();

        $id = $request->order_id;
        if($request->vendor_id){

        $data  = Order::where('id', $id)        
        ->where('restaurant_id', $request->vendor_id)
        ->orwhere('grocery_id', $request->vendor_id)
        ->orwhere('shopper_id', $request->vendor_id) 
        ->first();

        } elseif($request->rider_id) {

        $data  = Order::where('id', $id)
        ->where('rider_id', $request->rider_id)
        ->first();             
        }

        if($request->rider_id){
            $data->order_status     = 'completed';
            $data->completed_at_rider   = now();

        } else {
            $data->order_status     = 'dispatch';
            $data->completed_at_vendor   = now();            
        }
        
        $data->save();

        $finding = Wallet::where('rider_id', $data->rider_id)->first();

        if($finding == NULL){
            $commission = new Wallet();
            $commission->rider_id  = $data->rider_id;
            $commission->balance    = 0;
            $commission->save();

            $getWallet = Wallet::where('rider_id', $data->rider_id)->first();
            $getCommission = $data->delivery_cost - $data->delivery_cost / 100 * $setting->value ;
            // dd($getCommission);
            $commissionItem = new WalletItem();
            $commissionItem->wallet_id      = $commission->id;
            $commissionItem->order_id       = $data->id;
            $commissionItem->order_amount   = $data->grand_total;
            $commissionItem->commission     = $getCommission;
            $commissionItem->save();

            if($data->tip) {
                $tip = new WalletItem();
                $tip->wallet_id      = $commission->id;
                $tip->order_id       = $data->id;
                $tip->order_amount   = $data->grand_total;
                $tip->commission     = $data->tip;
                $tip->save();
            }

            $total = WalletItem::where('wallet_id', $commissionItem->wallet_id)->groupBy('wallet_id')->sum('commission');    

            $commission->balance    = $total;
            $commission->save();

        } else {    

            $commission = Wallet::where('rider_id', $data->rider_id)->first();

            $getWallet = Wallet::where('rider_id', $data->rider_id)->first();
            $getCommission = $data->delivery_cost - $data->delivery_cost / 100 * $setting->value ;
            // dd($getCommission);
            $commissionItem = new WalletItem();
            $commissionItem->wallet_id      = $getWallet->id;
            $commissionItem->order_id       = $data->id;
            $commissionItem->order_amount   = $data->grand_total;
            $commissionItem->commission     = $getCommission;
            $commissionItem->save();

            if($data->tip) {
                $tip = new WalletItem();
                $tip->wallet_id      = $commission->id;
                $tip->order_id       = $data->id;
                $tip->order_amount   = $data->grand_total;
                $tip->commission     = $data->tip;
                $tip->save();
            }
            
            $total = WalletItem::where('wallet_id', $commissionItem->wallet_id)->groupBy('wallet_id')->sum('commission');    

            $commission->balance   = $total;
            $commission->save();
        }
        // customer notification
        $customerToken = User::find($data->customer_id);           
        $device_token = $customerToken->device_token;
        
        if ($device_token) {            
            $notification = new Notification;
            $message2 = "Your Order has been delivered successfully!";
            $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'order_delivered', $data->id);
        }

        // vendor notification
        if($data->restaurant_id){
            $vendorToken = User::find($data->restaurant_id);           
        }
        if($data->grocery_id){
            $vendorToken = User::find($data->grocery_id);           
        }
        $d_token = $vendorToken->device_token;
        
        if ($d_token) {            
            $notification = new Notification;
            $message2 = "Your Order #" . $data->id . " has been dispatched successfully!";
            $notification->sendPushNotification($d_token, 'Porter', $message2, '', 'order_delivered', $data->id);
        }

        // rider notification
        $riderToken = User::find($data->rider_id);           
        $de_token = $riderToken->device_token;
        
        if ($de_token) {            
            $notification = new Notification;
            $message2 = "Your order has been completed successfully!";
            $notification->sendPushNotification($de_token, 'Porter', $message2, '', 'order_delivered', $data->id);
        }

        if ($data instanceof \App\Order) {
            return $this->apiSuccessMessageResponse('Success', $data);
        }
    }

    public function rejectOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id'     => 'required|exists:orders,id',
            'cancel_by'     => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }  
        $data                   = new CancelOrder();
        $data->order_id         = $request->order_id;
        $data->cancel_by        = $request->cancel_by;
        $data->cancel_at        = now();
        $data->save();

        $record = Order::where('id', $request->order_id)->first();
        if(Auth::user()->user_role == 'customer')
        {
            $record->order_status = 'cancelled';
            $record->save();
        } 
        if(Auth::user()->user_role == 'grocery' || Auth::user()->user_role == 'restaurant')
        {
            $record->order_status = 'cancelled';
            $record->save();

        } elseif(Auth::user()->user_role == 'rider') { 
            // $radius = 5;   
            // $vendor = User::where('id', $record->restaurant_id)->first();
            // $riders = User::role('rider')->where('onlineStatus', 1)->select('*')
            //     ->selectRaw('( 6371 * acos( cos( radians(?) ) *
            //                       cos( radians( latitude ) )
            //                       * cos( radians( longitude ) - radians(?)
            //                       ) + sin( radians(?) ) *
            //                       sin( radians( latitude ) ) )
            //                      ) AS distance', [$vendor->latitude, $vendor->longitude, $vendor->latitude])
            //     ->havingRaw("distance < ?", [$radius])
            //     ->first();
            
            // $orderCancellation = CancelOrder::where('order_id', $request->order_id)->count('order_id');
            
            // if($orderCancellation == 5){
            //     return response()->json([
            //     'status' => 0,
            //     'message' => 'No rider found at this time',
            //     'data' => []
            //     ]);
            // }
                
            // $cancelled = CancelOrder::where('order_id', $record->id)->where('cancel_by', $riders->id)->first();
            // if($cancelled){
            //     $cancellOrder = $cancelled->cancel_by;
            // } else {
            //     $cancellOrder = 0;
            // }

            // $rider = User::role('rider')->where('onlineStatus', 1)->where('id', '!=' ,$cancellOrder)->select('*')
            //     ->selectRaw('( 6371 * acos( cos( radians(?) ) *
            //                       cos( radians( latitude ) )
            //                       * cos( radians( longitude ) - radians(?)
            //                       ) + sin( radians(?) ) *
            //                       sin( radians( latitude ) ) )
            //                      ) AS distance', [$vendor->latitude, $vendor->longitude, $vendor->latitude])
            //     ->havingRaw("distance < ?", [$radius])
            //     ->first();
            $record->rider_id = 112;  
            $record->save();
        }          

        // notification to customer
        $customerToken = User::find($record->customer_id);           
        $device_token = $customerToken->device_token;
        
        if ($device_token) {            
            $notification = new Notification;
            $message2 = "We are sorry your order has been cancelled.";
            $notification->sendPushNotification($device_token, 'Porter', $message2, '', 'order_cancelled', $record->id);
        }

        if ($data instanceof \App\CancelOrder) {
            return $this->apiSuccessMessageResponse('Success', $data);
        }
    }

    public function pastOrders(Request $request)
    {
        if($request->vendorID){
        $data = Order::where('restaurant_id',  $request->vendorID)->where('order_status', 'completed')
        ->orWhere('grocery_id',  $request->vendorID)->where('order_status', 'completed')
        ->orWhere('shopper_id',  $request->vendorID)->where('order_status', 'completed')
        ->latest()->get();
        }
        if($request->riderID){
        $data = Order::where('rider_id',  $request->riderID)->where('order_status', 'completed')->latest()->get();
        }
        if($request->customerID){
        $data = Order::where('customer_id',  $request->customerID)->latest()->get();
        }
        // return $data;
        $result = ViewPastOrder::collection($data)->toArray($request);    

         if (count($data) > 0) {
        return $this->apiSuccessMessageResponse('success', $result);
        } else {
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
        ]);
        }
    }

    public function newOrders(Request $request)
    {        
        $items = Order::where(function($q) use($request) {
            $q->where('restaurant_id', $request->vendorID)
            ->orWhere('grocery_id', $request->vendorID)
            ->orWhere('shopper_id', $request->vendorID)
            ->orWhere('rider_id', $request->vendorID);
        })
        ->whereIn('order_status', ['pending', 'preparing', 'ready', 'rider_accepted', 'finding_rider'])
        ->where('payment_status', 'completed')
        ->latest()->get(); 
        $result = ViewPastOrder::collection($items)->toArray($request);    
        if (count($items) > 0) {
            return $this->apiSuccessMessageResponse('success', $result);
        } else {

            return response()->json([
                'status' => 0,
                'message' => 'No Record Found',
                'data' => []
            ]);
        }        
    }

    public function singleOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'     => 'required|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }
        $data = Order::where('id', $request->id)->first();
        $result = (new ViewOrderDetail($data))->resolve();
         if ($data) {
        return $this->apiSuccessMessageResponse('success', $result);
        } else {
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
        ]);
        }
    }
    
    public function orderDetailToRider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orderID'     => 'required|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }
        $radius = 10;
        $vendor = User::where('id', $request->vendorID)->first();
     
        $data['order'] = Order::where('id', $request->orderID)->first();                
        // return $data;
        if($data['order'] == NULL){
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
            ]);
        }
        // items object
        $data['order_items'] = DB::table('items')->where('order_id', $data['order']->id)->join('order_items', 'items.id', '=' ,'order_items.item_id')
        ->select('order_items.*', 'items.name')->get();
        
        // vendor object
        if($request->vendorID){      
        $data['vendor'] = User::where('id', $request->vendorID)->first();
        
        } else {

        $data['vendor'] = User::where('id', $data['order']->restaurant_id)->orWhere('id', $data['order']->grocery_id)->orWhere('id', $data['order']->shopper_id)->first();           
        }
        $data['rider'] = User::where('id', $data['order']->rider_id)
            ->select('*')
            ->selectRaw('( 6371 * acos( cos( radians(?) ) *
                               cos( radians( latitude ) )
                               * cos( radians( longitude ) - radians(?)
                               ) + sin( radians(?) ) *
                               sin( radians( latitude ) ) )
                             ) AS distance', [$data['vendor']->latitude, $data['vendor']->longitude, $data['vendor']->latitude])
            ->havingRaw("distance < ?", [$radius])
            ->first();
        // custoemr object
        
        if($data['order']->customer->latitude == NULL){
            $data['customer'] = User::where('id', $data['order']->customer_id)
            ->select('*')
            ->first();    
        } else {
            $data['customer'] = User::where('id', $data['order']->customer_id)
            ->select('*')
            ->selectRaw('( 6371 * acos( cos( radians(?) ) *
                               cos( radians( latitude ) )
                               * cos( radians( longitude ) - radians(?)
                               ) + sin( radians(?) ) *
                               sin( radians( latitude ) ) )
                             ) AS distance', [$data['vendor']->latitude, $data['vendor']->longitude, $data['vendor']->latitude])
            ->havingRaw("distance < ?", [$radius])
            ->first();
        }
        
        $data['customerOrder_address'] = Address::where('id', $data['order']->address_id)
        ->select('id', 'user_id', 'address', 'latitude', 'longitude')
        ->selectRaw('( 6371 * acos( cos( radians(?) ) *
                               cos( radians( latitude ) )
                               * cos( radians( longitude ) - radians(?)
                               ) + sin( radians(?) ) *
                               sin( radians( latitude ) ) )
                             ) AS distance', [$data['vendor']->latitude, $data['vendor']->longitude, $data['vendor']->latitude])
            ->havingRaw("distance < ?", [$radius])
            ->first();
        if ($data == NULL) {            
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
        ]);
        } else {
            // $result =  (new GetLatestOrder($data))->resolve() ;
            return $this->apiSuccessMessageResponse('success', $data);
        }         
    }

    public function getALLOrderITems(Request $request)
    {
        $order = OrderItem::get();
        $result = GetOrderItems::collection($order)->toArray($request);
        return $result;
    }

    public function getOrderView(Request $request)
    {
        $radius = 10;
        $vendor = User::where('id', $request->vendorID)->first();

        if($request->rider_id){
            $data['order'] = Order::with('itemsOrder')->where('order_status', 'finding_rider')
                ->whereDoesnthave('cancel_orders', function($q) use($request){
                    $q->where('cancel_by', $request->rider_id);
                    })
                ->where('rider_id', $request->rider_id)            
                ->latest()
                ->first();    
        } 
        
        if($request->vendorID) {
            $data['order'] = Order::with('itemsOrder')->where('order_status',  'pending')
                ->whereDoesnthave('cancel_orders', function($q) use($request){
                    $q->where('cancel_by', $request->vendorID);
                    })
                ->where(function($q) use($request) {
                    $q->where('restaurant_id', $request->vendorID)
                    ->orWhere('grocery_id', $request->vendorID)
                    ->orWhere('shopper_id', $request->vendorID);
                })
                ->latest()
                ->first();                
        }
     
        if($data['order'] == NULL){
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
            ]);
        }
        // return $date['order']->customer_id;
        if($request->vendorID){      
            $data['vendor'] = User::where('id', $request->vendorID)->first();
        
        } else {
    
            $data['vendor'] = User::where('id', $data['order']->restaurant_id)->orWhere('id', $data['order']->grocery_id)->orWhere('id', $data['order']->shopper_id)->first();           
        }
        $data['rider'] = User::where('id', $data['order']->rider_id)
            ->select('*')
            ->selectRaw('( 6371 * acos( cos( radians(?) ) *
                              cos( radians( latitude ) )
                              * cos( radians( longitude ) - radians(?)
                              ) + sin( radians(?) ) *
                              sin( radians( latitude ) ) )
                             ) AS distance', [$data['vendor']->latitude, $data['vendor']->longitude, $data['vendor']->latitude])
            ->havingRaw("distance < ?", [$radius])
            ->first();
        // dd($data['rider']);

        $data['customer'] = User::where('id', $data['order']->customer_id)
            ->select('*')
            
            ->first();
        
        $data['customerOrder_address'] = Address::where('id', $data['order']->address_id)
        ->select('id', 'user_id', 'address', 'latitude', 'longitude')
        ->selectRaw('( 6371 * acos( cos( radians(?) ) *
                               cos( radians( latitude ) )
                               * cos( radians( longitude ) - radians(?)
                               ) + sin( radians(?) ) *
                               sin( radians( latitude ) ) )
                             ) AS distance', [$data['vendor']->latitude, $data['vendor']->longitude, $data['vendor']->latitude])
            ->havingRaw("distance < ?", [$radius])
            ->first();

        if ($data == NULL) {            
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
        ]);
        } else {
            // $result =  (new GetLatestOrder($data))->resolve() ;
            return $this->apiSuccessMessageResponse('success', $data);
        }         
    }
    
    public function getOrderPaymentDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendorID'     => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->apiValidatorErrorResponse('Invalid Parameters', $validator->errors());
        }

        $vendor = Order::where('order_status', 'completed')->where('restaurant_id', $request->vendorID)
            ->orWhere('grocery_id', $request->vendorID)
            ->orWhere('shopper_id', $request->vendorID)
            ->latest()->get();

        if(count($vendor) < 1){
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
            ]);
        }
        $result = GetPaymentDetails::collection($vendor)->toArray($request);

        if ($vendor) {
        return $this->apiSuccessMessageResponse('success', $result);
        } else {
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
        ]);
        }        
    }
    
    public function getItemByQrCode(Request $request)
    {
        $data = Item::where('id', $request->qr_code)->select('id', 'name', 'price', 'image')->first();

        if ($data) {
            return $this->apiSuccessMessageResponse('success', $data);
        } else {
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
            ]);
        } 
    }

    public function test(Request $request)
    {
        $data['order'] = OrderItem::where('order_id', $request->orderID)->get();
        return $data;
        $result =  GetLatestOrder::collection($data)->toArray($request);

        if ($data) {
        return $this->apiSuccessMessageResponse('success', $result);
        } else {
            return response()->json([
            'status' => 0,
            'message' => 'No Record Found',
            'data' => []
        ]);
        }
        
    }
}   
