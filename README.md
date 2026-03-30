MassData OTP Pro
================

**Enterprise-grade SMS OTP verification for WooCommerce + Full Order SMS Notification System**

> A comprehensive SMS solution for WooCommerce that combines phone number verification during registration with automated order status notifications via the MassData SMS gateway.

🚀 Features
-----------

### 📱 OTP Phone Verification

*   **SMS OTP verification** during WooCommerce registration
    
*   **Real-time phone validation** with Bangladesh mobile number format (01XXXXXXXXX)
    
*   **AJAX-based OTP sending** – no page reload required
    
*   **5-minute OTP expiry** for security
    
*   **Customizable company name** in SMS templates
    
*   **Auto-save verified phone number** to user's billing phone field
    

### 🛒 Order SMS Notifications

*   **Automatic SMS alerts** for order status changes
    
*   **Customizable SMS templates** for each order status:
    
    *   Pending Payment (অপেক্ষমাণ পেমেন্ট)
        
    *   Processing (প্রসেসিং)
        
    *   On Hold (হোল্ড)
        
    *   Completed (সম্পন্ন)
        
    *   Cancelled (বাতিল)
        
    *   Refunded (ফেরত)
        
    *   Failed (ব্যর্থ)
        
*   **New order SMS** notification on placement
    
*   **Admin notification** option with customizable phone number
    
*   **Multi-status activation** – choose which statuses trigger SMS
    
*   **Test SMS feature** to validate templates
    

### 📝 SMS Templates

*   **Separate templates** for each order status
    
*   **Rich variable support** for dynamic content:
    
    *   {customer\_name} – Customer's full name
        
    *   {order\_id} – Order number
        
    *   {order\_total} – Order total amount
        
    *   {order\_status} – Current order status
        
    *   {payment\_method} – Payment method used
        
    *   {shop\_name} – Website/blog name
        
    *   {order\_date} – Order creation date
        
    *   {billing\_address} – Customer's billing address
        
    *   {shipping\_address} – Customer's shipping address
        
    *   {items\_list} – List of ordered items with quantities
        
    *   {customer\_note} – Customer's order note
        

### 📊 SMS Logs

*   **Complete history** of all sent SMS
    
*   **Success/failure tracking** with error messages
    
*   **Searchable log table** with order linking
    
*   **Last 500 entries** storage with auto-cleanup
    
*   **One-click log clearing** option
    

### 🔧 Admin Features

*   **Dedicated admin menu** with sub-pages:
    
    *   OTP Settings – API configuration and test
        
    *   Order SMS – Notification settings and test
        
    *   SMS Templates – Customize messages per status
        
    *   SMS Logs – View and manage sent messages
        
*   **API testing tool** to verify gateway connection
    
*   **Balance check** via MassData API
    
*   **Security integrity check** with tampering protection
    

📋 Requirements
---------------

RequirementMinimumWordPress5.8+PHP8.0+WooCommerceLatest versionSMS GatewayMassData API credentials

🔌 Installation
---------------

1.  **Upload the plugin** to /wp-content/plugins/massdata-otp-pro/
    
2.  **Activate** the plugin through the WordPress admin panel
    
3.  **Ensure WooCommerce** is active
    
4.  **Configure API credentials** in **MassData SMS Pro → OTP Settings**
    
5.  **Enable OTP verification** and/or **Order SMS** as needed
    

⚙️ Configuration Guide
----------------------

### Step 1: API Configuration

Navigate to **MassData SMS Pro → OTP Settings**:

*   Enter your **API Key**
    
*   Enter your **Sender ID**
    
*   Set your **Company Name** (appears in SMS)
    
*   Click **Save Settings**
    

### Step 2: Test API Connection

*   Click **API Test করুন** to verify credentials
    
*   If successful, your balance will be displayed
    

### Step 3: Enable OTP Verification

*   Check **OTP Verification চালু করুন**
    
