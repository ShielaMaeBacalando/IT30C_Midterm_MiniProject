
<?php
session_start();
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$message = "";

// Handle new material request
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $project_id = (int)($_POST["project_id"] ?? 0);
    $material_ids = $_POST["material_id"] ?? [];
    $quantities = $_POST["requested_quantity"] ?? [];

    if (
        $project_id <= 0 ||
        !is_array($material_ids) ||
        !is_array($quantities) ||
        count($material_ids) === 0 ||
        count($material_ids) !== count($quantities)
    ) {
        $message = "Please select a project and add at least one material.";
    } else {

        // Validate project
        $project_check = $conn->prepare(
            "SELECT material_id, material_code, material_name, unit_of_measure
FROM materials
WHERE material_status = 'Active'
ORDER BY material_name ASC"
        );

        $project_check->bind_param("i", $project_id);
        $project_check->execute();

        $project_result = $project_check->get_result();
        $project_exists = $project_result->num_rows > 0;

        $project_check->close();

        if (!$project_exists) {
            $message = "Selected project does not exist.";
        } else {

            $valid_items = [];
            $seen_materials = [];
            $invalid_items = false;

            // Validate every requested material and quantity
            foreach ($material_ids as $index => $material_value) {

                $material_id = filter_var(
                    $material_value,
                    FILTER_VALIDATE_INT
                );

                $quantity_value = $quantities[$index] ?? "";

                if (
                    !$material_id ||
                    !is_numeric($quantity_value) ||
                    !is_finite((float)$quantity_value) ||
                    (float)$quantity_value <= 0 ||
                    (float)$quantity_value > 99999999.99
                ) {
                    $invalid_items = true;
                    break;
                }

                $quantity = (float)$quantity_value;

                // Prevent duplicate materials in one request
                if (isset($seen_materials[$material_id])) {
                    $invalid_items = true;
                    break;
                }

                $seen_materials[$material_id] = true;

                // Check if material exists
                $material_check = $conn->prepare(
                    "SELECT material_id FROM materials WHERE material_id = ?"
                );

                $material_check->bind_param("i", $material_id);
                $material_check->execute();

                $material_result = $material_check->get_result();
                $material_exists = $material_result->num_rows > 0;

                $material_check->close();

                if (!$material_exists) {
                    $invalid_items = true;
                    break;
                }

                $valid_items[] = [
                    "material_id" => $material_id,
                    "quantity" => $quantity
                ];
            }

            if ($invalid_items) {
                $message = "Please check your materials and quantities. Avoid duplicate materials.";
            } else {

                // Save request and items together
                try {
                    $conn->begin_transaction();

                    // Create the main request
                    $request_stmt = $conn->prepare(
                        "INSERT INTO material_requests
                        (project_id, requested_by, request_date)
                        VALUES (?, ?, CURDATE())"
                    );

                    $request_stmt->bind_param(
                        "ii",
                        $project_id,
                        $user_id
                    );

                    $request_stmt->execute();

                    $request_id = $conn->insert_id;

                    $request_stmt->close();

                    // Insert requested materials
                    $item_stmt = $conn->prepare(
                        "INSERT INTO request_items
                        (request_id, material_id, requested_quantity)
                        VALUES (?, ?, ?)"
                    );

                    foreach ($valid_items as $item) {

                        $material_id = $item["material_id"];
                        $quantity = $item["quantity"];

                        $item_stmt->bind_param(
                            "iid",
                            $request_id,
                            $material_id,
                            $quantity
                        );

                        $item_stmt->execute();
                    }

                    $item_stmt->close();

                    $conn->commit();

                    header("Location: requests.php?success=1");
                    exit;

                } catch (Throwable $e) {

                    $conn->rollback();

                    $message = "Unable to save request. Please try again.";
                }
            }
        }
    }
}

// Success message
if (isset($_GET["success"])) {
    $message = "Material request submitted successfully!";
}

// Get available projects
$projects = $conn->query(
    "SELECT project_id, project_code, project_name, project_site
     FROM projects
     WHERE project_status = 'Ongoing'
     ORDER BY project_name ASC"
);

// Get available materials
$materials = $conn->query(
    "SELECT material_id, material_code, material_name, unit_of_measure
     FROM materials
     ORDER BY material_name ASC"
);

