<?php
/**
 * $active (string)  – give the page name ('dashboard' / 'reports' / etc.)
 *                    so we can highlight the current link.
 */
$active = $active ?? '';
?>
<style>
    /* Sidebar styling – move this to a separate CSS file if you like */
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


    <a class="side-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard_staff.php' ? 'active' : '' ?>"
        href="dashboard_staff.php">
        Dashboard
    </a>

    <a class="side-link <?= basename($_SERVER['PHP_SELF']) === 'report_form.php' ? 'active' : '' ?>"
        href="report_form.php">
        Report Form
    </a>

    <a class="side-link <?= basename($_SERVER['PHP_SELF']) === 'detailsReport_staff.php' ? 'active' : '' ?>"
        href="detailsReport_staff.php">
        Report Details
    </a>

    <div class="mt-auto p-3">
        <form action="logout.php" method="post">
            <button class="logout-btn">LOGOUT</button>
        </form>
    </div>
</div>