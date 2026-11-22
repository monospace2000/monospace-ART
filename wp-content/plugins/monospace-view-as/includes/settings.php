<?php
/**
 * View As - settings: the defaults, the getters every other file uses, and the Settings > View As screen.
 *
 * One option, `ms_view_as_settings`. Everything has a default that works on a plain WordPress site, so the plugin needs no setup.
 *
 * @package monospace-view-as
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function ms_view_as_defaults() {
    return [
        'capability'   => 'manage_options',   // who may use View As
        'hours'        => 2,                  // how long a View As lasts
        'recent'       => 5,                  // people in the toolbar pulldown
        'message'      => 'Action not allowed in View As mode.',
        'allowed_ajax' => '',                 // admin-ajax actions that only read, one per line
        'landing'      => '',                 // where a start from an admin screen lands (path or address)
        'place_left_of' => '',                // toolbar item id the button sits immediately left of (e.g. a cache Purge button)
        'users_column' => 1,                  // a View As button on every row of the Users screen
    ];
}

/** One setting (filter `ms_view_as_setting_<key>` for code that wants the last word) */
function ms_view_as_setting( $key ) {
    $saved = get_option( 'ms_view_as_settings', [] );
    $all   = array_merge( ms_view_as_defaults(), is_array( $saved ) ? $saved : [] );
    $value = $all[ $key ] ?? null;
    if ( $key === 'capability' && ( ! is_string( $value ) || $value === '' ) ) $value = 'manage_options';
    return apply_filters( 'ms_view_as_setting_' . $key, $value );
}

/** The capability that lets someone use View As */
function ms_view_as_cap() {
    return (string) ms_view_as_setting( 'capability' );
}

function ms_view_as_hours() {
    return max( 1, min( 24, (int) ms_view_as_setting( 'hours' ) ) );
}

/** Who can be looked at: anyone who could use View As or manage the site can not (the toolbar would not make sense, and it would be a way to act as an administrator) */
function ms_view_as_can_view( $user ) {
    return $user && ! user_can( $user, ms_view_as_cap() ) && ! user_can( $user, 'manage_options' );
}

/** The browser alert's words */
function ms_view_as_message() {
    $m = trim( (string) ms_view_as_setting( 'message' ) );
    return $m !== '' ? $m : ms_view_as_defaults()['message'];
}

/** admin-ajax actions the site's front end uses only to read: allowed while viewing */
function ms_view_as_allowed_ajax() {
    $lines = preg_split( '/[\s,]+/', (string) ms_view_as_setting( 'allowed_ajax' ), -1, PREG_SPLIT_NO_EMPTY );
    return apply_filters( 'ms_view_as_allowed_ajax_actions', array_map( 'sanitize_key', $lines ) );
}

