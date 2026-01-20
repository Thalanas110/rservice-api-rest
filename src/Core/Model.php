<?php

namespace Core;

abstract class Model
{
    protected \PDO $db;
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
    }
}
