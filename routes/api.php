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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\EmailController;

Route::post('send-email', [EmailController::class, 'sendEmail']);

//connection//
Route::options('/{any}', function (Request $request) {
    return response()->json(['status' => 'OK'], 200);
})->where('any', '.*');


///////////////////////////////////LOGIN//////////////////////////////////////////
Route::controller(AuthController::class)->group(function () {
    Route::post('login',  'login');
    Route::post('session',  'insertSession');
    Route::post('change-password/{id}',  'changePassword');
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);


Route::post('/reset-password-to-default', [AccountController::class, 'resetPasswordToDefault'])->middleware('auth:sanctum');


Route::middleware(['auth:sanctum', 'UserTypeAuth'])->group(function () {
    Route::get('/admin/dashboard', [AuthController::class, 'admin']);
    Route::get('/head/dashboard', [AuthController::class, 'head']);
    Route::get('/programchair/dashboard', [AuthController::class, 'programchair']);
    Route::get('/staff/dashboard', [AuthController::class, 'staff']);
    Route::get('/dean/dashboard', [AuthController::class, 'dean']);
});

//create acc///
Route::controller(AccountController::class)->group(function () {
    Route::post('getAccounts', 'getAccounts');
    Route::post('Add', 'createAccount');
    Route::post('updateAccounts/{id}', 'updateAccount');
    Route::post('/softdelete/{id}', 'changeStatusToInactive');
});
Route::get('/organization-logs', [AccountController::class, 'getOrganizationLogs']);

/*
Route::middleware(['auth:sanctum', 'session.expiry'])->group(function () {
    Route::get('/some-protected-route', [AuthController::class, 'someMethod']);
});*/

Route::post('/sample', function (Request $request) {
    return $request->requirements;
});

Route::controller(OrgLogController::class)->group(function () {

    Route::post('getOrgLog', 'getOrgLog');
    Route::post('getDropdownOrg','getDropdownOrg');
    Route::post('storeOrgLog', 'storeOrgLog');
    Route::post('editOrgLog', 'editOrgLog');
    Route::post('updateOrgLog', 'updateOrgLog');
    Route::post('deleteOrgLog', 'deleteOrgLog');
    Route::post('searchOrgLog', 'searchOrgLog');
    Route::post('filterCollege', 'filterCollege');
});

Route::controller(ProgramController::class)->group(function () {

    Route::post('getProgram', 'getProgram');
});

Route::controller(EventController::class)->group(function () {

    Route::get('getActiveEvent', 'getActiveEvent'); // ADMIN AND STAFF ONLY
    Route::post('getEvent', 'getEvent');
    Route::get('getAcademicYear', 'getAcademicYear');
    Route::post('viewEvent', 'viewEvent');
    Route::post('storeEvent', 'storeEvent');
    Route::post('editEvent', 'editEvent');
    Route::post('updateEvent', 'updateEvent');
    Route::post('deleteEvent', 'deleteEvent');
    Route::post('searchEvent', 'searchEvent');
});

Route::controller(RequirementController::class)->group(function () {
    Route::post('getRequirement', 'getRequirement');
    Route::post('deleteRequirement', 'deleteRequirement');
});

Route::controller(FileRequirementController::class)->group(function () {

    Route::get('getAllfile', 'getAllfile');
    Route::post('getFileRequirement', 'getFileRequirement');
    Route::post('storeFileRequirement', 'storeFileRequirement');
    Route::post('storeFolderRequirement', 'storeFolderRequirement');
    Route::post('storeDMO_files', 'storeDMO_files');
    Route::post('createDMO_folder', 'createDMO_folder');
    Route::post('downloadFileRequirement', 'downloadFileRequirement');
});

Route::controller(ConversationController::class)->group(function () {
    Route::post('storeConverstation', 'storeConverstation');
    Route::post('getConvesation', 'getConvesation');
});

Route::controller(RequestController::class)->group(function () {
    Route::post('rejectRequest', 'rejectRequest');
    Route::post('storeRequest', 'storeRequest');
    Route::post('getRequest', 'getRequest');
    Route::post('getReqInfo', 'getReqInfo');
    Route::post('getAcceptRequest', 'getAcceptRequest');
});

Route::controller(DashboardController::class)->group(function () {
    Route::get('getAdminDashboard', 'getAdminDashboard');
    Route::post('getDeanDashboard', 'getDeanDashboard');
    Route::post('getProgramDashboard', 'getProgramDashboard');
    Route::post('getHeadDashboard', 'getHeadDashboard');

    Route::post('getDocumentRequest', 'getDocumentRequest');
    Route::post('getCompliance', 'getCompliance');
    Route::post('getRecentUpload', 'getRecentUpload');
});

Route::controller(ReportController::class)->group(function () {

    Route::post('getReportRequest', 'getReportRequest');
    Route::post('getComplianceReport', 'getComplianceReport');
});
