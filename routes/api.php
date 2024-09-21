<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

Route::post('/sample',function(Request $request){
    return $request->requirements;
});


Route::controller(BaseController::class)->group(function () {
Route::post('createCustomer', 'createCustomer');
Route::post('createCustomer', 'updateCustomer');
Route::get('getUsers', 'getUsers');

});

Route::controller(AccountController::class)->group(function () {
   
    Route::get('getAccounts', 'getAccounts');
    Route::post('searchAccount', 'searchAccount');
    Route::post('createAccoount', 'storeAccount');
    Route::post('editAccount','editAccount');
    Route::post('updateAccoount', 'updateAccount');
    Route::post('deleteAccount','deleteAccount');
});

Route::controller(OrgLogController::class)->group(function () {

    Route::get('paginateOrgLog','paginateOrgLog');
    Route::post('getOrgLog','getOrgLog');
    Route::post('storeOrgLog','storeOrgLog');
    Route::post('editOrgLog','editOrgLog');
    Route::post('updateOrgLog','updateOrgLog');
    Route::post('deleteOrgLog','deleteOrgLog');
    Route::post('searchOrgLog','searchOrgLog');
  
});

Route::controller(EventController::class)->group(function () {

  //  Route::get('paginateOrgLog','paginateOrgLog');
   // Route::post('getOrgLog','getOrgLog');
    Route::get('getAcademicYear','getAcademicYear');
    Route::post('viewEvent','viewEvent');
    Route::post('storeEvent','storeEvent');
    Route::post('editEvent','editEvent');
    Route::post('updateEvent','updateEvent');
    Route::post('deleteEvent','deleteEvent');
    Route::post('getEvent','getEvent');
    Route::post('searchEvent','searchEvent');
  
});

Route::controller(RequestController::class)->group(function () {
    Route::get('/','index');
 //   Route::post('searchAccount', 'searchAccount');
      Route::post('createRequest', 'storeRequest');
  //  Route::post('updateAccoount/{account}', 'updateAccount');
   // Route::post('deleteAccount/{account}','deleteAccount');
    
});

Route::controller(RequirementController::class)->group(function () {

    Route::post('getRequirement','getRequirement');
    // Route::post('getOrgLog','getOrgLog');
    // Route::post('storeOrgLog','storeOrgLog');
    // Route::post('editOrgLog','editOrgLog');
    // Route::post('updateOrgLog','updateOrgLog');
    // Route::post('deleteOrgLog','deleteOrgLog');
    Route::post('viewRequirement','viewRequirement');
    Route::post('viewRequirement','viewRequirement');
    Route::post('searchRequirement','searchRequirement');
    Route::post('deleteRequirement','deleteRequirement');
  
});

Route::controller(FileRequirementController::class)->group(function () {
    Route::post('storeFileRequirement','storeFileRequirement');
    Route::post('storeFolderRequirement','storeFolderRequirement');
    Route::post('searchFileRequirement','searchFileRequirement');
    Route::post('getFileRequirement','getFileRequirement');
  
});

Route::controller(ConversationController::class)->group(function () {
    Route::post('storeConverstation','storeConverstation');
    Route::post('getConvesation','getConvesation');
    
    // Route::post('storeFolderRequirement','storeFolderRequirement');
    // Route::post('searchFileRequirement','searchFileRequirement');
    // Route::post('getFileRequirement','getFileRequirement');
});



Route::controller(OrgController::class)->group(function () {

      Route::post('storeOrg', 'storeOrg');
    
});


Route::controller(RoleController::class)->group(function () {

    Route::post('storeRole', 'storeRole');
  
});


