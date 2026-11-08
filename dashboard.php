
<?php
session_start();
require_once "db.php";

// Require login
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// Admin-only dashboard
if ($_SESSION["role"] !== "Admin") {
    header("Location: user_dashboard.php");
    exit;
}

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "Stockroom Staff";

// Get dashboard counts.
$projectCount = 0;
$materialCount = 0;
$requestCount = 0;
$pendingCount = 0;
$issueCount = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM projects");
if ($result) {
    $projectCount = $result->fetch_assoc()["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM materials");
if ($result) {
    $materialCount = $result->fetch_assoc()["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM material_requests");
if ($result) {
    $requestCount = $result->fetch_assoc()["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM material_requests
    WHERE request_status = 'Pending'
");
if ($result) {
    $pendingCount = $result->fetch_assoc()["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM material_issues");
if ($result) {
    $issueCount = $result->fetch_assoc()["total"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construction Materials Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #F5F1E8;
            font-family: Arial, sans-serif;
            color: #64748B;
        }

        .dashboard-container {
            max-width: 1100px;
            margin: 20px auto;
        }

        .header {
            background: #FFFEFA;
            padding: 25px 30px;
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 25px;
        }

        h1, h2, h3 {
            color: #34495E;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
        }

        .role {
            display: inline-block;
            background: #E7EEF5;
            color: #34495E;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 10px;
        }

        .logout {
            background: #647F9A;
            color: white;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .logout:hover {
            background: #506B86;
        }

        .section-title {
            margin: 25px 0 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .stat-card {
            background: #FFFEFA;
            padding: 22px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .stat-card p {
            margin: 0 0 10px;
            font-size: 14px;
        }

        .stat-card h2 {
            margin: 0;
            font-size: 30px;
        }

        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 18px;
        }

        .module-card {
            background: #FFFEFA;
            padding: 23px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid #E7EEF5;
            display: flex;
            flex-direction: column;
            min-height: 190px;
        }

        .module-icon {
            width: 43px;
            height: 43px;
            background: #E7EEF5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 12px;
        }

        .module-card h3 {
            margin: 0 0 9px;
        }

        .module-card p {
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 18px;
            flex-grow: 1;
        }

        .module-card a {
            display: inline-block;
            background: #647F9A;
            color: white;
            text-decoration: none;
            text-align: center;
            padding: 11px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        .module-card a:hover {
            background: #506B86;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
            color: #8492A3;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 23px;
            }

            .logout {
                display: inline-block;
            }
        }
    </style>
</head>

<body>

<div class="dashboard-container">

    <!-- HEADER -->
    <div class="header">
        <div>
            <h1>Construction Materials Allocation System</h1>
            <p>Welcome back, <?= htmlspecialchars($username) ?>!</p>
            <span class="role">
                <?= htmlspecialchars($role) ?>
            </span>
        </div>

        <a href="logout.php" class="logout">Logout</a>
    </div>

    <!-- DASHBOARD STATISTICS -->
    <h2 class="section-title">Dashboard Overview</h2>

    <div class="stats">

        <div class="stat-card">
            <p>Total Projects</p>
            <h2><?= $projectCount ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Materials</p>
            <h2><?= $materialCount ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Requests</p>
            <h2><?= $requestCount ?></h2>
        </div>

        <div class="stat-card">
            <p>Pending Requests</p>
            <h2><?= $pendingCount ?></h2>
        </div>

        <div class="stat-card">
            <p>Material Issues</p>
            <h2><?= $issueCount ?></h2>
        </div>

    </div>

    <!-- SYSTEM MODULES -->
    <h2 class="section-title">System Management</h2>

    <div class="modules">

        <?php if ($role === "Admin"): ?>
        <div class="module-card">
            <div class="module-icon">🏗️</div>
            <h3>Projects Management</h3>
            <p>Add, edit, view, and manage construction projects and their locations.</p>
            <a href="projects.php">Manage Projects</a>
        </div>

        <div class="module-card">
            <div class="module-icon">🧱</div>
            <h3>Materials Management</h3>
            <p>Manage construction materials, material codes, and units of measurement.</p>
            <a href="materials.php">Manage Materials</a>
        </div>
        <?php endif; ?>

        <div class="module-card">
            <div class="module-icon">📋</div>
            <h3>Material Requests</h3>
            <p>Submit and view material requests for construction projects.</p>
            <a href="requests.php">Open Requests</a>
        </div>

        <?php if ($role === "Admin" || $role === "Supervisor"): ?>
        <div class="module-card">
            <div class="module-icon">✅</div>
            <h3>Material Approvals</h3>
            <p>Review requests and approve or reject requested material quantities.</p>
            <a href="approvals.php">Manage Approvals</a>
        </div>
        <?php endif; ?>

        <?php if ($role === "Admin" || $role === "Stockroom Staff"): ?>
        <div class="module-card">
            <div class="module-icon">📦</div>
            <h3>Material Issuance</h3>
            <p>Record actual materials released by the stockroom to construction projects.</p>
            <a href="issues.php">Manage Issuance</a>
        </div>
        <?php endif; ?>

        <div class="module-card">
            <div class="module-icon">📊</div>
            <h3>Allocation Reports</h3>
            <p>View project material requests, approved quantities, and issued materials.</p>
            <a href="reports.php">View Reports</a>
        </div>

    </div>

    <div class="footer">
        <p>Construction Project Materials Allocation System</p>
        <p>Project Management | Material Requests | Approvals | Issuance</p>
    </div>

</div>

</body>
</html>
