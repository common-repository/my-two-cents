<?php
/**
 * Plugin Name: My Two Cents
 * Plugin URI: http://maymay.net/blog/projects/my-two-cents/
 * Description: Receive BitCoin from your commenters. Auto-approve comments that include a BitCoin donation.
 * Version: 0.2
 * Author: Meitar Moscovitz <meitar@maymay.net>
 * Author URI: http://maymay.net/
 * Text Domain: my-two-cents
 * Domain Path: /languages
 */

require_once 'lib/bitcoin-php/src/bitcoin.inc';

class My_Two_Cents {

    private $prefix = 'my_two_cents_'; //< String to prefix plugin options, settings, etc.
    private $btc_api = 'blockexplorer.com'; //< BitCoin blockchain API query service provider

    public function __construct () {
        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('init', array($this, 'scheduleProcessComments'));

        add_action('parse_request', array($this, 'parseRequest'));

        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));

        add_action('comment_post', array($this, 'saveComment'), 10, 2);

        add_action($this->prefix . 'process', array($this, 'processComments'));

        $options = get_option($this->prefix . 'settings');
        if (!empty($options['use_txs_polling'])) {
            add_filter('comment_form_default_fields', array($this, 'addCommentFormFields'));
        }
        add_filter('comments_array', array($this, 'showBitCoinAddress'), 10, 2);

        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        if (empty($options['receive_addresses'])) {
            add_action('admin_notices', array($this, 'showMissingConfigNotice'));
        }
    }

    public function scheduleProcessComments () {
        $options = get_option($this->prefix . 'settings');
        if (!wp_get_schedule($this->prefix . 'process') && 1 === $options['use_txs_polling']) {
            wp_schedule_event(time(), 'hourly', $this->prefix . 'process');
        }
    }

    public function deactivate () {
        wp_clear_scheduled_hook($this->prefix . 'process');
    }

    public function registerL10n () {
        load_plugin_textdomain('my-two-cents', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function showMissingConfigNotice () {
        $screen = get_current_screen();
        if ($screen->base === 'plugins') {
?>
<div class="updated">
    <p><a href="<?php print admin_url('options-general.php?page=' . $this->prefix . 'settings');?>" class="button"><?php esc_html_e('Configure BitCoin addresses', 'my-two-cents');?></a> &mdash; <?php esc_html_e('Almost done! Tell My Two Cents about your BitCoin address to start receiving BitCoin from commenters.', 'my-two-cents');?></p>
</div>
<?php
        }
    }

    private function showDonationAppeal () {
?>
<div class="donation-appeal">
    <p style="text-align: center; font-size: larger; margin: 1em auto;"><?php print sprintf(
esc_html__('My Two Cents is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'my-two-cents'),
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=meitarm%40gmail%2ecom&lc=US&amp;item_name=BitCoin%20Comments%20WordPress%20Plugin&amp;item_number=bitcoin%2dcomments&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted">' . esc_html__('making a donation', 'my-two-cents') . '</a>',
'<a href="http://Cyberbusking.org/">' . esc_html__('houseless, jobless, nomadic developer', 'my-two-cents') . '</a>'
);?></p>
</div>
<?php
    }

    private function captureDebugOf ($var) {
        ob_start();
        var_dump($var);
        $str = ob_get_contents();
        ob_end_clean();
        return $str;
    }

    private function maybeCaptureDebugOf ($var) {
        $msg = '';
        $options = get_option($this->prefix . 'settings');
        if (isset($options['debug'])) {
            $msg .= esc_html__('Debug output:', 'my-two-cents');
            $msg .= '<pre>' . $this->captureDebugOf($var) . '</pre>';
        }
        return $msg;
    }

    /**
     * Detect API callbacks.
     */
    public function parseRequest (&$wp) {
        if (!array_key_exists('action', $_GET) || $this->prefix . 'process_comment' !== $_GET['action']) { return; }
        if (!array_key_exists('secret', $_GET) || $this->getSecret() !== $_GET['secret']) { return; }

        $data = array();
        foreach ($_GET as $k => $v) {
            switch ($k) {
                case 'destination_address':
                    if (!in_array($v, $this->getReceiveAddresses())) {
                        error_log(sprintf(
                            __('Unknown BTC destination address: %s', 'my-two-cents'),
                            $v
                        ));
                    }
                    // fall through
                case 'input_address':
                    if (!Bitcoin::checkAddress($v)) {
                        error_log(sprintf(
                            __('Invalid BitCoin address: %s', 'my-two-cents'),
                            $v
                        ));
                    } else {
                        $data[$k] = $v;
                    }
                    break;
                case 'value':
                case 'secret':
                case 'transaction_hash':
                case 'input_transaction_hash':
                    $data[$k] = $v;
                    break;
                case 'comment_id':
                    $comment = get_comment($v);
                    if ($comment) {
                        $data[$k] = $v;
                    } else {
                        error_log(sprintf(
                            __('Unknown comment ID: %s', 'my-two-cents'),
                            $v
                        ));
                    }
                    // fall through
                case 'confirmations':
                    $data[$k] = intval($v);
                    break;
                case 'test':
                    // Stop here if this isn't actually a production callback.
                    return;
            }
        }

        // TODO: Include various options?
        //       For instance, provide settings for customizing:
        //       * minimum BTC value
        $options = get_option($this->prefix . 'settings');
        if (!empty($options['min_confirms'])) {
            if ($data['confirmations'] < $options['min_confirms']) {
                exit;
            }
        }

        if (!empty($data['comment_id']) && !empty($data['input_transaction_hash'])) {
            $this->approveComments(array(
                $data['comment_id'] => $data['input_transaction_hash']
            ));
            print '*ok*'; // Respond to BlockChain.info
        }
        exit; // stop all WordPress activity
    }

    private function addAdminNotices ($msgs) {
        if (is_string($msgs)) { $msgs = array($msgs); }
        $notices = get_option('_' . $this->prefix . 'admin_notices');
        if (empty($notices)) {
            $notices = array();
        }
        $notices = array_merge($notices, $msgs);
        update_option('_' . $this->prefix . 'admin_notices', $notices);
    }

    private function showAdminNotices () {
        $notices = get_option('_' . $this->prefix . 'admin_notices');
        if ($notices) {
            foreach ($notices as $msg) {
                $this->showNotice($msg);
            }
            delete_option('_' . $this->prefix . 'admin_notices');
        }
    }

    public function addCommentFormFields ($fields) {
        $html = '<p class="comment-form-bitcoin-address">';
        $html .= '<label for="bitcoin-address">' . esc_html__('BitCoin address', 'my-two-cents');
        // TODO: Required?
        $html .= '</label>';
        $html .= '<input id="bitcoin-address" name="bitcoin-address" placeholder="' . esc_attr__('your BitCoin address', 'my-two-cents') . '" />';
        $html .= '</p>';
        $html .= '<p class="comment-notes">' . esc_html__('To skip the moderation queue, enter the BitCoin address from which you will send BTC', 'my-two-cents') . '</p>';
        $fields['bitcoin_address'] = $html;
        return $fields;
    }

    public function showBitCoinAddress ($comments, $post_id) {
        $options = get_option($this->prefix . 'settings');
        foreach ($comments as $comment) {
            if (0 == $comment->comment_approved) {
                $address = get_comment_meta($comment->comment_ID, $this->prefix . 'receive_address', true);
                if (empty($address)) {
                    $addresses = $this->getReceiveAddresses();
                    // If the commenter already provided their own BitCoin address
                    if (get_comment_meta($comment->comment_ID, $this->prefix . 'input_address', true)) {
                        shuffle($addresses);
                        $address = array_pop($addresses);
                    } else {
                        $x = $this->generateReceivingAddress(
                            array_pop($addresses), $this->getCallbackUrl($comment->comment_ID)
                        );
                        $address = $x->input_address;
                    }
                    update_comment_meta($comment->comment_ID, $this->prefix . 'receive_address', $address);
                }

                $prepend = '<p>';
                $prepend .= sprintf(
                    esc_html__('To have your comment automatically approved, send BitCoin in the amount of your choosing to %s', 'my-two-cents'),
                    '<a href="bitcoin:' . $address . '" class="bitcoin-address">' . $address . '</a>'
                );
                $prepend .= '</p>';
                $prepend . '<p>';
                $prepend .= '<a href="bitcoin:' . $address . '">';
                $prepend .= '<img src="https://api.qrserver.com/v1/create-qr-code/?size=' . urlencode($options['qr_code_img_size']) . 'x' . urlencode($options['qr_code_img_size']) . '&amp;data=' . urlencode('bitcoin:' . $address) . '"';
                $prepend .= ' alt="' . esc_attr__('QR code to send BitCoins', 'my-two-cents') . '" />';
                $prepend .= '</a>';
                $prepend .= '</p>';
                $comment->comment_content = '<div class="my-two-cents comment-awaiting-moderation">' . $prepend . '</div>' . $comment->comment_content;
            }
        }
        return $comments;
    }

    private function getCallbackUrl ($comment_id) {
        return get_site_url()
            . '?action=' . $this->prefix . 'process_comment'
            . '&comment_id=' . $comment_id
            . '&secret=' . $this->getSecret();
    }

    /**
     * Calls the BlockChain.info Receive Payments API to generate a new BTC receieve address.
     *
     * @param string $forwarding_address The user's final BTC address destination.
     * @param string $callback_url A fully-qualified URL callback for payment receipt notification.
     * @return object A decoded JSON response from BlockChain.info or a WP_Error object on error.
     */
    private function generateReceivingAddress ($forwarding_address, $callback_url) {
        $api_url = 'https://blockchain.info/api/receive';
        $api_url .= '?method=create&address=' . $forwarding_address . '&callback=' . urlencode($callback_url);

        $resp = wp_remote_get($api_url);
        if (is_wp_error($resp)) {
            error_log(sprintf(
                __('Error generating new receive address.', 'my-two-cents')
            ));
            $data = false;
        } else {
            $data = json_decode($resp['body']);
        }
        return $data;
    }

    public function saveComment ($comment_ID, $comment_status) {
        if (Bitcoin::checkAddress($_POST['bitcoin-address'])) {
            update_comment_meta($comment_ID, $this->prefix . 'input_address', sanitize_text_field($_POST['bitcoin-address']));
        }
    }

    private function generateSecret () {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $secret = bin2hex(openssl_random_pseudo_bytes(mt_rand(20,20)));
        } else {
            $secret = uniqid('', true);
        }
        $options = get_option($this->prefix . 'settings');
        $options['secret'] = $secret;
        update_option($this->prefix . 'settings', $options);
        return $secret;
    }

    private function getSecret () {
        $options = get_option($this->prefix . 'settings');
        if (empty($options['secret'])) {
            $options['secret'] = $this->generateSecret();
        }
        return $options['secret'];
    }

    private function getReceiveAddresses () {
        $options = get_option($this->prefix . 'settings');
        if (false === strpos($options['receive_addresses'], "\n")) {
            $addresses = array($options['receive_addresses']);
        } else {
            $addresses = explode("\n", $options['receive_addresses']);
        }
        return $addresses;
    }

    public function processComments () {
        // Get a list of all pending (non-spam) comments with BitCoin addresses
        $comments = get_comments(array(
            'status' => 'hold',
            'meta_key' => $this->prefix . 'input_address'
        ));
        if (empty($comments)) { return; }

        $addresses = $this->getReceiveAddresses();

        if (1 === count($addresses) && empty($addresses[0])) {
            error_log(sprintf(
                __('%s: No receive addresses, skipping scheduled invocation to process comments.', 'my-two-cents'),
                __('My Two Cents', 'my-two-cents')
            ));
            return;
        }

        // Obtain transaction logs for our receive addresses in the BitCoin ledger
        $btc_txs = array();
        foreach ($addresses as $address) {
            if (empty($address)) {
                error_log(sprintf(
                    __('%s: Empty receive address.', 'my-two-cents'),
                    __('My Two Cents', 'my-two-cents')
                ));
                continue;
            }
            $resp = wp_remote_get('https://' . $this->btc_api . '/q/mytransactions/' . $address);
            if (is_wp_error($resp)) {
                error_log(sprintf(
                    __('%s: Failed to retrieve transaction data from %s for address %s', 'my-two-cents'),
                    __('My Two Cents', 'my-two-cents'),
                    $this->btc_api,
                    $address
                ));
                error_log($resp->get_error_message());
                continue;
            }
            if ('ERROR: ' === substr($resp['body'], 0, 7)) {
                error_log(sprintf(
                    __('%s: Error from %s API endpoint when querying for address %s: %s', 'my-two-cents'),
                    __('My Two Cents', 'my-two-cents'),
                    $this->btc_api,
                    $address,
                    substr($resp['body'], 7)
                ));
                continue;
            }
            $btc_txs[$address] = json_decode($resp['body']);
        }

        // For each pending comment,
        $comments_to_approve = array();
        foreach ($comments as $comment) {
            $spender = $comment->meta_value;
            // search transactions log of each of our addresses.
            foreach ($btc_txs as $address => $txs_log) {
                // For each transaction logged,
                foreach ($txs_log as $tx_hash => $tx_data) {
                    $got_bitcoin = false;
                    $sent_change = false;
                    $the_tx_hash = $tx_hash;
                    // look for a transaction record whose transation output (TXO)
                    if ($tx_data->out) {
                        foreach ($tx_data->out as $tx_output) {
                            // has been sent to our address
                            if ($address === $tx_output->address) {
                                $got_bitcoin = true; // (this was a TX where we received output)
                            } else if ($spender === $tx_output->address) {
                                // and that has "change" (another TXO) returning to the commenter
                                $sent_change = true;
                            }
                        }
                    }
                    // If we got BTC in a TX that occurred after the comment and that included "change",
                    if (
                        $got_bitcoin
                        &&
                        $sent_change
                        &&
                        (strtotime($comment->comment_date_gmt) < strtotime($tx_data->time))
                    ) {
                        // mark the comment to approve, and the TX wherein we got BitCoin.
                        $comments_to_approve[$comment->comment_ID] = $the_tx_hash;
                    }
                }
            }
        }

        $this->approveComments($comments_to_approve);
    }

    /**
     * Approves comments and saves transaction hash for reference.
     *
     * @param array $comments Array of data to approve, like: comment ID => transaction hash
     * @return void
     */
    private function approveComments ($comments) {
        foreach ($comments as $id => $tx_hash) {
            wp_set_comment_status($id, 'approve');
            update_comment_meta($id, $this->prefix . 'tx_hash', $tx_hash);
            delete_comment_meta($id, $this->prefix . 'receive_address');
        }
    }

    public function registerSettings () {
        register_setting(
            $this->prefix . 'settings',
            $this->prefix . 'settings',
            array($this, 'validateSettings')
        );
    }

    /**
     * @param array $input An array of of our unsanitized options.
     * @return array An array of sanitized options.
     */
    public function validateSettings ($input) {
        $safe_input = array();
        foreach ($input as $k => $v) {
            switch ($k) {
                case 'receive_addresses':
                    if (empty($v)) {
                        $errmsg = __('Receive addresses cannot be empty.', 'my-two-cents');
                        add_settings_error($this->prefix . 'settings', 'empty-receive-addresses', $errmsg);
                    }
                    $addrs = array();
                    foreach (explode("\n", $v) as $line) {
                        $line = trim($line);
                        if (Bitcoin::checkAddress($line)) {
                            $addrs[] = $line;
                        } else {
                            error_log(sprintf(
                                __('Rejecting invalid BitCoin receive address: %s', 'my-two-cents'),
                                $line
                            ));
                        }
                    }
                    if (empty($addrs)) {
                        $errmsg = __('You must supply at least one valid BitCoin address.', 'my-two-cents');
                        add_settings_error($this->prefix . 'settings', 'no-valid-receive-addresses', $errmsg);
                    }
                    $safe_input[$k] = implode("\n", array_map('sanitize_text_field', $addrs));
                break;
                case 'secret':
                    $safe_input[$k] = sanitize_text_field($v);
                    break;
                case 'qr_code_img_size':
                case 'use_txs_polling':
                case 'min_confirms':
                case 'debug':
                    $safe_input[$k] = intval($v);
                break;
            }
        }
        return $safe_input;
    }

    public function registerAdminMenu () {
        add_options_page(
            __('My Two Cents Settings', 'my-two-cents'),
            __('My Two Cents', 'my-two-cents'),
            'manage_options',
            $this->prefix . 'settings',
            array($this, 'renderOptionsPage')
        );
    }

    /**
     * Writes the HTML for the options page, and each setting, as needed.
     */
    // TODO: Add contextual help menu to this page.
    public function renderOptionsPage () {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'my-two-cents'));
        }
        $options = get_option($this->prefix . 'settings');
