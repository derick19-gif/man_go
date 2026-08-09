<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?> - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }

        .container {
            max-width: 400px;
            width: 100%;
        }

        .login-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .login-header .mango-emoji {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert.success {
            background-color: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .alert.info {
            background-color: #eef;
            color: #33c;
            border: 1px solid #ccf;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .remember-me input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            cursor: pointer;
        }

        .remember-me label {
            cursor: pointer;
            margin-bottom: 0;
            font-weight: normal;
            color: #666;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn.primary:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .login-footer {
            padding: 20px 30px;
            background: #f9f9f9;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #eee;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .session-expired {
            font-size: 13px;
            color: #666;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .loading {
            display: inline-block;
            width: 4px;
            height: 4px;
            background: currentColor;
            border-radius: 50%;
            animation: loading 1.4s infinite;
        }

        @keyframes loading {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <span class="mango-emoji">??</span>
                <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
                <p>Marketplace Platform</p>
            </div>

            <div class="login-body">
                <?php if (isset($error) && $error): ?>
                    <div class="alert error">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($message) && $message): ?>
                    <div class="alert success">
                        <?php echo htmlspecialchars($message['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($expired) && $expired): ?>
                    <div class="alert info" style="margin-top: 15px;">
                        Your session has expired. Please log in again.
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo APP_URL; ?>/authenticate" id="loginForm">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="admin@localhost"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@localhost'); ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember">Remember me</label>
                    </div>

                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                    <button type="submit" class="btn primary" id="submitBtn">
                        Sign In
                    </button>
                </form>

                <div class="session-expired">
                    <strong>Demo Credentials:</strong><br>
                    Email: <code>admin@localhost</code><br>
                    Password: <code>190719</code>
                </div>
            </div>

            <div class="login-footer">
                Don't have an account? <a href="<?php echo APP_URL; ?>/register">Sign up here</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Signing in <span class="loading">.</span>';

            // Simulate loading animation
            let dots = 1;
            const dotInterval = setInterval(() => {
                dots = (dots % 3) + 1;
                submitBtn.innerHTML = 'Signing in ' + '.'.repeat(dots);
            }, 500);

            // Store interval ID for cleanup
            this.dataset.dotInterval = dotInterval;
        });

        // Focus management
        document.getElementById('email').addEventListener('blur', function() {
            if (this.value && !this.value.includes('@')) {
                this.value = this.value + '@localhost';
            }
        });
    </script>
</body>
</html>
