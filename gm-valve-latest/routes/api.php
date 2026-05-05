<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::post('/device-name', [AuthController::class, 'getDeviceName']);
Route::get('/force-logout', [AuthController::class, 'forceLogout']);

Route::middleware(['auth:sanctum', 'check.activity'])->group(function () {
    Route::post('/order-list', [OrderController::class, 'orderList']);
    Route::post('/upload-order-file', [OrderController::class, 'uploadOrderFile']);
    Route::get('/order/{order_id}', [OrderController::class, 'getOrderDetail']);
    Route::post('/mark-urgent', [OrderController::class, 'markUrgent']);
    Route::post('/assign-order', [OrderController::class, 'assignOrder']);
    Route::post('/add-remarks', [OrderController::class, 'addRemarks']);
    Route::post('/customer-support', [OrderController::class, 'customerSupport']);
    Route::post('/order-counts', [OrderController::class, 'orderCounts']);
    Route::post('/add-delivery-date', [OrderController::class, 'updateDeliveryDate']);
    Route::post('/order-history', [OrderController::class, 'orderHistory']);
    Route::post('/get-svs-orders', [OrderController::class, 'getAssemblyCSVSSplits']);
    Route::post('dispatch-to-packaging', [OrderController::class, 'dispatchToPackaging']);
    Route::post('change-to-packaging', [OrderController::class, 'changeToPackaging']);
    Route::post('/packaging-orders', [OrderController::class, 'packagingOrders']);



});