/** Where a start from an admin screen lands: the setting, else WooCommerce's account page, else the front page */
function ms_view_as_landing() {
    $to = trim( (string) ms_view_as_setting( 'landing' ) );
    if ( $to !== '' ) return $to[0] === '/' ? home_url( $to ) : $to;
    return function_exists( 'wc_get_page_permalink' ) && wc_get_page_permalink( 'myaccount' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
}

/**
 * ===========================
 * SETTINGS > VIEW AS
 * ===========================
 */
add_action( 'admin_menu', function () {
    add_options_page( 'View As', 'View As', 'manage_options', 'ms-view-as', 'ms_view_as_settings_page' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( MS_VIEW_AS_FILE ), function ( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ms-view-as' ) ) . '">Settings</a>' );
    return $links;
} );

add_action( 'admin_init', function () {
    register_setting( 'ms_view_as', 'ms_view_as_settings', [
        'type'              => 'array',
        'default'           => [],
        'sanitize_callback' => function ( $in ) {
            $in  = is_array( $in ) ? $in : [];
            $def = ms_view_as_defaults();
            $actions = preg_split( '/[\s,]+/', (string) ( $in['allowed_ajax'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
            $landing = trim( (string) ( $in['landing'] ?? '' ) );
            return [
                'capability'    => sanitize_key( $in['capability'] ?? '' ) ?: $def['capability'],
                'hours'         => max( 1, min( 24, (int) ( $in['hours'] ?? $def['hours'] ) ) ),
                'recent'        => max( 1, min( 10, (int) ( $in['recent'] ?? $def['recent'] ) ) ),
                'message'       => sanitize_text_field( $in['message'] ?? '' ) ?: $def['message'],
                'allowed_ajax'  => implode( "\n", array_unique( array_map( 'sanitize_key', $actions ) ) ),
                'landing'       => $landing === '' ? '' : ( $landing[0] === '/' ? '/' . ltrim( sanitize_text_field( $landing ), '/' ) : esc_url_raw( $landing ) ),
                'place_left_of' => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $in['place_left_of'] ?? '' ) ),
                'users_column'  => empty( $in['users_column'] ) ? 0 : 1,
            ];
        },
    ] );
} );

function ms_view_as_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $s = array_merge( ms_view_as_defaults(), (array) get_option( 'ms_view_as_settings', [] ) );
    $f = fn( $k ) => 'name="ms_view_as_settings[' . esc_attr( $k ) . ']"';
    ?>
    <div class="wrap">
        <h1>View As</h1>
        <p>See the site exactly as one user sees it - read-only. You stay logged in as yourself; the pages are drawn for them, and anything that would act for them (sending an email, saving a change, placing an order, posting a comment, changing a password) is refused with an alert. Where the site has WooCommerce, items you add to the cart go in a throw-away cart that is deleted when you switch back.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'ms_view_as' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ms-vas-cap">Who can use it</label></th>
                    <td><input type="text" id="ms-vas-cap" class="regular-text code" <?php echo $f( 'capability' ); ?> value="<?php echo esc_attr( $s['capability'] ); ?>">
                        <p class="description">A capability. Default <code>manage_options</code> (administrators). Administrators and anyone with this capability can never be viewed.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ms-vas-hours">Lasts</label></th>
                    <td><input type="number" id="ms-vas-hours" min="1" max="24" class="small-text" <?php echo $f( 'hours' ); ?> value="<?php echo esc_attr( $s['hours'] ); ?>"> hours
                        <p class="description">Then it ends by itself (Switch back also ends it).</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ms-vas-message">Alert text</label></th>
                    <td><input type="text" id="ms-vas-message" class="large-text" <?php echo $f( 'message' ); ?> value="<?php echo esc_attr( $s['message'] ); ?>">
                        <p class="description">What the browser's alert box says when something is refused.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ms-vas-ajax">Allowed read-only AJAX actions</label></th>
                    <td><textarea id="ms-vas-ajax" rows="3" class="large-text code" <?php echo $f( 'allowed_ajax' ); ?>><?php echo esc_textarea( $s['allowed_ajax'] ); ?></textarea>
                        <p class="description">One <code>admin-ajax.php</code> action per line. While viewing, every AJAX action is refused except these (add the ones your front end uses only to read, such as a live filter or search). WooCommerce's own cart actions are built in.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ms-vas-landing">Start from an admin screen lands on</label></th>
                    <td><input type="text" id="ms-vas-landing" class="regular-text code" <?php echo $f( 'landing' ); ?> value="<?php echo esc_attr( $s['landing'] ); ?>" placeholder="/">
                        <p class="description">A path (<code>/account/</code>) or an address. Starting from a page of the site stays on that page; an admin screen has nothing to show a customer, so it goes here. Empty = the WooCommerce account page, else the front page.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ms-vas-recent">People in the toolbar pulldown</label></th>
                    <td><input type="number" id="ms-vas-recent" min="1" max="10" class="small-text" <?php echo $f( 'recent' ); ?> value="<?php echo esc_attr( $s['recent'] ); ?>">
                        <p class="description">The ones you looked at last, topped up with the newest users. "Other..." opens the Users screen.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ms-vas-left">Toolbar button sits left of</label></th>
                    <td><input type="text" id="ms-vas-left" class="regular-text code" <?php echo $f( 'place_left_of' ); ?> value="<?php echo esc_attr( $s['place_left_of'] ); ?>">
                        <p class="description">The id of a right-hand toolbar item (the part after <code>wp-admin-bar-</code>), e.g. a cache plugin's Purge button. Empty = the button's default place in the right-hand group.</p></td>
                </tr>
                <tr>
                    <th scope="row">Users screen</th>
                    <td><label><input type="checkbox" <?php echo $f( 'users_column' ); ?> value="1" <?php checked( ! empty( $s['users_column'] ) ); ?>> A View As button on every row</label></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
