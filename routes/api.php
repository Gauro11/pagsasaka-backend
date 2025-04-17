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
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShipmentController;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SalesController;

use App\Http\Controllers\RiderController;


use App\Http\Controllers\CodOrderController;


use App\Http\Controllers\ChatSessionController;
use App\Http\Controllers\MessagesController;

use App\Http\Controllers\RatingController;
use App\Http\Controllers\AddressController;

//connection//
Route::options('/{any}', function (Request $request) {
    return response()->json(['status' => 'OK'], 200);
})->where('any', '.*');


Route::post('shipments', [ShipmentController::class, 'addShipment']);
Route::middleware('auth:sanctum')->get('getOrders', [ShipmentController::class, 'getOrders']);
Route::middleware('auth:sanctum')->get('cancelled', [ShipmentController::class, 'getCancelledOrders']);
Route::middleware('auth:sanctum')->get('refund', [ShipmentController::class, 'getRefundOrders']);
Route::middleware('auth:sanctum')->post('updateOrderStatus/{id}', [ShipmentController::class, 'updateOrderStatus']);
Route::middleware('auth:sanctum')->post('confirmOrderReceived/{id}', [ShipmentController::class, 'confirmOrderReceived']);
Route::middleware('auth:sanctum')->get('orders-for-pickup', [ShipmentController::class, 'getOrdersForPickup']);
Route::middleware('auth:sanctum')->post('pickupOrder/{id}', [ShipmentController::class, 'pickupOrder']);
Route::middleware('auth:sanctum')->post('uploadDeliveryProof/{id}', [ShipmentController::class, 'uploadDeliveryProof']);
Route::middleware('auth:sanctum')->get('orders-in-transit', [ShipmentController::class, 'getInTransitOrders']);
Route::middleware('auth:sanctum')->get('get-delivery-proof/{id}', [ShipmentController::class, 'getDeliveryProofByOrderId']);
Route::middleware('auth:sanctum')->post('cancel-order/{id}', [ShipmentController::class, 'cancelOrder']);
Route::middleware('auth:sanctum')->get('cancellation-reasons', [ShipmentController::class, 'getCancellationReasons']);
Route::middleware('auth:sanctum')->post('refund/{order_id}', [ShipmentController::class, 'requestRefundByOrderId']);
Route::middleware('auth:sanctum')->post('refundapprove/{order_id}', [ShipmentController::class, 'approveRefundRequest']);




Route::middleware('auth:sanctum')->get('my-placed-orders', [ShipmentController::class, 'getPlacedOrders']);


Route::middleware('auth:sanctum')->get('my-products', [ProductController::class, 'getMyPublishedProducts']);









Route::get('/rider/{id}', [RiderController::class, 'getRiderProfile']);
Route::post('rider/apply', [RiderController::class, 'applyRider']);
Route::post('rider/approve/{id}', [RiderController::class, 'approveRider']);
Route::get('riders/pending', [RiderController::class, 'getPendingRiders']);
Route::post('ridersinvalidate/{id}', [RiderController::class, 'invalidateRider']);










Route::post('create', [QuestionController::class, 'createQuestion']);
Route::get('questions', [QuestionController::class, 'getAllQuestions']);


Route::get('dropdown-roles', [RoleController::class, 'getRoles']);
Route::post('roles/add', [RoleController::class, 'addRole']);
Route::post('verify-otp', [AccountController::class, 'verifyOTP']);



Route::post('send-email', [EmailController::class, 'sendEmail']);


// Route::post('academic-year/create', [AcademicYearController::class, 'addAcademicYear']);
// Route::post('academic-year/deactivate/{id}', [AcademicYearController::class, 'deactivateAcademicYear']);
// Route::post('academic-years/update-status', [AcademicYearController::class, 'updateAcademicYearStatus']);
// Route::post('academic-years', [AcademicYearController::class, 'getAcademicYear']);





///////////////////////////////////LOGIN//////////////////////////////////////////
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('session', 'insertSession');
    Route::post('profile/update/{id}', 'profileUpdate');
    Route::post('password/change/{id}', 'changePassword');
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);


Route::post('password/reset', [AccountController::class, 'resetPasswordToDefault'])->middleware('auth:sanctum');


