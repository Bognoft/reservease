<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

try {
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($action === 'admin-login' && $method === 'POST') {
        $data = input();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($username === '' || $password === '') jsonResponse(['error' => 'Username and password are required.'], 422);

        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            jsonResponse(['error' => 'Invalid admin credentials.'], 401);
        }
        jsonResponse(['token' => adminToken((int)$admin['id'], $admin['username']), 'username' => $admin['username']]);
    }

    if ($action === 'admin-check' && $method === 'GET') {
        $admin = requireAdmin();
        jsonResponse(['ok' => true, 'username' => $admin['username']]);
    }

    if ($action === 'tables' && $method === 'GET') {
        $stmt = $pdo->query('SELECT id, name, seats, x, y, width, height, status FROM restaurant_tables ORDER BY id');
        jsonResponse(['tables' => $stmt->fetchAll()]);
    }

    if ($action === 'reservations' && $method === 'GET') {
        requireAdmin();
        $stmt = $pdo->query('SELECT id, code, table_id, guest_name, phone, party_size, reservation_date, time_slot, deposit, payment_method, status, created_at FROM reservations ORDER BY created_at DESC');
        jsonResponse(['reservations' => $stmt->fetchAll()]);
    }

    if ($action === 'waitlist' && $method === 'GET') {
        requireAdmin();
        $stmt = $pdo->query('SELECT id, name, phone, party_size, reservation_date, time_slot, note, status, seated_table_id, created_at FROM waitlist ORDER BY created_at DESC');
        jsonResponse(['waitlist' => $stmt->fetchAll()]);
    }

    if ($action === 'reserve' && $method === 'POST') {
        $data = input();
        $tableId = (int)($data['table_id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $party = (int)($data['party_size'] ?? 0);
        $date = (string)($data['date'] ?? '');
        $time = trim((string)($data['time_slot'] ?? ''));
        $methodName = trim((string)($data['payment_method'] ?? 'GCash'));

        if (!$tableId || !$name || !$phone || $party < 1 || !$date || !$time) jsonResponse(['error' => 'Please complete all reservation fields.'], 422);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, seats, status FROM restaurant_tables WHERE id = ? FOR UPDATE');
        $stmt->execute([$tableId]);
        $table = $stmt->fetch();
        if (!$table || $table['status'] !== 'available' || (int)$table['seats'] < $party) {
            $pdo->rollBack();
            jsonResponse(['error' => 'That table is no longer available.'], 409);
        }

        $code = makeCode();
        $stmt = $pdo->prepare('INSERT INTO reservations (code, table_id, guest_name, phone, party_size, reservation_date, time_slot, deposit, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, 200, ?, "upcoming")');
        $stmt->execute([$code, $tableId, $name, $phone, $party, $date, $time, $methodName]);
        $pdo->prepare('UPDATE restaurant_tables SET status = "reserved" WHERE id = ?')->execute([$tableId]);
        $pdo->commit();
        jsonResponse(['message' => 'Reservation confirmed.', 'code' => $code]);
    }

    if ($action === 'waitlist-add' && $method === 'POST') {
        $data = input();
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $party = (int)($data['party_size'] ?? 0);
        $date = (string)($data['date'] ?? '');
        $time = trim((string)($data['time_slot'] ?? ''));
        $note = trim((string)($data['note'] ?? ''));
        if (!$name || !$phone || $party < 1 || !$date || !$time) jsonResponse(['error' => 'Please complete all waitlist fields.'], 422);
        $stmt = $pdo->prepare('INSERT INTO waitlist (name, phone, party_size, reservation_date, time_slot, note) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone, $party, $date, $time, $note ?: null]);
        jsonResponse(['message' => 'Added to the waitlist.']);
    }

    if ($action === 'reservation-status' && $method === 'POST') {
        $data = input();
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $stmt = $pdo->prepare('SELECT r.*, t.name AS table_name, t.seats FROM reservations r JOIN restaurant_tables t ON t.id = r.table_id WHERE r.code = ? LIMIT 1');
        $stmt->execute([$code]);
        $reservation = $stmt->fetch();
        if (!$reservation) jsonResponse(['error' => 'Reservation not found.'], 404);
        jsonResponse(['reservation' => $reservation]);
    }

    if ($action === 'admin-action' && $method === 'POST') {
        requireAdmin();
        $data = input();
        $type = $data['type'] ?? '';
        $code = trim((string)($data['code'] ?? ''));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE code = ? FOR UPDATE');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        if (!$r) { $pdo->rollBack(); jsonResponse(['error' => 'Reservation not found.'], 404); }

        if ($type === 'arrive' && $r['status'] === 'upcoming') {
            $pdo->prepare('UPDATE reservations SET status = "arrived" WHERE code = ?')->execute([$code]);
            $pdo->prepare('UPDATE restaurant_tables SET status = "occupied" WHERE id = ?')->execute([$r['table_id']]);
        } elseif ($type === 'release' && $r['status'] === 'arrived') {
            $pdo->prepare('UPDATE reservations SET status = "completed" WHERE code = ?')->execute([$code]);
            $pdo->prepare('UPDATE restaurant_tables SET status = "available" WHERE id = ?')->execute([$r['table_id']]);
        } elseif ($type === 'cancel' && $r['status'] === 'upcoming') {
            $pdo->prepare('UPDATE reservations SET status = "cancelled" WHERE code = ?')->execute([$code]);
            $pdo->prepare('UPDATE restaurant_tables SET status = "available" WHERE id = ?')->execute([$r['table_id']]);
        } else {
            $pdo->rollBack();
            jsonResponse(['error' => 'This action is not allowed for the current status.'], 409);
        }
        $pdo->commit();
        jsonResponse(['message' => 'Updated successfully.']);
    }

    if ($action === 'waitlist-seat' && $method === 'POST') {
        requireAdmin();
        $data = input();
        $waitId = (int)($data['waitlist_id'] ?? 0);
        $tableId = (int)($data['table_id'] ?? 0);
        $pdo->beginTransaction();
        $w = $pdo->prepare('SELECT * FROM waitlist WHERE id = ? FOR UPDATE');
        $w->execute([$waitId]);
        $wait = $w->fetch();
        $t = $pdo->prepare('SELECT * FROM restaurant_tables WHERE id = ? FOR UPDATE');
        $t->execute([$tableId]);
        $table = $t->fetch();
        if (!$wait || !$table || $wait['status'] !== 'waiting' || $table['status'] !== 'available' || (int)$table['seats'] < (int)$wait['party_size']) {
            $pdo->rollBack();
            jsonResponse(['error' => 'That table cannot seat this guest.'], 409);
        }
        $code = makeCode();
        $stmt = $pdo->prepare('INSERT INTO reservations (code, table_id, guest_name, phone, party_size, reservation_date, time_slot, deposit, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, 0, "Front Desk", "arrived")');
        $stmt->execute([$code, $tableId, $wait['name'], $wait['phone'], $wait['party_size'], $wait['reservation_date'], $wait['time_slot']]);
        $pdo->prepare('UPDATE restaurant_tables SET status = "occupied" WHERE id = ?')->execute([$tableId]);
        $pdo->prepare('UPDATE waitlist SET status = "converted", seated_table_id = ? WHERE id = ?')->execute([$tableId, $waitId]);
        $pdo->commit();
        jsonResponse(['message' => 'Guest seated.', 'code' => $code]);
    }

    jsonResponse(['error' => 'Unknown endpoint.'], 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonResponse(['error' => 'Server error. Check your database configuration.'], 500);
}
