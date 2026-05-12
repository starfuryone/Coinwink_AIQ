<?php

use App\Mail\PlainTextMail;
use App\Models\GetAppData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// Load routes
require __DIR__.'/api_alerts.php';
require __DIR__.'/api_alerts_portfolio.php';
require __DIR__.'/api_themes.php';
require __DIR__.'/api_stripe.php';


// 
// GENERAL
// 

Route::get('/app_data', function () {
    $cmc = DB::table('cw_data_cmc')->where('ID', '=', 1)->value('json');
    return (array( 'cmc' => json_decode($cmc) ));    
});


Route::middleware(['auth:sanctum', 'verified'])->get('/portfolio', function () {
    $id_user = Auth::user()->id;
    $alerts = DB::table('cw_settings')->where('user_ID', $id_user)->get();

    $result = json_decode($alerts, true);

    return($result[0]['portfolio']);
});


Route::middleware(['auth:sanctum', 'verified'])->post('/update_portfolio', function (Request $request) {
    $validated = $request->validate([
        'data' => 'required|string|max:1000000',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['portfolio' => $validated['data']]);

    return response()->json(['status' => 'success']);
});


Route::middleware(['auth:sanctum', 'verified'])->get('/watchlist', function () {
    $id_user = Auth::user()->id;
    $alerts = DB::table('cw_settings')->where('user_ID', $id_user)->get();

    $result = json_decode($alerts, true);

    return($result[0]['watchlist']);
});


Route::middleware(['auth:sanctum', 'verified'])->post('/update_watchlist', function (Request $request) {
    $validated = $request->validate([
        'data' => 'required|string|max:1000000',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['watchlist' => $validated['data']]);

    return response()->json(['status' => 'success']);
});


// Watchlist right column view
Route::middleware(['auth:sanctum', 'verified'])->post('/config_conf_w', function (Request $request) {
    $validated = $request->validate([
        'conf_w' => 'required|string|max:255',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['conf_w' => $validated['conf_w']]);

    return response()->json(['status' => 'success']);
});


Route::middleware(['auth:sanctum', 'verified'])->post('/config_cur_p', function (Request $request) {
    $validated = $request->validate([
        'cur_p' => 'required|string|max:16',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['cur_p' => $validated['cur_p']]);

    return response()->json(['status' => 'success']);
});


Route::middleware(['auth:sanctum', 'verified'])->post('/config_cur_w', function (Request $request) {
    $validated = $request->validate([
        'cur_w' => 'required|string|max:16',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['cur_w' => $validated['cur_w']]);

    return response()->json(['status' => 'success']);
});


Route::middleware(['auth:sanctum', 'verified'])->post('/config_cur_main', function (Request $request) {
    $validated = $request->validate([
        'cur_main' => 'required|string|max:16',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['cur_main' => $validated['cur_main']]);

    return response()->json(['status' => 'success']);
});


// Save currently opened tab
Route::middleware(['auth:sanctum', 'verified'])->post('/cw_tab', function (Request $request) {
    $validated = $request->validate([
        'cw_tab' => 'required|string|in:email,email-per,telegram,telegram-per,sms,sms-per',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')
    ->where('user_ID', $id_user)
    ->update(['cw_tab' => $validated['cw_tab']]);

    return response()->json(['status' => 'success']);
});


// CryptoConverter SHOW-HIDE
Route::middleware(['auth:sanctum', 'verified'])->post('/cryptocurrency_converter_expanded', function (Request $request) {
    $validated = $request->validate([
        'expanded' => 'required|boolean',
    ]);
    $id_user = Auth::user()->id;

    DB::table('cw_settings')->where('user_ID', $id_user)->update(['conv_exp' => $validated['expanded']]);

    return response()->json(['status' => 'success']);
});



// ACCOUNT FEEDBACK
Route::middleware(['auth:sanctum', 'verified'])->post('/feedback', function (Request $request) {
    $validated = $request->validate([
        'feedback' => 'required|string|max:5000',
    ]);
    $id_user = Auth::user()->id;
    $feedback = htmlspecialchars($validated['feedback']);

    $inserted = DB::table('cw_feedback')->insert([
        'message' => $feedback,
        'user_id' => $id_user,
    ]);

    if ($inserted === false) {
        return response()->json(['status' => 'error'], 500);
    }

    $user_email = DB::table('users')->where('id', $id_user)->value('email');

    // EMAIL NOTICE TO ADMIN — queued so the response is not blocked on SMTP.
    $message = "User ID: " . $id_user . "\nUser email: " . $user_email . "\n\n" . $feedback;
    Mail::to('feedback@coinwink.com')->queue(
        new PlainTextMail('New Feedback Received: ' . $id_user, $message)
    );

    return response()->json(['status' => 'success']);
});
