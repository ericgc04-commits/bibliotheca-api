<?php
// Run this file ONCE after deploying to set correct passwords
// Then DELETE it immediately for security

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

try {
    $db = Database::getInstance();

    $users = [
        ['username' => 'admin',      'password' => 'Admin@1234'],
        ['username' => 'john_doe',   'password' => 'Member@1234'],
        ['username' => 'jane_smith', 'password' => 'Member@1234'],
        ['username' => 'bob_jones',  'password' => 'Member@1234'],
        ['username' => 'emma_wilson','password' => 'Member@1234'],
    ];

    foreach ($users as $user) {
        $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE username = ?');
        $stmt->execute([$hash, $user['username']]);
        echo "✅ " . $user['username'] . " updated<br>";
    }

    echo "<br><strong style='color:green'>All passwords fixed! DELETE this file now.</strong>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
