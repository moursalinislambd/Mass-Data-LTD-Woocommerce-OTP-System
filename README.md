MassData OTP Pro - Plugin Description
=====================================

📱 Overview
-----------

**MassData OTP Pro** is a powerful WooCommerce extension that adds phone number verification via OTP (One-Time Password) during user registration. Built with security and customization in mind, it provides complete control over the verification process through an intuitive admin dashboard.

✨ Key Features
--------------

### Core Functionality

*   **SMS OTP Verification** - Verify customer phone numbers during WooCommerce registration
    
*   **Bangladesh Phone Support** - Specifically optimized for Bangladeshi phone numbers (01XXXXXXXXX format)
    
*   **Real-time Verification** - AJAX-powered OTP sending and verification without page reload
    
*   **Session-based OTP** - 5-minute OTP validity with secure transient storage
    

### Admin Dashboard

*   **Full Control Panel** - Enable/disable OTP verification with one click
    
*   **API Configuration** - Easy setup for MassData SMS API credentials
    
*   **Company Branding** - Customize company name appearing in SMS messages
    
*   **Live API Testing** - Test connection and check balance directly from settings
    
*   **SMS Preview** - Real-time preview of how your SMS will look
    

### Security Features

*   **Footer Protection System** - Built-in integrity check prevents unauthorized modification
    
*   **Tamper Detection** - Automatic plugin disable if footer credits are removed
    
*   **Activity Logging** - Records all tampering attempts for security auditing
    
*   **Nonce Verification** - Secure AJAX requests with WordPress nonces
    
*   **Input Validation** - Thorough phone number validation and sanitization
    

### Developer-Friendly

*   **Extensible Code** - Well-structured OOP PHP with hooks and filters
    
*   **AJAX Handlers** - Ready-to-use endpoints for custom integrations
    
*   **User Meta Storage** - Automatically saves verified phone numbers to user profile
    
*   **Translation Ready** - All text strings are prepared for localization
    

🚀 How It Works
---------------

1.  **User Registration Flow**
    
    *   User enters phone number on WooCommerce registration form
        
    *   Clicks "Send OTP" to request verification code
        
    *   Receives SMS with 6-digit OTP
        
    *   Enters OTP for verification
        
    *   Completes registration only after successful verification
        
2.  text\[Company Name\]Your OTP Is: 123456Valid 2 MinutesThanks From\[Company Name\]
    
3.  **Admin Control**
    
    *   Enable/disable verification anytime
        
    *   Update API credentials
        
    *   Customize SMS sender information
        
    *   Test API connectivity
        

💻 Technical Specifications
---------------------------

*   **WordPress Version**: 5.0+
    
*   **WooCommerce Version**: 4.0+
    
*   **PHP Version**: 7.2+
    
*   **Database**: Uses WordPress transients for OTP storage
    
*   **API Integration**: MassData SMS Gateway
    
*   **Security**: Nonce verification, data sanitization, capability checks
    

🔧 Installation
---------------

1.  Upload plugin files to /wp-content/plugins/massdata-otp-pro/
    
2.  Activate plugin through WordPress admin panel
    
3.  Navigate to "MassData OTP Pro" menu
    
4.  Configure API credentials and settings
    
5.  Enable OTP verification
    

⚙️ Configuration Options
------------------------

SettingDescriptionDefaultStatusEnable/disable OTP verificationEnabledCompany NameYour company name for SMSYour CompanyAPI KeyMassData SMS API key-Sender IDSMS sender ID8809617613279

📊 Database Usage
-----------------

*   **Options**: massdata\_pro\_settings, massdata\_footer\_hash
    
*   **Transients**: pro\_otp\_\* (5 minutes), pro\_verified\_\* (10 minutes)
    
*   **User Meta**: billing\_phone
    

🔒 Security Architecture
------------------------

### Footer Protection System

The plugin implements a unique security measure that ties functionality to footer credits:

*   Generates hash of footer content on activation
    
*   Continuously verifies footer integrity
    
*   Automatically disables if footer is modified
    
*   Logs all tampering attempts
    

### Validation Layers

1.  **Client-side**: JavaScript phone format validation
    
2.  **Server-side**: PHP regex validation
    
3.  **API-level**: MassData gateway validation
    
4.  **WordPress**: Nonce and capability checks
    

🎯 Use Cases
------------

*   **E-commerce Stores** - Verify customer phone numbers for order updates
    
*   **Membership Sites** - Ensure valid contact information for members
    
*   **Service Platforms** - Two-factor authentication for user accounts
    
*   **Local Businesses** - Bangladeshi phone number verification
    

🌟 Benefits
-----------

*   **Reduce Fake Registrations** - Verify genuine phone numbers
    
*   **Customer Communication** - Collect valid numbers for SMS marketing
    
*   **Enhanced Security** - Add verification layer to registration
    
*   **Brand Consistency** - Custom SMS with your company name
    
*   **User Trust** - Professional verification process
    

📞 Support & Contact
--------------------

**Developer:** Moursalin Islam

*   📧 Email: morsalinislam.net@gmail.com
    

**Company:** OnexusDev

*   🌐 Website: [onexusdev.xyz](https://onexusdev.xyz/)
    
*   📧 Email: onexusdev@gmail.com
    
*   📘 Facebook: [facebook.com/onexusdev](https://facebook.com/onexusdev)
    

_"Code The Future, Live The Dream"_

📝 Changelog
------------

### Version 4.0

*   Added footer protection system
    
*   Enhanced security features
    
*   Improved admin interface
    
*   Added live API testing
    
*   Optimized OTP handling
    

### Previous Versions

*   Basic OTP functionality
    
*   WooCommerce integration
    
*   MassData API support
    

📋 Requirements
---------------

*   WordPress 5.0 or higher
    
*   WooCommerce 4.0 or higher
*   PHP 7.2 or higher
*   cURL support enabled
*   MassData SMS account
    

⚠️ Important Notes
------------------

*   The plugin requires an active MassData SMS account
    
*   SMS charges apply as per MassData pricing
    
*   Footer credit must remain intact for functionality
    
*   Compatible with most WordPress themes
    
*   Regular updates recommended for security
    

**MassData OTP Pro** - The complete phone verification solution for WooCommerce stores, built with love by OnexusDev team.
