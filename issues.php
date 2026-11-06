
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// Only Admin and Stockroom Staff can issue materials.
$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["Admin", "Stockroom Staff"], true)) {
    die("Access denied. Only Admin and Stockroom Staff can issue materials.");
}

$message = "";
$messageType = "success";
$issuedBy = (int)$_SESSION["user_id"];

// Process material issuance.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["issue_material"])) {

    $requestItemId = (int)($_POST["request_item_id"] ?? 0);
    $issueQty = filter_var($_POST["issued_quantity"] ?? null, FILTER_VALIDATE_FLOAT);

    if ($requestItemId <= 0 || $issueQty === false || $issueQty <= 0) {
        $message = "Please enter a valid quantity greater than zero.";
        $messageType = "error";
    } else {
        try {
            $conn->begin_transaction();

            // Lock the request item to prevent simultaneous over-issuance.
            $stmt = $conn->prepare("
                SELECT request_item_id
                FROM request_items
                WHERE request_item_id = ?
                FOR UPDATE
            ");
            $stmt->bind_param("i", $requestItemId);
            $stmt->execute();
            $itemExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$itemExists) {
                throw new Exception("The requested material was not found.");
            }

            // Get the latest approval decision.
            $stmt = $conn->prepare("
                SELECT approved_quantity, approval_status
                FROM approvals
                WHERE request_item_id = ?
                ORDER BY revision_no DESC
                LIMIT 1
            ");
            $stmt->bind_param("i", $requestItemId);
            $stmt->execute();
            $approval = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$approval || $approval["approval_status"] !== "Approved") {
                throw new Exception("This material does not have a valid approval.");
            }

            $approvedQty = (float)$approval["approved_quantity"];

            // Calculate the total quantity already issued.
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(issued_quantity), 0) AS total_issued
                FROM material_issues
                WHERE request_item_id = ?
            ");
            $stmt->bind_param("i", $requestItemId);
            $stmt->execute();
            $issuedData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $totalIssued = (float)$issuedData["total_issued"];
            $remainingQty = $approvedQty - $totalIssued;

            if ($issueQty > $remainingQty) {
                throw new Exception(
                    "Issuance exceeds the remaining approved quantity. Remaining quantity: " .
                    number_format(max(0, $remainingQty), 2)
                );
            }

            // Save the issuance record.
            $stmt = $conn->prepare("
                INSERT INTO material_issues
                (request_item_id, issue_date, issued_quantity, issued_by)
                VALUES (?, CURDATE(), ?, ?)
            ");
            $stmt->bind_param("idi", $requestItemId, $issueQty, $issuedBy);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: issues.php?success=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

if (isset($_GET["success"])) {
    $message = "Material issuance recorded successfully!";
}

// Retrieve approved materials and their issuance totals.
$sql = "
    SELECT
        ri.request_item_id,
        ri.requested_quantity,
        mr.request_id,
        mr.request_date,
        p.project_code,
        p.project_name,
        p.project_site,
        m.material_code,
        m.material_name,
        m.unit_of_measure,
        a.approved_quantity,
        a.approval_date,
        COALESCE(issues.total_issued, 0) AS total_issued
    FROM request_items ri
    INNER JOIN material_requests mr
        ON ri.request_id = mr.request_id
    INNER JOIN projects p
        ON mr.project_id = p.project_id
    INNER JOIN materials m
        ON ri.material_id = m.material_id
    INNER JOIN approvals a
        ON a.request_item_id = ri.request_item_id
        AND a.revision_no = (
            SELECT MAX(a2.revision_no)
            FROM approvals a2
            WHERE a2.request_item_id = ri.request_item_id
        )
    LEFT JOIN (
        SELECT request_item_id, SUM(issued_quantity) AS total_issued
        FROM material_issues
        GROUP BY request_item_id
    ) issues
        ON issues.request_item_id = ri.request_item_id
    WHERE a.approval_status = 'Approved'
    ORDER BY mr.request_id DESC, ri.request_item_id ASC
";

$result = $conn->query($sql);

$items = [];

while ($row = $result->fetch_assoc()) {
    $approved = (float)$row["approved_quantity"];
    $issued = (float)$row["total_issued"];
    $row["remaining_quantity"] = max(0, $approved - $issued);
    $items[] = $row;
}

// Retrieve issuance history.
$historySql = "
    SELECT
        mi.issue_id,
        mi.issue_date,
        mi.issued_quantity,
        m.material_code,
        m.material_name,
        m.unit_of_measure,
        p.project_code,
        p.project_name,
        u.full_name AS issued_by_name
    FROM material_issues mi
    INNER JOIN request_items ri
        ON mi.request_item_id = ri.request_item_id
    INNER JOIN material_requests mr
        ON ri.request_id = mr.request_id
    INNER JOIN projects p
        ON mr.project_id = p.project_id
    INNER JOIN materials m
        ON ri.material_id = m.material_id
    INNER JOIN users u
        ON mi.issued_by = u.id
    ORDER BY mi.issue_id DESC
";

$history = $conn->query($historySql);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Issuance</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #F5F1E8;
            font-family: Arial, sans-serif;
            color: #64748B;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1100px;
            margin: 25px auto;
            background: #FFFEFA;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
        }

        h1, h2, h3 {
            color: #34495E;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #647F9A;
            text-decoration: none;
            font-weight: bold;
        }

        .item-card {
            border: 1px solid #E7EEF5;
            background: #FFFFFF;
            border-radius: 12px;
            padding: 20px;
            margin: 18px 0;
        }

        .info {
            line-height: 1.8;
            margin: 12px 0;
        }

        .issue-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
            margin-top: 15px;
        }

        .field {
            flex: 1;
            min-width: 180px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #D5DEE8;
            border-radius: 7px;
        }

        button {
            padding: 12px 18px;
            border: none;
            border-radius: 7px;
            background: #647F9A;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #506B86;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #E7EEF5;
            color: #34495E;
            font-size: 12px;
            font-weight: bold;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #E7F4E8;
            color: #28613A;
        }

        .message.error {
            background: #FCE8E8;
            color: #9B2929;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 650px;
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

        .muted {
            color: #8492A3;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 18px;
            }

            .issue-form {
                display: block;
            }

            .field, button {
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back" href="dashboard.php">← Back to Dashboard</a>

    <h1>Material Issuance</h1>
    <p>Record materials released by the stockroom to construction projects.</p>

    <?php if ($message): ?>
        <div class="message <?= $messageType === "error" ? "error" : "" ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <h2>Approved Materials</h2>

    <?php if (empty($items)): ?>
        <p>No approved materials are currently available for issuance.</p>
    <?php else: ?>

        <?php foreach ($items as $item): ?>
            <div class="item-card">

                <h3>
                    <?= e($item["material_code"]) ?> -
                    <?= e($item["material_name"]) ?>
                </h3>

                <div class="info">
                    <strong>Project:</strong>
                    <?= e($item["project_code"]) ?> -
                    <?= e($item["project_name"]) ?><br>

                    <strong>Project Site:</strong>
                    <?= e($item["project_site"]) ?><br>

                    <strong>Request ID:</strong>
                    <?= e($item["request_id"]) ?><br>

                    <strong>Requested Quantity:</strong>
                    <?= e($item["requested_quantity"]) ?>
                    <?= e($item["unit_of_measure"]) ?><br>

                    <strong>Approved Quantity:</strong>
                    <?= e($item["approved_quantity"]) ?>
                    <?= e($item["unit_of_measure"]) ?><br>

                    <strong>Total Already Issued:</strong>
                    <?= e($item["total_issued"]) ?>
                    <?= e($item["unit_of_measure"]) ?><br>

                    <strong>Remaining Quantity:</strong>
                    <span class="status">
                        <?= number_format($item["remaining_quantity"], 2) ?>
                        <?= e($item["unit_of_measure"]) ?>
                    </span>
                </div>

                <?php if ($item["remaining_quantity"] > 0): ?>
                    <form method="POST" class="issue-form">

                        <input type="hidden"
                               name="request_item_id"
                               value="<?= e($item["request_item_id"]) ?>">

                        <div class="field">
                            <label>Quantity to Issue</label>
                            <input type="number"
                                   name="issued_quantity"
                                   min="0.01"
                                   max="<?= e($item["remaining_quantity"]) ?>"
                                   step="0.01"
                                   placeholder="Enter quantity"
                                   required>
                        </div>

                        <button type="submit" name="issue_material">
                            Record Issuance
                        </button>

                    </form>
                <?php else: ?>
                    <p class="muted">The full approved quantity has already been issued.</p>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <h2>Issuance History</h2>

    <?php if ($history->num_rows === 0): ?>
        <p>No material issuance records yet.</p>
    <?php else: ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Issue ID</th>
                        <th>Date</th>
                        <th>Project</th>
                        <th>Material</th>
                        <th>Issued Quantity</th>
                        <th>Issued By</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($record = $history->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($record["issue_id"]) ?></td>
                        <td><?= e($record["issue_date"]) ?></td>
                        <td>
                            <?= e($record["project_code"]) ?> -
                            <?= e($record["project_name"]) ?>
                        </td>
                        <td>
                            <?= e($record["material_code"]) ?> -
                            <?= e($record["material_name"]) ?>
                        </td>
                        <td>
                            <?= e($record["issued_quantity"]) ?>
                            <?= e($record["unit_of_measure"]) ?>
                        </td>
                        <td><?= e($record["issued_by_name"]) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>
</body>
</html>
