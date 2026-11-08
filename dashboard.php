
<?php
session_start();
require_once "db.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: user_dashboard.php");
    exit;
}

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "Admin";



$projectCount = 0;
$materialCount = 0;
$requestCount = 0;
$pendingCount = 0;
$issueCount = 0;

// Total projects
$result = $conn->query("SELECT COUNT(*) AS total FROM projects");
if ($result) {
    $projectCount = (int)$result->fetch_assoc()["total"];
}

// Total materials
$result = $conn->query("SELECT COUNT(*) AS total FROM materials");
if ($result) {
    $materialCount = (int)$result->fetch_assoc()["total"];
}

// Total requests
$result = $conn->query("SELECT COUNT(*) AS total FROM material_requests");
if ($result) {
    $requestCount = (int)$result->fetch_assoc()["total"];
}

// Pending requests
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM material_requests
    WHERE request_status = 'Pending'
");
if ($result) {
    $pendingCount = (int)$result->fetch_assoc()["total"];
}

// Total material issuance records
$result = $conn->query("SELECT COUNT(*) AS total FROM material_issues");
if ($result) {
    $issueCount = (int)$result->fetch_assoc()["total"];
}



$statusLabels = [];
$statusValues = [];

$statusQuery = $conn->query("
    SELECT request_status, COUNT(*) AS total
    FROM material_requests
    GROUP BY request_status
");

if ($statusQuery) {
    while ($row = $statusQuery->fetch_assoc()) {
        $statusLabels[] = $row["request_status"];
        $statusValues[] = (int)$row["total"];
    }
}


$projectLabels = [];
$projectValues = [];

$projectQuery = $conn->query("
    SELECT
        p.project_name,
        COUNT(mr.request_id) AS total
    FROM projects p
    LEFT JOIN material_requests mr
        ON p.project_id = mr.project_id
    GROUP BY p.project_id, p.project_name
    ORDER BY total DESC
    LIMIT 8
");

if ($projectQuery) {
    while ($row = $projectQuery->fetch_assoc()) {
        $projectLabels[] = $row["project_name"];
        $projectValues[] = (int)$row["total"];
    }
}

// ======================================
// GRAPH 3: MATERIAL ISSUANCE TREND
// ======================================

$issueLabels = [];
$issueValues = [];

$issueQuery = $conn->query("
    SELECT
        issue_date,
        SUM(issued_quantity) AS total
    FROM material_issues
    GROUP BY issue_date
    ORDER BY issue_date DESC
    LIMIT 7
");

if ($issueQuery) {
    while ($row = $issueQuery->fetch_assoc()) {
        $issueLabels[] = date(
            "M d, Y",
            strtotime($row["issue_date"])
        );

        $issueValues[] = (float)$row["total"];
    }
}

// Reverse so oldest date appears first
$issueLabels = array_reverse($issueLabels);
$issueValues = array_reverse($issueValues);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | Construction Materials Allocation</title>

<!-- ======================================
     CSS DESIGN
====================================== -->

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #F5F1E8;
    font-family: Arial, sans-serif;
    color: #64748B;
    padding: 25px;
}

/* MAIN CONTAINER */

.dashboard-container {
    max-width: 1250px;
    margin: 15px auto;
    background: #FFFEFA;
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 8px 35px rgba(52, 73, 94, 0.08);
}

/* TOP NAVIGATION */

.topbar {
    min-height: 78px;
    padding: 15px 35px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: #FFFEFA;
    border-bottom: 1px solid #E7EEF5;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #34495E;
    font-weight: bold;
    font-size: 15px;
    white-space: nowrap;
}

.brand-icon {
    width: 42px;
    height: 42px;
    background: #E7EEF5;
    color: #647F9A;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: wrap;
}

.nav-links a {
    color: #64748B;
    text-decoration: none;
    font-size: 14px;
    padding: 12px 0;
    transition: 0.2s;
}

.nav-links a:hover,
.nav-links a.active {
    color: #547C9F;
}

.nav-links a.active {
    border-bottom: 3px solid #79A9C9;
}

/* ADMIN PROFILE */

.account {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-icon {
    width: 39px;
    height: 39px;
    background: #34495E;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.account-info {
    font-size: 13px;
    color: #34495E;
}

.account-info small {
    display: block;
    color: #94A3B8;
    margin-top: 3px;
}

.logout {
    text-decoration: none;
    color: #647F9A;
    font-size: 13px;
    font-weight: bold;
    margin-left: 8px;
}

.logout:hover {
    color: #34495E;
}

/* MAIN CONTENT */

.main-content {
    padding: 35px;
    background: #F2F6FA;
    min-height: 650px;
}

/* PAGE HEADING */

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 28px;
}

.page-heading h1 {
    color: #34495E;
    font-size: 29px;
    font-weight: 600;
    margin-bottom: 8px;
}

.page-heading p {
    font-size: 14px;
    color: #8492A3;
}

.admin-label {
    background: #E1EBF3;
    color: #547C9F;
    padding: 9px 17px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

/* DASHBOARD TABS */

.dashboard-tabs {
    display: flex;
    align-items: center;
    gap: 30px;
    border-bottom: 1px solid #DCE5ED;
    margin-bottom: 25px;
    overflow-x: auto;
}

.dashboard-tabs a {
    text-decoration: none;
    color: #64748B;
    padding: 13px 0;
    font-size: 14px;
    white-space: nowrap;
}

.dashboard-tabs a.active {
    color: #547C9F;
    border-bottom: 3px solid #79A9C9;
    font-weight: bold;
}

/* STATISTICS */

.stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 35px;
}

.stat-card {
    background: #FFFEFA;
    border: 1px solid #E4EAF0;
    border-radius: 13px;
    padding: 20px;
    min-height: 125px;
    transition: 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(52, 73, 94, 0.07);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.stat-label {
    font-size: 13px;
    color: #8492A3;
}

.stat-icon {
    width: 32px;
    height: 32px;
    background: #E7EEF5;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.stat-card h2 {
    color: #34495E;
    font-size: 27px;
    font-weight: 600;
}

/* ANALYTICS SECTION */

.analytics-section {
    margin: 35px 0;
}

.analytics-title {
    color: #34495E;
    font-size: 20px;
    margin-bottom: 8px;
}

.analytics-subtitle {
    color: #8492A3;
    font-size: 13px;
    margin-bottom: 20px;
}

/* CHART GRID */

.chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.chart-card {
    background: #FFFEFA;
    border: 1px solid #E2E9F0;
    border-radius: 14px;
    padding: 22px;
    min-width: 0;
}

.chart-card h3 {
    color: #34495E;
    font-size: 15px;
    margin-bottom: 7px;
}

.chart-card p {
    color: #94A3B8;
    font-size: 12px;
    margin-bottom: 18px;
}

.chart-container {
    position: relative;
    height: 260px;
    width: 100%;
}

.chart-card.full-width {
    grid-column: 1 / -1;
}

.chart-card.full-width .chart-container {
    height: 300px;
}

/* SYSTEM MANAGEMENT */

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 15px;
}

.section-heading h2 {
    font-size: 20px;
    color: #34495E;
    font-weight: 600;
}

.section-heading span {
    font-size: 13px;
    color: #94A3B8;
}

/* MODULE TABLE */

.module-table {
    background: #FFFEFA;
    border: 1px solid #E2E9F0;
    border-radius: 14px;
    overflow: hidden;
}

.table-header,
.module-row {
    display: grid;
    grid-template-columns: 2fr 3fr 1.2fr;
    align-items: center;
    gap: 20px;
    padding: 16px 22px;
}

.table-header {
    background: #E7EEF5;
    color: #64748B;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.7px;
}

.module-row {
    border-bottom: 1px solid #EDF1F5;
    min-height: 88px;
    transition: 0.2s;
}

.module-row:last-child {
    border-bottom: none;
}

.module-row:hover {
    background: #F9FBFD;
}

.module-name {
    display: flex;
    align-items: center;
    gap: 14px;
}

.module-icon {
    width: 43px;
    height: 43px;
    background: #E7EEF5;
    color: #547C9F;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    flex-shrink: 0;
}

.module-name h3 {
    color: #34495E;
    font-size: 14px;
    margin-bottom: 5px;
}

.module-name small {
    color: #94A3B8;
    font-size: 12px;
}

.module-description {
    font-size: 13px;
    line-height: 1.6;
    color: #8492A3;
}

.module-action {
    text-align: right;
}

.module-action a {
    display: inline-block;
    padding: 10px 15px;
    background: #E7EEF5;
    color: #547C9F;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
    border-radius: 7px;
    transition: 0.2s;
}

.module-action a:hover {
    background: #647F9A;
    color: white;
}

/* FOOTER */

.footer {
    text-align: center;
    padding: 23px;
    background: #FFFEFA;
    border-top: 1px solid #E7EEF5;
    font-size: 12px;
    color: #94A3B8;
    line-height: 1.8;
}

/* RESPONSIVE DESIGN */

@media (max-width: 1000px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }

    .topbar {
        flex-wrap: wrap;
    }

    .nav-links {
        order: 3;
        width: 100%;
        gap: 20px;
    }
}

@media (max-width: 750px) {

    .chart-grid {
        grid-template-columns: 1fr;
    }

    .chart-card.full-width {
        grid-column: auto;
    }
}

@media (max-width: 700px) {

    body {
        padding: 10px;
    }

    .main-content {
        padding: 22px 16px;
    }

    .topbar {
        padding: 18px;
    }

    .brand {
        font-size: 13px;
    }

    .account-info {
        display: none;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .table-header {
        display: none;
    }

    .module-row {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 18px;
    }

    .module-action {
        text-align: left;
    }

    .module-action a {
        width: 100%;
        text-align: center;
    }

    .section-heading h2 {
        font-size: 18px;
    }

    .page-heading h1 {
        font-size: 23px;
    }
}

@media (max-width: 400px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .nav-links {
        gap: 15px;
    }
}

</style>

</head>

<body>

<div class="dashboard-container">

    <!-- ======================================
         TOP NAVIGATION
    ======================================= -->

    <header class="topbar">

        <div class="brand">

            <div class="brand-icon">🏗️</div>

            <span>Materials Allocation</span>

        </div>

        <nav class="nav-links">

            <a href="dashboard.php" class="active">Dashboard</a>

            <a href="projects.php">Projects</a>

            <a href="materials.php">Materials</a>

            <a href="requests.php">Requests</a>

            <a href="reports.php">Reports</a>

        </nav>

        <div class="account">

            <div class="profile-icon">
                <?= htmlspecialchars(strtoupper(substr($username, 0, 1))) ?>
            </div>

            <div class="account-info">

                <strong><?= htmlspecialchars($username) ?></strong>

                <small><?= htmlspecialchars($role) ?></small>

            </div>

            <a href="logout.php" class="logout">Logout</a>

        </div>

    </header>



    <main class="main-content">
        <div class="page-heading">

            <div>

                <h1>Dashboard</h1>

                <p>
                    Welcome back, <?= htmlspecialchars($username) ?>!
                </p>

            </div>

            <span class="admin-label">ADMINISTRATOR</span>

        </div>

        <!-- DASHBOARD TABS -->

        <div class="dashboard-tabs">

            <a href="#overview" class="active">Overview</a>

            <a href="#analytics">Analytics</a>

            <a href="#management">System Management</a>

            <a href="requests.php">Material Requests</a>

            <a href="reports.php">Reports</a>

        </div>

        
        <section id="overview">

            <div class="stats">

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">Total Projects</span>

                        <div class="stat-icon">🏗️</div>

                    </div>

                    <h2><?= $projectCount ?></h2>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">Total Materials</span>

                        <div class="stat-icon">🧱</div>

                    </div>

                    <h2><?= $materialCount ?></h2>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">Total Requests</span>

                        <div class="stat-icon">📋</div>

                    </div>

                    <h2><?= $requestCount ?></h2>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">Pending Requests</span>

                        <div class="stat-icon">⏳</div>

                    </div>

                    <h2><?= $pendingCount ?></h2>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <span class="stat-label">Material Issues</span>

                        <div class="stat-icon">📦</div>

                    </div>

                    <h2><?= $issueCount ?></h2>

                </div>

            </div>

        </section>

        <!-- ======================================
             DASHBOARD ANALYTICS / GRAPHS
        ======================================= -->

        <section class="analytics-section" id="analytics">

            <h2 class="analytics-title">Dashboard Analytics</h2>

            <p class="analytics-subtitle">
                Visual summary of material requests, project activity,
                and material issuance.
            </p>

            <div class="chart-grid">

                <!-- GRAPH 1: REQUEST STATUS -->

                <div class="chart-card">

                    <h3>Material Request Status</h3>

                    <p>
                        Distribution of all submitted material requests.
                    </p>

                    <div class="chart-container">

                        <canvas id="statusChart"></canvas>

                    </div>

                </div>

                <!-- GRAPH 2: REQUESTS PER PROJECT -->

                <div class="chart-card">

                    <h3>Requests per Project</h3>

                    <p>
                        Number of material requests submitted for each project.
                    </p>

                    <div class="chart-container">

                        <canvas id="projectChart"></canvas>

                    </div>

                </div>

                <!-- GRAPH 3: MATERIAL ISSUANCE TREND -->

                <div class="chart-card full-width">

                    <h3>Material Issuance Trend</h3>

                    <p>
                        Total quantity of materials issued on the latest
                        seven recorded issuance dates.
                    </p>

                    <div class="chart-container">

                        <canvas id="issuanceChart"></canvas>

                    </div>

                </div>

            </div>

        </section>

        <!-- ======================================
             SYSTEM MANAGEMENT
        ======================================= -->

        <section id="management">

            <div class="section-heading">

                <h2>System Management</h2>

                <span>Manage your construction operations</span>

            </div>

            <div class="module-table">

                <!-- TABLE HEADER -->

                <div class="table-header">

                    <div>Module</div>

                    <div>Description</div>

                    <div style="text-align:right;">Action</div>

                </div>

                <!-- PROJECTS MANAGEMENT -->

                <div class="module-row">

                    <div class="module-name">

                        <div class="module-icon">🏗️</div>

                        <div>

                            <h3>Projects Management</h3>

                            <small>Project information</small>

                        </div>

                    </div>

                    <div class="module-description">

                        Add, edit, view, and manage construction
                        projects and their locations.

                    </div>

                    <div class="module-action">

                        <a href="projects.php">
                            Manage Projects →
                        </a>

                    </div>

                </div>

                <!-- MATERIALS MANAGEMENT -->

                <div class="module-row">

                    <div class="module-name">

                        <div class="module-icon">🧱</div>

                        <div>

                            <h3>Materials Management</h3>

                            <small>Material inventory records</small>

                        </div>

                    </div>

                    <div class="module-description">

                        Manage construction materials, material codes,
                        and units of measurement.

                    </div>

                    <div class="module-action">

                        <a href="materials.php">
                            Manage Materials →
                        </a>

                    </div>

                </div>

                <!-- MATERIAL REQUESTS -->

                <div class="module-row">

                    <div class="module-name">

                        <div class="module-icon">📋</div>

                        <div>

                            <h3>Material Requests</h3>

                            <small>Project material requirements</small>

                        </div>

                    </div>

                    <div class="module-description">

                        Submit and view material requests
                        for different construction projects.

                    </div>

                    <div class="module-action">

                        <a href="requests.php">
                            Open Requests →
                        </a>

                    </div>

                </div>

                <!-- MATERIAL APPROVALS -->

                <div class="module-row">

                    <div class="module-name">

                        <div class="module-icon">✅</div>

                        <div>

                            <h3>Material Approvals</h3>

                            <small>Supervisor review</small>

                        </div>

                    </div>

                    <div class="module-description">

                        Review requests and approve or reject
                        requested material quantities.

                    </div>

                    <div class="module-action">

                        <a href="approvals.php">
                            Manage Approvals →
                        </a>

                    </div>

                </div>

                <!-- MATERIAL ISSUANCE -->

                <div class="module-row">

                    <div class="module-name">

                        <div class="module-icon">📦</div>

                        <div>

                            <h3>Material Issuance</h3>

                            <small>Stockroom transactions</small>

                        </div>

                    </div>

                    <div class="module-description">

                        Record actual materials released by the stockroom
                        to construction projects.

                    </div>

                    <div class="module-action">

                        <a href="issues.php">
                            Manage Issuance →
                        </a>

                    </div>

                </div>

                <!-- ALLOCATION REPORTS -->

                <div class="module-row">

                    <div class="module-name">

                        <div class="module-icon">📊</div>

                        <div>

                            <h3>Allocation Reports</h3>

                            <small>Project allocation summary</small>

                        </div>

                    </div>

                    <div class="module-description">

                        View project requests, approved quantities,
                        issued materials, and remaining allocations.

                    </div>

                    <div class="module-action">

                        <a href="reports.php">
                            View Reports →
                        </a>

                    </div>

                </div>

            </div>

        </section>

    </main>

  

    <footer class="footer">

        <p>Construction Project Materials Allocation System</p>

        <p>
            Project Management |
            Material Requests |
            Approvals |
            Issuance
        </p>

    </footer>

</div>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>



const statusLabels = <?= json_encode(
    $statusLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const statusValues = <?= json_encode($statusValues) ?>;

const projectLabels = <?= json_encode(
    $projectLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const projectValues = <?= json_encode($projectValues) ?>;

const issueLabels = <?= json_encode(
    $issueLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const issueValues = <?= json_encode($issueValues) ?>;



Chart.defaults.font.family = "Arial";
Chart.defaults.color = "#64748B";




new Chart(document.getElementById("statusChart"), {

    type: "doughnut",

    data: {

        labels: statusLabels,

        datasets: [{

            label: "Number of Requests",

            data: statusValues,

            backgroundColor: [
                "#8FAFC9",
                "#647F9A",
                "#A8C4D8",
                "#D7B9A8"
            ],

            borderWidth: 2,

            borderColor: "#FFFEFA"

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {

                position: "bottom"

            },

            tooltip: {

                enabled: true

            }

        }

    }

});



new Chart(document.getElementById("projectChart"), {

    type: "bar",

    data: {

        labels: projectLabels,

        datasets: [{

            label: "Number of Requests",

            data: projectValues,

            backgroundColor: "#8FAFC9",

            borderRadius: 7

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        scales: {

            y: {

                beginAtZero: true,

                ticks: {

                    precision: 0

                }

            }

        },

        plugins: {

            legend: {

                display: false

            }

        }

    }

});


// ======================================
// GRAPH 3: MATERIAL ISSUANCE TREND
// ======================================

new Chart(document.getElementById("issuanceChart"), {

    type: "line",

    data: {

        labels: issueLabels,

        datasets: [{

            label: "Issued Quantity",

            data: issueValues,

            borderColor: "#647F9A",

            backgroundColor: "rgba(143, 175, 201, 0.20)",

            fill: true,

            tension: 0.35,

            pointBackgroundColor: "#547C9F",

            pointRadius: 5,

            pointHoverRadius: 7

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        scales: {

            y: {

                beginAtZero: true

            }

        },

        plugins: {

            legend: {

                position: "bottom"

            }

        }

    }

});

</script>

</body>
</html>

<?php
$conn->close();
?>
