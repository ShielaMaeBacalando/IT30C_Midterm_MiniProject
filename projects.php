
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
$message_type = "info";
$edit_project = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Only Admin can add, edit, or delete projects
    if (!$is_admin) {
        http_response_code(403);
        die("Access denied. Only Admin can manage projects.");
    }

    $action = $_POST["action"] ?? "";

    // ADD OR EDIT PROJECT
    if ($action === "add" || $action === "edit") {

        $project_code = trim($_POST["project_code"] ?? "");
        $project_name = trim($_POST["project_name"] ?? "");
        $project_site = trim($_POST["project_site"] ?? "");
        $project_status = $_POST["project_status"] ?? "Ongoing";

        $allowed_status = ["Ongoing", "Completed", "On Hold"];

        if (
            $project_code === "" ||
            $project_name === "" ||
            $project_site === ""
        ) {
            $message = "Please fill in all required fields.";
            $message_type = "warning";

        } elseif (!in_array($project_status, $allowed_status, true)) {

            $message = "Invalid project status.";
            $message_type = "warning";

        } else {

            try {

                if ($action === "add") {

                    $stmt = $conn->prepare(
                        "INSERT INTO projects
                        (project_code, project_name, project_site, project_status)
                        VALUES (?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "ssss",
                        $project_code,
                        $project_name,
                        $project_site,
                        $project_status
                    );

                } else {

                    $project_id = (int)($_POST["project_id"] ?? 0);

                    if ($project_id <= 0) {
                        throw new Exception("Invalid project ID.");
                    }

                    $stmt = $conn->prepare(
                        "UPDATE projects
                         SET project_code = ?,
                             project_name = ?,
                             project_site = ?,
                             project_status = ?
                         WHERE project_id = ?"
                    );

                    $stmt->bind_param(
                        "ssssi",
                        $project_code,
                        $project_name,
                        $project_site,
                        $project_status,
                        $project_id
                    );
                }

                $stmt->execute();
                $stmt->close();

                header("Location: projects.php?success=" . $action);
                exit;

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() == 1062) {
                    $message = "Project code already exists. Please use another code.";
                } else {
                    $message = "Unable to save project. Please try again.";
                }

                $message_type = "warning";

            } catch (Exception $e) {

                $message = $e->getMessage();
                $message_type = "warning";
            }
        }
    }

    // DELETE PROJECT - FIXED FOREIGN KEY ERROR
    elseif ($action === "delete") {

        $project_id = (int)($_POST["project_id"] ?? 0);

        if ($project_id <= 0) {

            $message = "Invalid project selected.";
            $message_type = "warning";

        } else {

            try {

                // STEP 1: Check whether project has material requests
                $check = $conn->prepare(
                    "SELECT COUNT(*)
                     FROM material_requests
                     WHERE project_id = ?"
                );

                $check->bind_param("i", $project_id);
                $check->execute();
                $check->bind_result($request_count);
                $check->fetch();
                $check->close();

                // STEP 2: Prevent deletion if requests exist
                if ($request_count > 0) {

                    $message = "Cannot delete this project because it already has "
                             . $request_count
                             . " material request(s). Project records and transaction "
                             . "history have been preserved.";

                    $message_type = "warning";

                } else {

                    // STEP 3: Delete only if no requests exist
                    $stmt = $conn->prepare(
                        "DELETE FROM projects WHERE project_id = ?"
                    );

                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {

                        $stmt->close();

                        header("Location: projects.php?success=delete");
                        exit;

                    } else {

                        $message = "Project not found or already deleted.";
                        $message_type = "warning";
                    }

                    $stmt->close();
                }

            } catch (mysqli_sql_exception $e) {

                // STEP 4: Safety check for foreign key errors
                if ($e->getCode() == 1451) {

                    $message = "This project cannot be deleted because it is "
                             . "still connected to existing transactions. "
                             . "Please retain the project record.";

                } else {

                    $message = "Unable to delete project. Please try again.";
                }

                $message_type = "warning";
            }
        }
    }
}

// DISPLAY SUCCESS MESSAGES
if (isset($_GET["success"])) {

    if ($_GET["success"] === "add") {
        $message = "Project added successfully!";
        $message_type = "success";

    } elseif ($_GET["success"] === "edit") {
        $message = "Project updated successfully!";
        $message_type = "success";

    } elseif ($_GET["success"] === "delete") {
        $message = "Project deleted successfully!";
        $message_type = "success";
    }
}

