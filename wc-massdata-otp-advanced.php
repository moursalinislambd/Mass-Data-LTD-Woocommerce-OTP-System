<?php
/**
 * Plugin Name:     MassData OTP Pro
 * Plugin URI:      https://onexusdev.xyz/
 * Description:     Enterprise-grade SMS OTP verification for WooCommerce registration. Secure phone number validation with MassData SMS gateway integration, real-time verification, and full admin control panel.
 * Version:         4.0.0
 * Requires at least: 5.8
 * Requires PHP:    8.0
 * Author:          Moursalin Islam
 * Author URI:      https://onexusdev.xyz
 * Developer:       Moursalin Islam
 * Developer URI:   https://onexusdev.xyz
 * Text Domain:     massdata-otp-pro
 * Domain Path:     /languages
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * WC requires at least: 6.0
 * WC tested up to:      8.5
 * Requires Plugins: woocommerce
 *
 * @package         MassData_OTP_Pro
 * @author          Moursalin Islam
 * @copyright       2026 OnexusDev
 * @license         GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

class MassData_OTP_Pro {
    
    private $option_name = 'massdata_pro_settings';
    private $api_url = 'https://smsmassdata.massdata.xyz/api/sms/send';
    private $footer_hash = 'onexusdev_2024_secure_hash';
    private $developer_email = 'morsalinislam.net@gmail.com';
    private $developer_site = 'onexusdev.xyz';
    
    public function __construct() {
        // Check footer integrity first
        add_action('init', array($this, 'check_footer_integrity'), 1);
        
        // Admin
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_test_massdata_pro', array($this, 'test_api'));
        
        // Frontend
        add_action('woocommerce_register_form', array($this, 'add_otp_field'));
        add_action('wp_footer', array($this, 'add_scripts'));
        add_action('wp_footer', array($this, 'add_footer_credit'), 9999);
        
        // AJAX
        add_action('wp_ajax_nopriv_pro_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_pro_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_nopriv_pro_verify_otp', array($this, 'verify_otp'));
        add_action('wp_ajax_pro_verify_otp', array($this, 'verify_otp'));
        
        // Validation
        add_action('woocommerce_register_post', array($this, 'validate_otp'), 10, 3);
        add_action('woocommerce_created_customer', array($this, 'save_phone'), 10, 3);
        
        // Admin footer
        add_action('admin_footer_text', array($this, 'add_admin_footer_credit'));
    }
    
    // ========== FOOTER INTEGRITY CHECK ==========
    public function check_footer_integrity() {
        $stored_hash = get_option('massdata_footer_hash');
        $current_hash = $this->generate_footer_hash();
        
        if (empty($stored_hash)) {
            update_option('massdata_footer_hash', $current_hash);
        } elseif ($stored_hash !== $current_hash) {
            // Footer has been modified - disable plugin functionality
            add_filter('pre_option_' . $this->option_name, function($value) {
                if (is_array($value)) {
                    $value['enabled'] = 0;
                }
                return $value;
            });
            
            // Log the tampering attempt
            $this->log_tampering_attempt();
        }
    }
    
    private function generate_footer_hash() {
        $footer_content = $this->get_expected_footer();
        return wp_hash($footer_content . $this->developer_email . $this->developer_site);
    }
    
    private function get_expected_footer() {
        return "This Plugin Developed By Moursalin Islam (morsalinislam.net@gmail.com)\nThanks From\nOnexusDev\n<Code The Future, Live The Dream/>\nonexusdev.xyz\nonexusdev@gmail.com\nfacebook.com/onexusdev";
    }
    
    private function log_tampering_attempt() {
        $log_entry = date('Y-m-d H:i:s') . " - Footer tampering detected from IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
        $log_file = WP_CONTENT_DIR . '/massdata-security.log';
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    // ========== FOOTER CREDIT ==========
    public function add_footer_credit() {
        // Only show on frontend
        if (is_admin()) return;
        
        $settings = get_option($this->option_name);
        if (empty($settings['enabled'])) return;
        
        // Generate a unique signature
        $signature = wp_hash($this->footer_hash . date('Ymd'));
        ?>
        <!-- MassData OTP Pro - Protected Footer -->
        <div id="massdata-credit" data-signature="<?php echo esc_attr($signature); ?>" style="text-align: center; padding: 10px; background: #f9f9f9; border-top: 1px solid #eee; font-size: 12px; color: #666; margin-top: 20px;">
            <div style="max-width: 600px; margin: 0 auto;">
                <p style="margin: 5px 0;">
                    This Plugin Developed By <strong>Moursalin Islam</strong> (<a href="mailto:morsalinislam.net@gmail.com" style="color: #0073aa; text-decoration: none;">morsalinislam.net@gmail.com</a>)
                </p>
                <p style="margin: 5px 0;">Thanks From <strong>OnexusDev</strong></p>
                <p style="margin: 5px 0; font-family: monospace;">&lt;Code The Future, Live The Dream/&gt;</p>
                <p style="margin: 5px 0;">
                    <a href="https://onexusdev.xyz" target="_blank" style="color: #0073aa; text-decoration: none;">onexusdev.xyz</a> | 
                    <a href="mailto:onexusdev@gmail.com" style="color: #0073aa; text-decoration: none;">onexusdev@gmail.com</a> | 
                    <a href="https://facebook.com/onexusdev" target="_blank" style="color: #0073aa; text-decoration: none;">facebook.com/onexusdev</a>
                </p>
            </div>
        </div>
        
        <style>
            #massdata-credit a:hover {
                text-decoration: underline !important;
            }
        </style>
        
        <script>
        (function($) {
            // Self-healing footer
            var footerCheck = setInterval(function() {
                if ($('#massdata-credit').length === 0) {
                    // Footer removed - disable plugin
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'check_footer_tampering',
                        nonce: '<?php echo wp_create_nonce('footer_check'); ?>',
                        status: 'removed'
                    });
                    
                    // Disable OTP functionality
                    $('#pro_send, #pro_verify').prop('disabled', true);
                    alert('Plugin integrity check failed. Please contact support.');
                }
            }, 5000);
        })(jQuery);
        </script>
        <?php
    }
    
    public function add_admin_footer_credit($footer_text) {
        $footer_text .= '<br><span style="color: #666; font-size: 11px;">MassData OTP Pro by <a href="https://onexusdev.xyz" target="_blank">OnexusDev</a> | Developed by Moursalin Islam</span>';
        return $footer_text;
    }
    
    // ========== ADMIN PANEL ==========
    public function admin_menu() {
        add_menu_page(
            'MassData OTP Pro',
            'MassData OTP Pro',
            'manage_options',
            'massdata-pro',
            array($this, 'admin_page'),
            'dashicons-phone',
            56
        );
    }
    
    public function register_settings() {
        register_setting('massdata_pro_settings', $this->option_name);
        
        // Add security setting
        add_option('massdata_footer_hash', $this->generate_footer_hash());
    }
    
    public function admin_page() {
        // Check footer integrity for admin too
        $this->check_footer_integrity();
        
        $settings = get_option($this->option_name, [
            'api_key' => '01762666963.fdee9fbb-d586-414f-80c5-613460dd1c2e',
            'sender_id' => '8809617613279',
            'company_name' => 'Your Company',
            'enabled' => 1
        ]);
        
        // Check if plugin is disabled due to footer tampering
        $stored_hash = get_option('massdata_footer_hash');
        $current_hash = $this->generate_footer_hash();
        $is_compromised = ($stored_hash !== $current_hash);
        ?>
        <div class="wrap">
            <h1>📱 MassData OTP Pro Settings</h1>
            
            <?php if ($is_compromised): ?>
            <div class="notice notice-error">
                <p><strong>⚠️ SECURITY ALERT:</strong> Plugin footer has been modified. Plugin functionality has been disabled. Please restore the original footer credit to enable the plugin.</p>
            </div>
            <?php endif; ?>
            
            <div style="background: #fff; padding: 20px; max-width: 600px; border-radius: 5px; margin-top: 20px;">
                <form method="post" action="options.php">
                    <?php settings_fields('massdata_pro_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[enabled]" value="1" <?php checked(1, $settings['enabled']); ?> <?php echo $is_compromised ? 'disabled' : ''; ?>>
                                    Enable OTP Verification
                                </label>
                                <?php if ($is_compromised): ?>
                                <p class="description" style="color: #e74c3c;">Disabled due to footer modification</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Company Name</th>
                            <td>
                                <input type="text" 
                                       name="<?php echo $this->option_name; ?>[company_name]" 
                                       value="<?php echo esc_attr($settings['company_name']); ?>" 
                                       class="regular-text"
                                       placeholder="Your Company Name"
                                       style="width: 100%;"
                                       <?php echo $is_compromised ? 'disabled' : ''; ?>>
                                <p class="description">This name will appear in SMS</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input type="text" 
                                       name="<?php echo $this->option_name; ?>[api_key]" 
                                       value="<?php echo esc_attr($settings['api_key']); ?>" 
                                       class="regular-text"
                                       placeholder="MassData API Key"
                                       style="width: 100%;"
                                       <?php echo $is_compromised ? 'disabled' : ''; ?>>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Sender ID</th>
                            <td>
                                <input type="text" 
                                       name="<?php echo $this->option_name; ?>[sender_id]" 
                                       value="<?php echo esc_attr($settings['sender_id']); ?>" 
                                       class="regular-text"
                                       placeholder="Sender ID"
                                       style="width: 100%;"
                                       <?php echo $is_compromised ? 'disabled' : ''; ?>>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Test Connection</th>
                            <td>
                                <button type="button" id="test_api_btn" class="button button-primary" <?php echo $is_compromised ? 'disabled' : ''; ?>>🔌 Test API Connection</button>
                                <span id="test_result" style="margin-left: 10px; font-weight: bold;"></span>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!$is_compromised): ?>
                        <?php submit_button('Save Settings'); ?>
                    <?php endif; ?>
                </form>
                
                <div style="margin-top: 30px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
                    <h3>📱 SMS Format Preview</h3>
                    <p style="background: #fff; padding: 15px; border: 1px solid #ddd; font-family: monospace;">
                        <strong><?php echo esc_html($settings['company_name']); ?></strong><br>
                        Your OTP Is: 123456<br>
                        Valid 2 Minutes<br>
                        Thanks From<br>
                        <strong><?php echo esc_html($settings['company_name']); ?></strong>
                    </p>
                </div>
                
                <!-- Developer Information -->
                <div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #46b450;">
                    <h3>👨‍💻 Developer Information</h3>
                    <p><strong>Moursalin Islam</strong><br>
                    Email: <a href="mailto:morsalinislam.net@gmail.com">morsalinislam.net@gmail.com</a></p>
                    <p><strong>OnexusDev</strong><br>
                    Website: <a href="https://onexusdev.xyz" target="_blank">onexusdev.xyz</a><br>
                    Email: <a href="mailto:onexusdev@gmail.com">onexusdev@gmail.com</a><br>
                    Facebook: <a href="https://facebook.com/onexusdev" target="_blank">facebook.com/onexusdev</a></p>
                    <p style="font-family: monospace; color: #666;">&lt;Code The Future, Live The Dream/&gt;</p>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test_api_btn').click(function() {
                var key = $('input[name*="api_key"]').val();
                if(!key) {
                    alert('Please enter API Key first');
                    return;
                }
                
                $('#test_result').html('<span style="color: #3498db;">Testing...</span>');
                
                $.post(ajaxurl, {
                    action: 'test_massdata_pro',
                    key: key,
                    nonce: '<?php echo wp_create_nonce('test_pro'); ?>'
                }, function(res) {
                    if(res.success) {
                        $('#test_result').html('<span style="color: #27ae60;">✅ Connection Successful! Balance: ৳' + res.data + '</span>');
                    } else {
                        $('#test_result').html('<span style="color: #e74c3c;">❌ ' + res.data + '</span>');
                    }
                }).fail(function() {
                    $('#test_result').html('<span style="color: #e74c3c;">❌ Connection Failed</span>');
                });
            });
        });
        </script>
        <?php
    }
    
    public function test_api() {
        check_ajax_referer('test_pro', 'nonce');
        $key = sanitize_text_field($_POST['key']);
        
        $response = wp_remote_get("https://smsmassdata.massdata.xyz/api/sms/balance?apiKey=" . urlencode($key), [
            'timeout' => 15,
            'sslverify' => false
        ]);
        
        if(is_wp_error($response)) {
            wp_send_json_error('Connection failed');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if(isset($data['balance'])) {
            wp_send_json_success($data['balance']);
        } else {
            wp_send_json_error('Invalid API Key');
        }
    }
    
    // ========== FRONTEND FORM ==========
    public function add_otp_field() {
        $settings = get_option($this->option_name);
        if(empty($settings['enabled']) || empty($settings['api_key'])) return;
        
        // Check footer integrity before showing form
        $stored_hash = get_option('massdata_footer_hash');
        $current_hash = $this->generate_footer_hash();
        if ($stored_hash !== $current_hash) {
            echo '<div class="woocommerce-error">Plugin integrity check failed. Please contact support.</div>';
            return;
        }
        ?>
        <div style="margin:15px 0; padding:15px; background:#f9f9f9; border:1px solid #eee; border-radius:4px;">
            <p><strong>📱 Phone Verification (Required)</strong></p>
            
            <input type="tel" id="pro_phone" placeholder="01XXXXXXXXX" style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ddd; border-radius:3px;">
            
            <div style="margin:10px 0;">
                <button type="button" id="pro_send" style="padding:8px 15px; background:#333; color:#fff; border:none; border-radius:3px; cursor:pointer;">📨 Send OTP</button>
                <span id="pro_send_status" style="margin-left:8px;"></span>
            </div>
            
            <div id="pro_verify_box" style="display:none; margin-top:10px;">
                <input type="text" id="pro_otp" placeholder="Enter 6 digit OTP" style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ddd; border-radius:3px;">
                <div>
                    <button type="button" id="pro_verify" style="padding:8px 15px; background:#333; color:#fff; border:none; border-radius:3px; cursor:pointer;">✓ Verify OTP</button>
                    <span id="pro_verify_status" style="margin-left:8px;"></span>
                </div>
            </div>
            
            <input type="hidden" id="pro_verified" name="pro_verified" value="no">
        </div>
        <?php
    }
    
    // ========== JAVASCRIPT ==========
    public function add_scripts() {
        if(!is_account_page()) return;
        
        $settings = get_option($this->option_name);
        if(empty($settings['enabled'])) return;
        
        // Add footer tampering check AJAX handler
        add_action('wp_ajax_check_footer_tampering', array($this, 'handle_footer_tampering'));
        add_action('wp_ajax_nopriv_check_footer_tampering', array($this, 'handle_footer_tampering'));
        ?>
        <script>
        jQuery(function($){
            $('#pro_send').click(function(){
                var phone = $('#pro_phone').val().trim();
                if(!phone.match(/^01[3-9]\d{8}$/)) {
                    alert('Please enter valid Bangladeshi phone number');
                    return;
                }
                
                $('#pro_send').text('Sending...').prop('disabled', true);
                $('#pro_send_status').text('⏳ Sending OTP...').css('color', '#3498db');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'pro_send_otp',
                    phone: phone,
                    nonce: '<?php echo wp_create_nonce('pro_nonce'); ?>'
                }, function(res){
                    if(res.success) {
                        $('#pro_send_status').html('<span style="color:#27ae60;">✅ OTP Sent Successfully</span>');
                        $('#pro_verify_box').show();
                        $('#pro_otp').focus();
                    } else {
                        $('#pro_send_status').html('<span style="color:#e74c3c;">❌ ' + res.data + '</span>');
                    }
                }).fail(function(){
                    $('#pro_send_status').html('<span style="color:#e74c3c;">❌ Network Error</span>');
                }).always(function(){
                    $('#pro_send').text('📨 Send OTP').prop('disabled', false);
                });
            });
            
            $('#pro_verify').click(function(){
                var phone = $('#pro_phone').val().trim();
                var otp = $('#pro_otp').val().trim();
                
                if(!otp || otp.length != 6) {
                    alert('Please enter 6 digit OTP');
                    return;
                }
                
                $('#pro_verify').text('Verifying...').prop('disabled', true);
                $('#pro_verify_status').text('⏳ Verifying...').css('color', '#3498db');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'pro_verify_otp',
                    phone: phone,
                    otp: otp,
                    nonce: '<?php echo wp_create_nonce('pro_nonce'); ?>'
                }, function(res){
                    if(res.success) {
                        $('#pro_verify_status').html('<span style="color:#27ae60;">✅ Phone Verified Successfully!</span>');
                        $('#pro_verified').val('yes');
                        $('#pro_verify').hide();
                        $('#pro_otp').prop('disabled', true);
                        $('#pro_phone').prop('readonly', true);
                    } else {
                        $('#pro_verify_status').html('<span style="color:#e74c3c;">❌ ' + res.data + '</span>');
                    }
                }).fail(function(){
                    $('#pro_verify_status').html('<span style="color:#e74c3c;">❌ Server Error</span>');
                }).always(function(){
                    $('#pro_verify').text('✓ Verify OTP').prop('disabled', false);
                });
            });
            
            $('form.register').submit(function(e){
                if($('#pro_verified').val() != 'yes') {
                    e.preventDefault();
                    alert('Please verify your phone number first');
                    $('#pro_verify_box').show();
                }
            });
        });
        </script>
        <?php
    }
    
    public function handle_footer_tampering() {
        check_ajax_referer('footer_check', 'nonce');
        
        // Log the tampering
        $this->log_tampering_attempt();
        
        // Disable the plugin
        $settings = get_option($this->option_name);
        $settings['enabled'] = 0;
        update_option($this->option_name, $settings);
        
        wp_send_json_success('Plugin disabled due to footer modification');
    }
    
    // ========== OTP SENDING WITH CUSTOM MESSAGE ==========
    public function send_otp() {
        check_ajax_referer('pro_nonce', 'nonce');
        
        // Check footer integrity
        $stored_hash = get_option('massdata_footer_hash');
        $current_hash = $this->generate_footer_hash();
        if ($stored_hash !== $current_hash) {
            wp_send_json_error('Plugin integrity check failed');
        }
        
        $settings = get_option($this->option_name);
        $phone = sanitize_text_field($_POST['phone']);
        
        if(!preg_match('/^01[3-9]\d{8}$/', $phone)) {
            wp_send_json_error('Invalid phone number');
        }
        
        $otp = rand(100000, 999999);
        $company = !empty($settings['company_name']) ? $settings['company_name'] : 'Your Company';
        
        // Format: Company Name + OTP + Validity + Thanks
        $message = "$company\nYour OTP Is: $otp\nValid 2 Minutes\nThanks From\n$company";
        
        // Format phone for API
        $api_phone = '880' . substr($phone, 1);
        
        // Build API URL
        $url = $this->api_url . '?' . http_build_query([
            'apiKey' => $settings['api_key'],
            'contactNumbers' => $api_phone,
            'senderId' => $settings['sender_id'],
            'textBody' => $message,
            'type' => 'text',
            'label' => 'transactional'
        ]);
        
        // Send request
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => false
        ]);
        
        if(is_wp_error($response)) {
            wp_send_json_error('API Error: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check response
        if(isset($data['dlrRef'])) {
            set_transient('pro_otp_' . $api_phone, $otp, 300); // 5 minutes
            wp_send_json_success('OTP Sent');
        } else {
            wp_send_json_error('SMS Failed');
        }
    }
    
    // ========== VERIFY OTP ==========
    public function verify_otp() {
        check_ajax_referer('pro_nonce', 'nonce');
        
        // Check footer integrity
        $stored_hash = get_option('massdata_footer_hash');
        $current_hash = $this->generate_footer_hash();
        if ($stored_hash !== $current_hash) {
            wp_send_json_error('Plugin integrity check failed');
        }
        
        $phone = '880' . substr(sanitize_text_field($_POST['phone']), 1);
        $otp_input = sanitize_text_field($_POST['otp']);
        
        $stored = get_transient('pro_otp_' . $phone);
        
        if(!$stored) {
            wp_send_json_error('OTP Expired');
        }
        
        if($stored == $otp_input) {
            set_transient('pro_verified_' . $phone, 'yes', 600); // 10 minutes
            wp_send_json_success('Verified');
        } else {
            wp_send_json_error('Wrong OTP');
        }
    }
    
    // ========== REGISTRATION VALIDATION ==========
    public function validate_otp($username, $email, $errors) {
        $settings = get_option($this->option_name);
        if(empty($settings['enabled'])) return;
        
        // Check footer integrity
        $stored_hash = get_option('massdata_footer_hash');
        $current_hash = $this->generate_footer_hash();
        if ($stored_hash !== $current_hash) {
            $errors->add('integrity_error', 'Plugin integrity check failed. Please contact support.');
            return;
        }
        
        if(empty($_POST['pro_verified']) || $_POST['pro_verified'] != 'yes') {
            $errors->add('otp_error', 'Phone verification is required');
        }
    }
    
    // ========== SAVE PHONE NUMBER ==========
    public function save_phone($customer_id, $new_customer_data, $password_generated) {
        if(isset($_POST['pro_phone'])) {
            update_user_meta($customer_id, 'billing_phone', sanitize_text_field($_POST['pro_phone']));
        }
    }
}

// Initialize plugin
new MassData_OTP_Pro();
?>