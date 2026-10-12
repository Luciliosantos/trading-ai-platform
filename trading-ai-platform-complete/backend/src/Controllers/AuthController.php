<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use PDO;

final class AuthController
{
    public static function register(): void
    {
        $data = \requestJson();
        $name = trim((string)($data['name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        if ($name === '' || strlen($name) > 120) {
            \jsonResponse(['error' => 'Informe um nome válido.'], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            \jsonResponse(['error' => 'E-mail inválido.'], 422);
            return;
        }

        if (strlen($password) < 10) {
            \jsonResponse([
                'error' => 'A senha deve ter pelo menos 10 caracteres.'
            ], 422);
            return;
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'INSERT INTO users (name, email, role)
                 VALUES (:name, :email, :role)
                 RETURNING id, name, email, created_at'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'role' => 'customer'
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare(
                'INSERT INTO user_credentials (user_id, password_hash)
                 VALUES (:user_id, :password_hash)'
            );
            $stmt->execute([
                'user_id' => $user['id'],
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                )
            ]);

            $stmt = $db->prepare(
                'INSERT INTO risk_profiles (user_id)
                 VALUES (:user_id)
                 ON CONFLICT (user_id) DO NOTHING'
            );
            $stmt->execute(['user_id' => $user['id']]);

            $db->commit();

            \jsonResponse([
                'status' => 'ok',
                'message' => 'Cadastro concluído. Faça login para continuar.',
                'user' => $user
            ], 201);
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($e->getCode() === '23505') {
                \jsonResponse(['error' => 'Este e-mail já está cadastrado.'], 409);
                return;
            }

            error_log('Falha no cadastro: ' . $e->getMessage());
            \jsonResponse(['error' => 'Não foi possível concluir o cadastro.'], 500);
        }
    }

    public static function login(): void
    {
        $data = \requestJson();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            \jsonResponse(['error' => 'Informe e-mail e senha.'], 422);
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email, c.password_hash
             FROM users u
             JOIN user_credentials c ON c.user_id = u.id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            \jsonResponse(['error' => 'E-mail ou senha incorretos.'], 401);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare(
            'INSERT INTO auth_sessions (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, NOW() + INTERVAL \'7 days\')'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token)
        ]);

        unset($user['password_hash']);

        \jsonResponse([
            'status' => 'ok',
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 604800,
            'user' => $user
        ]);
    }

    public static function me(): void
    {
        $userId = self::authenticatedUserId();

        if ($userId === null) {
            \jsonResponse(['error' => 'Sessão inválida ou expirada.'], 401);
            return;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, role, created_at
             FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);

        \jsonResponse([
            'status' => 'ok',
            'user' => $stmt->fetch(PDO::FETCH_ASSOC)
        ]);
    }

    public static function logout(): void
    {
        $token = self::bearerToken();

        if ($token !== null) {
            $stmt = Database::connection()->prepare(
                'DELETE FROM auth_sessions WHERE token_hash = :hash'
            );
            $stmt->execute(['hash' => hash('sha256', $token)]);
        }

        \jsonResponse(['status' => 'ok', 'message' => 'Sessão encerrada.']);
    }

    public static function authenticatedUserId(): ?int
    {
        $token = self::bearerToken();

        if ($token === null) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT user_id
             FROM auth_sessions
             WHERE token_hash = :hash AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
