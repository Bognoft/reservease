<?php

declare(strict_types=1);

function env(string $key, string $default = ''): string
{
    $value = getenv($key);

    return $value === false ? $default : $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // XAMPP MySQL configuration
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'reservease');
    $user = env('DB_USER', 'admin');
    $pass = env('DB_PASS', 'password');

    if (!$host || !$name || !$user) {
        throw new RuntimeException(
            'Database environment variables are not configured.'
        );
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($data);

    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input');

    $data = json_decode($raw ?: '{}', true);

    return is_array($data) ? $data : [];
}

function adminToken(int $adminId, string $username): string
{
    $payload = base64url(
        json_encode([
            'id' => $adminId,
            'username' => $username,
            'exp' => time() + 60 * 60 * 8,
        ])
    );

    $sig = base64url(
        hash_hmac(
            'sha256',
            $payload,
            env(
                'ADMIN_SECRET',
                'reservease-admin-secret-change-this'
            ),
            true
        )
    );

    return $payload . '.' . $sig;
}

function base64url(string $value): string
{
    return rtrim(
        strtr(
            base64_encode($value),
            '+/',
            '-_'
        ),
        '='
    );
}

function requireAdmin(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        jsonResponse(
            ['error' => 'Admin login required.'],
            401
        );
    }

    [$payload, $sig] = array_pad(
        explode('.', $m[1], 2),
        2,
        ''
    );

    $expected = base64url(
        hash_hmac(
            'sha256',
            $payload,
            env(
                'ADMIN_SECRET',
                'reservease-admin-secret-change-this'
            ),
            true
        )
    );

    if (
        !$payload ||
        !$sig ||
        !hash_equals($expected, $sig)
    ) {
        jsonResponse(
            ['error' => 'Invalid admin token.'],
            401
        );
    }

    $decoded = json_decode(
        base64_decode(
            strtr($payload, '-_', '+/') .
            str_repeat(
                '=',
                (4 - strlen($payload) % 4) % 4
            )
        ),
        true
    );

    if (
        !is_array($decoded) ||
        ($decoded['exp'] ?? 0) < time()
    ) {
        jsonResponse(
            ['error' => 'Admin token expired.'],
            401
        );
    }

    return $decoded;
}

function makeCode(): string
{
    return 'RE-' . strtoupper(
        bin2hex(random_bytes(3))
    );
}