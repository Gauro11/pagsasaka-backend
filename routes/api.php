<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\OrgController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\OrgLogController;
use App\Http\Controllers\userlistController;


 

    Route::post('create', [userlistController::class, 'create']); // Create a new user
    Route::post('/users/{id}', [userlistController::class, 'edit']);
    Route::post('/delete/{id}', [userlistController::class, 'destroy']);
    Route::post('searchuser', [userlistController::class, 'searchuser']); 


///////////////////////////////////LOGIN//////////////////////////////////////////
Route::controller(AuthController::class)->group(function () {
    Route::post('login',  'login');
    Route::post('session',  'insertSession');
    Route::post('reset-password',  'resetPassword');

    });
    Route::middleware(['auth:sanctum', 'UserTypeAuth'])->group(function () {
        Route::get('/admin/dashboard', [AuthController::class, 'admin']);
        Route::get('/head/dashboard', [AuthController::class, 'head']);
        Route::get('/programchair/dashboard', [AuthController::class, 'programchair']);
        Route::get('/staff/dashboard', [AuthController::class, 'staff']);
        Route::get('/dean/dashboard', [AuthController::class, 'dean']);
       
    
        // Add more protected routes here
    });


Route::middleware('auth:sanctum')->post('/logout/{id}', [AuthController::class, 'logout']);


Route::middleware(['auth:sanctum', 'session.expiry'])->group(function () {
    Route::get('/some-protected-route', [AuthController::class, 'someMethod']);

});

   


Route::controller(BaseController::class)->group(function () {
Route::post('createCustomer', 'createCustomer');
Route::post('createCustomer', 'updateCustomer');
Route::get('getUsers', 'getUsers');

});
//create acc///
Route::controller(AccountController::class)->group(function () {
   
    Route::get('getAccounts', 'getAccounts');
    Route::post('searchAccount', 'searchAccount');
    Route::post('createAccoount', 'createAccount');
    Route::post('editAccount','editAccount');
    Route::post('/updateAccoount/{id}', 'updateAccount');
    Route::post('deleteAccount','deleteAccount');
    
});

Route::controller(OrgLogController::class)->group(function () {

    Route::get('getOrgLog','getOrgLog');
    Route::post('storeOrgLog','storeOrgLog');
    Route::post('editOrgLog','editOrgLog');
    Route::post('updateOrgLog','updateOrgLog');
    Route::post('deleteOrgLog','deleteOrgLog');
    Route::post('searchOrgLog','searchOrgLog');
  
});








Route::controller(RequestController::class)->group(function () {
    Route::get('/','index');
 //   Route::post('searchAccount', 'searchAccount');
      Route::post('createRequest', 'storeRequest');
  //  Route::post('updateAccoount/{account}', 'updateAccount');
   // Route::post('deleteAccount/{account}','deleteAccount');
    
});



Route::controller(OrgController::class)->group(function () {

      Route::post('storeOrg', 'storeOrg');
    
});


Route::controller(RoleController::class)->group(function () {

    Route::post('storeRole', 'storeRole');
  
});


