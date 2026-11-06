
<?php
session_start();
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION["role"] ?? "Stockroom Staff";
$is_admin = ($role === "Admin");

$message = "";
$edit_material = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Only Admin can manage materials
    if (!$is_admin) {
        die("Access denied. Only Admin can manage materials.");
    }

    $action = $_POST["action"] ?? "";

    // Add or edit material
    if ($action === "add" || $action === "edit") {

        $material_code = trim($_POST["material_code"] ?? "");
        $material_name = trim($_POST["material_name"] ?? "");
        $unit_of_measure = trim($_POST["unit_of_measure"] ?? "");

        if (
            $material_code === "" ||
            $material_name === "" ||
            $unit_of_measure === ""
        ) {
            $message = "Please fill in all required fields.";
        } else {

            if ($action === "add") {

                $stmt = $conn->prepare(
                    "INSERT INTO materials
                    (material_code, material_name, unit_of_measure)
                    VALUES (?, ?, ?)"
                );

                $stmt->bind_param(
                    "sss",
                    $material_code,
                    $material_name,
                    $unit_of_measure
                );

            } else {

                $material_id = (int)($_POST["material_id"] ?? 0);

                $stmt = $conn->prepare(
                    "UPDATE materials
                    SET material_code = ?,
                        material_name = ?,
                        unit_of_measure = ?
                    WHERE material_id = ?"
                );

                $stmt->bind_param(
                    "sssi",
                    $material_code,
                    $material_name,
                    $unit_of_measure,
                    $material_id
                );
            }

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: materials.php?success=" . $action);
                exit;
            } else {
                if ($stmt->errno === 1062) {
                    $message = "Material code already exists. Please use another code.";
                } else {
                    $message = "Unable to save material. Please try again.";
                }
            }

            $stmt->close();
        }
    }

    // Delete material
    elseif ($action === "delete") {

        $material_id = (int)($_POST["material_id"] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM materials WHERE material_id = ?"
        );

        $stmt->bind_param("i", $material_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: materials.php?success=delete");
            exit;
        } else {
            $message = "Cannot delete this material because it may already be used in a material request.";
        }

        $stmt->close();
    }
}

// Success messages
if (isset($_GET["success"])) {

    if ($_GET["success"] === "add") {
        $message = "Material added successfully!";
    } elseif ($_GET["success"] === "edit") {
        $message = "Material updated successfully!";
    } elseif ($_GET["success"] === "delete") {
        $message = "Material deleted successfully!";
    }
}

// Get material information for editing
if (isset($_GET["edit"]) && $is_admin) {

    $material_id = (int)$_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT * FROM materials WHERE material_id = ?"
    );

    $stmt->bind_param("i", $material_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $edit_material = $result->fetch_assoc();

    $stmt->close();
}

// Get all materials
$materials = $conn->query(
    "SELECT * FROM materials ORDER BY material_id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Materials Management</title>

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
            color: #34495E;
            margin-top: 0;
        }

        input {
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

        .delete {
            background: #B96B6B;
        }

        .delete:hover {
            background: #984F4F;
        }

        .message {
            padding: 12px;
            background: #E7EEF5;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #E5EAF0;
        }

        th {
            background: #E7EEF5;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #647F9A;
            text-decoration: none;
        }

        @media(max-width: 700px) {
            .container {
                padding: 18px;
                overflow-x: auto;
            }

            table {
                min-width: 650px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="dashboard.php">
        ← Back to Dashboard
    </a>

    <h2>Materials Management</h2>
    <p>Manage construction materials and their units of measurement.</p>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($is_admin): ?>

        <h3>
            <?= $edit_material ? "Edit Material" : "Add New Material" ?>
        </h3>

        <form method="POST" action="materials.php">

            <input type="hidden"
                   name="action"
                   value="<?= $edit_material ? "edit" : "add" ?>">

            <?php if ($edit_material): ?>
                <input type="hidden"
                       name="material_id"
                       value="<?= $edit_material["material_id"] ?>">
            <?php endif; ?>

            <label>Material Code</label>
            <input type="text"
                   name="material_code"
                   maxlength="30"
                   placeholder="Example: MAT-001"
                   value="<?= htmlspecialchars($edit_material["material_code"] ?? "") ?>"
                   required>

            <label>Material Name</label>
            <input type="text"
                   name="material_name"
                   maxlength="100"
                   placeholder="Enter material name"
                   value="<?= htmlspecialchars($edit_material["material_name"] ?? "") ?>"
                   required>

            <label>Unit of Measure</label>
            <input type="text"
                   name="unit_of_measure"
                   maxlength="30"
                   placeholder="Example: Bag, Piece, Cubic Meter"
                   value="<?= htmlspecialchars($edit_material["unit_of_measure"] ?? "") ?>"
                   required>

            <button type="submit">
                <?= $edit_material ? "Update Material" : "Add Material" ?>
            </button>

            <?php if ($edit_material): ?>
                <a href="materials.php" class="btn">Cancel</a>
            <?php endif; ?>

        </form>

    <?php else: ?>

        <p>You can view material records, but only Admin can manage them.</p>

    <?php endif; ?>

    <hr>

    <h3>Material Records</h3>

    <table>
        <thead>
            <tr>
                <th>Material Code</th>
                <th>Material Name</th>
                <th>Unit of Measure</th>

                <?php if ($is_admin): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>

        <?php if ($materials && $materials->num_rows > 0): ?>

            <?php while ($material = $materials->fetch_assoc()): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars($material["material_code"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($material["material_name"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($material["unit_of_measure"]) ?>
                    </td>

                    <?php if ($is_admin): ?>
                        <td>
                            <div class="actions">

                                <a class="btn"
                                   href="materials.php?edit=<?= $material["material_id"] ?>">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="materials.php"
                                      onsubmit="return confirm('Are you sure you want to delete this material?');">

                                    <input type="hidden"
                                           name="action"
                                           value="delete">

                                    <input type="hidden"
                                           name="material_id"
                                           value="<?= $material["material_id"] ?>">

                                    <button type="submit" class="delete">
                                        Delete
                                    </button>

                                </form>

                            </div>
                        </td>
                    <?php endif; ?>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="<?= $is_admin ? 4 : 3 ?>">
                    No materials have been added yet.
                </td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

</div>

</body>
</html>
