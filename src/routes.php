<?php

use Controllers\AuthController;
use Controllers\AdminController;
use Controllers\StudentController;
use Controllers\DriverController;
use Controllers\ParentController;
use Middleware\AuthMiddleware;

/** @var \Core\Router $router */

// Public Routes
$router->get('/', [AuthController::class, 'checkAccount']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/login', [AuthController::class, 'login']);

// Protected Routes Middleware
$auth = [AuthMiddleware::class];

// ADMIN Routes
$router->get('/api/admin/dashboard', [AdminController::class, 'dashboard'], $auth);
$router->get('/api/admin/users', [AdminController::class, 'getUsers'], $auth);
$router->post('/api/admin/users', [AdminController::class, 'createUser'], $auth);
$router->patch('/api/admin/users/:uuid', [AdminController::class, 'updateUser'], $auth);
$router->delete('/api/admin/users/:uuid', [AdminController::class, 'deleteUser'], $auth);
$router->get('/api/admin/drivers/:uuid/location', [AdminController::class, 'getDriverLocation'], $auth);
$router->post('/api/admin/assignments', [AdminController::class, 'assignStudent'], $auth);
$router->patch('/api/admin/drivers/:uuid/limit', [AdminController::class, 'updateDriverLimit'], $auth);

// STUDENT Routes
$router->get('/api/student/profile', [StudentController::class, 'getProfile'], $auth);
$router->patch('/api/student/profile', [StudentController::class, 'updateProfile'], $auth);
$router->get('/api/student/driver', [StudentController::class, 'getDriver'], $auth);
$router->post('/api/student/join', [StudentController::class, 'joinDriver'], $auth);
$router->post('/api/student/parents', [StudentController::class, 'addParent'], $auth);
$router->get('/api/student/parents', [StudentController::class, 'getParents'], $auth);

// DRIVER Routes
$router->get('/api/driver/profile', [DriverController::class, 'getProfile'], $auth);
$router->patch('/api/driver/profile', [DriverController::class, 'updateProfile'], $auth);
$router->get('/api/driver/code', [DriverController::class, 'getCode'], $auth);
$router->post('/api/driver/location', [DriverController::class, 'updateLocation'], $auth);
$router->get('/api/driver/students', [DriverController::class, 'getStudents'], $auth);

// PARENT Routes
$router->get('/api/parent/profile', [ParentController::class, 'getProfile'], $auth);
$router->patch('/api/parent/profile', [ParentController::class, 'updateProfile'], $auth);
$router->get('/api/parent/children', [ParentController::class, 'getChildren'], $auth);
$router->get('/api/parent/children/:uuid/location', [ParentController::class, 'getChildLocation'], $auth);
