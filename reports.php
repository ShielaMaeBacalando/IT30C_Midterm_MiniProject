
<?php
session_start();
require_once "db.php";

// Require login
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// Get filters
$project_filter = filter_input(INPUT_GET, "project_id", FILTER_VALIDATE_INT);
$project_filter = ($project_filter && $project_filter > 0)
    ? $project_filter : 0;

$date_from = $_GET["date_from"] ?? "";
$date_to = $_GET["date_to"] ?? "";

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = "";
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = "";
}

// Get projects for filter
$project_query = mysqli_query(
    $conn,
    "SELECT project_id, project_code, project_name
     FROM projects
     ORDER BY project_name ASC"
);

// Allocation report query
$sql = "
SELECT
    p.project_code,
    p.project_name,
    mr.request_id,
    mr.request_date,
    mr.request_status,
    m.material_code,
    m.material_name,
    m.unit_of_measure,
    ri.requested_quantity,

    CASE
        WHEN a.approval_status = 'Approved'
        THEN a.approved_quantity
        ELSE 0
    END AS approved_quantity,

    COALESCE(mi.total_issued, 0) AS issued_quantity,

    GREATEST(
        CASE
            WHEN a.approval_status = 'Approved'
            THEN a.approved_quantity
            ELSE 0
        END - COALESCE(mi.total_issued, 0),
        0
    ) AS remaining_quantity

FROM request_items ri

INNER JOIN material_requests mr
    ON ri.request_id = mr.request_id

INNER JOIN projects p
    ON mr.project_id = p.project_id

INNER JOIN materials m
    ON ri.material_id = m.material_id

LEFT JOIN approvals a
    ON a.request_item_id = ri.request_item_id
    AND a.revision_no = (
        SELECT MAX(a2.revision_no)
        FROM approvals a2
        WHERE a2.request_item_id = ri.request_item_id
    )

LEFT JOIN (
    SELECT
        request_item_id,
        SUM(issued_quantity) AS total_issued
    FROM material_issues
    GROUP BY request_item_id
) mi
    ON mi.request_item_id = ri.request_item_id

WHERE
    (? = 0 OR p.project_id = ?)
    AND (? = '' OR mr.request_date >= ?)
    AND (? = '' OR mr.request_date <= ?)

ORDER BY mr.request_date DESC, mr.request_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "iissss",
    $project_filter,
    $project_filter,
    $date_from,
    $date_from,
    $date_to,
    $date_to
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Store rows and calculate totals
$reports = [];

$total_requested = 0;
$total_approved = 0;
$total_issued = 0;
$total_remaining = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $reports[] = $row;

    $total_requested += (float)$row["requested_quantity"];
    $total_approved += (float)$row["approved_quantity"];
    $total_issued += (float)$row["issued_quantity"];
    $total_remaining += (float)$row["remaining_quantity"];
}

$total_records = count($reports);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Allocation Reports</title>

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

.container {
    max-width: 1250px;
    margin: auto;
}

.header {
    background: #FFFEFA;
    padding: 22px;
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

.back {
    display: inline-block;
    background: #647F9A;
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
    text-decoration: none;
}

.filters, .summary, .report-section {
    background: #FFFEFA;
    padding: 22px;
    border-radius: 12px;
    margin-top: 22px;
}

.filter-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    flex: 1;
    min-width: 180px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
    color: #34495E;
}

input, select {
    width: 100%;
    padding: 11px;
    border: 1px solid #D5DEE8;
    border-radius: 7px;
    background: white;
}

button, .reset {
    padding: 11px 18px;
    border: none;
    border-radius: 7px;
    background: #647F9A;
    color: white;
    cursor: pointer;
    text-decoration: none;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.summary-card {
    background: #E7EEF5;
    padding: 18px;
    border-radius: 10px;
}

.summary-card p {
    margin: 0;
}

.summary-card h2 {
    margin-bottom: 0;
    font-size: 25px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
    margin-top: 15px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #E7EEF5;
}

th {
    background: #E7EEF5;
    color: #34495E;
}

.empty {
    text-align: center;
    padding: 25px;
}

@media(max-width: 600px) {
    body {
        padding: 12px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Allocation Reports</h1>
            <p>
                Welcome,
                <?= e($_SESSION["username"]) ?> |
                <?= e($_SESSION["role"]) ?>
            </p>
        </div>

        <?php if ($_SESSION["role"] === "Admin"): ?>
            <a href="dashboard.php" class="back">Back to Dashboard</a>
        <?php else: ?>
            <a href="user_dashboard.php" class="back">Back to Dashboard</a>
        <?php endif; ?>
    </div>

    <div class="filters">
        <h2>Filter Reports</h2>

        <form method="GET" class="filter-form">

            <div class="filter-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="0">All Projects</option>

                    <?php while ($project = mysqli_fetch_assoc($project_query)): ?>
                        <option
                            value="<?= e($project["project_id"]) ?>"
                            <?= $project_filter == $project["project_id"] ? "selected" : "" ?>
                        >
                            <?= e($project["project_code"] . " - " . $project["project_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from"
                       value="<?= e($date_from) ?>">
            </div>

            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to"
                       value="<?= e($date_to) ?>">
            </div>

            <button type="submit">Apply Filter</button>
            <a href="reports.php" class="reset">Reset</a>

        </form>
    </div>

    <div class="summary">
        <h2>Allocation Summary</h2>

        <div class="summary-grid">

            <div class="summary-card">
                <p>Total Records</p>
                <h2><?= $total_records ?></h2>
            </div>

            <div class="summary-card">
                <p>Total Requested</p>
                <h2><?= number_format($total_requested, 2) ?></h2>
            </div>

            <div class="summary-card">
                <p>Total Approved</p>
                <h2><?= number_format($total_approved, 2) ?></h2>
            </div>

            <div class="summary-card">
                <p>Total Issued</p>
                <h2><?= number_format($total_issued, 2) ?></h2>
            </div>

            <div class="summary-card">
                <p>Total Remaining</p>
                <h2><?= number_format($total_remaining, 2) ?></h2>
            </div>

        </div>
    </div>

    <div class="report-section">
        <h2>Material Allocation Details</h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Request No.</th>
                        <th>Request Date</th>
                        <th>Material</th>
                        <th>Requested</th>
                        <th>Approved</th>
                        <th>Issued</th>
                        <th>Remaining</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_records > 0): ?>

                    <?php foreach ($reports as $row): ?>
                    <tr>
                        <td>
                            <?= e($row["project_code"]) ?><br>
                            <?= e($row["project_name"]) ?>
                        </td>

                        <td><?= e($row["request_id"]) ?></td>

                        <td><?= e($row["request_date"]) ?></td>

                        <td>
                            <?= e($row["material_name"]) ?><br>
                            <small><?= e($row["material_code"]) ?></small>
                        </td>

                        <td>
                            <?= number_format((float)$row["requested_quantity"], 2) ?>
                            <?= e($row["unit_of_measure"]) ?>
                        </td>

                        <td>
                            <?= number_format((float)$row["approved_quantity"], 2) ?>
                            <?= e($row["unit_of_measure"]) ?>
                        </td>

                        <td>
                            <?= number_format((float)$row["issued_quantity"], 2) ?>
                            <?= e($row["unit_of_measure"]) ?>
                        </td>

                        <td>
                            <?= number_format((float)$row["remaining_quantity"], 2) ?>
                            <?= e($row["unit_of_measure"]) ?>
                        </td>

                        <td><?= e($row["request_status"]) ?></td>
                    </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="empty">
                            No allocation records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
