<?php
// ============================================================
//  WVMS - Authentication Helper
// ============================================================

require_once __DIR__ . '/../config/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}

function requireLogin(string $redirect = '../index.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole(string $role, string $redirect = '../index.php'): void {
    requireLogin($redirect);
    if ($_SESSION['role'] !== $role) {
        header("Location: $redirect");
        exit;
    }
}

function login(string $phone, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE phone = ? AND is_active = 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid phone number or password.'];
    }

    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];

    return ['success' => true, 'role' => $user['role']];
}

function register(array $data): array {
    $db = getDB();

    // Check if phone already exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE phone = ?");
    $stmt->execute([$data['phone']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Phone number already registered.'];
    }

    $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        "INSERT INTO users (full_name, phone, email, password, role, location)
         VALUES (?, ?, ?, ?, 'customer', ?)"
    );
    $stmt->execute([
        $data['full_name'],
        $data['phone'],
        $data['email']   ?? null,
        $hashed,
        $data['location'] ?? null,
    ]);

    return ['success' => true, 'message' => 'Account created successfully. Please log in.'];
}

function logout(): void {
    startSession();
    session_destroy();
    header("Location: ../index.php");
    exit;
}
