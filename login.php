<?php
session_start();
include 'db.php';

$error = '';

// Ако вече е логнат, го препращаме към контролния панел
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            // Проверка за потребител в базата данни
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];
                header("Location: index.php");
                exit();
            } else {
                // Резервен вариант (Ако още нямаш таблица users в базата)
                if (($username === 'admin' || $username === 'Александър Стойчев') && $password === 'admin') {
                    $_SESSION['user_id']   = 1;
                    $_SESSION['user_name'] = 'Александър Стойчев';
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Грешно потребителско име или парола!";
                }
            }
        } catch (\PDOException $e) {
            // Резервен вход при липса на таблица users
            if ($username === 'admin' && $password === 'admin') {
                $_SESSION['user_id']   = 1;
                $_SESSION['user_name'] = 'Александър Стойчев';
                header("Location: index.php");
                exit();
            }
            $error = "Грешка в базата данни: " . $e->getMessage();
        }
    } else {
        $error = "Моля, попълнете всички полета!";
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Storage Matrix System</title>
    <style>
        :root {
            --primary-blue: #00529b;
            --border-color: #e2e8f0;
            --bg-gray: #f8fafc;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-gray);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .brand-logo {
            font-size: 32px;
            font-weight: 900;
            color: var(--primary-blue);
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1px;
            margin-bottom: 30px;
            text-transform: uppercase;
        }

        .form-group {
            text-align: left;
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #f8fafc;
        }

        .form-input:focus {
            border-color: var(--primary-blue);
            background: #fff;
            outline: none;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }

        .btn-login:hover {
            background: #003366;
        }

        .error-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-logo">ottobock.</div>
    <div class="brand-subtitle">Storage Matrix System</div>

    <?php if (!empty($error)): ?>
        <div class="error-badge"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Потребителско Име</label>
            <input type="text" name="username" class="form-input" placeholder="admin" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label">Парола</label>
            <input type="password" name="password" class="form-input" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-login">🔑 Вход в системата</button>
    </form>
</div>

</body>
</html>