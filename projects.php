
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
$edit_project = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Only Admin can add, edit, or delete projects
    if (!$is_admin) {
        die("Access denied. Only Admin can manage projects.");
    }

    $action = $_POST["action"] ?? "";

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
        } elseif (!in_array($project_status, $allowed_status, true)) {
            $message = "Invalid project status.";
        } else {

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

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: projects.php?success=" . $action);
                exit;
            } else {
                if ($stmt->errno === 1062) {
                    $message = "Project code already exists. Please use another code.";
                } else {
                    $message = "Unable to save project. Please try again.";
                }
            }

            $stmt->close();
        }
    }

    // Delete project
    elseif ($action === "delete") {

        $project_id = (int)($_POST["project_id"] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM projects WHERE project_id = ?"
        );

        $stmt->bind_param("i", $project_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: projects.php?success=delete");
            exit;
        } else {
            $message = "Cannot delete this project because it may already have material requests.";
        }

        $stmt->close();
    }
}

// Display success messages
if (isset($_GET["success"])) {
    if ($_GET["success"] === "add") {
        $message = "Project added successfully!";
    } elseif ($_GET["success"] === "edit") {
        $message = "Project updated successfully!";
    } elseif ($_GET["success"] === "delete") {
        $message = "Project deleted successfully!";
    }
}

// Get project information for editing
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

// Get all projects
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
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
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
                       value="<?= $edit_project["project_id"] ?>">
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
                    <option value="<?= $status ?>"
                        <?= $current_status === $status ? "selected" : "" ?>>
                        <?= $status ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">
                <?= $edit_project ? "Update Project" : "Add Project" ?>
            </button>

            <?php if ($edit_project): ?>
                <a href="projects.php" class="btn">Cancel</a>
            <?php endif; ?>

        </form>

    <?php else: ?>

        <p>You can view project records, but only Admin can manage them.</p>

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
                                   href="projects.php?edit=<?= $project["project_id"] ?>">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="projects.php"
                                      onsubmit="return confirm('Are you sure you want to delete this project?');">

                                    <input type="hidden"
                                           name="action"
                                           value="delete">

                                    <input type="hidden"
                                           name="project_id"
                                           value="<?= $project["project_id"] ?>">

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
