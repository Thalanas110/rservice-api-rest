<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Uuid;

class ParentController extends Controller {

    private function ensureParent(Request $request) {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'parent') {
            Response::error('Forbidden', 403);
        }
        return $user;
    }

    public function getProfile(Request $request) {
        $userCtx = $this->ensureParent($request);
        $uuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("SELECT name, email FROM users WHERE uuid = ?");
        $stmt->execute([$uuidBin]);
        $profile = $stmt->fetch();
        
        Response::json($profile);
    }

    public function updateProfile(Request $request) {
        Response::json(['message' => 'Not implemented']);
    }

    public function getChildren(Request $request) {
        $userCtx = $this->ensureParent($request);
        $parentUuidBin = Uuid::toBin($userCtx['uuid']);
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.uuid, u.name, d_user.name as driver_name, d.lat, d.lng, d.location_updated
            FROM student_parents sp
            JOIN students s ON sp.student_uuid = s.user_uuid
            JOIN users u ON s.user_uuid = u.uuid
            LEFT JOIN drivers d ON s.driver_uuid = d.user_uuid
            LEFT JOIN users d_user ON d.user_uuid = d_user.uuid
            WHERE sp.parent_uuid = ?
        ");
        $stmt->execute([$parentUuidBin]);
        $children = $stmt->fetchAll();
        
        $result = [];
        foreach ($children as $child) {
            $result[] = [
                'uuid' => Uuid::fromBin($child['uuid']),
                'name' => $child['name'],
                'driver_name' => $child['driver_name'],
                'last_location' => [
                    'lat' => $child['lat'],
                    'lng' => $child['lng'],
                    'updated_at' => $child['location_updated']
                ]
            ];
        }
        
        Response::json($result);
    }

    public function getChildLocation(Request $request, $childUuid) {
        $userCtx = $this->ensureParent($request);
        $parentUuidBin = Uuid::toBin($userCtx['uuid']);
        $childUuidBin = Uuid::toBin($childUuid);

        // Verify parent owns child
        $stmt = $this->db->getConnection()->prepare("SELECT 1 FROM student_parents WHERE parent_uuid = ? AND student_uuid = ?");
        $stmt->execute([$parentUuidBin, $childUuidBin]);
        if (!$stmt->fetch()) {
            Response::error('Child not found or not linked', 404);
        }

        // Get location (from driver)
        $stmt = $this->db->getConnection()->prepare("
            SELECT d.lat, d.lng, d.location_updated as updated_at
            FROM students s
            JOIN drivers d ON s.driver_uuid = d.user_uuid
            WHERE s.user_uuid = ?
        ");
        $stmt->execute([$childUuidBin]);
        $location = $stmt->fetch();
        
        if (!$location) {
            Response::error('Location not available (child has no driver or driver has no location)');
        }
        
        Response::json($location);
    }
}
