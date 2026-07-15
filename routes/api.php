<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CashierController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SubcategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\WhatsappController;
use App\Http\Controllers\Api\PurchaseController;

// ── AUTH ROUTES ──
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot_password', [AuthController::class, 'forgotPassword']);
    Route::post('send_otp', [AuthController::class, 'sendOtp']);
    Route::post('send_otp_for_credit', [AuthController::class, 'sendOtpForCredit']);
    Route::post('verify_otp', [AuthController::class, 'verifyOtp']);
    Route::post('logout', [AuthController::class, 'logout']);
});

// ── ADMIN ROUTES ──
Route::prefix('admin')->group(function () {
    Route::post('change_password', [AdminController::class, 'changePassword']);
    Route::post('create_admin', [AdminController::class, 'createAdmin']);
    Route::get('get_admins', [AdminController::class, 'getAdmins']);
    Route::get('get_admin_by_id', [AdminController::class, 'getAdminById']);
    Route::post('toggle_status_admin', [AdminController::class, 'toggleStatusAdmin']);
    Route::post('update_admin', [AdminController::class, 'updateAdmin']);
});

// ── BRAND ROUTES ──
Route::prefix('brand')->group(function () {
    Route::post('create', [BrandController::class, 'create']);
    Route::get('get_active_brand', [BrandController::class, 'getActiveBrand']);
    Route::get('get_all', [BrandController::class, 'getAll']);
    Route::get('get_by_id', [BrandController::class, 'getById']);
    Route::post('status_toggle', [BrandController::class, 'statusToggle']);
    Route::post('update', [BrandController::class, 'update']);
});

// ── CASHIER ROUTES ──
Route::prefix('cashier')->group(function () {
    Route::post('delete_cashier', [CashierController::class, 'deleteCashier']);
    Route::post('get_cashiers', [CashierController::class, 'getCashiers']);
    Route::get('get_cashier_by_id', [CashierController::class, 'getCashierById']);
    Route::post('toggle_status_cashier', [CashierController::class, 'toggleStatusCashier']);
    Route::post('update_cashier', [CashierController::class, 'updateCashier']);
});

// ── CASHIER REQUESTS ROUTES ──
Route::prefix('CashierRequest')->group(function () {
    Route::post('approve_cashier_request', [CashierController::class, 'approveCashierRequest']);
    Route::get('get_cashier_requests', [CashierController::class, 'getCashierRequests']);
    Route::post('reject_cashier_request', [CashierController::class, 'rejectCashierRequest']);
});

// ── CATEGORY ROUTES ──
Route::prefix('category')->group(function () {
    Route::post('create', [CategoryController::class, 'create']);
    Route::post('delete', [CategoryController::class, 'delete']);
    Route::get('get_active_category', [CategoryController::class, 'getActiveCategory']);
    Route::get('get_all', [CategoryController::class, 'getAll']);
    Route::get('get_by_id', [CategoryController::class, 'getById']);
    Route::post('toggle_category_status', [CategoryController::class, 'toggleCategoryStatus']);
    Route::post('update', [CategoryController::class, 'update']);
});

// ── COMPANY ROUTES ──
Route::prefix('company')->group(function () {
    Route::post('add_company', [CompanyController::class, 'addCompany']);
    Route::post('delete_company', [CompanyController::class, 'deleteCompany']);
    Route::get('get_companies', [CompanyController::class, 'getCompanies']);
    Route::get('get_companies_by_admin', [CompanyController::class, 'getCompaniesByAdmin']);
    Route::post('get_company_by_id', [CompanyController::class, 'getCompanyById']);
    Route::post('toggle_company_status', [CompanyController::class, 'toggleCompanyStatus']);
    Route::post('update_company', [CompanyController::class, 'updateCompany']);
});

// ── COMPANY REQUESTS ROUTES ──
Route::prefix('CompanyRequest')->group(function () {
    Route::post('approve_company_request', [CompanyController::class, 'approveCompanyRequest']);
    Route::get('get_company_requests', [CompanyController::class, 'getCompanyRequests']);
    Route::post('reject_company_request', [CompanyController::class, 'rejectCompanyRequest']);
});

// ── CREDIT ROUTES ──
Route::prefix('credit')->group(function () {
    Route::get('get', [CreditController::class, 'get']);
    Route::post('save', [CreditController::class, 'save']);
});

// ── CUSTOMER ROUTES ──
Route::prefix('customer')->group(function () {
    Route::post('create_customer', [CustomerController::class, 'createCustomer']);
    Route::post('customer_save', [CustomerController::class, 'customerSave']);
    Route::get('customer_search', [CustomerController::class, 'customerSearch']);
    Route::post('delete', [CustomerController::class, 'delete']);
    Route::get('get_all_customer', [CustomerController::class, 'getAllCustomer']);
    Route::get('get_by_phone', [CustomerController::class, 'getByPhone']);
    Route::get('get_customer_by_id', [CustomerController::class, 'getCustomerById']);
    Route::post('toggle_status_customer', [CustomerController::class, 'toggleStatusCustomer']);
    Route::post('update', [CustomerController::class, 'update']);
});

