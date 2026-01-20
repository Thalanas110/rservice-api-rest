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

    /* 
    this function is mainly the login function.
    First you wil be checking the body, then get the email and password.
    If the user exists then good, if not then yeah 401 error for invalid credentials.

    We also convert the uuid string to a token via roles.
    */
    public function login(Request $request)
    {
        try {
            $body = $request->getBody();
            $email = $body['email'] ?? '';
            $password = $body['password'] ?? '';

            $user = $this->userModel->findByEmail($email);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->logger->warning("Failed login attempt for email: $email");
                Response::error('Invalid credentials', 401);
            }

            // string to token uuid conversion
            $userUuid = Uuid::fromBin($user['uuid']);

            $jwt = new JWT();
            $token = $jwt->generate([
                'uuid' => $userUuid,
                'role' => $user['role']
            ]);

            $this->logger->info("User logged in: $email (Role: {$user['role']})");

            Response::json([
                'message' => 'Login successful',
                'token' => $token,
                'role' => $user['role']
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Login error: " . $e->getMessage());
            return Response::error(
                'Login failed: ' . $e->getMessage(),
                500
            );
        }
    }

    private function generateUniqueDriverCode(PDO $pdo): string
    {
        try {
            do {
                $code = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM drivers WHERE code = ?");
                $stmt->execute([$code]);
                $exists = $stmt->fetchColumn() > 0;
            } while ($exists);
            return $code;
        } catch (\Exception $e) {
            Response::error('' . $e->getMessage(), 500);
            return '';
        }
    }
}