// Prepare material options for the form
$material_options = [];

while ($material = $materials->fetch_assoc()) {
    $material_options[] = $material;
}

// View individual request details
$view_request = null;
$request_items = null;

if (isset($_GET["view"])) {

    $view_id = (int)$_GET["view"];

    $detail_stmt = $conn->prepare(
        "SELECT
            mr.request_id,
            mr.request_date,
            mr.request_status,
            p.project_code,
            p.project_name,
            p.project_site,
            u.full_name
         FROM material_requests mr
         INNER JOIN projects p ON mr.project_id = p.project_id
         INNER JOIN users u ON mr.requested_by = u.id
         WHERE mr.request_id = ?"
    );

    $detail_stmt->bind_param("i", $view_id);
    $detail_stmt->execute();

    $detail_result = $detail_stmt->get_result();
    $view_request = $detail_result->fetch_assoc();

    $detail_stmt->close();

    if ($view_request) {

        $items_stmt = $conn->prepare(
            "SELECT
                m.material_code,
                m.material_name,
                m.unit_of_measure,
                ri.requested_quantity
             FROM request_items ri
             INNER JOIN materials m
                ON ri.material_id = m.material_id
             WHERE ri.request_id = ?"
        );

        $items_stmt->bind_param("i", $view_id);
        $items_stmt->execute();

        $request_items = $items_stmt->get_result();
    }
}

