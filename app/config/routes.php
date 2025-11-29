<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/


// Root route - return API info for Vue frontend
$router->get('/', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'LavaLust API is running',
        'version' => '1.0',
        'endpoints' => [
            'auth' => ['/auth/login', '/auth/register', '/auth/logout'],
            'api' => ['/api/get_posts', '/api/get_user', '/api/get_notifications'],
            'posts' => ['/post_section/create', '/post_section/edit_post/{id}']
        ]
    ]);
});

// Auth routes
$router->match('/auth/register', 'UserController::register', ['GET','POST']);
$router->match('/auth/login', 'UserController::login', ['GET','POST']);
$router->get('/auth/logout', 'UserController::logout');

// Homepage (after login)
$router->get('/users', 'UserController::index');
$router->get('/users/user-page', 'UserController::user_page');
$router->get('/users/user_page', 'UserController::user_page'); 
// Email verification routes
$router->get('/auth/verify', 'UserController::verify');
$router->match('/auth/verify_code', 'UserController::verify_code', ['GET','POST']);
$router->get('/auth/resend', 'UserController::resend_verification');
$router->get('/api/auth/pending-email', 'UserController::api_pending_email');
$router->match('/api/auth/verify_code', 'UserController::api_verify_code', ['POST']);



// Users CRUD
$router->match('/users/create', 'UserController::create', ['GET', 'POST']);
$router->match('/users/update/{id}', 'UserController::update', ['GET', 'POST']);
$router->get('/users/delete/{id}', 'UserController::delete');

$router->get('/post_section/post', 'UserController::post_page');
$router->match('/post_section/create', 'UserController::create_post', ['GET', 'POST']);

// Edit and Delete Post routes
$router->match('/post_section/edit_post/{id}', 'UserController::edit_post', ['GET', 'POST']);
$router->get('/post_section/delete_post/{id}', 'UserController::delete_post');

// Categories routes
$router->get('/categories', 'UserController::categories');
$router->get('/categories/filter/{category}', 'UserController::filter_by_category');

$router->match('/api/toggle_like', 'UserController::toggle_like', ['POST']);
$router->match('/api/add_comment', 'UserController::add_comment', ['POST']);
$router->match('/api/delete_comment', 'UserController::delete_comment', ['POST']);
$router->match('/api/add_reply', 'UserController::add_reply', ['POST']);
$router->match('/api/delete_reply', 'UserController::delete_reply', ['POST']);

$router->get('/api/search', 'UserController::api_search');  // <=== added search route

// API routes for Vue frontend
$router->get('/api/get_posts', 'UserController::api_get_posts');
$router->get('/api/get_user', 'UserController::api_get_user');
$router->get('/api/get_post/{id}', 'UserController::api_get_post');
$router->get('/api/get_posts_by_category', 'UserController::api_get_posts_by_category');
$router->get('/api/admin/users', 'UserController::api_admin_users');
$router->match('/api/admin/users', 'UserController::api_admin_create_user', ['POST']);
$router->get('/api/admin/users/{id}', 'UserController::api_admin_user');
$router->match('/api/admin/users/{id}', 'UserController::api_admin_update_user', ['POST']);
$router->match('/api/admin/users/{id}/delete', 'UserController::api_admin_delete_user', ['POST']);

// Notifications routes
$router->get('/api/get_notifications', 'UserController::get_notifications');
$router->match('/api/mark_notification_read', 'UserController::mark_notification_read', ['POST']);
$router->get('/notifications', 'UserController::notifications_page');

// Profile routes
$router->get('/users/profile', 'UserController::profile');
$router->match('/users/update_profile', 'UserController::update_profile', ['POST']);
