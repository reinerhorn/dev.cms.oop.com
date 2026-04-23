
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header("Location:/index.php");
    exit;
}

use CMSApp;
$connection = CMSApp::getDb();

$result = $connection->query("SELECT l.*, u.username, u.email FROM login_agb_log l
    LEFT JOIN login_users u ON l.user_id = u.id
    ORDER BY l.login_time DESC");

?>

<div class="admin_container">
    <div class="admin_box">
        <h2>AGB Zustimmungen (Log)</h2>
        <table border="1" cellpadding="6" cellspacing="0" style="width:100%; background:#fff; color:#333;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>IP-Adresse</th>
                    <th>Zugestimmt</th>
                    <th>Login-Zeit</th>
                    <th>Logout-Zeit</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['ip_address']) ?></td>
                    <td style="text-align:center; color:green;">
                        <?= $row['agb_agreed'] ? '✅' : '❌' ?>
                    </td>
                    <td><?= $row['login_time'] ?></td>
                    <td><?= $row['logout_time'] ?? '—' ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>