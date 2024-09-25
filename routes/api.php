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
use App\Http\Controllers\ProgramController;



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








