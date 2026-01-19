<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Uuid;
use PDOException;

class AdminController extends Controller
{

    private function ensureAdmin(Request $request)
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'admin') {
            Response::error('Forbidden', 403);
        }
    }

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

    public function createUser(Request $request) {
        $this->ensureAdmin($request);
        $body = $request->getBody();
        
        $role = $body['role'] ?? '';
        $name = $body['name'] ?? '';
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        
        // Basic Validation
        if (!in_array($role, ['admin', 'student', 'driver', 'parent'])) {
            Response::error('Invalid role');
        }
        
        // Check email
        $stmt = $this->db->getConnection()->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('Email already exists');
        }

        $pdo = $this->db->getConnection();
        try {
            $pdo->beginTransaction();
            
            // Create User (Reusing logic logic or calling User model if updated, here manual for custom cols)
            $uuid = Uuid::generate();
            $uuidBin = Uuid::toBin($uuid);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (uuid, role, name, email, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$uuidBin, $role, $name, $email, $hash]);
            
            // Role Specifics
            if ($role === 'driver') {
                $maxStudents = $body['max_students'] ?? 7;
                // Generate Code
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
            Response::json(['uuid' => $uuid, 'message' => 'User created'], 201);
            
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

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
        
        // Password update could be here too but separate endpoint is safer usually.
        // Prompt implies general update.
        
        if (!empty($fields)) {
            $params[] = $uuidBin;
            $stmt = $this->db->getConnection()->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE uuid = ?");
            $stmt->execute($params);
        }
        
        // Role specific updates
        if (isset($body['max_students'])) {
            // Check if user is driver
            $stmt = $this->db->getConnection()->prepare("SELECT role FROM users WHERE uuid = ?");
            $stmt->execute([$uuidBin]);
            if ($stmt->fetchColumn() === 'driver') {
                 $stmt = $this->db->getConnection()->prepare("UPDATE drivers SET max_students = ? WHERE user_uuid = ?");
                 $stmt->execute([$body['max_students'], $uuidBin]);
            }
        }

        Response::json(['message' => 'User updated']);
    }

    public function deleteUser(Request $request, $uuid)
    {
        try {
            $this->ensureAdmin($request);

            $uuidBin = Uuid::toBin($uuid);
            $stmt = $this->db->getConnection()->prepare("DELETE FROM users WHERE uuid = ?");
            $stmt->execute([$uuidBin]);

            Response::json(['message' => 'User deleted']);
        } catch (PDOException $e) {
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