// GET PROJECT INFORMATION FOR EDITING
if (isset($_GET["edit"]) && $is_admin) {

    $project_id = (int)$_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT * FROM projects WHERE project_id = ?"
    );

    $stmt->bind_param("i", $project_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $edit_project = $result->fetch_assoc();

    $stmt->close();
}

// GET ALL PROJECTS
$projects = $conn->query(
    "SELECT * FROM projects ORDER BY project_id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Projects Management</title>

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

        h3 {
            color: #34495E;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #D5DEE8;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #647F9A;
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
            transition: background 0.2s ease;
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
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .message.success {
            background: #E2F2E5;
            color: #28633A;
            border: 1px solid #B8DCC0;
        }

        .message.warning {
            background: #FFF0D9;
            color: #875B17;
            border: 1px solid #F0D5A5;
        }

        .message.info {
            background: #E7EEF5;
            color: #34495E;
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

        tr:hover {
            background: #FAFBFC;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #647F9A;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s ease;
        }

        .back:hover {
            color: #34495E;
        }

        hr {
            border: none;
            border-top: 1px solid #E5EAF0;
            margin: 30px 0;
        }

        @media(max-width: 700px) {

            body {
                padding: 12px;
            }

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

    <h2>Projects Management</h2>

    <p>Manage construction projects and their locations.</p>

    <?php if ($message !== ""): ?>

        <div class="message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($is_admin): ?>

        <h3>
            <?= $edit_project ? "Edit Project" : "Add New Project" ?>
        </h3>

        <form method="POST" action="projects.php">

            <input type="hidden"
                   name="action"
                   value="<?= $edit_project ? "edit" : "add" ?>">

            <?php if ($edit_project): ?>

                <input type="hidden"
                       name="project_id"
                       value="<?= (int)$edit_project["project_id"] ?>">

            <?php endif; ?>

            <label>Project Code</label>

            <input type="text"
                   name="project_code"
                   maxlength="30"
                   placeholder="Example: PRJ-001"
                   value="<?= htmlspecialchars($edit_project["project_code"] ?? "") ?>"
                   required>


            <label>Project Name</label>

            <input type="text"
                   name="project_name"
                   maxlength="100"
                   placeholder="Enter project name"
                   value="<?= htmlspecialchars($edit_project["project_name"] ?? "") ?>"
                   required>


            <label>Project Site</label>

            <input type="text"
                   name="project_site"
                   maxlength="150"
                   placeholder="Enter project location"
                   value="<?= htmlspecialchars($edit_project["project_site"] ?? "") ?>"
                   required>


            <label>Project Status</label>

            <select name="project_status" required>

                <?php

                $current_status = $edit_project["project_status"] ?? "Ongoing";

                $statuses = ["Ongoing", "Completed", "On Hold"];

                foreach ($statuses as $status):

                ?>

                    <option value="<?= htmlspecialchars($status) ?>"
                        <?= $current_status === $status ? "selected" : "" ?>>

                        <?= htmlspecialchars($status) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <button type="submit">
                <?= $edit_project ? "Update Project" : "Add Project" ?>
            </button>


            <?php if ($edit_project): ?>

                <a href="projects.php" class="btn">
                    Cancel
                </a>

            <?php endif; ?>

        </form>

    <?php else: ?>

        <p>
            You can view project records, but only Admin can manage them.
        </p>

    <?php endif; ?>


    <hr>

    <h3>Project Records</h3>

    <table>

        <thead>

            <tr>

                <th>Project Code</th>
                <th>Project Name</th>
                <th>Project Site</th>
                <th>Status</th>

                <?php if ($is_admin): ?>
                    <th>Actions</th>
                <?php endif; ?>

            </tr>

        </thead>


        <tbody>

        <?php if ($projects && $projects->num_rows > 0): ?>

            <?php while ($project = $projects->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($project["project_code"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($project["project_name"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($project["project_site"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($project["project_status"]) ?>
                    </td>


                    <?php if ($is_admin): ?>

                        <td>

                            <div class="actions">

                                <a class="btn"
                                   href="projects.php?edit=<?= (int)$project["project_id"] ?>">
                                    Edit
                                </a>


                                <form method="POST"
                                      action="projects.php"
                                      onsubmit="return confirm('Are you sure you want to delete this project? Projects with existing material requests cannot be deleted.');">

                                    <input type="hidden"
                                           name="action"
                                           value="delete">

                                    <input type="hidden"
                                           name="project_id"
                                           value="<?= (int)$project["project_id"] ?>">

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

                <td colspan="<?= $is_admin ? 5 : 4 ?>">
                    No projects have been added yet.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

</body>
</html>
