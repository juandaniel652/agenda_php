<?php
// ─────────────────────────────────────────────
//  app/api/v1/AuthController.php
//  Espejo de app/api/v1/auth.py
// ─────────────────────────────────────────────

namespace App\Api\V1;

use App\Core\Security;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\UserRepository;

class AuthController
{
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    // POST /api/v1/auth/login
    public function login(): never
    {
        $data = Validator::fromBody()
            ->required('email')
            ->required('password')
            ->email('email')
            ->validate();

        $user = $this->userRepo->findByEmail($data['email']);

        if (!$user || !Security::verifyPassword($data['password'], $user['password_hash'])) {
            Response::unauthorized('Email o contraseña incorrectos');
        }

        if (!$user['is_active']) {
            Response::forbidden('Cuenta inactiva');
        }

        $this->userRepo->updateLastLogin($user['id']);

        $token = Security::createAccessToken([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        Response::success([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'user' => [
                'id'          => $user['id'],
                'email'       => $user['email'],
                'role'        => $user['role'],
                'is_verified' => (bool)$user['is_verified'],
            ],
        ]);
    }

    // POST /api/v1/auth/register
    public function register(): never
    {
        $data = Validator::fromBody()
            ->required('email')
            ->required('password')
            ->email('email')
            ->string('password', 6, 100)
            ->validate();

        // Verificar que el email no exista
        if ($this->userRepo->findByEmail($data['email'])) {
            Response::error('El email ya está registrado', 409);
        }

        $user = $this->userRepo->create([
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => 'user',
        ]);

        $token = Security::createAccessToken([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        Response::success([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'user' => [
                'id'          => $user['id'],
                'email'       => $user['email'],
                'role'        => $user['role'],
                'is_verified' => false,
            ],
        ], 201);
    }

    // POST /api/v1/auth/forgot-password
    public function forgotPassword(): never
    {
        $data = Validator::fromBody()
            ->required('email')
            ->email('email')
            ->validate();

        $user = $this->userRepo->findByEmail($data['email']);

        // Respuesta genérica (no revelar si el email existe)
        if (!$user) {
            Response::success(['message' => 'Si el email existe, recibirás las instrucciones']);
        }

        // Mismo token que Python: secrets.token_urlsafe(32)
        $token   = Security::generateResetToken();
        $expires = new \DateTime('+1 hour');
        $this->userRepo->setResetToken($data['email'], $token, $expires);

        // Equivalente a: await send_reset_email(user.email, token)
        try {
            \App\Core\Email::sendResetEmail($user['email'], $token);
        } catch (\RuntimeException $e) {
            // No exponer el error al cliente (mismo comportamiento que Python en prod)
            error_log('Error enviando email de reset: ' . $e->getMessage());
        }

        Response::success(['message' => 'Si el email existe, recibirás instrucciones']);
    }

    // POST /api/v1/auth/reset-password
    public function resetPassword(): never
    {
        $data = Validator::fromBody()
            ->required('token')
            ->required('password')
            ->string('password', 6, 100)
            ->validate();

        $user = $this->userRepo->findByResetToken($data['token']);
        if (!$user) {
            Response::error('Token inválido o expirado', 400);
        }

        $this->userRepo->resetPassword($user['id'], $data['password']);

        Response::success(['message' => 'Contraseña actualizada correctamente']);
    }

    // GET /api/v1/auth/me
    public function me(): never
    {
        $user = \App\Api\Deps::getCurrentUser();

        Response::success([
            'id'          => $user['id'],
            'email'       => $user['email'],
            'role'        => $user['role'],
            'is_active'   => (bool)$user['is_active'],
            'is_verified' => (bool)$user['is_verified'],
            'last_login'  => $user['last_login'],
            'created_at'  => $user['created_at'],
        ]);
    }
}