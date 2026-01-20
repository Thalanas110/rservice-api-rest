<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Uuid;
use PDOException;

class DriverController extends Controller {
    
    private function ensureDriver(Request $request) {
        try {
            $user = $request->getAttribute('user');
            if ($user['role'] !== 'driver') {
                Response::error('Forbidden', 403);
            }
            return $user;
        }
        catch (PDOException $e) {
            Response::error('Failed to fetch user: ' . $e->getMessage(), 500);
        }
    }

    public function getProfile(Request $request) {
        try {
            $userCtx = $this->ensureDriver($request);
            $uuidBin = Uuid::toBin($userCtx['uuid']);
            
            $stmt = $this->db->getConnection()->prepare("
                SELECT u.name, u.email, d.max_students, d.code 
                FROM users u 
                JOIN drivers d ON u.uuid = d.user_uuid 
                WHERE u.uuid = ?
            ");
            $stmt->execute([$uuidBin]);
            $profile = $stmt->fetch();
            
            Response::json($profile);
        }
        catch (PDOException $e) {
            Response::error('Failed to fetch user: ' . $e->getMessage(), 500);  
        }
    }

    public function updateProfile(Request $request) {
        try {
            $userCtx = $this->ensureDriver($request);
            Response::json(['message' => 'Not implemented']);
        }
        catch (PDOException $e) {
            Response::error('Failed to update user: ' . $e->getMessage(), 500);  
        }
    }

    public function getCode(Request $request) {
        $userCtx = $this->ensureDriver($request);
        $uuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("SELECT code FROM drivers WHERE user_uuid = ?");
        $stmt->execute([$uuidBin]);
        $data = $stmt->fetch();
        
        Response::json($data);
    }

    public function updateLocation(Request $request) {
        $userCtx = $this->ensureDriver($request);
        $uuidBin = Uuid::toBin($userCtx['uuid']);
        $body = $request->getBody();
        
        $lat = $body['lat'] ?? null;
        $lng = $body['lng'] ?? null;
        
        if ($lat === null || $lng === null) {
            Response::error('Missing lat/lng');
        }

        $stmt = $this->db->getConnection()->prepare("UPDATE drivers SET lat = ?, lng = ?, location_updated = NOW() WHERE user_uuid = ?");
        $stmt->execute([$lat, $lng, $uuidBin]);
        
        Response::json(['message' => 'Location updated']);
    }

    public function getStudents(Request $request) {
        $userCtx = $this->ensureDriver($request);
        $driverUuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.uuid, s.anon_id 
            FROM students s 
            JOIN users u ON s.user_uuid = u.uuid 
            WHERE s.driver_uuid = ?
        ");
        $stmt->execute([$driverUuidBin]);
        $rows = $stmt->fetchAll();
        
        $students = [];
        foreach ($rows as $row) {
            $students[] = [
                'uuid' => Uuid::fromBin($row['uuid']),
                'name' => 'Student ' . $row['anon_id'], // Masking real name
                'anon_id' => $row['anon_id']
            ];
        }
        
        Response::json($students);
    }
}
