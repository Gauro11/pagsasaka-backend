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
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\HistoryDocumentController;






Route::post('send-email', [EmailController::class, 'sendEmail']);


Route::post('academic-year/create', [AcademicYearController::class, 'addAcademicYear']);
Route::post('academic-year/deactivate/{id}', [AcademicYearController::class, 'deactivateAcademicYear']);
Route::post('academic-years', [AcademicYearController::class, 'getAcademicYear']);


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


Route::post('password/reset', [AccountController::class, 'resetPasswordToDefault'])->middleware('auth:sanctum');


//create acc///
Route::controller(AccountController::class)->group(function () {
    Route::post('accounts', 'getAccounts');
    Route::post('account/create', 'createAccount');
    Route::post('account/update/{id}', 'updateAccount');
    Route::post('account/deactivate/{id}', 'deactivateAccount');
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
    Route::post('organizations', 'getOrganization');
    Route::post('dropdown-organization', 'getDropdownOrganization');
    Route::post('organization/create', 'createOrganization');
    Route::post('organization/update', 'updateOrganization');
    Route::post('organization/delete', 'deleteOrganization');
    Route::post('organization-status/update', 'updateOrganizationStatus');
    Route::post('programs/filter', 'getFilteredPrograms');
    Route::get('dropdown-office-program', 'getConcernedOfficeProgram');
});


Route::controller(EventController::class)->group(function () {
    Route::get('active-event', 'getActiveEvent'); // ADMIN AND STAFF ONLY
    Route::post('all-events', 'getEvent');
    Route::post('event-details', 'eventDetails');
    Route::post('event/create', 'createEvent');
    Route::post('event/update', 'updateEvent');
    Route::post('event/delete', 'deleteEvent');
    Route::post('event-status', 'eventApprovalStatus');
});

Route::controller(RequirementController::class)->group(function () {
    Route::post('requirements', 'getRequirement');
    Route::post('requirement/update', 'updateRequirement');
    Route::post('requirement/delete', 'deleteRequirement');
});

Route::controller(RequestController::class)->group(function () {
    Route::post('request', 'getRequest');
    Route::post('request/create', 'createRequest');
    Route::post('request/reject', 'rejectRequest');
    Route::post('request/accept', 'getAcceptRequest');
    Route::post('requirement-information', 'getRequestInformation');
});

Route::controller(DashboardController::class)->group(function () {
    Route::post('dashboard/admin', 'getAdminDashboard');
    Route::post('dashboard/dean', 'getDeanDashboard');
    Route::post('dashboard/program-chair', 'getProgramDashboard');
    Route::post('dashboard/head', 'getHeadDashboard');

    Route::post('document-request-dashboard', 'getDocumentRequestDashboard');
    Route::post('compliance-dashboard', 'getComplianceDashboard');
    Route::post('recent-upload-dashboard', 'getRecentUploadDashboard');
});


Route::controller(FileRequirementController::class)->group(function () {

    Route::post('all-files', 'getAllfile'); // ADMIN AND STAFF ONLY
    Route::post('files-requirement', 'getFileRequirement');

    Route::post('file-requirement/create', 'createFileRequirement');
    Route::post('file-requirement/download', 'downloadFileRequirement');
    Route::post('folder-requirement/create', 'createFolderRequirement');


    Route::post('dmo-files/create', 'createDMOFiles');
    Route::post('dmo-folder/create', 'createDMOFolder');

    Route::post('file-folder/update', 'updateFileOrFolder');
    Route::post('file-folder/delete', 'deleteFile');

    Route::post('confirmation', 'confirmationForEditDelete');
    Route::post('files-inside-folder', 'getFilesInsideFolder');

});

Route::controller(ConversationController::class)->group(function () {
    Route::post('storeConverstation', 'createConverstation');
    Route::post('getConvesation', 'getConvesation');
});




Route::controller(ReportController::class)->group(function () {

    Route::post('report-request', 'getReportRequest');
    Route::post('compliance-report', 'getComplianceReport');
});

Route::controller(HistoryDocumentController::class)->group(function () {

    Route::post('history', 'history');

});