// Get all submitted requests
$requests = $conn->query(
    "SELECT
        mr.request_id,
        mr.request_date,
        mr.request_status,
        p.project_code,
        p.project_name,
        u.full_name,
        COUNT(ri.request_item_id) AS item_count
     FROM material_requests mr
     INNER JOIN projects p ON mr.project_id = p.project_id
     INNER JOIN users u ON mr.requested_by = u.id
     LEFT JOIN request_items ri ON mr.request_id = ri.request_id
     GROUP BY
        mr.request_id,
        mr.request_date,
        mr.request_status,
        p.project_code,
        p.project_name,
        u.full_name
     ORDER BY mr.request_id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Material Requests</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #F5F1E8;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 25px;
            color: #34495E;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #FFFEFA;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        h2 {
            margin-top: 0;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #D5DEE8;
            border-radius: 8px;
        }

        button, .btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            background: #647F9A;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        button:hover, .btn:hover {
            background: #49647F;
        }

        .remove {
            background: #B96B6B;
        }

        .remove:hover {
            background: #984F4F;
        }

        .message {
            padding: 12px;
            background: #E7EEF5;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #647F9A;
            text-decoration: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #E5EAF0;
        }

        th {
            background: #E7EEF5;
        }

        .item-row {
            background: #F8FAFC;
            padding: 15px;
            border: 1px solid #E5EAF0;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .item-row label {
            display: block;
            font-weight: bold;
            margin-top: 5px;
        }

        .item-row input,
        .item-row select {
            margin-bottom: 8px;
        }

        .section {
            margin-top: 30px;
        }

        .info {
            background: #E7EEF5;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }

        @media(max-width: 700px) {
            .container {
                padding: 18px;
                overflow-x: auto;
            }

            table {
                min-width: 700px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="dashboard.php">
        ← Back to Dashboard
    </a>

    <h2>Material Requests</h2>
    <p>Submit and view construction project material requests.</p>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($view_request): ?>

        <div class="section">

            <h3>Request Details #<?= $view_request["request_id"] ?></h3>

            <div class="info">
                <p><strong>Project:</strong>
                    <?= htmlspecialchars($view_request["project_code"]) ?>
                    - <?= htmlspecialchars($view_request["project_name"]) ?>
                </p>

                <p><strong>Project Site:</strong>
                    <?= htmlspecialchars($view_request["project_site"]) ?>
                </p>

                <p><strong>Requested By:</strong>
                    <?= htmlspecialchars($view_request["full_name"]) ?>
                </p>

                <p><strong>Request Date:</strong>
                    <?= htmlspecialchars($view_request["request_date"]) ?>
                </p>

                <p><strong>Status:</strong>
                    <?= htmlspecialchars($view_request["request_status"]) ?>
                </p>
            </div>

            <h3>Requested Materials</h3>

            <table>
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($item = $request_items->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($item["material_code"]) ?></td>
                            <td><?= htmlspecialchars($item["material_name"]) ?></td>
                            <td><?= number_format((float)$item["requested_quantity"], 2) ?></td>
                            <td><?= htmlspecialchars($item["unit_of_measure"]) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <br>

            <a href="requests.php" class="btn">Back to Requests</a>

        </div>

    <?php else: ?>

        <?php if ($projects->num_rows > 0 && count($material_options) > 0): ?>

            <div class="section">

                <h3>Submit New Material Request</h3>

                <form method="POST" action="requests.php" id="requestForm">

                    <label><strong>Select Construction Project</strong></label>

                    <select name="project_id" required>
                        <option value="">-- Select Project --</option>

                        <?php while ($project = $projects->fetch_assoc()): ?>
                            <option value="<?= $project["project_id"] ?>">
                                <?= htmlspecialchars(
                                    $project["project_code"] . " - " .
                                    $project["project_name"] . " (" .
                                    $project["project_site"] . ")"
                                ) ?>
                            </option>
                        <?php endwhile; ?>

                    </select>

                    <h3>Requested Materials</h3>

                    <div id="itemsContainer">

                        <div class="item-row">

                            <label>Material</label>

                            <select name="material_id[]" required>
                                <option value="">-- Select Material --</option>

                                <?php foreach ($material_options as $material): ?>
                                    <option value="<?= $material["material_id"] ?>">
                                        <?= htmlspecialchars(
                                            $material["material_code"] . " - " .
                                            $material["material_name"] . " (" .
                                            $material["unit_of_measure"] . ")"
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                            <label>Requested Quantity</label>

                            <input type="number"
                                   name="requested_quantity[]"
                                   min="0.01"
                                   max="99999999.99"
                                   step="0.01"
                                   placeholder="Enter quantity"
                                   required>

                        </div>

                    </div>

                    <button type="button" id="addItem">
                        + Add Another Material
                    </button>

                    <br><br>

                    <button type="submit">
                        Submit Material Request
                    </button>

                </form>

            </div>

        <?php else: ?>

            <div class="info">
                <strong>Cannot submit a request yet.</strong>
                <p>
                    You need at least one Ongoing project and one registered
                    material before submitting a request.
                </p>
                <a href="projects.php" class="btn">Manage Projects</a>
                <a href="materials.php" class="btn">Manage Materials</a>
            </div>

        <?php endif; ?>

    <?php endif; ?>

    <hr class="section">

    <h3>Submitted Requests</h3>

    <table>
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Project</th>
                <th>Requested By</th>
                <th>Date</th>
                <th>Items</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        <?php if ($requests && $requests->num_rows > 0): ?>

            <?php while ($request = $requests->fetch_assoc()): ?>

                <tr>
                    <td>#<?= $request["request_id"] ?></td>

                    <td>
                        <?= htmlspecialchars($request["project_code"]) ?>
                        - <?= htmlspecialchars($request["project_name"]) ?>
                    </td>

                    <td><?= htmlspecialchars($request["full_name"]) ?></td>

                    <td><?= htmlspecialchars($request["request_date"]) ?></td>

                    <td><?= $request["item_count"] ?></td>

                    <td><?= htmlspecialchars($request["request_status"]) ?></td>

                    <td>
                        <a class="btn"
                           href="requests.php?view=<?= $request["request_id"] ?>">
                            View
                        </a>
                    </td>
                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">No material requests have been submitted yet.</td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

</div>

<script>
    const addItemButton = document.getElementById("addItem");
    const itemsContainer = document.getElementById("itemsContainer");

    if (addItemButton) {
        addItemButton.addEventListener("click", function () {

            const firstRow = itemsContainer.querySelector(".item-row");
            const newRow = firstRow.cloneNode(true);

            // Clear selected material and quantity
            newRow.querySelector("select").selectedIndex = 0;
            newRow.querySelector("input").value = "";

            // Add remove button
            const removeButton = document.createElement("button");

            removeButton.type = "button";
            removeButton.textContent = "Remove Material";
            removeButton.className = "remove";

            removeButton.addEventListener("click", function () {
                newRow.remove();
            });

            newRow.appendChild(removeButton);

            itemsContainer.appendChild(newRow);
        });
    }
</script>

</body>
</html>
