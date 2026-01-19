<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\JWT;
use Core\Uuid;
use Models\User;
use PDO;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function checkAccount()
    {
        $hasAccount = $this->userModel->exists();
        Response::json(['hasAccount' => $hasAccount]);
    }

    public function register(Request $request)
    {
        $body = $request->getBody();
        $role = $body['role'] ?? '';
        $name = $body['name'] ?? '';
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (!in_array($role, ['admin', 'student', 'driver', 'parent'])) {
            Response::error('Invalid role');
        }
        if (empty($email) || empty($password) || empty($name)) {
            Response::error('Missing required fields');
        }

        if ($this->userModel->findByEmail($email)) {
            Response::error('Email already exists');
        }

        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Create User
            $user = $this->userModel->create($role, $name, $email, $password);
            $uuidBin = Uuid::toBin($user['uuid']);

            // Role specific logic
            if ($role === 'driver') {
                $stmt = $pdo->prepare("INSERT INTO drivers (user_uuid, max_students, code) VALUES (?, 7, ?)");
                // Generate unique 6-digit code
                $code = $this->generateUniqueDriverCode($pdo);
                $stmt->execute([$uuidBin, $code]);
            } elseif ($role === 'student') {
                $stmt = $pdo->prepare("INSERT INTO students (user_uuid, anon_id) VALUES (?, ?)");
                // Generate unique anon_id
                $anonId = bin2hex(random_bytes(4)); // 8 chars
                $stmt->execute([$uuidBin, $anonId]);
            } elseif ($role === 'parent') {
                $stmt = $pdo->prepare("INSERT INTO parents (user_uuid) VALUES (?)");
                $stmt->execute([$uuidBin]);
            }

            $pdo->commit();

            // Auto-login
            $jwt = new JWT();
            $token = $jwt->generate([
                'uuid' => $user['uuid'],
                'role' => $user['role']
            ]);

            Response::json([
                'message' => 'Registration successful',
                'token' => $token,
                'role' => $user['role']
            ], 201);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    public function login(Request $request)
    {
        $body = $request->getBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

        // Convert uuid binary to string for token
        $userUuid = Uuid::fromBin($user['uuid']);

        $jwt = new JWT();
        $token = $jwt->generate([
            'uuid' => $userUuid,
            'role' => $user['role']
        ]);

        Response::json([
            'message' => 'Login successful',
            'token' => $token,
            'role' => $user['role']
        ]);
    }

    private function generateUniqueDriverCode(PDO $pdo): string
    {
        do {
            $code = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM drivers WHERE code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        return $code;
    }
}
