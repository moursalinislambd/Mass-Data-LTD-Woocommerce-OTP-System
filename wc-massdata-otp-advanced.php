<?php
/**
 * Plugin Name:     MassData OTP Pro
 * Plugin URI:      https://onexusdev.xyz/
 * Description:     Enterprise-grade SMS OTP verification for WooCommerce registration + Full Order SMS Notification System. Secure phone number validation with MassData SMS gateway integration, real-time verification, and full admin control panel.
 * Version:         5.0.0
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
 * @package         MassData_OTP_Pro
 * @author          Moursalin Islam
 * @copyright       2026 OnexusDev
 * @license         GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

class MassData_OTP_Pro {

    private $option_name      = 'massdata_pro_settings';
    private $sms_option_name  = 'massdata_order_sms_settings';
    private $api_url          = 'https://smsmassdata.massdata.xyz/api/sms/send';
    private $footer_hash      = 'onexusdev_2024_secure_hash';
    private $developer_email  = 'morsalinislam.net@gmail.com';
    private $developer_site   = 'onexusdev.xyz';

    /* ---------------------------------------------------------------
     *  WooCommerce order statuses with Bengali/English labels
     * ------------------------------------------------------------- */
    private $wc_statuses = [
        'pending'        => 'Pending Payment (অপেক্ষমাণ পেমেন্ট)',
        'processing'     => 'Processing (প্রসেসিং)',
        'on-hold'        => 'On Hold (হোল্ড)',
        'completed'      => 'Completed (সম্পন্ন)',
        'cancelled'      => 'Cancelled (বাতিল)',
        'refunded'       => 'Refunded (ফেরত)',
        'failed'         => 'Failed (ব্যর্থ)',
        'checkout-draft' => 'Draft (ড্রাফট)',
    ];

    /* ---------------------------------------------------------------
     *  Default SMS templates
     * ------------------------------------------------------------- */
    private $default_templates = [
        'pending'    => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} পেমেন্টের জন্য অপেক্ষমাণ।\nমোট: {order_total}\n{shop_name}",
        'processing' => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} প্রসেস হচ্ছে।\nমোট: {order_total}\nধন্যবাদ, {shop_name}",
        'on-hold'    => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} হোল্ডে আছে।\nবিস্তারিত জানতে যোগাযোগ করুন।\n{shop_name}",
        'completed'  => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} সফলভাবে ডেলিভারি হয়েছে।\nআমাদের সাথে থাকুন।\n{shop_name}",
        'cancelled'  => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} বাতিল করা হয়েছে।\nসমস্যার জন্য দুঃখিত।\n{shop_name}",
        'refunded'   => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} এর পেমেন্ট ফেরত দেওয়া হয়েছে।\nধন্যবাদ, {shop_name}",
        'failed'     => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} এর পেমেন্ট ব্যর্থ হয়েছে।\nপুনরায় চেষ্টা করুন।\n{shop_name}",
    ];

    public function __construct() {
        add_action('init', [$this, 'check_footer_integrity'], 1);

        // Admin
        add_action('admin_menu',  [$this, 'admin_menu']);
        add_action('admin_init',  [$this, 'register_settings']);
        add_action('wp_ajax_test_massdata_pro',           [$this, 'test_api']);
        add_action('wp_ajax_massdata_send_test_order_sms',[$this, 'ajax_send_test_order_sms']);
        add_action('wp_ajax_massdata_get_sms_logs',       [$this, 'ajax_get_sms_logs']);
        add_action('wp_ajax_massdata_clear_sms_logs',     [$this, 'ajax_clear_sms_logs']);

        // Frontend OTP
        add_action('woocommerce_register_form',  [$this, 'add_otp_field']);
        add_action('wp_footer',                  [$this, 'add_scripts']);
        add_action('wp_footer',                  [$this, 'add_footer_credit'], 9999);

        // AJAX OTP
        add_action('wp_ajax_nopriv_pro_send_otp',  [$this, 'send_otp']);
        add_action('wp_ajax_pro_send_otp',          [$this, 'send_otp']);
        add_action('wp_ajax_nopriv_pro_verify_otp', [$this, 'verify_otp']);
        add_action('wp_ajax_pro_verify_otp',        [$this, 'verify_otp']);

        // Validation & save
        add_action('woocommerce_register_post',    [$this, 'validate_otp'],  10, 3);
        add_action('woocommerce_created_customer', [$this, 'save_phone'],    10, 3);

        // ===== ORDER SMS HOOKS =====
        add_action('woocommerce_order_status_changed', [$this, 'order_status_changed_sms'], 10, 4);
        add_action('woocommerce_new_order',             [$this, 'new_order_sms'], 10, 1);

        // Admin footer
        add_action('admin_footer_text', [$this, 'add_admin_footer_credit']);
    }

    // ================================================================
    //  FOOTER INTEGRITY
    // ================================================================

    public function check_footer_integrity() {
        $stored  = get_option('massdata_footer_hash');
        $current = $this->generate_footer_hash();
        if (empty($stored)) {
            update_option('massdata_footer_hash', $current);
        } elseif ($stored !== $current) {
            add_filter('pre_option_' . $this->option_name, function ($v) {
                if (is_array($v)) $v['enabled'] = 0;
                return $v;
            });
            $this->log_tampering_attempt();
        }
    }

    private function generate_footer_hash() {
        return wp_hash($this->get_expected_footer() . $this->developer_email . $this->developer_site);
    }

    private function get_expected_footer() {
        return "This Plugin Developed By Moursalin Islam (morsalinislam.net@gmail.com)\nThanks From\nOnexusDev\n<Code The Future, Live The Dream/>\nonexusdev.xyz\nonexusdev@gmail.com\nfacebook.com/onexusdev";
    }

    private function log_tampering_attempt() {
        $entry = date('Y-m-d H:i:s') . " - Footer tampering from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        file_put_contents(WP_CONTENT_DIR . '/massdata-security.log', $entry, FILE_APPEND);
    }

    // ================================================================
    //  FOOTER CREDIT
    // ================================================================

    public function add_footer_credit() {
        if (is_admin()) return;
        $settings = get_option($this->option_name);
        if (empty($settings['enabled'])) return;
        $sig = wp_hash($this->footer_hash . date('Ymd'));
        ?>
        <!-- MassData OTP Pro - Protected Footer -->
        <div id="massdata-credit" data-signature="<?php echo esc_attr($sig); ?>" style="text-align:center;padding:10px;background:#f9f9f9;border-top:1px solid #eee;font-size:12px;color:#666;margin-top:20px;">
            <div style="max-width:600px;margin:0 auto;">
                <p style="margin:5px 0;">This Plugin Developed By <strong>Moursalin Islam</strong> (<a href="mailto:morsalinislam.net@gmail.com" style="color:#0073aa;text-decoration:none;">morsalinislam.net@gmail.com</a>)</p>
                <p style="margin:5px 0;">Thanks From <strong>OnexusDev</strong></p>
                <p style="margin:5px 0;font-family:monospace;">&lt;Code The Future, Live The Dream/&gt;</p>
                <p style="margin:5px 0;">
                    <a href="https://onexusdev.xyz" target="_blank" style="color:#0073aa;text-decoration:none;">onexusdev.xyz</a> |
                    <a href="mailto:onexusdev@gmail.com" style="color:#0073aa;text-decoration:none;">onexusdev@gmail.com</a> |
                    <a href="https://facebook.com/onexusdev" target="_blank" style="color:#0073aa;text-decoration:none;">facebook.com/onexusdev</a>
                </p>
            </div>
        </div>
        <style>#massdata-credit a:hover{text-decoration:underline!important;}</style>
        <script>
        (function($){
            var fc=setInterval(function(){
                if($('#massdata-credit').length===0){
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'check_footer_tampering',nonce:'<?php echo wp_create_nonce('footer_check'); ?>',status:'removed'});
                    $('#pro_send,#pro_verify').prop('disabled',true);
                    alert('Plugin integrity check failed. Please contact support.');
                }
            },5000);
        })(jQuery);
        </script>
        <?php
    }

    public function add_admin_footer_credit($t) {
        return $t . '<br><span style="color:#666;font-size:11px;">MassData OTP Pro by <a href="https://onexusdev.xyz" target="_blank">OnexusDev</a> | Developed by Moursalin Islam</span>';
    }

    // ================================================================
    //  ADMIN MENU
    // ================================================================

    public function admin_menu() {
        add_menu_page('MassData OTP Pro', 'MassData SMS Pro', 'manage_options', 'massdata-pro', [$this, 'admin_page'], 'dashicons-phone', 56);

        add_submenu_page('massdata-pro', 'OTP Settings',        'OTP সেটিংস',       'manage_options', 'massdata-pro',          [$this, 'admin_page']);
        add_submenu_page('massdata-pro', 'Order SMS',           'অর্ডার SMS',        'manage_options', 'massdata-order-sms',    [$this, 'order_sms_page']);
        add_submenu_page('massdata-pro', 'SMS Templates',       'SMS টেমপ্লেট',      'manage_options', 'massdata-sms-templates',[$this, 'sms_templates_page']);
        add_submenu_page('massdata-pro', 'SMS Logs',            'SMS লগ',            'manage_options', 'massdata-sms-logs',     [$this, 'sms_logs_page']);
    }

    public function register_settings() {
        register_setting('massdata_pro_settings',       $this->option_name);
        register_setting('massdata_order_sms_group',    $this->sms_option_name);
        register_setting('massdata_sms_templates_group','massdata_sms_templates');
        add_option('massdata_footer_hash', $this->generate_footer_hash());
    }

    // ================================================================
    //  ADMIN PAGE — OTP SETTINGS (original)
    // ================================================================

    public function admin_page() {
        $this->check_footer_integrity();

        $settings = get_option($this->option_name, [
            'api_key'      => '01762666963.fdee9fbb-d586-414f-80c5-613460dd1c2e',
            'sender_id'    => '8809617613279',
            'company_name' => 'Your Company',
            'enabled'      => 1,
        ]);

        $stored      = get_option('massdata_footer_hash');
        $current     = $this->generate_footer_hash();
        $compromised = ($stored !== $current);
        ?>
        <div class="wrap">
            <h1>📱 MassData OTP Pro — সেটিংস</h1>
            <?php if ($compromised): ?>
            <div class="notice notice-error"><p><strong>⚠️ SECURITY ALERT:</strong> Plugin footer পরিবর্তন করা হয়েছে। Plugin বন্ধ করা হয়েছে।</p></div>
            <?php endif; ?>

            <div style="background:#fff;padding:20px;max-width:650px;border-radius:6px;margin-top:20px;border:1px solid #ddd;">
                <form method="post" action="options.php">
                    <?php settings_fields('massdata_pro_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Status</th>
                            <td><label><input type="checkbox" name="<?php echo $this->option_name; ?>[enabled]" value="1" <?php checked(1, $settings['enabled']); ?> <?php echo $compromised ? 'disabled' : ''; ?>> OTP Verification চালু করুন</label></td>
                        </tr>
                        <tr>
                            <th>Company Name</th>
                            <td><input type="text" name="<?php echo $this->option_name; ?>[company_name]" value="<?php echo esc_attr($settings['company_name']); ?>" class="regular-text" style="width:100%;" <?php echo $compromised ? 'disabled' : ''; ?>><p class="description">SMS-এ এই নাম দেখাবে</p></td>
                        </tr>
                        <tr>
                            <th>API Key</th>
                            <td><input type="text" name="<?php echo $this->option_name; ?>[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" style="width:100%;" <?php echo $compromised ? 'disabled' : ''; ?>></td>
                        </tr>
                        <tr>
                            <th>Sender ID</th>
                            <td><input type="text" name="<?php echo $this->option_name; ?>[sender_id]" value="<?php echo esc_attr($settings['sender_id']); ?>" class="regular-text" style="width:100%;" <?php echo $compromised ? 'disabled' : ''; ?>></td>
                        </tr>
                        <tr>
                            <th>API Test</th>
                            <td>
                                <button type="button" id="test_api_btn" class="button button-primary" <?php echo $compromised ? 'disabled' : ''; ?>>🔌 API Test করুন</button>
                                <span id="test_result" style="margin-left:10px;font-weight:bold;"></span>
                            </td>
                        </tr>
                    </table>
                    <?php if (!$compromised) submit_button('সেটিংস সেভ করুন'); ?>
                </form>

                <div style="margin-top:25px;padding:15px;background:#f0f8ff;border-left:4px solid #0073aa;">
                    <h3>📱 OTP SMS প্রিভিউ</h3>
                    <pre style="background:#fff;padding:15px;border:1px solid #ddd;"><?php echo esc_html($settings['company_name']); ?>
Your OTP Is: 123456
Valid 2 Minutes
Thanks From
<?php echo esc_html($settings['company_name']); ?></pre>
                </div>

                <div style="margin-top:20px;padding:15px;background:#f9f9f9;border-left:4px solid #46b450;">
                    <h3>👨‍💻 Developer Information</h3>
                    <p><strong>Moursalin Islam</strong><br>Email: <a href="mailto:morsalinislam.net@gmail.com">morsalinislam.net@gmail.com</a></p>
                    <p><strong>OnexusDev</strong><br>
                    Website: <a href="https://onexusdev.xyz" target="_blank">onexusdev.xyz</a> |
                    Email: <a href="mailto:onexusdev@gmail.com">onexusdev@gmail.com</a> |
                    Facebook: <a href="https://facebook.com/onexusdev" target="_blank">facebook.com/onexusdev</a></p>
                    <p style="font-family:monospace;color:#666;">&lt;Code The Future, Live The Dream/&gt;</p>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('#test_api_btn').click(function(){
                var key=$('input[name*="api_key"]').val();
                if(!key){alert('API Key দিন');return;}
                $('#test_result').html('<span style="color:#3498db;">Testing...</span>');
                $.post(ajaxurl,{action:'test_massdata_pro',key:key,nonce:'<?php echo wp_create_nonce('test_pro'); ?>'},function(res){
                    if(res.success) $('#test_result').html('<span style="color:#27ae60;">✅ সফল! Balance: ৳'+res.data+'</span>');
                    else $('#test_result').html('<span style="color:#e74c3c;">❌ '+res.data+'</span>');
                }).fail(function(){$('#test_result').html('<span style="color:#e74c3c;">❌ Connection Failed</span>');});
            });
        });
        </script>
        <?php
    }

    // ================================================================
    //  ADMIN PAGE — ORDER SMS SETTINGS
    // ================================================================

    public function order_sms_page() {
        $sms_settings = get_option($this->sms_option_name, []);
        $otp_settings = get_option($this->option_name, []);
        ?>
        <div class="wrap">
            <h1>🛒 অর্ডার SMS নোটিফিকেশন সেটিংস</h1>
            <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:20px;">

                <!-- Settings Form -->
                <div style="flex:1;min-width:380px;background:#fff;padding:20px;border-radius:6px;border:1px solid #ddd;">
                    <form method="post" action="options.php">
                        <?php settings_fields('massdata_order_sms_group'); ?>
                        <h2 style="border-bottom:2px solid #0073aa;padding-bottom:8px;">⚙️ সাধারণ সেটিংস</h2>
                        <table class="form-table">
                            <tr>
                                <th>Order SMS চালু করুন</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->sms_option_name; ?>[enabled]" value="1"
                                            <?php checked(1, $sms_settings['enabled'] ?? 0); ?>>
                                        অর্ডার SMS পাঠানো সক্রিয় করুন
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>নতুন অর্ডার SMS</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->sms_option_name; ?>[notify_new_order]" value="1"
                                            <?php checked(1, $sms_settings['notify_new_order'] ?? 1); ?>>
                                        নতুন অর্ডার হলে কাস্টমারকে SMS পাঠান
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>Admin নোটিফিকেশন</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->sms_option_name; ?>[notify_admin]" value="1"
                                            <?php checked(1, $sms_settings['notify_admin'] ?? 0); ?>>
                                        প্রতিটি অর্ডারে Admin কে SMS পাঠান
                                    </label><br>
                                    <input type="text" name="<?php echo $this->sms_option_name; ?>[admin_phone]"
                                        value="<?php echo esc_attr($sms_settings['admin_phone'] ?? ''); ?>"
                                        placeholder="01XXXXXXXXX" class="regular-text" style="margin-top:6px;">
                                    <p class="description">Admin এর ফোন নম্বর (বাংলাদেশ ফরম্যাট)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Admin অর্ডার টেমপ্লেট</th>
                                <td>
                                    <textarea name="<?php echo $this->sms_option_name; ?>[admin_template]" rows="5" style="width:100%;font-family:monospace;"
                                        placeholder="নতুন অর্ডার SMS টেমপ্লেট..."><?php echo esc_textarea($sms_settings['admin_template'] ?? "নতুন অর্ডার পেয়েছেন!\nঅর্ডার নং: #{order_id}\nকাস্টমার: {customer_name}\nমোট: {order_total}\nপেমেন্ট: {payment_method}"); ?></textarea>
                                    <p class="description">ভেরিয়েবল: {order_id} {customer_name} {order_total} {payment_method} {shop_name}</p>
                                </td>
                            </tr>
                            <tr>
                                <th>SMS পাঠানোর পরিসর</th>
                                <td>
                                    <?php foreach ($this->wc_statuses as $status => $label): ?>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="checkbox"
                                            name="<?php echo $this->sms_option_name; ?>[active_statuses][]"
                                            value="<?php echo esc_attr($status); ?>"
                                            <?php checked(true, in_array($status, (array)($sms_settings['active_statuses'] ?? array_keys($this->wc_statuses)))); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('সেটিংস সেভ করুন'); ?>
                    </form>

                    <!-- Test SMS -->
                    <div style="margin-top:20px;padding:15px;background:#fff8e1;border-left:4px solid #ffa000;border-radius:4px;">
                        <h3>🧪 টেস্ট SMS পাঠান</h3>
                        <input type="text" id="test_sms_phone" placeholder="01XXXXXXXXX" class="regular-text" style="width:60%;"><br><br>
                        <select id="test_sms_status" style="width:60%;padding:6px;margin-bottom:10px;">
                            <?php foreach ($this->wc_statuses as $k => $v): ?>
                                <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                            <?php endforeach; ?>
                        </select><br>
                        <button type="button" id="btn_test_order_sms" class="button button-primary">📤 টেস্ট SMS পাঠান</button>
                        <span id="test_sms_result" style="margin-left:10px;font-weight:bold;"></span>
                    </div>
                </div>

                <!-- Info Panel -->
                <div style="min-width:260px;max-width:320px;">
                    <div style="background:#e8f5e9;padding:15px;border-radius:6px;border:1px solid #a5d6a7;margin-bottom:15px;">
                        <h3 style="margin-top:0;color:#2e7d32;">📌 ভেরিয়েবল তালিকা</h3>
                        <table style="width:100%;font-size:13px;">
                            <tr><td><code>{customer_name}</code></td><td>কাস্টমারের নাম</td></tr>
                            <tr><td><code>{order_id}</code></td><td>অর্ডার নম্বর</td></tr>
                            <tr><td><code>{order_total}</code></td><td>মোট টাকা</td></tr>
                            <tr><td><code>{order_status}</code></td><td>অর্ডার স্ট্যাটাস</td></tr>
                            <tr><td><code>{payment_method}</code></td><td>পেমেন্ট পদ্ধতি</td></tr>
                            <tr><td><code>{shop_name}</code></td><td>শপের নাম</td></tr>
                            <tr><td><code>{order_date}</code></td><td>অর্ডারের তারিখ</td></tr>
                            <tr><td><code>{billing_address}</code></td><td>বিলিং ঠিকানা</td></tr>
                            <tr><td><code>{shipping_address}</code></td><td>শিপিং ঠিকানা</td></tr>
                            <tr><td><code>{items_list}</code></td><td>পণ্যের তালিকা</td></tr>
                            <tr><td><code>{customer_note}</code></td><td>কাস্টমারের নোট</td></tr>
                        </table>
                    </div>

                    <div style="background:#e3f2fd;padding:15px;border-radius:6px;border:1px solid #90caf9;">
                        <h3 style="margin-top:0;color:#1565c0;">💡 টিপস</h3>
                        <ul style="font-size:13px;padding-left:18px;">
                            <li>SMS টেমপ্লেট "SMS টেমপ্লেট" মেনু থেকে কাস্টমাইজ করুন</li>
                            <li>প্রতিটি স্ট্যাটাসের জন্য আলাদা টেমপ্লেট সেট করা যাবে</li>
                            <li>SMS লগ "SMS লগ" মেনু থেকে দেখুন</li>
                            <li>Bangladeshi নম্বর ফরম্যাট: 01XXXXXXXXX</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#btn_test_order_sms').click(function(){
                var phone=$('#test_sms_phone').val().trim();
                var status=$('#test_sms_status').val();
                if(!phone.match(/^01[3-9]\d{8}$/)){alert('সঠিক বাংলাদেশি নম্বর দিন (01XXXXXXXXX)');return;}
                $('#btn_test_order_sms').text('পাঠানো হচ্ছে...').prop('disabled',true);
                $('#test_sms_result').html('<span style="color:#3498db;">⏳ পাঠানো হচ্ছে...</span>');
                $.post(ajaxurl,{action:'massdata_send_test_order_sms',phone:phone,status:status,nonce:'<?php echo wp_create_nonce('massdata_order_sms'); ?>'},function(res){
                    if(res.success) $('#test_sms_result').html('<span style="color:#27ae60;">✅ '+res.data+'</span>');
                    else $('#test_sms_result').html('<span style="color:#e74c3c;">❌ '+res.data+'</span>');
                }).fail(function(){$('#test_sms_result').html('<span style="color:#e74c3c;">❌ Error</span>');})
                .always(function(){$('#btn_test_order_sms').text('📤 টেস্ট SMS পাঠান').prop('disabled',false);});
            });
        });
        </script>
        <?php
    }

    // ================================================================
    //  ADMIN PAGE — SMS TEMPLATES
    // ================================================================

    public function sms_templates_page() {
        $templates = get_option('massdata_sms_templates', $this->default_templates);
        ?>
        <div class="wrap">
            <h1>✉️ SMS টেমপ্লেট সেটিংস</h1>
            <p style="color:#666;">প্রতিটি অর্ডার স্ট্যাটাসের জন্য আলাদা SMS টেমপ্লেট তৈরি করুন। টেমপ্লেটে ভেরিয়েবল ব্যবহার করুন।</p>

            <!-- Variable Reference -->
            <div style="background:#e3f2fd;padding:12px 20px;border-radius:6px;margin-bottom:20px;border:1px solid #90caf9;max-width:900px;">
                <strong>📌 ভেরিয়েবল:</strong>
                <code>{customer_name}</code> <code>{order_id}</code> <code>{order_total}</code>
                <code>{order_status}</code> <code>{payment_method}</code> <code>{shop_name}</code>
                <code>{order_date}</code> <code>{billing_address}</code> <code>{shipping_address}</code>
                <code>{items_list}</code> <code>{customer_note}</code>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('massdata_sms_templates_group'); ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:18px;max-width:1100px;">

                    <?php foreach ($this->wc_statuses as $status => $label):
                        if ($status === 'checkout-draft') continue;
                        $icon = $this->get_status_icon($status);
                        $color = $this->get_status_color($status);
                    ?>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                        <div style="background:<?php echo $color; ?>;color:#fff;padding:10px 15px;display:flex;justify-content:space-between;align-items:center;">
                            <strong><?php echo $icon . ' ' . esc_html($label); ?></strong>
                            <label style="font-weight:normal;font-size:12px;cursor:pointer;">
                                <input type="checkbox"
                                    name="massdata_sms_templates[<?php echo esc_attr($status); ?>_enabled]"
                                    value="1"
                                    <?php checked(1, $templates[$status . '_enabled'] ?? 1); ?>
                                    style="margin-right:4px;">
                                সক্রিয়
                            </label>
                        </div>
                        <div style="padding:15px;">
                            <textarea
                                name="massdata_sms_templates[<?php echo esc_attr($status); ?>]"
                                rows="6"
                                style="width:100%;font-family:monospace;font-size:12px;border:1px solid #ccc;border-radius:4px;padding:8px;box-sizing:border-box;"
                                placeholder="<?php echo esc_attr($this->default_templates[$status] ?? ''); ?>"
                            ><?php echo esc_textarea($templates[$status] ?? ($this->default_templates[$status] ?? '')); ?></textarea>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                                <small style="color:#888;">ভেরিয়েবল ব্যবহার করুন উপরের তালিকা থেকে</small>
                                <button type="button" class="button button-small btn-reset-template" data-status="<?php echo esc_attr($status); ?>"
                                    data-default="<?php echo esc_attr($this->default_templates[$status] ?? ''); ?>">↩ রিসেট</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
                <div style="margin-top:25px;">
                    <?php submit_button('সকল টেমপ্লেট সেভ করুন', 'primary large'); ?>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('.btn-reset-template').click(function(){
                var def=$(this).data('default');
                $(this).closest('.'+$(this).closest('div').attr('class')).find('textarea').val(def);
                // simpler: find sibling textarea
                $(this).closest('div').find('textarea').val(def);
            });
        });
        </script>
        <?php
    }

    // ================================================================
    //  ADMIN PAGE — SMS LOGS
    // ================================================================

    public function sms_logs_page() {
        $logs = get_option('massdata_sms_logs', []);
        $logs = array_reverse($logs); // newest first
        ?>
        <div class="wrap">
            <h1>📋 SMS লগ</h1>
            <div style="display:flex;justify-content:space-between;align-items:center;max-width:1000px;margin-bottom:15px;">
                <p style="color:#666;margin:0;">মোট লগ: <strong><?php echo count($logs); ?></strong> টি</p>
                <button type="button" id="btn_clear_logs" class="button button-secondary">🗑️ সব লগ মুছুন</button>
            </div>

            <?php if (empty($logs)): ?>
            <div style="background:#fff;padding:40px;text-align:center;border-radius:6px;border:1px solid #ddd;max-width:1000px;">
                <span style="font-size:48px;">📭</span>
                <p style="color:#666;font-size:16px;">এখনো কোনো SMS লগ নেই।</p>
            </div>
            <?php else: ?>
            <table class="widefat striped" style="max-width:1000px;">
                <thead>
                    <tr>
                        <th width="160">তারিখ/সময়</th>
                        <th width="80">অর্ডার</th>
                        <th width="120">ফোন</th>
                        <th width="100">স্ট্যাটাস</th>
                        <th>বার্তা</th>
                        <th width="80">ফলাফল</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-size:12px;"><?php echo esc_html($log['time'] ?? ''); ?></td>
                        <td><a href="<?php echo esc_url(admin_url('post.php?post=' . ($log['order_id'] ?? '') . '&action=edit')); ?>" target="_blank">#<?php echo esc_html($log['order_id'] ?? ''); ?></a></td>
                        <td><?php echo esc_html($log['phone'] ?? ''); ?></td>
                        <td>
                            <span style="padding:2px 8px;border-radius:3px;font-size:11px;background:<?php echo $this->get_status_color($log['status'] ?? ''); ?>;color:#fff;">
                                <?php echo esc_html($log['status'] ?? ''); ?>
                            </span>
                        </td>
                        <td style="font-size:12px;white-space:pre-wrap;max-width:380px;"><?php echo esc_html($log['message'] ?? ''); ?></td>
                        <td>
                            <?php if (($log['success'] ?? false)): ?>
                            <span style="color:#27ae60;">✅ সফল</span>
                            <?php else: ?>
                            <span style="color:#e74c3c;" title="<?php echo esc_attr($log['error'] ?? ''); ?>">❌ ব্যর্থ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#btn_clear_logs').click(function(){
                if(!confirm('সব SMS লগ মুছে ফেলবেন?')) return;
                $(this).text('মুছছে...').prop('disabled',true);
                $.post(ajaxurl,{action:'massdata_clear_sms_logs',nonce:'<?php echo wp_create_nonce('massdata_logs'); ?>'},function(res){
                    if(res.success) location.reload();
                    else alert('Error: '+res.data);
                });
            });
        });
        </script>
        <?php
    }

    // ================================================================
    //  ORDER SMS — CORE LOGIC
    // ================================================================

    /**
     * Fires when an order status changes.
     */
    public function order_status_changed_sms($order_id, $old_status, $new_status, $order) {
        $sms_settings = get_option($this->sms_option_name, []);
        if (empty($sms_settings['enabled'])) return;

        $active = (array)($sms_settings['active_statuses'] ?? array_keys($this->wc_statuses));
        if (!in_array($new_status, $active)) return;

        $templates = get_option('massdata_sms_templates', $this->default_templates);

        // Check if this status template is enabled
        if (empty($templates[$new_status . '_enabled']) && isset($templates[$new_status . '_enabled'])) return;

        $template = $templates[$new_status] ?? ($this->default_templates[$new_status] ?? '');
        if (empty($template)) return;

        $phone = $this->get_order_phone($order);
        if (empty($phone)) return;

        $message = $this->parse_template($template, $order);
        $result  = $this->send_sms($phone, $message);

        $this->log_sms([
            'order_id' => $order_id,
            'phone'    => $phone,
            'status'   => $new_status,
            'message'  => $message,
            'success'  => $result['success'],
            'error'    => $result['error'] ?? '',
        ]);

        // Admin notification
        if (!empty($sms_settings['notify_admin']) && !empty($sms_settings['admin_phone'])) {
            $admin_tpl = $sms_settings['admin_template'] ?? "নতুন অর্ডার!\nঅর্ডার: #{order_id}\nকাস্টমার: {customer_name}\nমোট: {order_total}";
            $admin_msg = $this->parse_template($admin_tpl, $order);
            $this->send_sms($sms_settings['admin_phone'], $admin_msg);
        }
    }

    /**
     * Fires on brand new order placement.
     */
    public function new_order_sms($order_id) {
        $sms_settings = get_option($this->sms_option_name, []);
        if (empty($sms_settings['enabled'])) return;
        if (empty($sms_settings['notify_new_order'])) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $templates = get_option('massdata_sms_templates', $this->default_templates);
        $template  = $templates['processing'] ?? ($this->default_templates['processing'] ?? '');
        if (empty($template)) return;

        $phone = $this->get_order_phone($order);
        if (empty($phone)) return;

        $message = $this->parse_template($template, $order);
        $result  = $this->send_sms($phone, $message);

        $this->log_sms([
            'order_id' => $order_id,
            'phone'    => $phone,
            'status'   => 'new',
            'message'  => $message,
            'success'  => $result['success'],
            'error'    => $result['error'] ?? '',
        ]);
    }

    // ================================================================
    //  HELPERS
    // ================================================================

    /**
     * Get customer phone from order.
     */
    private function get_order_phone($order) {
        $phone = $order->get_billing_phone();
        if (empty($phone)) return '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '01') return $phone;
        if (strlen($phone) === 13 && substr($phone, 0, 3) === '880') return '0' . substr($phone, 3);
        return '';
    }

    /**
     * Replace template variables with actual order data.
     */
    private function parse_template($template, $order) {
        $first = $order->get_billing_first_name();
        $last  = $order->get_billing_last_name();
        $name  = trim("$first $last") ?: 'কাস্টমার';

        // Build items list
        $items_arr = [];
        foreach ($order->get_items() as $item) {
            $items_arr[] = $item->get_name() . ' x' . $item->get_quantity();
        }
        $items_list = implode(', ', $items_arr);

        $billing_parts  = array_filter([$order->get_billing_address_1(), $order->get_billing_city(), $order->get_billing_state()]);
        $shipping_parts = array_filter([$order->get_shipping_address_1(), $order->get_shipping_city(), $order->get_shipping_state()]);

        $vars = [
            '{customer_name}'    => $name,
            '{order_id}'         => $order->get_order_number(),
            '{order_total}'      => '৳' . number_format((float)$order->get_total(), 2),
            '{order_status}'     => wc_get_order_status_name($order->get_status()),
            '{payment_method}'   => $order->get_payment_method_title(),
            '{shop_name}'        => get_bloginfo('name'),
            '{order_date}'       => $order->get_date_created() ? $order->get_date_created()->date('d/m/Y') : date('d/m/Y'),
            '{billing_address}'  => implode(', ', $billing_parts),
            '{shipping_address}' => implode(', ', $shipping_parts),
            '{items_list}'       => $items_list,
            '{customer_note}'    => $order->get_customer_note() ?: '',
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    /**
     * Send SMS via MassData API.
     * Returns ['success' => bool, 'error' => string]
     */
    private function send_sms($phone, $message) {
        $settings = get_option($this->option_name, []);

        if (empty($settings['api_key']) || empty($settings['sender_id'])) {
            return ['success' => false, 'error' => 'API Key বা Sender ID সেট করা নেই'];
        }

        // Convert to 880xxxxxxxxxx format
        $api_phone = $phone;
        if (substr($phone, 0, 2) === '01') {
            $api_phone = '880' . substr($phone, 1);
        }

        $url = $this->api_url . '?' . http_build_query([
            'apiKey'         => $settings['api_key'],
            'contactNumbers' => $api_phone,
            'senderId'       => $settings['sender_id'],
            'textBody'       => $message,
            'type'           => 'text',
            'label'          => 'transactional',
        ]);

        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['dlrRef'])) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => $body];
    }

    /**
     * Append to SMS log (keep last 500 entries).
     */
    private function log_sms($entry) {
        $logs   = get_option('massdata_sms_logs', []);
        $entry['time'] = date('Y-m-d H:i:s');
        $logs[] = $entry;
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }
        update_option('massdata_sms_logs', $logs);
    }

    // ================================================================
    //  AJAX HANDLERS
    // ================================================================

    public function ajax_send_test_order_sms() {
        check_ajax_referer('massdata_order_sms', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('অনুমতি নেই');

        $phone  = sanitize_text_field($_POST['phone'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'processing');

        if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
            wp_send_json_error('সঠিক বাংলাদেশি নম্বর দিন (01XXXXXXXXX)');
        }

        $templates = get_option('massdata_sms_templates', $this->default_templates);
        $template  = $templates[$status] ?? ($this->default_templates[$status] ?? "Test SMS for status: $status");

        $dummy_vars = [
            '{customer_name}'    => 'Test কাস্টমার',
            '{order_id}'         => '12345',
            '{order_total}'      => '৳1,500.00',
            '{order_status}'     => $status,
            '{payment_method}'   => 'bKash',
            '{shop_name}'        => get_bloginfo('name'),
            '{order_date}'       => date('d/m/Y'),
            '{billing_address}'  => 'ঢাকা, বাংলাদেশ',
            '{shipping_address}' => 'ঢাকা, বাংলাদেশ',
            '{items_list}'       => 'Test Product x1',
            '{customer_note}'    => 'Test নোট',
        ];

        $message = str_replace(array_keys($dummy_vars), array_values($dummy_vars), $template);
        $result  = $this->send_sms($phone, $message);

        if ($result['success']) {
            wp_send_json_success("SMS সফলভাবে পাঠানো হয়েছে: $phone");
        } else {
            wp_send_json_error('SMS ব্যর্থ: ' . ($result['error'] ?? 'অজানা ত্রুটি'));
        }
    }

    public function ajax_get_sms_logs() {
        check_ajax_referer('massdata_logs', 'nonce');
        $logs = get_option('massdata_sms_logs', []);
        wp_send_json_success(array_reverse($logs));
    }

    public function ajax_clear_sms_logs() {
        check_ajax_referer('massdata_logs', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('অনুমতি নেই');
        update_option('massdata_sms_logs', []);
        wp_send_json_success('লগ মুছে ফেলা হয়েছে');
    }

    // ================================================================
    //  STATUS HELPERS
    // ================================================================

    private function get_status_icon($status) {
        $icons = [
            'pending'    => '⏳',
            'processing' => '🔄',
            'on-hold'    => '⏸️',
            'completed'  => '✅',
            'cancelled'  => '❌',
            'refunded'   => '↩️',
            'failed'     => '❗',
        ];
        return $icons[$status] ?? '📦';
    }

    private function get_status_color($status) {
        $colors = [
            'pending'    => '#f39c12',
            'processing' => '#3498db',
            'on-hold'    => '#9b59b6',
            'completed'  => '#27ae60',
            'cancelled'  => '#e74c3c',
            'refunded'   => '#1abc9c',
            'failed'     => '#c0392b',
            'new'        => '#2980b9',
        ];
        return $colors[$status] ?? '#7f8c8d';
    }

    // ================================================================
    //  API TEST (AJAX)
    // ================================================================

    public function test_api() {
        check_ajax_referer('test_pro', 'nonce');
        $key = sanitize_text_field($_POST['key']);

        $response = wp_remote_get("https://smsmassdata.massdata.xyz/api/sms/balance?apiKey=" . urlencode($key), [
            'timeout'   => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) { wp_send_json_error('Connection failed'); }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['balance'])) {
            wp_send_json_success($data['balance']);
        } else {
            wp_send_json_error('Invalid API Key');
        }
    }

    // ================================================================
    //  FRONTEND OTP FORM
    // ================================================================

    public function add_otp_field() {
        $settings = get_option($this->option_name);
        if (empty($settings['enabled']) || empty($settings['api_key'])) return;

        $stored  = get_option('massdata_footer_hash');
        $current = $this->generate_footer_hash();
        if ($stored !== $current) {
            echo '<div class="woocommerce-error">Plugin integrity check failed. Please contact support.</div>';
            return;
        }
        ?>
        <div style="margin:15px 0;padding:15px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;">
            <p><strong>📱 ফোন যাচাইকরণ (প্রয়োজনীয়)</strong></p>
            <input type="tel" id="pro_phone" placeholder="01XXXXXXXXX" style="width:100%;padding:8px;margin-bottom:10px;border:1px solid #ddd;border-radius:3px;">
            <div style="margin:10px 0;">
                <button type="button" id="pro_send" style="padding:8px 15px;background:#333;color:#fff;border:none;border-radius:3px;cursor:pointer;">📨 OTP পাঠান</button>
                <span id="pro_send_status" style="margin-left:8px;"></span>
            </div>
            <div id="pro_verify_box" style="display:none;margin-top:10px;">
                <input type="text" id="pro_otp" placeholder="৬ সংখ্যার OTP দিন" style="width:100%;padding:8px;margin-bottom:10px;border:1px solid #ddd;border-radius:3px;">
                <div>
                    <button type="button" id="pro_verify" style="padding:8px 15px;background:#333;color:#fff;border:none;border-radius:3px;cursor:pointer;">✓ যাচাই করুন</button>
                    <span id="pro_verify_status" style="margin-left:8px;"></span>
                </div>
            </div>
            <input type="hidden" id="pro_verified" name="pro_verified" value="no">
            <input type="hidden" name="pro_phone" id="pro_phone_hidden">
        </div>
        <?php
    }

    // ================================================================
    //  JAVASCRIPT (OTP Frontend)
    // ================================================================

    public function add_scripts() {
        if (!is_account_page()) return;
        $settings = get_option($this->option_name);
        if (empty($settings['enabled'])) return;

        add_action('wp_ajax_check_footer_tampering',        [$this, 'handle_footer_tampering']);
        add_action('wp_ajax_nopriv_check_footer_tampering', [$this, 'handle_footer_tampering']);
        ?>
        <script>
        jQuery(function($){
            $('#pro_send').click(function(){
                var phone=$('#pro_phone').val().trim();
                if(!phone.match(/^01[3-9]\d{8}$/)){alert('সঠিক বাংলাদেশি নম্বর দিন');return;}
                $('#pro_send').text('পাঠানো হচ্ছে...').prop('disabled',true);
                $('#pro_send_status').text('⏳ পাঠানো হচ্ছে...').css('color','#3498db');
                $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'pro_send_otp',phone:phone,nonce:'<?php echo wp_create_nonce('pro_nonce'); ?>'},function(res){
                    if(res.success){$('#pro_send_status').html('<span style="color:#27ae60;">✅ OTP পাঠানো হয়েছে</span>');$('#pro_verify_box').show();$('#pro_otp').focus();}
                    else{$('#pro_send_status').html('<span style="color:#e74c3c;">❌ '+res.data+'</span>');}
                }).fail(function(){$('#pro_send_status').html('<span style="color:#e74c3c;">❌ নেটওয়ার্ক সমস্যা</span>');})
                .always(function(){$('#pro_send').text('📨 OTP পাঠান').prop('disabled',false);});
            });

            $('#pro_verify').click(function(){
                var phone=$('#pro_phone').val().trim();
                var otp=$('#pro_otp').val().trim();
                if(!otp||otp.length!=6){alert('৬ সংখ্যার OTP দিন');return;}
                $('#pro_verify').text('যাচাই হচ্ছে...').prop('disabled',true);
                $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'pro_verify_otp',phone:phone,otp:otp,nonce:'<?php echo wp_create_nonce('pro_nonce'); ?>'},function(res){
                    if(res.success){$('#pro_verify_status').html('<span style="color:#27ae60;">✅ ফোন যাচাই সম্পন্ন!</span>');$('#pro_verified').val('yes');$('#pro_phone_hidden').val(phone);$('#pro_verify').hide();$('#pro_otp').prop('disabled',true);$('#pro_phone').prop('readonly',true);}
                    else{$('#pro_verify_status').html('<span style="color:#e74c3c;">❌ '+res.data+'</span>');}
                }).fail(function(){$('#pro_verify_status').html('<span style="color:#e74c3c;">❌ সার্ভার সমস্যা</span>');})
                .always(function(){$('#pro_verify').text('✓ যাচাই করুন').prop('disabled',false);});
            });

            $('form.register').submit(function(e){
                if($('#pro_verified').val()!='yes'){e.preventDefault();alert('প্রথমে ফোন নম্বর যাচাই করুন');$('#pro_verify_box').show();}
            });
        });
        </script>
        <?php
    }

    // ================================================================
    //  OTP SEND / VERIFY
    // ================================================================

    public function send_otp() {
        check_ajax_referer('pro_nonce', 'nonce');

        $stored  = get_option('massdata_footer_hash');
        $current = $this->generate_footer_hash();
        if ($stored !== $current) { wp_send_json_error('Plugin integrity check failed'); }

        $settings = get_option($this->option_name);
        $phone    = sanitize_text_field($_POST['phone']);

        if (!preg_match('/^01[3-9]\d{8}$/', $phone)) { wp_send_json_error('Invalid phone number'); }

        $otp      = rand(100000, 999999);
        $company  = !empty($settings['company_name']) ? $settings['company_name'] : 'Your Company';
        $message  = "$company\nYour OTP Is: $otp\nValid 2 Minutes\nThanks From\n$company";
        $api_phone = '880' . substr($phone, 1);

        $url = $this->api_url . '?' . http_build_query([
            'apiKey'         => $settings['api_key'],
            'contactNumbers' => $api_phone,
            'senderId'       => $settings['sender_id'],
            'textBody'       => $message,
            'type'           => 'text',
            'label'          => 'transactional',
        ]);

        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);

        if (is_wp_error($response)) { wp_send_json_error('API Error: ' . $response->get_error_message()); }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['dlrRef'])) {
            set_transient('pro_otp_' . $api_phone, $otp, 300);
            wp_send_json_success('OTP Sent');
        } else {
            wp_send_json_error('SMS Failed');
        }
    }

    public function verify_otp() {
        check_ajax_referer('pro_nonce', 'nonce');

        $stored  = get_option('massdata_footer_hash');
        $current = $this->generate_footer_hash();
        if ($stored !== $current) { wp_send_json_error('Plugin integrity check failed'); }

        $phone     = '880' . substr(sanitize_text_field($_POST['phone']), 1);
        $otp_input = sanitize_text_field($_POST['otp']);
        $saved     = get_transient('pro_otp_' . $phone);

        if (!$saved) { wp_send_json_error('OTP মেয়াদ শেষ'); }
        if ($saved == $otp_input) {
            set_transient('pro_verified_' . $phone, 'yes', 600);
            wp_send_json_success('Verified');
        } else {
            wp_send_json_error('ভুল OTP');
        }
    }

    // ================================================================
    //  VALIDATION & SAVE
    // ================================================================

    public function validate_otp($username, $email, $errors) {
        $settings = get_option($this->option_name);
        if (empty($settings['enabled'])) return;

        $stored  = get_option('massdata_footer_hash');
        $current = $this->generate_footer_hash();
        if ($stored !== $current) {
            $errors->add('integrity_error', 'Plugin integrity check failed. Please contact support.');
            return;
        }

        if (empty($_POST['pro_verified']) || $_POST['pro_verified'] != 'yes') {
            $errors->add('otp_error', 'ফোন যাচাইকরণ প্রয়োজন');
        }
    }

    public function save_phone($customer_id, $new_customer_data, $password_generated) {
        if (isset($_POST['pro_phone'])) {
            update_user_meta($customer_id, 'billing_phone', sanitize_text_field($_POST['pro_phone']));
        }
    }

    // ================================================================
    //  FOOTER TAMPERING HANDLER
    // ================================================================

    public function handle_footer_tampering() {
        check_ajax_referer('footer_check', 'nonce');
        $this->log_tampering_attempt();
        $settings = get_option($this->option_name);
        $settings['enabled'] = 0;
        update_option($this->option_name, $settings);
        wp_send_json_success('Plugin disabled due to footer modification');
    }
}

// Initialize
new MassData_OTP_Pro();
?>
