<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$active = $active ?? '';
?>
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<style>
    .sidebar {
        width: 250px;
        min-height: 100vh;
        background: #008C9E;
        color: #fff;
    }

    .sidebar h3 {
        border-bottom: 2px solid #fff;
        padding: 20px 20px 10px;
        margin: 0;
    }

    .side-link {
        color: #fff;
        text-decoration: none;
        display: block;
        padding: 12px 20px;
    }

    .side-link:hover,
    .side-link.active {
        background: #00727e;
    }

    .logout-btn {
        border: none;
        background: #ff3838;
        color: #fff;
        width: 100%;
        padding: 10px 0;
    }
</style>

<div class="sidebar d-flex flex-column">
    <h3>Maintenance<br>Report System</h3>

    <a class="side-link <?= basename($_SERVER['PHP_SELF']) === 'dashboardadmin.php' ? 'active' : '' ?>"
        href="dashboardadmin.php">
        Dashboard
    </a>

    <a class="side-link <?= basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? 'active' : '' ?>"
        href="manage_users.php">
        Manage Users
    </a>

    <a class="side-link <?= basename($_SERVER['PHP_SELF']) === 'view_reports.php' ? 'active' : '' ?>"
        href="view_reports.php">
        All Reports
    </a>

    <div class="mt-auto p-3">
        <form action="logout.php" method="post">
            <button class="logout-btn">LOGOUT</button>
        </form>
    </div>
</div>
<?php endif; ?>
