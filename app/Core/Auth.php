<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function attempt(PDO $pdo, string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        $stmt = $pdo->prepare('SELECT id, role, name, email, password_hash, active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['active'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $rehash->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        }

        self::establish([
            'id' => (int) $user['id'],
            'role' => (string) $user['role'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ]);

        return true;
    }

    public static function establish(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'role' => (string) $user['role'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ];
    }

    public static function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireAuth(): array
    {
        $user = self::user();
        if ($user === null) {
            header('Location: login.php');
            exit;
        }

        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }
}