?>
<h2><?php esc_html_e('My Two Cents Settings', 'my-two-cents');?></h2>
<form method="post" action="options.php">
<?php settings_fields($this->prefix . 'settings');?>
<?php if (!empty($options['secret'])) { ?>
<input type="hidden" value="<?php esc_attr_e($options['secret']);?>" name="<?php esc_attr_e($this->prefix);?>settings[secret]" />
<?php } ?>
<fieldset><legend><?php esc_html_e('BitCoin configuration', 'my-two-cents');?></legend>
<table class="form-table" summary="<?php esc_attr_e('Your BitCoin wallet configuration', 'my-two-cents');?>">
    <tbody>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>receive_addresses"><?php esc_html_e('Your BitCoin addresses:', 'my-two-cents');?></label>
            </th>
            <td>
                <textarea
                    id="<?php esc_attr_e($this->prefix);?>receive_addresses"
                    name="<?php esc_attr_e($this->prefix);?>settings[receive_addresses]"
                    style="width:75%;min-height:5em;"
                    placeholder="<?php esc_attr_e('one address per line', 'my-two-cents');?>"><?php
        if (isset($options['receive_addresses'])) {
            print esc_textarea($options['receive_addresses']);
        }
?></textarea>
                <p class="description"><?php esc_html_e('Enter your BitCoin address or addresses here. If you have more than one address, enter each one on its own line.', 'my-two-cents');?></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>qr_code_img_size">
                    <?php esc_html_e('QR code size:', 'my-two-cents');?>
                </label>
            </th>
            <td>
                <input id="<?php esc_attr_e($this->prefix);?>qr_code_img_size"
                    name="<?php esc_attr_e($this->prefix);?>settings[qr_code_img_size]"
                    value="<?php if (!isset($options['qr_code_img_size'])) { print '150'; } else { esc_attr_e($options['qr_code_img_size']); }?>"
                    placeholder="150"
                    size="3"
                />
                <p class="description"><label for="<?php esc_attr_e($this->prefix);?>qr_code_img_size"><?php
                print sprintf(
                    esc_html__('Size in pixels of the QR code image used in unapproved comments. This must be a square image, so this number will be used for both the width and the height of your QR code.', 'my-two-cents')
                );
                ?></label></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>min_confirms">
                    <?php esc_html_e('Minimum confirmations:', 'my-two-cents');?>
                </label>
            </th>
            <td>
                <input id="<?php esc_attr_e($this->prefix);?>min_confirms"
                    name="<?php esc_attr_e($this->prefix);?>settings[min_confirms]"
                    value="<?php if (!isset($options['min_confirms'])) { print '0'; } else { esc_attr_e($options['min_confirms']); }?>"
                    placeholder="1"
                    size="3"
                />
                <p class="description"><label for="<?php esc_attr_e($this->prefix);?>min_confirms"><?php
                print sprintf(
                    esc_html__('Number of network %1$sconfirmations%2$s required to consider a transaction valid. Increasing this number improves security against double-spending but takes more time.', 'my-two-cents'),
                    '<a href="https://en.bitcoin.it/wiki/Confirmation" title="' . esc_attr__('Learn more about BitCoin confirmations.', 'my-two-cents') . '">', '</a>'
                );
                ?></label></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>use_txs_polling">
                    <?php esc_html_e('Enable transaction polling method?', 'my-two-cents');?>
                </label>
            </th>
            <td>
                <input type="checkbox" <?php if (isset($options['use_txs_polling'])) : print 'checked="checked"'; endif; ?> value="1" id="<?php esc_attr_e($this->prefix);?>use_txs_polling" name="<?php esc_attr_e($this->prefix);?>settings[use_txs_polling]" />
                <label for="<?php esc_attr_e($this->prefix);?>use_txs_polling"><span class="description"><?php
        print sprintf(
            esc_html__('When enabled, this will schedule a once-per-hour poll of your BitCoin receive addresses to look for transactions that your commenters have pledged they will make when they posted their comment. It is arguably less secure than the default method because you will occasionally re-use one of your receive addresses multiple times but, unlike the default method that uses automatically generated forwarding addresses, there is no middle-man. Enabling this option also adds a field to your comment form asking your commenters for their BTC sending address. Even if you enable this option, commenters who do not supply a sending address with their comment will still be prompted to use the default automatically generated address method as a fallback.', 'my-two-cents')
        );
                ?></span></label>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>debug">
                    <?php esc_html_e('Enable detailed debugging information?', 'my-two-cents');?>
                </label>
            </th>
            <td>
                <input type="checkbox" <?php if (isset($options['debug'])) : print 'checked="checked"'; endif; ?> value="1" id="<?php esc_attr_e($this->prefix);?>debug" name="<?php esc_attr_e($this->prefix);?>settings[debug]" />
                <label for="<?php esc_attr_e($this->prefix);?>debug"><span class="description"><?php
        print sprintf(
            esc_html__('Turn this on only if you are experiencing problems using this plugin, or if you were told to do so by someone helping you fix a problem (or if you really know what you are doing). When enabled, extremely detailed technical information is displayed as a WordPress admin notice when you take certain actions. If you have also enabled WordPress\'s built-in debugging (%1$s) and debug log (%2$s) feature, additional information will be sent to a log file (%3$s). This file may contain sensitive information, so turn this off and erase the debug log file when you have resolved the issue.', 'my-two-cents'),
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG"><code>WP_DEBUG</code></a>',
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG_LOG"><code>WP_DEBUG_LOG</code></a>',
            '<code>' . content_url() . '/debug.log' . '</code>'
        );
                ?></span></label>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<?php submit_button();?>
</form>
<?php
        $this->showDonationAppeal();
?>
<p style="text-align:center;"><a href="bitcoin:1KYJnfoTDG1izoV89cH68GX6LTrDVz24bw"><img alt="" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data=bitcoin%3A1KYJnfoTDG1izoV89cH68GX6LTrDVz24bw"></a></p>
<?php
    } // end public function renderOptionsPage

}

$my_two_cents = new My_Two_Cents();
