<?php

namespace Middleware;

use Core\Request;
use Core\Response;
use Core\JWT;

class AuthMiddleware
{
    private \Core\LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new \Core\Logger();
    }

    public function handle(Request $request): void
    {
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->logger->warning('Unauthorized access attempt: Missing or invalid header');
            Response::error('Unauthorized', 401);
        }

        $token = $matches[1];
        $jwt = new JWT();
        $payload = $jwt->validate($token);

        if (!$payload) {
            $this->logger->warning('Unauthorized access attempt: Invalid token');
            Response::error('Invalid Token', 401);
        }

        $request->setAttribute('user', $payload);
    }
}
