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

    public function createUser(Request $request)
    {
        try {
            $this->ensureAdmin($request);
            // Implementation similar to AuthController::register but by Admin
            Response::json(['message' => 'Not implemented yet'], 501);
        } catch (PDOException $e) {
            return Response::error(
                'Failed to create user: ' . $e->getMessage(),
                500
            );
        }
    }

    public function updateUser(Request $request, $uuid)
    {
        $this->ensureAdmin($request);
        Response::json(['message' => 'Not implemented yet'], 501);
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