//create acc///
Route::controller(AccountController::class)->group(function () {
    Route::get('accounts', 'getAccounts');
    Route::post('account/add', 'register');
    Route::post('account/update/{id}', 'updateAccount');
    Route::post('account/deactivate/{id}', 'deactivateAccount');
    Route::post('account/update-password/{id}', 'updatePassword');
});
Route::get('/organization-logs', [AccountController::class, 'getOrganizationLogs']);

Route::prefix('category')->group(function () {

    Route::post('create', [CategoryController::class, 'createCategory']);
    Route::post('edit/{id}', [CategoryController::class, 'editCategory']);
    Route::get('list', [CategoryController::class, 'getCategory']);
    Route::post('delete/{id}', [CategoryController::class, 'deleteCategory']);

});

Route::prefix('product')->middleware('auth:sanctum')->group(function () {
    Route::post('add', [ProductController::class, 'addProduct']);
    Route::post('edit/{id}', [ProductController::class, 'editProduct']);
    Route::post('account', [ProductController::class, 'getProductsByAccountId']);
    Route::post('delete/{id}', [ProductController::class, 'deleteProduct']);
    Route::post('cart/{id}', [ProductController::class, 'addToCart']);
    Route::post('cart-list', [ProductController::class, 'getCartList']);                                                                
    Route::post('cart-remove/{id}', [ProductController::class, 'deleteFromCart']);
    Route::post('buynow/{id}', [ProductController::class, 'buyNow']);
    Route::post('checkout-preview', [ProductController::class, 'getCheckoutPreview']);
    Route::get('cart-item-details/{id}', [ProductController::class, 'getCartItemDetails']);
    Route::get('list-cart-status', [ProductController::class, 'getCartListStatus']);
    Route::post('checkout/item/{id}', [ProductController::class, 'checkoutItem']);
});

Route::post('list', [ProductController::class, 'getAllProductsList']);
Route::post('product-list-id', [ProductController::class, 'getAllProductbyId']);
Route::get('by-id/{id}', [ProductController::class, 'getProductById']);

Route::prefix('dropdown')->group(function () {
    Route::get('category', [CategoryController::class, 'dropdownCategory']);
});

// Redirect old route to new route
Route::middleware('auth:sanctum')->post('checkout-preview', function () {
    return redirect()->route('checkout');
});

// In routes/api.php payment
Route::post('pay/{account_id}/{product_id}', [PaymentController::class, 'payment']);

Route::get('success', [PaymentController::class, 'success']);

Route::get('/payment/success/{productId}', [PaymentController::class, 'paymentSuccess']);
Route::get('/payment/cancel', [PaymentController::class, 'paymentCancel']);
Route::post('/products/{productId}/pay', [PaymentController::class, 'payForProduct']);

Route::post('/payment/pay-link', [PaymentController::class, 'createMultipleItemsPayLink']);
Route::get('/payment/pay-link/{linkId}', [PaymentController::class, 'checkMultiPayLinkStatus']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sales', [SalesController::class, 'index']);
});

Route::post('/paymongo/webhook', [PaymentController::class, 'handlePaymongoWebhook']);






// final payment cod and gcash and maya gateway
// Route::post('/orders/cod', [CODOrderController::class, 'createCODOrder']);
Route::middleware('auth:sanctum')->post('/orders/cod', [CodOrderController::class, 'createCODOrder']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('pay', [PaymentController::class, 'payment']);
});









Route::middleware('auth:sanctum')->group(function () {
    // Chat Sessions
    Route::get('/chat-sessions-lists', [ChatSessionController::class, 'index']); // fetch chatlist
    Route::post('/chatnow', [ChatSessionController::class, 'store']); // start new chat
    Route::get('/chat-view/{id}', [ChatSessionController::class, 'show']); // view conversation
    Route::delete('/chat-delete/{id}', [ChatSessionController::class, 'destroy']); // delete conversation

    // Messages
    Route::post('/send/{conversation_id}/messages', [MessagesController::class, 'store']); // send messsage
    Route::post('/messages/read', [MessagesController::class, 'markAsRead']); // read message 
    Route::get('/messages/unread', [MessagesController::class, 'unreadCount']); //undread message
    Route::delete('/messages/delete{id}', [MessagesController::class, 'destroy']); //delete message
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products/{product}/ratings', [RatingController::class, 'store'])->name('ratings.store');
    Route::get('/products/{product}/ratings', [RatingController::class, 'index'])->name('ratings.index');
});

