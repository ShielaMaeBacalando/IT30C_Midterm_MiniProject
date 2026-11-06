
<?php
session_start();
require_once "db.php";

// Only Admin and Supervisor can approve requests.
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["Admin", "Supervisor"], true)) {
    die("Access denied. Only Admin and Supervisors can manage approvals.");
}

$message = "";
$messageType = "success";

// Process approval or rejection.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_approval"])) {
    $requestItemId = (int)($_POST["request_item_id"] ?? 0);
    $decision = $_POST["approval_status"] ?? "";
    $approvedQuantity = (float)($_POST["approved_quantity"] ?? 0);
    $supervisorId = (int)$_SESSION["user_id"];

    if ($requestItemId <= 0 || !in_array($decision, ["Approved", "Rejected"], true)) {
        $message = "Invalid approval information.";
        $messageType = "error";
    } else {
        try {
            $conn->begin_transaction();

            // Get the requested quantity and lock the item during approval.
            $stmt = $conn->prepare("
                SELECT ri.request_id, ri.requested_quantity
                FROM request_items ri
                WHERE ri.request_item_id = ?
                FOR UPDATE
            ");
            $stmt->bind_param("i", $requestItemId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$item) {
                throw new Exception("The requested material could not be found.");
            }

            $requestedQuantity = (float)$item["requested_quantity"];

            if ($decision === "Approved") {
                if ($approvedQuantity <= 0 || $approvedQuantity > $requestedQuantity) {
                    throw new Exception(
                        "Approved quantity must be greater than zero and cannot exceed the requested quantity."
                    );
                }
            } else {
                $approvedQuantity = 0;
            }

            // Get the next revision number for this request item.
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(revision_no), 0) + 1 AS next_revision
                FROM approvals
                WHERE request_item_id = ?
            ");
            $stmt->bind_param("i", $requestItemId);
            $stmt->execute();
            $revision = (int)$stmt->get_result()->fetch_assoc()["next_revision"];
            $stmt->close();

            // Save the approval decision.
            $stmt = $conn->prepare("
                INSERT INTO approvals
                (request_item_id, supervisor_id, revision_no,
                 approved_quantity, approval_status, approval_date)
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->bind_param(
                "iiids",
                $requestItemId,
                $supervisorId,
                $revision,
                $approvedQuantity,
                $decision
            );
            $stmt->execute();
            $stmt->close();

            // Check the latest decision for every item in this request.
            $requestId = (int)$item["request_id"];

            $stmt = $conn->prepare("
                SELECT
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN latest.approval_status = 'Approved'
                        THEN 1 ELSE 0 END) AS approved_items,
                    SUM(CASE WHEN latest.approval_status = 'Rejected'
                        THEN 1 ELSE 0 END) AS rejected_items
                FROM request_items ri
                LEFT JOIN approvals latest
                    ON latest.request_item_id = ri.request_item_id
                    AND latest.revision_no = (
                        SELECT MAX(a2.revision_no)
                        FROM approvals a2
                        WHERE a2.request_item_id = ri.request_item_id
                    )
                WHERE ri.request_id = ?
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $counts = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $total = (int)$counts["total_items"];
            $approved = (int)$counts["approved_items"];
            $rejected = (int)$counts["rejected_items"];

            if ($total > 0 && $approved === $total) {
                $requestStatus = "Approved";
            } elseif ($total > 0 && $rejected === $total) {
                $requestStatus = "Rejected";
            } elseif ($approved > 0 || $rejected > 0) {
                $requestStatus = "Partially Approved";
            } else {
                $requestStatus = "Pending";
            }

            $stmt = $conn->prepare("
                UPDATE material_requests
                SET request_status = ?
                WHERE request_id = ?
            ");
            $stmt->bind_param("si", $requestStatus, $requestId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: approvals.php?success=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

if (isset($_GET["success"])) {
    $message = "Approval decision saved successfully!";
}

// Retrieve requests and their latest approval decisions.
$sql = "
    SELECT
        mr.request_id,
        mr.request_date,
        mr.request_status,
        p.project_code,
        p.project_name,
        p.project_site,
        u.full_name AS requester_name,
        ri.request_item_id,
        ri.requested_quantity,
        m.material_code,
        m.material_name,
        m.unit_of_measure,
        a.approval_status AS latest_approval_status,
        a.approved_quantity AS latest_approved_quantity,
        a.revision_no AS latest_revision,
        a.approval_date
    FROM material_requests mr
    INNER JOIN projects p
        ON mr.project_id = p.project_id
    INNER JOIN users u
        ON mr.requested_by = u.id
    INNER JOIN request_items ri
        ON mr.request_id = ri.request_id
    INNER JOIN materials m
        ON ri.material_id = m.material_id
    LEFT JOIN approvals a
        ON a.request_item_id = ri.request_item_id
        AND a.revision_no = (
            SELECT MAX(a2.revision_no)
            FROM approvals a2
            WHERE a2.request_item_id = ri.request_item_id
        )
    ORDER BY mr.request_id DESC, ri.request_item_id ASC
";

$result = $conn->query($sql);

$requests = [];

while ($row = $result->fetch_assoc()) {
    $id = $row["request_id"];

    if (!isset($requests[$id])) {
        $requests[$id] = [
            "request_id" => $id,
            "request_date" => $row["request_date"],
            "request_status" => $row["request_status"],
            "project_code" => $row["project_code"],
            "project_name" => $row["project_name"],
            "project_site" => $row["project_site"],
            "requester_name" => $row["requester_name"],
            "items" => []
        ];
    }

    $requests[$id]["items"][] = $row;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Approvals</title>

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

        .request-card {
            background: #FFFFFF;
            border: 1px solid #E7EEF5;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }

        .request-info {
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .item {
            background: #F7F9FC;
            border: 1px solid #E7EEF5;
            border-radius: 10px;
            padding: 15px;
            margin-top: 12px;
        }

        .approval-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
            margin-top: 12px;
        }

        .field {
            flex: 1;
            min-width: 150px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #D5DEE8;
            border-radius: 7px;
            background: white;
        }

        button {
            padding: 11px 18px;
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

            .approval-form {
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

    <h1>Material Approvals</h1>
    <p>Review project material requests and approve or reject requested quantities.</p>

    <?php if ($message): ?>
        <div class="message <?= $messageType === "error" ? "error" : "" ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($requests)): ?>
        <p>No material requests found.</p>
    <?php else: ?>

        <?php foreach ($requests as $request): ?>
            <div class="request-card">

                <h2>
                    <?= e($request["project_code"]) ?> -
                    <?= e($request["project_name"]) ?>
                </h2>

                <div class="request-info">
                    <strong>Request ID:</strong> <?= e($request["request_id"]) ?><br>
                    <strong>Project Site:</strong> <?= e($request["project_site"]) ?><br>
                    <strong>Requested By:</strong> <?= e($request["requester_name"]) ?><br>
                    <strong>Request Date:</strong> <?= e($request["request_date"]) ?><br>
                    <strong>Request Status:</strong>
                    <span class="status"><?= e($request["request_status"]) ?></span>
                </div>

                <h3>Requested Materials</h3>

                <?php foreach ($request["items"] as $item): ?>
                    <div class="item">

                        <strong>
                            <?= e($item["material_code"]) ?> -
                            <?= e($item["material_name"]) ?>
                        </strong>

                        <p>
                            Requested Quantity:
                            <strong>
                                <?= e($item["requested_quantity"]) ?>
                                <?= e($item["unit_of_measure"]) ?>
                            </strong>
                        </p>

                        <?php if ($item["latest_approval_status"]): ?>
                            <p>
                                Latest Decision:
                                <span class="status">
                                    <?= e($item["latest_approval_status"]) ?>
                                </span>
                                <br>
                                Approved Quantity:
                                <?= e($item["latest_approved_quantity"]) ?>
                                <?= e($item["unit_of_measure"]) ?>
                                <br>
                                Revision:
                                <?= e($item["latest_revision"]) ?>
                                <br>
                                Approval Date:
                                <?= e($item["approval_date"]) ?>
                            </p>
                        <?php else: ?>
                            <p class="muted">This item has not been reviewed yet.</p>
                        <?php endif; ?>

                        <form method="POST" class="approval-form">

                            <input type="hidden"
                                   name="request_item_id"
                                   value="<?= e($item["request_item_id"]) ?>">

                            <div class="field">
                                <label>Decision</label>
                                <select name="approval_status"
                                        class="decision"
                                        required>
                                    <option value="Approved">Approve</option>
                                    <option value="Rejected">Reject</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Approved Quantity</label>
                                <input type="number"
                                       name="approved_quantity"
                                       min="0.01"
                                       max="<?= e($item["requested_quantity"]) ?>"
                                       step="0.01"
                                       value="<?= e($item["requested_quantity"]) ?>"
                                       required>
                            </div>

                            <button type="submit" name="submit_approval">
                                Save Decision
                            </button>

                        </form>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<script>
document.querySelectorAll(".approval-form").forEach(function(form) {
    const decision = form.querySelector(".decision");
    const quantity = form.querySelector('input[name="approved_quantity"]');

    function updateQuantity() {
        if (decision.value === "Rejected") {
            quantity.value = "0";
            quantity.disabled = true;
            quantity.required = false;
        } else {
            quantity.disabled = false;
            quantity.required = true;

            if (Number(quantity.value) <= 0) {
                quantity.value = quantity.max;
            }
        }
    }

    decision.addEventListener("change", updateQuantity);
    updateQuantity();
});
</script>

</body>
</html>
