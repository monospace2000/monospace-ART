<?php
/**
 * Quick Debug Toggle
 * Adds a simple admin page to toggle WP_DEBUG on/off
 *
 * @package monospace-art-theme
 */

// Add admin menu item
add_action('admin_menu', function() {
    add_management_page(
        'Debug Toggle',
        'Debug Toggle',
        'manage_options',
        'monospace-debug-toggle',
        'monospace_debug_toggle_page'
    );
});

// Render the admin page
function monospace_debug_toggle_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $wp_config_path = ABSPATH . 'wp-config.php';

    if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
        echo '<div class="wrap"><h1>Debug Toggle</h1>';
        echo '<div class="notice notice-error"><p>wp-config.php is not writable. Please check file permissions.</p></div>';
        echo '</div>';
        return;
    }

    // Handle toggle action
    if (isset($_POST['toggle_debug']) && check_admin_referer('monospace_debug_toggle')) {
        $config_content = file_get_contents($wp_config_path);
        $new_value = $_POST['debug_state'] === 'true';

        // Update WP_DEBUG
        if (preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/", $config_content)) {
            $config_content = preg_replace(
                "/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/",
                "define( 'WP_DEBUG', " . ($new_value ? 'true' : 'false') . " )",
                $config_content
            );
        } else {
            // If WP_DEBUG doesn't exist, add it before the "That's all" comment
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

        if (file_put_contents($wp_config_path, $config_content) !== false) {
            $message = $new_value ? 'Debug mode ENABLED' : 'Debug mode DISABLED';
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . $message . '</strong></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update wp-config.php</p></div>';
        }
    }

    // Get current debug status
    $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;

    ?>
    <div class="wrap">
        <h1>Debug Toggle</h1>

        <div style="max-width: 600px;">
            <div class="card" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;">Current Status</h2>
                <p style="font-size: 16px; margin: 20px 0;">
                    Debug Mode:
                    <strong style="color: <?php echo $debug_enabled ? '#d63638' : '#00a32a'; ?>; font-size: 18px;">
                        <?php echo $debug_enabled ? 'ENABLED' : 'DISABLED'; ?>
                    </strong>
                </p>

                <form method="post" style="margin-top: 30px;">
                    <?php wp_nonce_field('monospace_debug_toggle'); ?>
                    <input type="hidden" name="toggle_debug" value="1">
                    <input type="hidden" name="debug_state" value="<?php echo $debug_enabled ? 'false' : 'true'; ?>">

                    <?php if ($debug_enabled): ?>
                        <button type="submit" class="button button-primary button-hero" style="background: #00a32a; border-color: #00a32a;">
                            Turn Debug OFF
                        </button>
                    <?php else: ?>
                        <button type="submit" class="button button-primary button-hero" style="background: #d63638; border-color: #d63638;">
                            Turn Debug ON
                        </button>
                    <?php endif; ?>
                </form>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h3 style="margin-top: 0;">What this toggles:</h3>
                    <ul style="line-height: 1.8;">
                        <li><code>WP_DEBUG</code> - Master debug switch</li>
                        <li><code>WP_DEBUG_LOG</code> - Log errors to wp-content/debug.log</li>
                        <li><code>WP_DEBUG_DISPLAY</code> - Display errors on screen</li>
                    </ul>
                </div>

                <?php if ($debug_enabled && file_exists(WP_CONTENT_DIR . '/debug.log')): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                        <p style="margin: 0;">
                            <strong>Debug log exists:</strong>
                            <a href="<?php echo admin_url('tools.php?page=monospace-debug-log'); ?>">View debug.log</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// Add a log viewer page
add_action('admin_menu', function() {
    add_management_page(
        'Debug Log',
        'Debug Log',
        'manage_options',
        'monospace-debug-log',
        'monospace_debug_log_viewer'
    );
});

function monospace_debug_log_viewer() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $log_file = WP_CONTENT_DIR . '/debug.log';
    $lines_to_show = isset($_GET['lines']) ? intval($_GET['lines']) : 100;

    ?>
    <div class="wrap">
        <h1>Debug Log Viewer</h1>

        <?php if (file_exists($log_file)): ?>
            <p>
                <a href="?page=monospace-debug-log&lines=50" class="button">Last 50 lines</a>
                <a href="?page=monospace-debug-log&lines=100" class="button">Last 100 lines</a>
                <a href="?page=monospace-debug-log&lines=500" class="button">Last 500 lines</a>
                <a href="?page=monospace-debug-log&lines=all" class="button">All</a>
                <?php if (isset($_GET['clear']) && $_GET['clear'] === '1' && check_admin_referer('clear_debug_log')): ?>
                    <?php file_put_contents($log_file, ''); ?>
                    <span style="color: green; margin-left: 10px;">✓ Log cleared</span>
                <?php else: ?>
                    <a href="<?php echo wp_nonce_url('?page=monospace-debug-log&clear=1', 'clear_debug_log'); ?>"
                       class="button button-secondary"
                       onclick="return confirm('Clear the entire debug log?');">Clear Log</a>
                <?php endif; ?>
            </p>

            <div style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 600px; overflow-y: auto;">
                <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php
                    if ($lines_to_show === 'all') {
                        echo esc_html(file_get_contents($log_file));
                    } else {
                        $lines = file($log_file);
                        if ($lines) {
                            $lines = array_slice($lines, -$lines_to_show);
                            echo esc_html(implode('', $lines));
                        }
                    }
                ?></pre>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p>Debug log file does not exist yet. Enable debugging and errors will be logged to <code>wp-content/debug.log</code></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
