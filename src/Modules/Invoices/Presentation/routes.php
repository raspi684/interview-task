<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Presentation\Http\InvoiceController;

Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->whereUuid('id')->name('invoices.show');
Route::post('/invoices/{id}/send', [InvoiceController::class, 'send'])->whereUuid('id')->name('invoices.send');
