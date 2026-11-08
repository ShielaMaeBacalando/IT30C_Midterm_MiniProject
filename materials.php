
<?php
session_start();
require_once "db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// Admin only
$role = $_SESSION["role"] ?? "";

if ($role !== "Admin") {
    die("Access denied. Only Admin can manage materials.");
}

$message = "";
$messageType = "success";

// ADD MATERIAL
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_material"])) {

    $code = trim($_POST["material_code"] ?? "");
    $name = trim($_POST["material_name"] ?? "");
    $unit = trim($_POST["unit_of_measure"] ?? "");

    if ($code === "" || $name === "" || $unit === "") {
        $message = "Please complete all material fields.";
        $messageType = "error";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO materials
                (material_code, material_name, unit_of_measure, material_status)
                VALUES (?, ?, ?, 'Active')
            ");

            $stmt->bind_param("sss", $code, $name, $unit);
            $stmt->execute();
            $stmt->close();

            header("Location: materials.php?added=1");
            exit;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $message = "Material code already exists. Please use another code.";
            } else {
                $message = "Unable to add material. " . $e->getMessage();
            }
            $messageType = "error";
        }
    }
}

// EDIT MATERIAL
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_material"])) {

    $id = (int)($_POST["material_id"] ?? 0);
    $code = trim($_POST["material_code"] ?? "");
    $name = trim($_POST["material_name"] ?? "");
    $unit = trim($_POST["unit_of_measure"] ?? "");

    if ($id <= 0 || $code === "" || $name === "" || $unit === "") {
        $message = "Please provide valid material information.";
        $messageType = "error";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE materials
                SET material_code = ?,
                    material_name = ?,
                    unit_of_measure = ?
                WHERE material_id = ?
            ");

            $stmt->bind_param("sssi", $code, $name, $unit, $id);
            $stmt->execute();
            $stmt->close();

            header("Location: materials.php?updated=1");
            exit;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $message = "Material code already exists.";
            } else {
                $message = "Unable to update material. " . $e->getMessage();
            }
            $messageType = "error";
        }
    }
}

// ACTIVATE OR DEACTIVATE MATERIAL
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_status"])) {

    $id = (int)($_POST["material_id"] ?? 0);
    $newStatus = $_POST["new_status"] ?? "";

    if ($id <= 0 || !in_array($newStatus, ["Active", "Inactive"], true)) {
        $message = "Invalid material status.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("
            UPDATE materials
            SET material_status = ?
            WHERE material_id = ?
        ");

        $stmt->bind_param("si", $newStatus, $id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: materials.php?status=1");
            exit;
        } else {
            $message = "Unable to change material status.";
            $messageType = "error";
            $stmt->close();
        }
    }
}

// SUCCESS MESSAGES
if (isset($_GET["added"])) {
    $message = "Material successfully added!";
}

if (isset($_GET["updated"])) {
    $message = "Material successfully updated!";
}

if (isset($_GET["status"])) {
    $message = "Material status successfully changed!";
}

