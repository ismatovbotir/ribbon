<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ParameterController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PriceController;
use App\Http\Controllers\Admin\RateController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('Dashboard');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::group(['prefix' => 'crm', 'as' => 'crm.', 'middleware' => 'auth'], function () {
    //global

    Route::resource('/country', CountryController::class);
    Route::resource('/region', RegionController::class);
    Route::resource('/city', CityController::class);
    Route::resource('/address', AddressController::class);


    Route::resource('/category', CategoryController::class);
    Route::resource('/parameter', ParameterController::class);


    //crm
    Route::resource('/rate', RateController::class);
    Route::resource('/company', CompanyController::class);
    Route::resource('/user', UserController::class);

    Route::resource('/product', ProductController::class);
    Route::resource('/price', PriceController::class);
});
