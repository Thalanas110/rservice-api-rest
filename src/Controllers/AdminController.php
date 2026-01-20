<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Uuid;
use PDOException;

class AdminController extends Controller
{

    /*
    this function ensures if the user is an admin. If its not, then throw 403: forbidden to access, because why tf
    would someone let an admin do this?
    */
    private function ensureAdmin(Request $request)
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'admin') {
            Response::error('Forbidden', 403);
        }
    }

    /*
    this function returns the dashboard stats. Usually only the given driver, student, parent. Otheerwise, return 500.
    */
    public function dashboard(Request $request)
    {
        try {
            $this->ensureAdmin($request);

            $stats = [
                'drivers' => $this->db->getConnection()->query("SELECT COUNT(*) FROM users WHERE role = 'driver'")->fetchColumn(),
                'students' => $this->db->getConnection()->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
                'parents' => $this->db->getConnection()->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn(),
            ];

            Response::json($stats);
        } catch (PDOException $e) {
            return Response::error(
                'Failed to fetch stats: ' . $e->getMessage(),
                500
            );
        }
    }


    /*
    this function just returns the users based on the role. If the role is not valid, throw error.
    */
    public function getUsers(Request $request)
    {
        try {
            $this->ensureAdmin($request);
            $role = $request->getQuery('role');

            $allowedRoles = ['driver', 'student', 'parent'];
            if (!in_array($role, $allowedRoles)) {
                Response::error('Invalid role filter');
            }

            $stmt = $this->db->getConnection()->prepare("SELECT uuid, name, email, role, created_at FROM users WHERE role = ?");
            $stmt->execute([$role]);
            $users = $stmt->fetchAll();

            // Convert UUIDs
            foreach ($users as &$user) {
                $user['uuid'] = Uuid::fromBin($user['uuid']);
            }

            Response::json($users);
        } catch (PDOException $e) {
            return Response::error(
                'Failed to fetch users: ' . $e->getMessage(),
                500
            );
        }
    }

    /*
    this function creates a user at request. There are 4 roles: admin, student, driver, parent, and at the same time, 4 fields:
    role, name, email, and password.
    First we will be validating if the role is valid or existing, then check if the email ALREADY exists. If it does, throw error.
    Otherwise, we will be creating a new user dependent on role.
    */
    public function createUser(Request $request) {
        $this->ensureAdmin($request);
        $body = $request->getBody();
        
        $role = $body['role'] ?? '';
        $name = $body['name'] ?? '';
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        
        if (!in_array($role, ['admin', 'student', 'driver', 'parent'])) {
            Response::error('Invalid role');
        }
        
        $stmt = $this->db->getConnection()->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->execute([$email]);
        // if email already exists, throw error
        if ($stmt->fetch()) {
            Response::error('Email already exists');
        }

        $pdo = $this->db->getConnection();
        try {
            $pdo->beginTransaction();
            
            $uuid = Uuid::generate();
            $uuidBin = Uuid::toBin($uuid);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (uuid, role, name, email, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$uuidBin, $role, $name, $email, $hash]);
            
            // role validation dependent on role (this should be manually selected by whoever is registering into the app)
            if ($role === 'driver') {
                $maxStudents = $body['max_students'] ?? 7;
                $code = '';
                do {
                    $code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $check = $pdo->prepare("SELECT 1 FROM drivers WHERE code = ?");
                    $check->execute([$code]);
                } while ($check->fetch());
                
                $stmt = $pdo->prepare("INSERT INTO drivers (user_uuid, max_students, code) VALUES (?, ?, ?)");
                $stmt->execute([$uuidBin, $maxStudents, $code]);
            } elseif ($role === 'student') {
                $anonId = bin2hex(random_bytes(4));
                $stmt = $pdo->prepare("INSERT INTO students (user_uuid, anon_id) VALUES (?, ?)");
                $stmt->execute([$uuidBin, $anonId]);
            } elseif ($role === 'parent') {
                $stmt = $pdo->prepare("INSERT INTO parents (user_uuid) VALUES (?)");
                $stmt->execute([$uuidBin]);
            }
            
            $pdo->commit();
            $this->logger->info("Admin created user: $email ($role)");
            Response::json(['uuid' => $uuid, 'message' => 'User created'], 201);
            
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->logger->error("Admin failed to create user: " . $e->getMessage());
            Response::error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /* 
    this function involves mainly updating the user from the admin endpoint.
    We first ensure that we're an admin, then do a request with the uuids. We will be updating the user based on the uuid.
    
    */
    public function updateUser(Request $request, $uuid) {
        $this->ensureAdmin($request);
        $body = $request->getBody();
        $uuidBin = Uuid::toBin($uuid);
        
        $fields = [];
        $params = [];
        
        if (isset($body['name'])) {
            $fields[] = 'name = ?';
            $params[] = $body['name'];
        }
        if (isset($body['email'])) {
            $fields[] = 'email = ?';
            $params[] = $body['email'];
        }
        
        // separate endpoint for password is placed.
        
        if (!empty($fields)) {
            $params[] = $uuidBin;
            $stmt = $this->db->getConnection()->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE uuid = ?");
            $stmt->execute($params);
        }
        
        // This function checks if a driver has reached maximum number of students OR/AND sets them.
        if (isset($body['max_students'])) {
            $stmt = $this->db->getConnection()->prepare("SELECT role FROM users WHERE uuid = ?");
            $stmt->execute([$uuidBin]);
            if ($stmt->fetchColumn() === 'driver') {
                 $stmt = $this->db->getConnection()->prepare("UPDATE drivers SET max_students = ? WHERE user_uuid = ?");
                 $stmt->execute([$body['max_students'], $uuidBin]);
            }
        }

        $this->logger->info("Admin updated user: $uuid");
        Response::json(['message' => 'User updated']);
    }

    /*
    this function deletes the user from the admin endpoint.
    We first ensure that we're an admin, then do a request with the uuids. We will be deleting the user based on the uuid.
    */
    public function deleteUser(Request $request, $uuid)
    {
        try {
            $this->ensureAdmin($request);

            $uuidBin = Uuid::toBin($uuid);
            $stmt = $this->db->getConnection()->prepare("DELETE FROM users WHERE uuid = ?");
            $stmt->execute([$uuidBin]);

            $this->logger->info("Admin deleted user: $uuid");
            Response::json(['message' => 'User deleted']);
        } catch (PDOException $e) {
            $this->logger->error("Admin failed to delete user: " . $e->getMessage());
            return Response::error(
                'Failed to delete user: ' . $e->getMessage(),
                500
            );
        }
    }

    public function getDriverLocation(Request $request, $uuid)
    {
        try {
            $this->ensureAdmin($request);

            $uuidBin = Uuid::toBin($uuid);
            $stmt = $this->db->getConnection()->prepare("SELECT lat, lng, location_updated as updated_at FROM drivers WHERE user_uuid = ?");
            $stmt->execute([$uuidBin]);
            $location = $stmt->fetch();

            if (!$location) {
                Response::error('Driver not found or no location');
            }

            Response::json($location);
        } catch (PDOException $e) {
            return Response::error(
                'Failed to delete user: ' . $e->getMessage(),
                500
            );
        }
    }

    public function assignStudent(Request $request)
    {
        try {
            $this->ensureAdmin($request);
            $body = $request->getBody();

            $driverUuid = Uuid::toBin($body['driver_uuid']);
            $studentUuid = Uuid::toBin($body['student_uuid']);

            $stmt = $this->db->getConnection()->prepare("UPDATE students SET driver_uuid = ? WHERE user_uuid = ?");
            $stmt->execute([$driverUuid, $studentUuid]);

            Response::json(['message' => 'Student assigned to driver']);
        } catch (PDOException $e) {
            return Response::error(
                'Failed to assign student: ' . $e->getMessage(),
                500
            );
        }
    }

    public function updateDriverLimit(Request $request, $uuid)
    {
        try {
            $this->ensureAdmin($request);
            $body = $request->getBody();
            $limit = $body['max_students'] ?? 7;

            $uuidBin = Uuid::toBin($uuid);
            $stmt = $this->db->getConnection()->prepare("UPDATE drivers SET max_students = ? WHERE user_uuid = ?");
            $stmt->execute([$limit, $uuidBin]);

            Response::json(['message' => 'Driver limit updated']);
        } catch (PDOException $e) {
            return Response::error(
                'Failed to update driver limit: ' . $e->getMessage(),
                500
            );
        }
    }
}


?>