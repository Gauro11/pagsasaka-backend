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
use App\Http\Controllers\EventController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\FileRequirementController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ProgramController;






     //connection//
     Route::options('/{any}', function (Request $request) {
        return response()->json(['status' => 'OK'], 200);
    })->where('any', '.*');
 

   /* Route::post('create', [userlistController::class, 'create']); // Create a new user
    Route::post('/users/{id}', [userlistController::class, 'edit']);
    Route::post('/delete/{id}', [userlistController::class, 'destroy']);
    Route::post('searchuser', [userlistController::class, 'searchuser']); */


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

    //create acc///
Route::controller(AccountController::class)->group(function () {
   
    Route::get('getAccounts', 'getAccounts');
    Route::post('searchAccount', 'searchAccount');
    Route::post('createAccoount', 'createAccount');
   // Route::post('editAccount','editAccount');
    Route::post('/updateAccoount/{id}', 'updateAccount');
    Route::post('deleteAccount/{id}','deleteAccount');
    
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);



Route::middleware(['auth:sanctum', 'session.expiry'])->group(function () {
    Route::get('/some-protected-route', [AuthController::class, 'someMethod']);

});

   

Route::post('/sample',function(Request $request){
    return $request->requirements;
});



Route::controller(OrgLogController::class)->group(function () {

    Route::post('getOrgLog','getOrgLog');
    Route::post('storeOrgLog','storeOrgLog');
    Route::post('editOrgLog','editOrgLog');
    Route::post('updateOrgLog','updateOrgLog');
    Route::post('deleteOrgLog','deleteOrgLog');
    Route::post('searchOrgLog','searchOrgLog');
  
});

Route::controller(ProgramController::class)->group(function () {

    Route::post('getProgram','getProgram');

});

Route::controller(EventController::class)->group(function () {

    Route::post('getEvent','getEvent');
    Route::get('getAcademicYear','getAcademicYear');
    Route::post('viewEvent','viewEvent');
    Route::post('storeEvent','storeEvent');
    Route::post('editEvent','editEvent');
    Route::post('updateEvent','updateEvent');
    Route::post('deleteEvent','deleteEvent');
    Route::post('searchEvent','searchEvent');
  
});

Route::controller(RequirementController::class)->group(function () {

    Route::post('getRequirement','getRequirement');
    Route::post('deleteRequirement','deleteRequirement');
});

Route::controller(FileRequirementController::class)->group(function () {

    Route::post('getFileRequirement','getFileRequirement');
    Route::post('storeFileRequirement','storeFileRequirement');
    Route::post('storeFolderRequirement','storeFolderRequirement');
    Route::post('downloadFileRequirement','downloadFileRequirement');    
  
});

Route::controller(ConversationController::class)->group(function () {
    Route::post('storeConverstation','storeConverstation');
    Route::post('getConvesation','getConvesation');
});








