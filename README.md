# WPiko Chatbot Pro - GitHub Updates Repository

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)

This repository hosts the **WPiko Chatbot Pro** plugin and provides automatic updates through GitHub Releases. This is the premium add-on for the WPiko Chatbot WordPress plugin, offering advanced AI-powered features for enhanced website interactions.

## 🤖 About WPiko Chatbot Pro

WPiko Chatbot Pro is a premium WordPress plugin that seamlessly integrates OpenAI's powerful language models into websites, providing an intelligent and highly customizable chat interface. It extends the base WPiko Chatbot plugin with advanced features for professional websites and e-commerce stores.

### Core Features

- **AI Configuration Management** - Complete control over OpenAI Assistants with custom training and instructions
- **Advanced Analytics** - Detailed conversation insights, user tracking, and performance metrics
- **WooCommerce Integration** - Product cards, shopping assistance, and e-commerce optimization
- **Conversation Management** - Full conversation history, user details, and interaction tracking
- **Contact Form System** - Lead generation with file attachments and reCAPTCHA protection
- **Website Content Scanning** - Automatic training from your website content
- **Multi-deployment Options** - Floating chatbot and shortcode embedding

## 🚀 Installation & Updates

### Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- **WPiko Chatbot** base plugin (required)
- Valid license key for premium features

### Automatic Updates

The plugin automatically checks for updates from this GitHub repository every 12 hours. Updates are delivered through WordPress's native update system.

#### For Administrators:
1. Navigate to your WordPress admin dashboard
2. Go to **WPiko Chatbot > License Activation**
3. View the GitHub Update Status widget
4. Click "Force Update Check" for immediate update verification

#### Manual Update Check:
```bash
# From your WordPress admin, you can force an update check
# This clears the update cache and checks for new releases
```

## 📋 System Requirements

### WordPress Environment
- **WordPress Version:** 5.0 or newer
- **PHP Version:** 7.4 or newer  
- **Base Plugin:** WPiko Chatbot (latest version)

### External Services
- **OpenAI API:** Required for AI functionality
- **WooCommerce:** Optional (for e-commerce features)

## ⚙️ Configuration

### GitHub Update Configuration

The plugin uses a configuration file to manage GitHub updates:

```php
// includes/github-config.php
return array(
    'github_user' => 'username',
    'github_repo' => 'repo',
    'check_frequency' => 12 // Hours between update checks
);
```

### License Activation

1. Purchase a license from [WPiko.com](https://wpiko.com)
2. Navigate to **WPiko Chatbot > License Activation**
3. Enter your license key
4. Activate to unlock premium features

## 🔧 Development & Releases

### Release Process

This repository uses an automated release system:

1. **Version Management** - Semantic versioning (X.Y.Z format)
2. **Automated Tagging** - Git tags trigger new releases
3. **WordPress Integration** - Updates appear in WordPress admin
4. **Release Notes** - Detailed changelogs for each version

### Creating a New Release

For maintainers, use the included release script:

```bash
# Run the release script
./release.sh

# Follow the prompts to:
# 1. Set new version number
# 2. Update plugin files
# 3. Create Git tag
# 4. Push to repository
# 5. Create GitHub release
```

### Version History

All releases are tracked through GitHub Releases with detailed changelogs:

- **Bug Fixes** - Security patches and error corrections
- **New Features** - Enhanced functionality and capabilities  
- **Improvements** - Performance optimizations and UX enhancements
- **Technical Changes** - Backend updates and system improvements

## 📊 Premium Features

### Advanced Analytics Dashboard
- Real-time conversation metrics and user insights
- Interactive charts with period-over-period comparisons
- User location tracking and device usage statistics
- Peak activity analysis and engagement patterns

### Enhanced User Interaction
- **Email Capture System** - Lead generation before chat sessions
- **Contact Form Integration** - Direct messaging with file attachments
- **Sound Notifications** - Audio feedback for new messages
- **AI-Powered Responses** - Enhanced text processing and replies

### WooCommerce Integration
- **Product Cards** - Visual product presentation in chat
- **Shopping Assistance** - AI-powered product recommendations
- **Cart Integration** - Seamless e-commerce experience
- **Inventory Awareness** - Real-time product availability

### Content Management
- **Website Scanning** - Automatic content extraction for training
- **Q&A Builder** - Custom knowledge base creation
- **Conversation Cleanup** - Automated data management
- **Transcript Generation** - Beautifully formatted conversation exports

## 🔒 Security & Privacy

### Data Protection
- **Encrypted Storage** - API keys and sensitive data encryption
- **Secure Transmission** - HTTPS-only communications
- **WordPress Standards** - Nonce verification and sanitization
- **User Privacy** - GDPR-compliant data handling

### Update Security
- **Verified Downloads** - GitHub-hosted release verification
- **Secure Updates** - Authenticated download process
- **Backup Recommendations** - Pre-update safety measures

## 📞 Support & Documentation

### Getting Help
- **Documentation:** [wpiko.com/docs/chatbot](https://wpiko.com/docs/chatbot/)
- **Support Portal:** Available to licensed users
- **Community:** WordPress.org forums
- **Updates:** This GitHub repository

### Troubleshooting

#### Update Issues
1. Check your license status in WordPress admin
2. Test your site's external connectivity
4. Force an update check from the admin panel

#### Common Solutions
- **Connection Errors:** Check firewall and hosting restrictions
- **License Problems:** Verify key validity and expiration
- **Plugin Conflicts:** Test with other plugins deactivated

## 🤝 Contributing

This is a commercial plugin repository. For feature requests or bug reports:

1. **Licensed Users:** Use the support portal
2. **General Issues:** Contact through official channels
3. **Security Issues:** Report privately to support@wpiko.com

## 📄 License & Legal

### Plugin License
- **License:** GPL-2.0+
- **Commercial Use:** Requires valid license key
- **Distribution:** Licensed users only

### Third-Party Services
- **OpenAI API:** Subject to OpenAI terms of service
- **GitHub:** Used for update delivery
- **WordPress.org:** Base platform requirements

---

## 🏷️ Latest Release

Check the [Releases](../../releases) section for the most recent version, changelog, and download links.

**Current Version:** See plugin header or admin dashboard for installed version.

---

*WPiko Chatbot Pro is developed by [WPiko](https://wpiko.com) - Enhancing WordPress websites with intelligent AI interactions.*