*   Save settings – OTP field will appear on registration page
    

### Step 4: Configure Order SMS

Navigate to **MassData SMS Pro → Order SMS**:

*   Enable **Order SMS পাঠানো সক্রিয় করুন**
    
*   Choose which order statuses trigger SMS
    
*   Optionally enable **Admin notification** with admin phone number
    
*   Save settings
    

### Step 5: Customize SMS Templates

Navigate to **MassData SMS Pro → SMS Templates**:

*   Edit templates for each order status
    
*   Use available variables for dynamic content
    
*   Enable/disable individual status templates
    
*   Save templates
    

📱 SMS Template Examples
------------------------

**Processing Order (প্রসেসিং)**

প্রিয় {customer\_name},

আপনার অর্ডার #{order\_id} প্রসেস হচ্ছে।

মোট: {order\_total}

ধন্যবাদ, {shop\_name}

**Completed Order (সম্পন্ন)**

প্রিয় {customer\_name},

আপনার অর্ডার #{order\_id} সফলভাবে ডেলিভারি হয়েছে।

আমাদের সাথে থাকুন।

{shop\_name}

🔒 Security Features
--------------------

*   **Footer integrity check** – prevents plugin tampering
    
*   **Tampering logging** – records unauthorized modifications
    
*   **Nonce validation** on all AJAX requests
    
*   **Capability checks** for admin actions
    
*   **OTP expiry** (5 minutes) for security
    
*   **Phone number format validation** (Bangladesh format)
    

🛡️ Integrity Protection
------------------------

The plugin includes a unique footer protection system:

*   If plugin footer is modified, OTP functionality is automatically disabled
    
*   Tampering attempts are logged to /wp-content/massdata-security.log
    
*   A visual security alert is displayed in admin panel
    

🌐 Language Support
-------------------

*   **Bengali** interface text throughout admin panel
    
*   **English** fallback support
    
*   SMS templates support both Bengali and English content
    
*   Admin menu items with Bengali descriptions
    

📊 SMS Log Format
-----------------

Each log entry includes:

*   **Timestamp** – Date and time of sending
    
*   **Order ID** – Link to edit order
    
*   **Phone number** – Recipient's number
    
*   **Status** – Order status that triggered SMS
    
*   **Message** – Actual SMS content sent
    
*   **Result** – Success/Failure status with error details
    

🧪 Testing
----------

### Test OTP Verification

1.  Go to WooCommerce registration page
    
2.  Enter a valid Bangladesh phone number (01XXXXXXXXX)
    
3.  Click **OTP পাঠান**
    
4.  Enter the received OTP
    
5.  Complete registration
    

### Test Order SMS

1.  Go to **MassData SMS Pro → Order SMS**
    
2.  Scroll to **টেস্ট SMS পাঠান** section
    
3.  Enter a phone number
    
4.  Select an order status
    
5.  Click **📤 টেস্ট SMS পাঠান**
    
6.  Verify the test message is received
    

👨‍💻 Developer Information
---------------------------

**Developer:** Moursalin Islam**Email:** morsalinislam.net@gmail.com**Agency:** OnexusDev**Website:** [onexusdev.xyz](https://onexusdev.xyz/)**Support:** onexusdev@gmail.com

📄 License
----------

**GPL v2 or later**[https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

🙏 Acknowledgments
------------------

*   WooCommerce for providing the e-commerce framework
    
*   MassData for SMS gateway services
    
*   WordPress community for plugin development standards
    

🔄 Changelog
------------

### Version 5.0.0

*   Complete plugin rewrite with enhanced security
    
*   Added Order SMS notification system
    
*   Added customizable SMS templates per order status
    
*   Added SMS logging with admin interface
    
*   Added API testing tool
    
*   Added admin notification option
    
*   Enhanced footer integrity protection
    
*   Bengali language support throughout admin
    
*   Multiple order status support
    

_Code The Future, Live The Dream_**OnexusDev**
