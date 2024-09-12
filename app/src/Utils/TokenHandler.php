<?php

namespace Okhub\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class TokenHandler
{
    private $key;
    private $bearerTokenExpiry;
    private $refreshTokenExpiry;

    public function __construct($key, $bearerTokenExpiry = 900, $refreshTokenExpiry = 2592000)
    {
        $this->key = $key;
        $this->bearerTokenExpiry = $bearerTokenExpiry;
        $this->refreshTokenExpiry = $refreshTokenExpiry;
    }

    public function generateTokens($userId)
    {
        $issuedAt = time();

        // Bearer token payload
        $payload = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->bearerTokenExpiry,
            'user_id' => $userId
        );
        $bearerToken = JWT::encode($payload, $this->key, 'HS256');

        // Refresh token payload
        $refreshPayload = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->refreshTokenExpiry,
            'user_id' => $userId,
            'type' => 'refresh'
        );
        $refreshToken = JWT::encode($refreshPayload, $this->key, 'HS256');

        return array(
            'bearerToken' => $bearerToken,
            'refreshToken' => $refreshToken,
            'refreshPayload' => $refreshPayload,
            'payload' => $payload
        );
    }

    public function decodeToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function validateBearerToken($bearerToken)
    {
        $decodedToken = $this->decodeToken($bearerToken);
        if ($decodedToken && $decodedToken['exp'] > time()) {
            return $decodedToken;
        }
        return null;
    }

    public function refreshBearerToken($refreshToken)
    {
        $decodedToken = $this->decodeToken($refreshToken);

        if ($decodedToken && $decodedToken['exp'] > time() && $decodedToken['type'] === 'refresh') {
            return $this->generateTokens($decodedToken['user_id']);
        }

        return null;
    }
}