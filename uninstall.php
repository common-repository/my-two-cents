<?php
/**
 * My Two Cents uninstaller
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

// Delete options.
delete_option('my_two_cents_settings');
delete_option('_my_two_cents_admin_notices');

/**
 * TODO: Should we really delete this meta?
 */
// delete_metadata('comment', null, 'my_two_cents_input_address', '', true);
// delete_metadata('comment', null, 'my_two_cents_tx_hash', '', true);
