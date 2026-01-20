<?php

namespace Core;

use PDO;

class Logger implements LoggerInterface
{
    public const INFO = 'INFO';
    public const ERROR = 'ERROR';
    public const DEBUG = 'DEBUG';
    public const WARNING = 'WARNING';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function log(string $level, string $message): void
    {
        try {
            $sql = "INSERT INTO logs (level, message) VALUES (:level, :message)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'level' => $level,
                'message' => $message
            ]);
        } catch (\PDOException $e) {
            // Fallback to default PHP error logger if DB fails
            error_log("Logger DB Error: [{$level}] {$message} - " . $e->getMessage());
        }
    }

    public function info(string $message): void
    {
        $this->log(self::INFO, $message);
    }

    public function error(string $message): void
    {
        $this->log(self::ERROR, $message);
    }

    public function debug(string $message): void
    {
        $this->log(self::DEBUG, $message);
    }

    public function warning(string $message): void
    {
        $this->log(self::WARNING, $message);
    }
}
