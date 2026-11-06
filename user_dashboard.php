
<?php
session_start();
require_once "db.php";

// Require login
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// Admin must use the admin dashboard
if ($_SESSION["role"] === "Admin") {
    header("Location: dashboard.php");
    exit;
}

$projects = mysqli_query($conn, "
    SELECT project_code, project_name, project_site, project_status
    FROM projects
    ORDER BY project_name ASC
");

$materials = mysqli_query($conn, "
    SELECT material_code, material_name, unit_of_measure
    FROM materials
    WHERE material_status = 'Active'
    ORDER BY material_name ASC
");

$project_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM projects"
))["total"];

$material_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM materials WHERE material_status = 'Active'"
))["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 25px;
    background: #F5F1E8;
    font-family: Arial, sans-serif;
    color: #64748B;
}

.container {
    max-width: 1100px;
    margin: auto;
}

.header {
    background: #FFFEFA;
    padding: 25px;
    border-radius: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

h1, h2 {
    color: #34495E;
}

.header p {
    margin-bottom: 0;
}

.logout {
    background: #647F9A;
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
    text-decoration: none;
}

.stats {
    display: flex;
    gap: 20px;
    margin: 25px 0;
    flex-wrap: wrap;
}

.stat {
    flex: 1;
    min-width: 200px;
    background: #FFFEFA;
    padding: 22px;
    border-radius: 12px;
}

.stat h2 {
    font-size: 30px;
    margin: 10px 0 0;
}

.section {
    background: #FFFEFA;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 22px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #E7EEF5;
}

th {
    background: #E7EEF5;
    color: #34495E;
}

.empty {
    text-align: center;
    padding: 20px;
}

@media(max-width: 600px) {
    body { padding: 12px; }
    .header { align-items: flex-start; }
}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>User Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?>!</p>
            <p>Role: <?= htmlspecialchars($_SESSION["role"]) ?></p>
        </div>
        <a class="logout" href="logout.php">Logout</a>
    </div>

    <div class="stats">
        <div class="stat">
            <p>Total Projects</p>
            <h2><?= $project_count ?></h2>
        </div>

        <div class="stat">
            <p>Active Materials</p>
            <h2><?= $material_count ?></h2>
        </div>
    </div>

    <div class="section">
        <h2>Project Information</h2>

        <table>
            <tr>
                <th>Project Code</th>
                <th>Project Name</th>
                <th>Project Site</th>
                <th>Status</th>
            </tr>

            <?php if (mysqli_num_rows($projects) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($projects)): ?>
                <tr>
                    <td><?= htmlspecialchars($row["project_code"]) ?></td>
                    <td><?= htmlspecialchars($row["project_name"]) ?></td>
                    <td><?= htmlspecialchars($row["project_site"]) ?></td>
                    <td><?= htmlspecialchars($row["project_status"]) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" class="empty">No projects available.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="section">
        <h2>Available Materials</h2>

        <table>
            <tr>
                <th>Material Code</th>
                <th>Material Name</th>
                <th>Unit of Measure</th>
            </tr>

            <?php if (mysqli_num_rows($materials) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($materials)): ?>
                <tr>
                    <td><?= htmlspecialchars($row["material_code"]) ?></td>
                    <td><?= htmlspecialchars($row["material_name"]) ?></td>
                    <td><?= htmlspecialchars($row["unit_of_measure"]) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3" class="empty">No active materials available.</td></tr>
            <?php endif; ?>
        </table>
    </div>

</div>
</body>
</html>
