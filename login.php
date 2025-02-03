<?php

require_once 'config.php';


// Replace the current session handling code with this
if (isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(),'',0,'/');
    session_start();
}


$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];

        // Validate input
        if (empty($username) || empty($password)) {
            throw new Exception('Please enter both username and password.');
        }

        // Check user credentials
        $stmt = $pdo->prepare("
            SELECT u.*, GROUP_CONCAT(p.name) as permissions 
            FROM users u 
            LEFT JOIN user_permissions up ON u.id = up.user_id 
            LEFT JOIN permissions p ON up.permission_id = p.id 
            WHERE u.username = ? AND u.is_active = 1 
            GROUP BY u.id
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['permissions'] = $user['permissions'] ? explode(',', $user['permissions']) : [];

            // Mark attendance
            $stmt = $pdo->prepare("
                INSERT INTO attendance (user_id, check_in, status) 
                VALUES (?, NOW(), 'present')
            ");
            $stmt->execute([$user['id']]);
            $attendance_id = $pdo->lastInsertId();
            $_SESSION['current_attendance_id'] = $attendance_id;

            // Update last login
            $stmt = $pdo->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);

            // Redirect based on role
            switch ($user['role']) {
                case ROLE_ADMIN:
                    header('Location: admin/dashboard.php');
                    break;
                case ROLE_HR:
                    header('Location: hr/dashboard.php');
                    break;
                case ROLE_TL:
                    header('Location: tl/dashboard.php');
                    break;
                case ROLE_AGENT:
                    header('Location: agent/dashboard.php');
                    break;
                default:
                    header('Location: dashboard.php');
            }
            exit;
        } else {
            throw new Exception('Invalid username or password.');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Call Center Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <style>
        :root {
            --color-primary: <?php echo COLOR_PRIMARY; ?>;
            --color-secondary: <?php echo COLOR_SECONDARY; ?>;
            --color-accent: <?php echo COLOR_ACCENT; ?>;
            --color-highlight: <?php echo COLOR_HIGHLIGHT; ?>;
            --color-background: <?php echo COLOR_BACKGROUND; ?>;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(255, 162, 182, 0.2);
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(228, 61, 18, 0.3);
        }

        .error-message {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .company-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Replace with your company logo -->
        <img src="assets/images/logo.png" alt="Company Logo" class="company-logo">
        
        <h1 class="text-3xl font-bold text-center mb-8" style="color: var(--color-primary)">
            Welcome Back
        </h1>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="input-group">
                <input type="text" 
                       name="username" 
                       placeholder="Username"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       required>
            </div>

            <div class="input-group">
                <input type="password" 
                       name="password" 
                       placeholder="Password"
                       required>
            </div>

            <button type="submit" class="submit-btn">
                Sign In
            </button>

            <p class="text-center mt-4 text-sm text-gray-600">
                Your attendance will be marked automatically upon login.
            </p>
        </form>
    </div>

    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add loading state to button on submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = document.querySelector('.submit-btn');
            button.textContent = 'Signing In...';
            button.disabled = true;
        });
    </script>
</body>
</html>