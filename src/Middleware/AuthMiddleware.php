<?php

namespace Middleware;

use Core\Request;
use Core\Response;
use Core\JWT;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::error('Unauthorized', 401);
        }

        $token = $matches[1];
        $jwt = new JWT();
        $payload = $jwt->validate($token);

        if (!$payload) {
            Response::error('Invalid Token', 401);
        }

        $request->setAttribute('user', $payload);
    }
}
