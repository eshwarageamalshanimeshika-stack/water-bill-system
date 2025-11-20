<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Role | Water Bill Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Make body take full height and push footer down */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            background: #121212;
            color: #E0E0E0;
            font-family: Arial, sans-serif;
        }

        header {
            text-align: center;
            padding: 20px;
            background: #0D47A1;
            color: white;
        }

        main {
            flex: 1; /* expands to fill remaining space */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .register-container {
            max-width: 400px;
            background: #1E1E1E;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            color: #E0E0E0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.6);
        }

        select, button {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            border: none;
            font-size: 16px;
        }

        button {
            background: #0D47A1;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background: #1565C0;
        }

        footer {
            text-align: center;
            padding: 15px;
            background: #1E1E1E;
            color: #ccc;
        }
    </style>
</head>
<body>
<header>
    <h1>💧 Water Bill Management System</h1>
    <p>Please select your role</p>
</header>

<main>
    <div class="register-container">
        <h2>📝 Register</h2>
        <form method="GET" action="">
            <select name="role" required>
                <option value="">-- Choose Role --</option>
                <option value="admin">Admin</option>
                <option value="customer">Customer</option>
            </select>
            <button type="submit">Continue</button>
        </form>

        <?php
        if (isset($_GET['role'])) {
            if ($_GET['role'] === "admin") {
                header("Location: admin_register.php");
                exit();
            } elseif ($_GET['role'] === "customer") {
                header("Location: customer_register.php");
                exit();
            }
        }
        ?>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date("Y"); ?>  Water Supply Department Kothmale | Sri Lanka Mahawali Authority</p>
</footer>
</body>
</html>