Route::prefix('billing-address')->middleware('auth:sanctum')->group(function () {
    Route::post('add', [AccountController::class, 'addBillingAddress']);
    Route::post('edit/{id}', [AccountController::class, 'editBillingAddress']);
    Route::post('remove/{id}', [AccountController::class, 'removeBillingAddress']);
    Route::get('get', [AccountController::class, 'listBillingAddress']);
});

// other routes...
/*
Route::middleware(['auth:sanctum', 'session.expiry'])->group(function () {
    Route::get('/some-protected-route', [AuthController::class, 'someMethod']);
});*/

// Route::post('/sample', function (Request $request) {
//     return $request->requirements;
// });

// Route::controller(OrgLogController::class)->group(function () {
//     Route::post('organizations', 'getOrganization');
//     Route::post('dropdown-organization', 'getDropdownOrganization');
//     Route::post('organization/create', 'createOrganization');
//     Route::post('organization/update', 'updateOrganization');
//     Route::post('organization/delete', 'deleteOrganization');
//     Route::post('organization-status/update', 'updateOrganizationStatus');
//     Route::post('programs/filter', 'getFilteredPrograms');
//     Route::get('dropdown-office-program', 'getConcernedOfficeProgram');
// });
//

// Route::controller(EventController::class)->group(function () {
//     Route::get('active-event', 'getActiveEvent'); // ADMIN AND STAFF ONLY
//     Route::post('all-events', 'getEvent');
//     Route::post('event-details', 'eventDetails');
//     Route::post('event/create', 'createEvent');
//     Route::post('event/update', 'updateEvent');
//     Route::post('event/delete', 'deleteEvent');
//     Route::post('event-status', 'eventApprovalStatus');
// });

// Route::controller(RequirementController::class)->group(function () {
//     Route::post('requirements', 'getRequirement');
//     Route::post('requirement/update', 'updateRequirement');
//     Route::post('requirement/delete', 'deleteRequirement');
// });

// Route::controller(RequestController::class)->group(function () {
//     Route::post('request', 'getRequest');
//     Route::post('request/create', 'createRequest');
//     Route::post('request/reject', 'rejectRequest');
//     Route::post('request/accept', 'getAcceptRequest');
//     Route::post('requirement-information', 'getRequestInformation');
// });

// Route::controller(DashboardController::class)->group(function () {
//     Route::post('dashboard/admin', 'getAdminDashboard');
//     Route::post('dashboard/dean', 'getDeanDashboard');
//     Route::post('dashboard/program-chair', 'getProgramDashboard');
//     Route::post('dashboard/head', 'getHeadDashboard');

//     Route::post('dashboard/document-request', 'getDocumentRequestDashboard');
//     Route::post('dashboard/recent-upload', 'getRecentUploadDashboard');
//     Route::post('dashboard/compliance', 'getComplianceDashboard');

// });


// Route::controller(FileRequirementController::class)->group(function () {


//     Route::post('files-requirement', 'getFileRequirement');

//     Route::post('file-requirement/create', 'createFileRequirement');
//     Route::post('file-requirement/download', 'downloadFileRequirement');
//     Route::post('folder-requirement/create', 'createFolderRequirement');

//     Route::post('dmo/files', 'getAllfile'); // ADMIN AND STAFF ONLY
//     Route::post('dmo/files/upload', 'uploadDMOFiles');
//     Route::post('dmo/folder/create', 'createDMOFolder');

//     Route::post('file-folder/update', 'updateFileOrFolder');
//     Route::post('file-folder/delete', 'deleteFileFolder');

//     Route::post('confirmation', 'confirmationForEditDelete');
//     Route::post('files-inside-folder', 'getFilesInsideFolder');

// });

// Route::controller(ConversationController::class)->group(function () {
//     Route::post('storeConverstation', 'createConverstation');
//     Route::post('getConvesation', 'getConvesation');
// });




// Route::controller(ReportController::class)->group(function () {

//     Route::post('report-request', 'getReportRequest');
//     Route::post('compliance-report', 'getComplianceReport');
// });

// Route::controller(HistoryDocumentController::class)->group(function () {

//     Route::post('history', 'history');

// });


//seller dasboard 
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products/all', [ProductController::class, 'getAllProducts']);
});