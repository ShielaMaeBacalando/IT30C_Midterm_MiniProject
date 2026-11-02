<?php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "construction_materials_db"
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