// ── DASHBOARD ROUTES ──
Route::prefix('dashboard')->group(function () {
    Route::get('get_admin_overdue_notifications', [DashboardController::class, 'getAdminOverdueNotifications']);
    Route::get('get_analytics', [DashboardController::class, 'getAnalytics']);
    Route::get('get_dashboard', [DashboardController::class, 'getDashboard']);
    Route::get('get_stats', [DashboardController::class, 'getStats']);
    Route::get('get_unsold_products_notification', [DashboardController::class, 'getUnsoldProductsNotification']);
});

// ── INVOICE ROUTES ──
Route::prefix('invoice')->group(function () {
    Route::post('create_invoice', [InvoiceController::class, 'createInvoice']);
    Route::get('get_all_invoice', [InvoiceController::class, 'getAllInvoice']);
    Route::get('get_filtered_invoices', [InvoiceController::class, 'getFilteredInvoices']);
    Route::get('get_filtered_pending', [InvoiceController::class, 'getFilteredPending']);
    Route::get('get_invoice_by_id', [InvoiceController::class, 'getInvoiceById']);
    Route::get('get_pending_invoice', [InvoiceController::class, 'getPendingInvoice']);
    Route::get('get_pending_invoice_history', [InvoiceController::class, 'getPendingInvoiceHistory']);
    Route::post('mark_as_paid', [InvoiceController::class, 'markAsPaid']);
    Route::post('payment', [InvoiceController::class, 'payment']);
    Route::post('update_credit_payment', [InvoiceController::class, 'updateCreditPayment']);
    Route::post('pay_customer_bulk', [InvoiceController::class, 'payCustomerBulk']);
    Route::get('get_customer_payments', [InvoiceController::class, 'getCustomerPayments']);
    Route::get('verify_gst', [InvoiceController::class, 'verifyGst']);
});

// ── PRODUCT ROUTES ──
Route::prefix('product')->group(function () {
    Route::post('add', [ProductController::class, 'add']);
    Route::post('delete', [ProductController::class, 'delete']);
    Route::get('get', [ProductController::class, 'get']);
    Route::get('get_by_id', [ProductController::class, 'getById']);
    Route::get('get_by_supplier', [ProductController::class, 'getBySupplier']);
    Route::get('get_by_code', [ProductController::class, 'getByCode']);
    Route::post('toggle_status_product', [ProductController::class, 'toggleStatusProduct']);
    Route::post('update', [ProductController::class, 'update']);
});

// ── SUBCATEGORY ROUTES ──
Route::prefix('subcategory')->group(function () {
    Route::post('create', [SubcategoryController::class, 'create']);
    Route::get('get_active_subcategory', [SubcategoryController::class, 'getActiveSubcategory']);
    Route::get('get_all', [SubcategoryController::class, 'getAll']);
    Route::get('get_by_id', [SubcategoryController::class, 'getById']);
    Route::post('statustoggle', [SubcategoryController::class, 'statusToggle']);
    Route::post('update', [SubcategoryController::class, 'update']);
});

// ── SUPPLIER ROUTES ──
Route::prefix('supplier')->group(function () {
    Route::post('create', [SupplierController::class, 'create']);
    Route::get('get_all', [SupplierController::class, 'getAll']);
    Route::get('get_by_id', [SupplierController::class, 'getById']);
    Route::post('toggle_supplier_status', [SupplierController::class, 'toggleSupplierStatus']);
    Route::post('update', [SupplierController::class, 'update']);
});

// ── SUPPLIER PRODUCT ROUTES ──
Route::prefix('supplier_product')->group(function () {
    Route::post('add', [SupplierProductController::class, 'add']);
    Route::get('get_by_id', [SupplierProductController::class, 'getById']);
    Route::get('get_by_supplier', [SupplierProductController::class, 'getBySupplier']);
    Route::post('update', [SupplierProductController::class, 'update']);
});

// ── WHATSAPP ROUTES ──
Route::prefix('whatsapp')->group(function () {
    Route::post('send_reminder', [WhatsappController::class, 'sendReminder']);
});

// ── PURCHASE ROUTES ──
Route::prefix('purchase')->group(function () {
    Route::post('validate_items', [PurchaseController::class, 'validateItems']);
    Route::post('save_draft', [PurchaseController::class, 'saveDraft']);
    Route::post('submit_purchase', [PurchaseController::class, 'submitPurchase']);
    Route::get('get_purchases', [PurchaseController::class, 'getPurchases']);
    Route::get('get_purchase_by_id', [PurchaseController::class, 'getPurchaseById']);
    Route::post('delete_purchase', [PurchaseController::class, 'deletePurchase']);
    Route::post('pay_purchase', [PurchaseController::class, 'payPurchase']);
    Route::get('get_payments', [PurchaseController::class, 'getPurchasePayments']);
    Route::get('get_supplier_payments', [PurchaseController::class, 'getSupplierPayments']);
    Route::post('pay_supplier_bulk', [PurchaseController::class, 'paySupplierBulk']);
});

