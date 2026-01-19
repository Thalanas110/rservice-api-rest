<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Uuid;

class StudentController extends Controller {

    private function ensureStudent(Request $request) {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'student') {
            Response::error('Forbidden', 403);
        }
        return $user;
    }

    public function getProfile(Request $request) {
        $userCtx = $this->ensureStudent($request);
        $uuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.name, u.email, s.anon_id 
            FROM users u 
            JOIN students s ON u.uuid = s.user_uuid 
            WHERE u.uuid = ?
        ");
        $stmt->execute([$uuidBin]);
        $profile = $stmt->fetch();
        
        Response::json($profile);
    }

    public function updateProfile(Request $request) {
        // ...
        Response::json(['message' => 'Not implemented']);
    }

    public function getDriver(Request $request) {
        $userCtx = $this->ensureStudent($request);
        $studentUuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT d_user.name, d_user.email, d.code 
            FROM students s
            JOIN drivers d ON s.driver_uuid = d.user_uuid
            JOIN users d_user ON d.user_uuid = d_user.uuid
            WHERE s.user_uuid = ?
        ");
        $stmt->execute([$studentUuidBin]);
        $driver = $stmt->fetch();
        
        if (!$driver) {
            Response::json(null);
        }
        
        $driver['chat_ws_url'] = 'ws://localhost:8080/chat'; // Mock URL
        Response::json($driver);
    }

    public function joinDriver(Request $request) {
        $userCtx = $this->ensureStudent($request);
        $studentUuidBin = Uuid::toBin($userCtx['uuid']);
        
        $body = $request->getBody();
        $code = $body['code'] ?? '';
        
        $pdo = $this->db->getConnection();
        
        // Find driver by code
        $stmt = $pdo->prepare("SELECT user_uuid, max_students FROM drivers WHERE code = ?");
        $stmt->execute([$code]);
        $driver = $stmt->fetch();
        
        if (!$driver) {
            Response::error('Driver not found', 404);
        }
        
        $driverUuidBin = $driver['user_uuid'];
        $maxStudents = $driver['max_students'];
        
        // Check capacity
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE driver_uuid = ?");
        $stmt->execute([$driverUuidBin]);
        $currentCount = $stmt->fetchColumn();
        
        if ($currentCount >= $maxStudents) {
            Response::error('Driver is full', 409);
        }
        
        // Update student
        $stmt = $pdo->prepare("UPDATE students SET driver_uuid = ? WHERE user_uuid = ?");
        $stmt->execute([$driverUuidBin, $studentUuidBin]);
        
        Response::json(['message' => 'Joined driver successfully'], 201);
    }

    public function addParent(Request $request) {
        $userCtx = $this->ensureStudent($request);
        $studentUuidBin = Uuid::toBin($userCtx['uuid']);
        
        $body = $request->getBody();
        $parentUuid = $body['parent_uuid'] ?? '';
        $parentUuidBin = Uuid::toBin($parentUuid); // Validate UUID format?
        
        // Insert link
        $stmt = $this->db->getConnection()->prepare("INSERT IGNORE INTO student_parents (student_uuid, parent_uuid) VALUES (?, ?)");
        try {
            $stmt->execute([$studentUuidBin, $parentUuidBin]);
            Response::json(['message' => 'Parent linked']);
        } catch (\PDOException $e) {
            Response::error('Failed to link parent: ' . $e->getMessage());
        }
    }

    public function getParents(Request $request) {
        $userCtx = $this->ensureStudent($request);
        $studentUuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.uuid, u.name, u.email 
            FROM student_parents sp
            JOIN users u ON sp.parent_uuid = u.uuid
            WHERE sp.student_uuid = ?
        ");
        $stmt->execute([$studentUuidBin]);
        $parents = $stmt->fetchAll();
        
        foreach ($parents as &$parent) {
            $parent['uuid'] = Uuid::fromBin($parent['uuid']);
        }
        
        Response::json($parents);
    }
}
