<?php
/**
 * Design module: Tab color - the highlight on the current tab of the My Account page (2026-11-16, owner: "make it an
 * optional separate color picker"). Empty = the Buttons color (Look > Buttons), so the tab and the buttons match.
 * Option monospace_tab_color [ color ]. Printed as --ms-tab-color / --ms-tab-text on :root through monospace_look_style()
 * (and monospace_look_all_css(), so the dark look has its own); read by _woocommerce.scss (MY ACCOUNT PAGE).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_tab_color() {
    $s = get_option( 'monospace_tab_color', [] );
    return [ 'color' => (string) ( sanitize_hex_color( is_array( $s ) ? ( $s['color'] ?? '' ) : '' ) ?: '' ) ];
}

/** The variables for it (none = follow the Buttons color) */
function monospace_tab_color_vars( $s = null ) {
    $c = ( $s ?: monospace_tab_color() )['color'];
    if ( $c === '' ) return [];
    return [ '--ms-tab-color' => $c, '--ms-tab-text' => function_exists( 'monospace_look_text_on' ) ? monospace_look_text_on( $c ) : '#fff' ];
}

add_action( 'wp_head', function () {
    $v = monospace_tab_color_vars();
    if ( ! $v || ! function_exists( 'monospace_look_style' ) ) return;
    $c = ''; foreach ( $v as $k => $x ) $c .= $k . ':' . $x . ';';
    monospace_look_style( 'ms-tab-color', ':root{' . $c . '}' ); // scoped when dark mode is on
}, 31 );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['tab-color'] = [
        'title'       => 'Tab color',
        'description' => 'The highlight on the current tab of the My Account page - its own color, or the same as the buttons.',
        'group'       => 'look',
        'order'       => 13,
        'summary'     => function () { return monospace_tab_color()['color'] ?: 'Same as buttons'; },
        'render'      => 'monospace_render_tab_color',
        'save'        => 'monospace_save_tab_color',
    ];
    return $modules;
} );

function monospace_render_tab_color() {
    $s = monospace_tab_color(); ?>
    <table class="form-table" role="presentation" id="ms-tc">
        <tr><th scope="row">Color</th><td>
            <input type="hidden" name="ms_tc[seen]" value="1">
            <input type="color" id="ms-tc-pick" value="<?php echo esc_attr( $s['color'] ?: '#046bd2' ); ?>"><input type="hidden" name="ms_tc[color]" id="ms-tc-val" value="<?php echo esc_attr( $s['color'] ); ?>">
            <button type="button" class="button-link" id="ms-tc-unset">Same as buttons</button>
            <p class="description">Empty = the Buttons color above. The label turns black or white, whichever reads.</p>
        </td></tr>
    </table>
    <script>
    (function () {
        var pick = document.getElementById('ms-tc-pick'), val = document.getElementById('ms-tc-val'), unset = document.getElementById('ms-tc-unset'), iframe = document.getElementById('ms-spot-iframe');
        if (!pick) return;
        function textOn(hex) { var h = hex.replace('#', ''), l = (0.299 * parseInt(h.substr(0, 2), 16) + 0.587 * parseInt(h.substr(2, 2), 16) + 0.114 * parseInt(h.substr(4, 2), 16)) / 255; return l > 0.62 ? '#111' : '#fff'; }
        function apply() {
            var doc; try { doc = iframe && iframe.contentDocument; } catch (e) {}
            if (!doc || !doc.documentElement) return;
            if (val.value) { doc.documentElement.style.setProperty('--ms-tab-color', val.value); doc.documentElement.style.setProperty('--ms-tab-text', textOn(val.value)); }
            else { doc.documentElement.style.removeProperty('--ms-tab-color'); doc.documentElement.style.removeProperty('--ms-tab-text'); }
        }
        pick.addEventListener('input', function () { val.value = pick.value; apply(); });
        unset.addEventListener('click', function () { val.value = ''; apply(); });
        if (iframe) iframe.addEventListener('load', apply);
    })();
    </script>
    <?php
}

function monospace_save_tab_color() {
    if ( empty( $_POST['ms_tc']['seen'] ) ) return;
    $c = sanitize_hex_color( wp_unslash( $_POST['ms_tc']['color'] ?? '' ) ) ?: '';
    $c === '' ? delete_option( 'monospace_tab_color' ) : update_option( 'monospace_tab_color', [ 'color' => $c ] );
}
