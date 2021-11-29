<?php

/** @var \Laravel\Lumen\Routing\Router $router */
/*
  |--------------------------------------------------------------------------
  | Application Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register all the routes for an application.
  | It is a breeze. Simply tell Lumen the URIs it should respond to
  | and give it the Closure to call when that URI is requested.
  |
 */

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group([
    'prefix' => 'api'
        ], function ($router) {
    $router->post('registration', 'AuthController@registration');
    $router->post('login', 'AuthController@login');
    $router->post('logout', 'AuthController@logout');
    $router->post('refresh', 'AuthController@refresh');
    $router->post('user/update', 'AuthController@update');
    $router->post('user/updatePassword', 'AuthController@updatePassword');
    $router->post('user/recoveryPassword', 'AuthController@recoveryPassword');

    $router->post('timeStore', 'TimeScheduleController@timeStore');
    $router->post('history', 'TimeScheduleController@history');
    $router->post('monthlyReport', 'TimeScheduleController@monthlyReport');
    $router->post('dayWiseReport', 'TimeScheduleController@dayWiseReport');
    $router->post('monthWiseReport', 'TimeScheduleController@monthWiseReport');

    $router->post('home', 'HomeController@index');
    $router->get('rulesAndRegulation', 'HomeController@rulesAndRegulation');
    $router->get('termsAndCondition', 'HomeController@termsAndCondition');
    $router->get('allUser', 'HomeController@allUser');

    $router->post('saveNotification', 'PushNotificationController@saveNotification');

    $router->post('store/device', 'NotificationController@store');
    $router->get('get/user/notification', 'NotificationController@userNotification');


    //tushar
    $router->post('add/category/{type}', 'CategoryController@addCategory');
    $router->post('add/bank_account', 'CategoryController@createBankAccount');
    $router->post('add/transaction', 'CategoryController@createTransaction');
    $router->post('add/bank/transaction', 'BankController@createBankTransaction');
    $router->post('add/fund/transaction', 'FundController@store');

    $router->get('get/list/bank', 'BankController@activeBankListing');
    $router->get('get/bankAccount/{id}', 'BankController@singleBankAccount');
    $router->get('get/list/bank/transaction/{id}', 'BankController@transactionByAccount');
    $router->get('get/category/{type}', 'CategoryController@getCategory');
    $router->get('get/list/bank/pending', 'BankController@pendingBank');
    $router->get('get/list/bank/active', 'BankController@activeBank');
    $router->get('get/list/transaction/by/{day}/{date}', 'BankController@transactionByDate');
    $router->get('get/summery/by/{date}', 'HomeController@monthlySummery');
    $router->get('get/fund/summery/{date}', 'FundController@monthlyReport');
    $router->get('get/manager/balance', 'HomeController@managerExpense');

    $router->post('accept/transaction/pending/{id}', 'BankController@acceptPendingTransaction');
    $router->post('delete/transaction/{id}', 'BankController@deleteTransaction');
    $router->post('delete/bank/transaction/{id}', 'BankController@deleteBankTransaction');
    $router->get('delete/bank/{id}', 'BankController@deleteBankAccount');
    $router->get('delete/head/{type}/{id}', 'EditController@deleteHead');
    $router->delete('delete/fund/{id}', 'FundController@destroy');

    $router->post('edit/bank/{bankAccount}', 'EditController@editBankAccount');
    $router->post('edit/transaction/{id}', 'EditController@editTransaction');
    $router->post('edit/transaction/bank/{id}', 'EditController@editBankTransaction');
    $router->post('edit/head/{type}/{id}', 'EditController@editHead');
    $router->put('fund/edit/{id}', 'FundController@edit');

    $router->get('get/managers', 'UserController@getManagers');
});

$router->get('artisan_call', 'HomeController@schedule');
