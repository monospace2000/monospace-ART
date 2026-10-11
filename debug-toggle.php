<?php
/**
 * Quick Debug Toggle
 * Access this directly at: https://art.monospace.com/debug-toggle.php
 *
 * Security: Set a password below
 */

// ===== CONFIGURATION =====
define('DEBUG_TOGGLE_PASSWORD', '1234'); // Change this!
// =========================

session_start();

// Simple authentication
if (!isset($_SESSION['debug_toggle_auth'])) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === DEBUG_TOGGLE_PASSWORD) {
            $_SESSION['debug_toggle_auth'] = true;
        } else {
            $error = 'Invalid password';
        }
    }

    if (!isset($_SESSION['debug_toggle_auth'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Debug Toggle - Login</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f0f0f0; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 300px; }
                input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
                button { width: 100%; padding: 10px; background: #2271b1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
                button:hover { background: #135e96; }
                .error { color: #d63638; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2 style="margin-top: 0;">Debug Toggle</h2>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="password" name="password" placeholder="Password" autofocus required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: debug-toggle.php');
    exit;
}

// Toggle handler
$wp_config_path = __DIR__ . '/wp-config.php';
$message = '';
$message_type = '';

if (isset($_POST['toggle']) || isset($_POST['toggle_setting'])) {
    if (!file_exists($wp_config_path)) {
        $message = 'wp-config.php not found';
        $message_type = 'error';
    } elseif (!is_writable($wp_config_path)) {
        $message = 'wp-config.php is not writable';
        $message_type = 'error';
    } else {
        $config_content = file_get_contents($wp_config_path);

        // Handle individual setting toggle
        if (isset($_POST['toggle_setting'])) {
            $setting = $_POST['setting'];
            $new_value = $_POST['value'] === 'true';

            if (preg_match("/define\s*\(\s*['\"]{$setting}['\"]\s*,\s*(true|false)\s*\)/", $config_content)) {
                $config_content = preg_replace(
                    "/define\s*\(\s*['\"]{$setting}['\"]\s*,\s*(true|false)\s*\)/",
                    "define( '{$setting}', " . ($new_value ? 'true' : 'false') . " )",
                    $config_content
                );
            } else {
                $config_content = preg_replace(
                    "/(\/\*\s*That's all.*?\*\/)/s",
                    "define( '{$setting}', " . ($new_value ? 'true' : 'false') . " );\n$1",
                    $config_content
                );
            }

            $action = $new_value ? 'enabled' : 'disabled';
            $message = "{$setting} {$action}";
            $message_type = 'success';
        }
        // Handle master toggle
        else {
            $new_value = $_POST['toggle'] === 'on';

            // Update WP_DEBUG
            if (preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/", $config_content)) {
                $config_content = preg_replace(
                    "/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/",
                    "define( 'WP_DEBUG', " . ($new_value ? 'true' : 'false') . " )",
                    $config_content
                );
            } else {
                $config_content = preg_replace(
                    "/(\/\*\s*That's all.*?\*\/)/s",
                    "define( 'WP_DEBUG', " . ($new_value ? 'true' : 'false') . " );\n\n$1",
                    $config_content
                );
            }

            // Update WP_DEBUG_LOG
            if (preg_match("/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*(true|false)\s*\)/", $config_content)) {
                $config_content = preg_replace(
                    "/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*(true|false)\s*\)/",
                    "define( 'WP_DEBUG_LOG', " . ($new_value ? 'true' : 'false') . " )",
                    $config_content
                );
            } else {
                $config_content = preg_replace(
                    "/(\/\*\s*That's all.*?\*\/)/s",
                    "define( 'WP_DEBUG_LOG', " . ($new_value ? 'true' : 'false') . " );\n$1",
                    $config_content
                );
            }

            // Update WP_DEBUG_DISPLAY
            if (preg_match("/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*(true|false)\s*\)/", $config_content)) {
                $config_content = preg_replace(
                    "/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*(true|false)\s*\)/",
                    "define( 'WP_DEBUG_DISPLAY', " . ($new_value ? 'true' : 'false') . " )",
                    $config_content
                );
            } else {
                $config_content = preg_replace(
                    "/(\/\*\s*That's all.*?\*\/)/s",
                    "define( 'WP_DEBUG_DISPLAY', " . ($new_value ? 'true' : 'false') . " );\n$1",
                    $config_content
                );
            }

            $message = 'Debug mode ' . ($new_value ? 'ENABLED' : 'DISABLED');
            $message_type = 'success';
        }

        if (file_put_contents($wp_config_path, $config_content) === false) {
            $message = 'Failed to update wp-config.php';
            $message_type = 'error';
        }
    }
}

// Clear log handler
$log_path = __DIR__ . '/wp-content/debug.log';
if (isset($_POST['clear_log'])) {
    file_put_contents($log_path, '');
    $message = 'Log cleared';
    $message_type = 'success';
    clearstatcache();
}

// Get current debug status
$config_content = file_get_contents($wp_config_path);
$debug_enabled = preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*true\s*\)/", $config_content);
$debug_log = preg_match("/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*true\s*\)/", $config_content);
$debug_display = preg_match("/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*true\s*\)/", $config_content);

$log_exists = file_exists($log_path);
$log_size = $log_exists ? filesize($log_path) : 0;
$show_log = isset($_GET['view']) && $_GET['view'] === 'log';
$log_lines = [];
if ($show_log && $log_exists && $log_size > 0) {
    $all_lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_lines = array_slice($all_lines, -200); // last 200 lines
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Toggle</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            margin: 0 0 20px 0;
            font-size: 24px;
            color: #1e1e1e;
        }
        .status {
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 18px;
            text-align: center;
        }
        .status.enabled {
            background: #ffebee;
            border: 2px solid #d63638;
            color: #d63638;
        }
        .status.disabled {
            background: #e8f5e9;
            border: 2px solid #00a32a;
            color: #00a32a;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            width: 100%;
            margin: 10px 0;
        }
        .button-enable {
            background: #d63638;
        }
        .button-enable:hover {
            background: #b32d2e;
        }
        .button-disable {
            background: #00a32a;
        }
        .button-disable:hover {
            background: #008a20;
        }
        .button-secondary {
            background: #6c757d;
            padding: 8px 20px;
            font-size: 14px;
            width: auto;
        }
        .button-secondary:hover {
            background: #5a6268;
        }
        .info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .info h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .info ul {
            margin: 0;
            padding-left: 20px;
            line-height: 1.8;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            margin: 10px 0;
        }
        .toggle-label {
            display: flex;
            flex-direction: column;
        }
        .toggle-label strong {
            font-size: 14px;
            margin-bottom: 3px;
        }
        .toggle-label span {
            font-size: 12px;
            color: #666;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2271b1;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>WordPress Debug Toggle</h1>
            <a href="?logout=1" class="button button-secondary">Logout</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="status <?php echo $debug_enabled ? 'enabled' : 'disabled'; ?>">
            Debug Mode: <strong><?php echo $debug_enabled ? 'ENABLED' : 'DISABLED'; ?></strong>
        </div>

        <form method="post">
            <?php if ($debug_enabled): ?>
                <button type="submit" name="toggle" value="off" class="button button-disable">
                    Turn Debug OFF
                </button>
            <?php else: ?>
                <button type="submit" name="toggle" value="on" class="button button-enable">
                    Turn Debug ON
                </button>
            <?php endif; ?>
        </form>

        <div class="info">
            <h3>Individual Settings:</h3>

            <form method="post" id="toggle-form">
                <input type="hidden" name="toggle_setting" value="1">
                <input type="hidden" name="setting" id="setting-name">
                <input type="hidden" name="value" id="setting-value">

                <div class="toggle-row">
                    <div class="toggle-label">
                        <strong>WP_DEBUG_LOG</strong>
                        <span>Log errors to wp-content/debug.log</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" <?php echo $debug_log ? 'checked' : ''; ?>
                               onchange="toggleSetting('WP_DEBUG_LOG', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-row">
                    <div class="toggle-label">
                        <strong>WP_DEBUG_DISPLAY</strong>
                        <span>Display errors on screen</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" <?php echo $debug_display ? 'checked' : ''; ?>
                               onchange="toggleSetting('WP_DEBUG_DISPLAY', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </form>
        </div>

        <script>
            function toggleSetting(setting, value) {
                document.getElementById('setting-name').value = setting;
                document.getElementById('setting-value').value = value ? 'true' : 'false';
                document.getElementById('toggle-form').submit();
            }
        </script>

        <div class="info">
            <h3>What these mean:</h3>
            <ul>
                <li><code>WP_DEBUG</code> - Master debug switch (use button above)</li>
                <li><code>WP_DEBUG_LOG</code> - Write errors to log file (recommended)</li>
                <li><code>WP_DEBUG_DISPLAY</code> - Show errors on page (use with caution)</li>
            </ul>
        </div>

        <div class="info">
            <h3>Access URLs:</h3>
            <ul>
                <li>This page: <code><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']); ?></code></li>
                <li>WordPress: <a href="<?php echo htmlspecialchars('//' . $_SERVER['HTTP_HOST'] . '/wp-admin/'); ?>" target="_blank">Admin</a></li>
            </ul>
        </div>

        <div class="info">
            <h3>Debug Log:</h3>
            <?php if (!$log_exists): ?>
                <p style="color:#666; font-size:14px;">No log file found at <code>wp-content/debug.log</code>. Enable WP_DEBUG_LOG and trigger an error to create it.</p>
            <?php else: ?>
                <p style="font-size:13px; color:#666; margin:0 0 10px 0;">
                    <code>wp-content/debug.log</code> — <?php echo number_format($log_size / 1024, 1); ?> KB
                </p>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <?php if ($show_log): ?>
                        <a href="?" class="button button-secondary" style="text-decoration:none; display:inline-block;">Hide Log</a>
                    <?php else: ?>
                        <a href="?view=log" class="button button-secondary" style="text-decoration:none; display:inline-block;">View Log</a>
                    <?php endif; ?>
                    <form method="post" style="margin:0;" onsubmit="return confirm('Clear the entire debug log?');">
                        <button type="submit" name="clear_log" value="1" class="button button-secondary" style="background:#d63638;">Clear Log</button>
                    </form>
                </div>
                <?php if ($show_log): ?>
                    <pre style="margin-top:15px; background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:4px; font-size:12px; line-height:1.5; overflow-x:auto; max-height:500px; overflow-y:auto; white-space:pre-wrap; word-break:break-all;"><?php
                        if (empty($log_lines)) {
                            echo '(log is empty)';
                        } else {
                            echo htmlspecialchars(implode("\n", $log_lines));
                            if (count($log_lines) === 200) {
                                echo "\n\n[Showing last 200 lines]";
                            }
                        }
                    ?></pre>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