// GET MATERIALS
$result = $conn->query("
    SELECT *
    FROM materials
    ORDER BY material_id DESC
");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
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
            margin: 0;
            padding: 25px;
            background: #F5F1E8;
            font-family: Arial, sans-serif;
            color: #64748B;
        }

        .container {
            max-width: 1100px;
            margin: 20px auto;
            background: #FFFEFA;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        h1, h2 {
            color: #34495E;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #647F9A;
            text-decoration: none;
            font-weight: bold;
        }

        .form-box {
            background: #F7F9FC;
            border: 1px solid #E7EEF5;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #D5DEE8;
            border-radius: 7px;
            background: white;
        }

        button, .edit-link {
            padding: 10px 14px;
            border: none;
            border-radius: 7px;
            background: #647F9A;
            color: white;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }

        button:hover, .edit-link:hover {
            background: #506B86;
        }

        .deactivate {
            background: #A66B6B;
        }

        .deactivate:hover {
            background: #895454;
        }

        .activate {
            background: #66866D;
        }

        .activate:hover {
            background: #4E6F56;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
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

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #E3F2E5;
            color: #347343;
        }

        .inactive {
            background: #FCE8E8;
            color: #A33D3D;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            background: #E7F4E8;
            color: #28613A;
            margin-bottom: 20px;
        }

        .message.error {
            background: #FCE8E8;
            color: #9B2929;
        }

        .actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .edit-section {
            margin-top: 25px;
            padding: 20px;
            border: 1px solid #E7EEF5;
            border-radius: 12px;
            background: #F7F9FC;
        }

        @media(max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="dashboard.php" class="back">← Back to Dashboard</a>

    <h1>Materials Management</h1>
    <p>Manage construction materials, codes, units, and availability.</p>

    <?php if ($message): ?>
        <div class="message <?= $messageType === "error" ? "error" : "" ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- ADD MATERIAL -->
    <div class="form-box">
        <h2>Add New Material</h2>

        <form method="POST">

            <div class="form-grid">

                <div>
                    <label>Material Code</label>
                    <input type="text" name="material_code"
                           placeholder="Example: MAT-005" required>
                </div>

                <div>
                    <label>Material Name</label>
                    <input type="text" name="material_name"
                           placeholder="Example: Hollow Blocks" required>
                </div>

                <div>
                    <label>Unit of Measure</label>
                    <input type="text" name="unit_of_measure"
                           placeholder="Example: Piece" required>
                </div>

            </div>

            <br>
            <button type="submit" name="add_material">Add Material</button>

        </form>
    </div>

    <!-- MATERIAL LIST -->
    <h2>Material Records</h2>

    <div class="table-wrapper">
        <table>

            <thead>
                <tr>
                    <th>Material ID</th>
                    <th>Material Code</th>
                    <th>Material Name</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>
                        <td><?= e($row["material_id"]) ?></td>
                        <td><?= e($row["material_code"]) ?></td>
                        <td><?= e($row["material_name"]) ?></td>
                        <td><?= e($row["unit_of_measure"]) ?></td>

                        <td>
                            <span class="status <?= strtolower(e($row["material_status"])) ?>">
                                <?= e($row["material_status"]) ?>
                            </span>
                        </td>

                        <td>
                            <div class="actions">

                                <a class="edit-link"
                                   href="materials.php?edit=<?= e($row["material_id"]) ?>">
                                    Edit
                                </a>

                                <form method="POST"
                                      onsubmit="return confirm('Are you sure you want to change this material status?');">

                                    <input type="hidden"
                                           name="material_id"
                                           value="<?= e($row["material_id"]) ?>">

                                    <?php if ($row["material_status"] === "Active"): ?>

                                        <input type="hidden"
                                               name="new_status"
                                               value="Inactive">

                                        <button type="submit"
                                                name="toggle_status"
                                                class="deactivate">
                                            Deactivate
                                        </button>

                                    <?php else: ?>

                                        <input type="hidden"
                                               name="new_status"
                                               value="Active">

                                        <button type="submit"
                                                name="toggle_status"
                                                class="activate">
                                            Activate
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </div>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">No materials found.</td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

    <!-- EDIT MATERIAL FORM -->
    <?php
    if (isset($_GET["edit"])):

        $editId = (int)$_GET["edit"];

        $stmt = $conn->prepare("
            SELECT *
            FROM materials
            WHERE material_id = ?
        ");

        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $editResult = $stmt->get_result();
        $editMaterial = $editResult->fetch_assoc();
        $stmt->close();

        if ($editMaterial):
    ?>

        <div class="edit-section">

            <h2>Edit Material</h2>

            <form method="POST">

                <input type="hidden" name="material_id"
                       value="<?= e($editMaterial["material_id"]) ?>">

                <div class="form-grid">

                    <div>
                        <label>Material Code</label>
                        <input type="text" name="material_code"
                               value="<?= e($editMaterial["material_code"]) ?>"
                               required>
                    </div>

                    <div>
                        <label>Material Name</label>
                        <input type="text" name="material_name"
                               value="<?= e($editMaterial["material_name"]) ?>"
                               required>
                    </div>

                    <div>
                        <label>Unit of Measure</label>
                        <input type="text" name="unit_of_measure"
                               value="<?= e($editMaterial["unit_of_measure"]) ?>"
                               required>
                    </div>

                </div>

                <br>

                <button type="submit" name="edit_material">
                    Save Changes
                </button>

                <a href="materials.php" class="edit-link">
                    Cancel
                </a>

            </form>

        </div>

    <?php
        else:
            echo "<p>Material record not found.</p>";
        endif;
    endif;
    ?>

</div>

</body>
</html>
