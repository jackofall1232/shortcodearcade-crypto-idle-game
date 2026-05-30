<?php
/**
 * Global helper functions for Crypto Miner Tycoon Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get a brandable UI label string.
 * Returns the admin-saved value if set, otherwise the default.
 * Option key pattern: cmt_label_{key}
 *
 * @param string $key     The label key (e.g. 'per_click')
 * @param string $default Fallback string if no override is saved
 * @return string
 */
if ( ! function_exists( 'cmt_label' ) ) {
    function cmt_label( $key, $default ) {
        $saved = get_option( 'cmt_label_' . sanitize_key( $key ), '' );
        return ( '' !== $saved ) ? $saved : $default;
    }
}
