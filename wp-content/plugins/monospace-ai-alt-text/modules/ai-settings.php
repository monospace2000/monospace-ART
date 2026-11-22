<?php
/**
 * Artworks > AI writing: the state of the connection (the key is in wp-config.php, never shown here) with a Test button,
 * and the model. Admin UI style (CLAUDE.md): WP-native, folding panels that start closed, a state chip each.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'monospace-artworks', 'AI writing', 'AI writing', 'manage_options', 'ms-ai-writing', 'ms_ai_settings_page' );
}, 20 );

add_action( 'admin_post_ms_ai_settings', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'ms_ai_settings' );
    $model = trim( (string) wp_unslash( $_POST['model'] ?? '' ) );
    update_option( 'monospace_ai_alt', [ 'model' => preg_match( '/^[A-Za-z0-9._-]{3,80}$/', $model ) ? $model : MS_AI_DEFAULT_MODEL ], false );
    wp_safe_redirect( add_query_arg( [ 'page' => 'ms-ai-writing', 'saved' => 1 ], admin_url( 'admin.php' ) ) );
    exit;
} );

function ms_ai_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    $has_key = ms_ai_key() !== '';
    $model   = ms_ai_settings()['model'];
    ?>
    <div class="wrap">
        <h1>AI writing</h1>
        <?php if ( ! empty( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
        <p>On <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-artworks' ) ); ?>">Artworks &rarr; New artwork</a>, <strong>Generate alt text</strong> asks Claude to describe the picture (the Alt text box stays editable; <strong>Regenerate alt text</strong> tries another version) and <strong>Suggest tags</strong> adds tags from your own tag list. Nothing is saved until you press Create draft. Each request costs about a cent.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ms_ai_settings">
            <?php wp_nonce_field( 'ms_ai_settings' ); ?>
            <div class="lm-areas"><div class="lm-panels">

                <details class="lm-panel" data-panel="connection">
                    <summary>
                        <span class="lm-t">Connection</span>
                        <span class="lm-chip <?php echo $has_key ? 'is-on' : 'is-empty'; ?>" id="ms-ai-chip"><?php echo $has_key ? 'Key found' : 'No key'; ?></span>
                        <span class="lm-sub"><?php echo $has_key ? 'Press Test to check that Claude answers' : 'The buttons are hidden until a key is in wp-config.php'; ?></span>
                    </summary>
                    <div class="lm-body">
                        <p class="description">The key is the constant <code>MONOSPACE_ANTHROPIC_KEY</code> in <code>wp-config.php</code> - never in the database, never shown here. It is a separate key from the Claude subscription and the one in Settings &rarr; Connectors; put a monthly spend limit on it in the Anthropic Console.</p>
                        <p><button type="button" class="button" id="ms-ai-test"<?php disabled( ! $has_key ); ?>>Test connection</button> <span id="ms-ai-test-msg" aria-live="polite"></span></p>
                    </div>
                </details>

                <details class="lm-panel" data-panel="model">
                    <summary>
                        <span class="lm-t">Model</span>
                        <span class="lm-chip is-on"><?php echo esc_html( $model ); ?></span>
                        <span class="lm-sub">Which Claude model writes the text</span>
                    </summary>
                    <div class="lm-body">
                        <p><label>Model <input type="text" name="model" value="<?php echo esc_attr( $model ); ?>" class="regular-text code"></label></p>
                        <p class="description">Default <code><?php echo esc_html( MS_AI_DEFAULT_MODEL ); ?></code> (the one the 680 existing alt texts were written with). Leave it unless a newer model is out.</p>
                    </div>
                </details>

            </div></div>
            <?php submit_button( 'Save' ); ?>
        </form>
    </div>
    <script>
    jQuery(function ($) {
        $('#ms-ai-test').on('click', function () {
            var $b = $(this), $m = $('#ms-ai-test-msg');
            $b.prop('disabled', true); $m.text('Asking Claude…').css('color', '');
            $.post(ajaxurl, { action: 'ms_ai_test', _ajax_nonce: <?php echo wp_json_encode( wp_create_nonce( 'ms_ai' ) ); ?> })
                .done(function (r) { $m.text(r && r.data || '').css('color', r && r.success ? '#1a7f37' : '#b32d2e'); })
                .fail(function (x) { $m.text('Request failed (' + x.status + ').').css('color', '#b32d2e'); })
                .always(function () { $b.prop('disabled', false); });
        });
    });
    </script>
    <?php
}
