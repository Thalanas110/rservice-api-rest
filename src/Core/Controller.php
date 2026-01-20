<?php

namespace Core;

abstract class Controller
{
    protected Database $db;
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
}
