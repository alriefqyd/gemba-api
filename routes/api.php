<?php

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::group(["middleware" => ["auth:sanctum"]], function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/project', [\App\Http\Controllers\ProjectController::class,'index']);
    Route::post('/project', [\App\Http\Controllers\ProjectController::class,'store']);
    Route::put('/project/{project:id}', [\App\Http\Controllers\ProjectController::class,'update']);
    Route::delete('/project/{project:id}', [\App\Http\Controllers\ProjectController::class,'delete']);
    Route::get('/project/{project:id}', [\App\Http\Controllers\ProjectController::class,'show']);
    Route::get('/project/{project}/pptx', [\App\Http\Controllers\ProjectController::class, 'generatePptx']);
    Route::post('/logout', function (Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    });

});

Route::post('/register', function (Request $request){
    $request->validate([
        'name' => ['required','string','max:255'],
        'email' => ['required','string','lowercase','email','max:255','unique:'.\App\Models\User::class],
        'password' => ['required','confirmed', Password::defaults()],
        'device_name' => ['required']
    ]);

    $user = \App\Models\User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    event(new Registered($user));

    return response()->json([
        'token' => $user->createToken($request->device_name)->plainTextToken
    ]);
});


Route::post("/login", function (Request $request){
    $request->validate([
        'email' => ['required','email'],
        'password' => ['required'],
        'device_name' => ['required']
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();
    if(!$user || !Hash::check($request->password, $user->password)){
        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect']
        ]);
    }

    return response()->json([
        'token' => $user->createToken($request->device_name)->plainTextToken
    ]);
});



