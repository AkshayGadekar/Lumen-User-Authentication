<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});


//email verification
$router->get('email-verification-link/{token}', ["as" => "user.emailVerificationLink", "uses" => "AuthController@emailVerificationLink"]);

//guest routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {
    //login-signup
    $router->post('login', ["uses" => "AuthController@logIn"]);
    $router->post('signup', ["uses" => "AuthController@signUp"]);
    $router->get('forget-password-link', ["uses" => "AuthController@forgetPasswordLink"]);
    $router->put('reset-password/{token}', ["uses" => "AuthController@resetPassword"]);
    
    //get refresh token
    $router->post('oauth/access-token', ["uses" => "AuthController@getAccessToken"]);
});

//auth routes
$router->group(['prefix' => 'api/v1', 'middleware' => 'auth'], function () use ($router) {
    $router->get('get-user', ["uses" => "AuthController@getUser"]);
    
    //logout
    $router->get('logout', ["uses" => "AuthController@logOut"]);
});