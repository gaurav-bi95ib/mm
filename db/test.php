<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=meromaidan;charset=utf8mb4', 'root', '');
    echo "Connected OK\n";
    echo "Venues: " . $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn() . "\n";
    echo "Slots: " . $pdo->query('SELECT COUNT(*) FROM venue_slots')->fetchColumn() . "\n";
    echo "Bookings: " . $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn() . "\n";
    echo "Admins: " . $pdo->query('SELECT COUNT(*) FROM superadmins')->fetchColumn() . "\n";
} catch(Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
