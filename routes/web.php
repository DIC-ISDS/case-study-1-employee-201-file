<?php

use App\Http\Controllers\Reports\PersonnelMasterListController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')
    ->get('/reports/personnel-master-list', PersonnelMasterListController::class)
    ->name('reports.personnel-master-list');
