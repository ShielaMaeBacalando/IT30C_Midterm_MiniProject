
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION["username"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            background: #F5F1E8;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .dashboard-container {
            display: flex;
            width: 100%;
            max-width: 850px;
            min-height: 400px;
            background: #FFFEFA;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(52, 73, 94, 0.10);
        }

        .dashboard-illustration {
            width: 45%;
            background: #E7EEF5;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px;
            text-align: center;
        }

        .dashboard-illustration img {
            width: 100%;
            max-width: 280px;
            border-radius: 12px;
            object-fit: contain;
        }

        .dashboard-illustration h2 {
            color: #34495E;
            margin-top: 22px;
            margin-bottom: 10px;
            font-size: 23px;
        }

        .dashboard-illustration p {
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .dashboard-content {
            width: 55%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 45px 35px;
        }

        .dashboard-label {
            color: #7C9DBB;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .dashboard-content h1 {
            color: #34495E;
            font-size: 30px;
            margin-bottom: 25px;
        }

        .dashboard-content h2 {
            color: #52799C;
            font-size: 23px;
            margin-bottom: 15px;
            overflow-wrap: anywhere;
        }

        .dashboard-content p {
            color: #64748B;
            font-size: 15px;
            line-height: 1.7;
            margin: 0 0 15px;
        }

        .status {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #F0F5F1;
            color: #547B68;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin: 8px 0 25px;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            background: #78A58C;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dashboard-content .button-link {
            display: block;
            width: 100%;
            padding: 13px;
            background: #7C9DBB;
            color: #FFFFFF;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            transition: background 0.2s ease;
        }

        .dashboard-content .button-link:hover {
            background: #5F83A5;
        }

        @media (max-width: 600px) {
            .dashboard-container {
                flex-direction: column;
                max-width: 400px;
            }

            .dashboard-illustration,
            .dashboard-content {
                width: 100%;
            }

            .dashboard-illustration {
                padding: 25px 25px 20px;
            }

            .dashboard-illustration img {
                max-width: 190px;
            }

            .dashboard-illustration h2 {
                margin-top: 15px;
            }

            .dashboard-content {
                padding: 30px 25px;
            }

            .dashboard-content h1 {
                font-size: 27px;
            }
         } 
    </style>
</head>
<body>

    <div class="dashboard-container">

        <div class="dashboard-illustration">
            <img
                src="CHOOKIEPIC.jpeg"
                alt="Welcome illustration in soft blue"
            >

            <h2>Chookie_bruh.</h2>
            <p>My personal space please take a quick view.</p>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-label">Your account</div>

            <h1>Dashboard</h1>

            <h2>
                Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!
            </h2>

            <p>
                You have successfully logged in.
                Nothing to see here, hehe.
            </p>

            <div class="status">
                <span class="status-dot"></span>
                <span>Account connected to database</span>
            </div>

            <a class="button-link" href="logout.php">Logout</a>
        </div>

    </div>

</body>
</html>
