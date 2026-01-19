<?php

namespace Models;

use Core\Model;
use Core\Uuid;
use PDO;

class User extends Model {
    public function exists(): bool {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn() > 0;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $role, string $name, string $email, string $password): array {
        $uuid = Uuid::generate();
        $uuidBin = Uuid::toBin($uuid);
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("INSERT INTO users (uuid, role, name, email, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uuidBin, $role, $name, $email, $hash]);

        return [
            'uuid' => $uuid,
            'role' => $role,
            'name' => $name,
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s') 
        ];
    }
    
    public function find(string $uuid): ?array {
        $uuidBin = Uuid::toBin($uuid);
        $stmt = $this->db->prepare("SELECT * FROM users WHERE uuid = ?");
        $stmt->execute([$uuidBin]);
        $user = $stmt->fetch();
        if ($user) {
            $user['uuid'] = Uuid::fromBin($user['uuid']); // Convert back to string
        }
        return $user ?: null;
    }
}
