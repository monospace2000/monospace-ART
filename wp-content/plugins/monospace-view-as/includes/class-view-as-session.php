<?php
/**
 * View As: WooCommerce's session for the throw-away cart. Stored under its own key (viewas_<admin>_<customer>) so the customer's
 * real session and cart are never read or written; no cookie is set; deleted when the admin switches back (view-as.php).
 *
 * @package monospace-view-as
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MS_View_As_Session extends WC_Session_Handler {

    public function init() {
        $state = ms_view_as_active();
        $this->_customer_id        = ms_view_as_session_key( $state['admin'], $state['target'] );
        $this->_session_expiration = time() + ms_view_as_hours() * HOUR_IN_SECONDS;
        $this->_data               = (array) $this->get_session( $this->_customer_id, array() );
        add_action( 'shutdown', array( $this, 'save_data' ), 20 );
    }

    public function has_session() {
        return true;
    }

    // No cookie: the session is found by who is looking, not by a cookie in the browser
    public function maybe_set_customer_session_cookie() {}
    public function set_customer_session_cookie( $set ) {}
    public function destroy_session_if_empty() {}

    public function forget_session() {
        $this->_data  = array();
        $this->_dirty = false;
    }

    public function destroy_session() {
        $this->delete_session( $this->get_customer_id() );
        $this->forget_session();
    }
}
