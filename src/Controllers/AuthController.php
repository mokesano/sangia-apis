<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Auth\JWT;
use Sangia\Api\Config\Config;
use Sangia\Api\Models\User;
use Sangia\Api\Response;

class AuthController extends BaseController
{
    public function login(): void
    {
        $body = $this->jsonBody();

        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error('Email and password are required');
        }

        $model = new User();
        $user  = $model->findByEmail($email);

        if (!$user || !$model->verifyPassword($password, $user['password'] ?? '')) {
            Response::unauthorized('Invalid email or password');
        }

        $token = JWT::encode([
            'sub'  => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ]);

        Response::success([
            'token' => $token,
            'user'  => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    public function orcidLogin(): void
    {
        $clientId    = Config::get('ORCID_CLIENT_ID');
        $redirectUri = Config::get('ORCID_REDIRECT_URI');

        if (!$clientId) {
            Response::error('ORCID OAuth not configured');
        }

        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'scope'         => '/authenticate',
            'redirect_uri'  => $redirectUri,
        ]);

        header('Location: https://orcid.org/oauth/authorize?' . $params);
        exit;
    }

    public function orcidCallback(): void
    {
        $code = $_GET['code'] ?? '';

        if (empty($code)) {
            Response::error('Authorization code missing');
        }

        $tokenData = $this->exchangeOrcidCode($code);

        if (!$tokenData) {
            Response::error('Failed to exchange ORCID authorization code');
        }

        $orcidId = $tokenData['orcid'];
        $name    = $tokenData['name'] ?? 'ORCID User';
        $email   = $orcidId . '@orcid.placeholder';

        $model = new User();
        $user  = $model->findByOrcid($orcidId);

        if (!$user) {
            $userId = $model->create([
                'name'     => $name,
                'email'    => $email,
                'orcid_id' => $orcidId,
                'role'     => 'researcher',
            ]);
            $user = $model->findById($userId);
        }

        $token       = JWT::encode([
            'sub'  => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ]);
        $frontendUrl = Config::get('FRONTEND_URL', 'http://localhost:3000');

        header("Location: $frontendUrl/dashboard?token=" . urlencode($token));
        exit;
    }

    private function exchangeOrcidCode(string $code): ?array
    {
        $clientId     = Config::get('ORCID_CLIENT_ID');
        $clientSecret = Config::get('ORCID_CLIENT_SECRET');
        $redirectUri  = Config::get('ORCID_REDIRECT_URI');

        $ch = curl_init('https://orcid.org/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) return null;

        return json_decode($response, true);
    }
}
