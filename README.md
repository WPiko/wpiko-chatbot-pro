# WPiko AI Chatbot Pro

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)

Source of the **WPiko AI Chatbot Pro** plugin. Customer sites get updates from wpiko.com (WPiko Keymaster), not from this repository. This is the premium add-on for the WPiko AI Chatbot WordPress plugin, offering advanced AI-powered features for enhanced website interactions.

## 2.1.4 Updates from wpiko.com

Updates now come from wpiko.com instead of GitHub. A site asks the WPiko license server
about new versions; only a site whose license is valid and activated on it can download
them, and every update must carry the WPiko release signature before WordPress installs
it. Sites without a valid license (for example after a free trial) still see that a new
version exists, with a link to activate a license or buy one.

## 2.1.3 Conversation memory

Works with the new Conversation Memory setting in WPiko AI Chatbot 2.1.3: live chat
takeover and the contact form follow the conversation when the site remembers it across
visits, and a takeover nobody from the team looks after for 12 hours goes back to the AI.
Still works with WPiko AI Chatbot 2.1.2. See [CHANGELOG.md](CHANGELOG.md).

## 2.1.2 Admin redesign

Update both WPiko AI Chatbot and WPiko AI Chatbot Pro to 2.1.2. Pro now requires the
shared admin styles and popups provided by WPiko AI Chatbot 2.1.2 or newer. Scan Website
can now scan many pages at once, rescan changed pages and read posts and documentation
plugins (added as clean Markdown, with no AI cost),
and the Q&A Builder allows up to 50 pairs. See [CHANGELOG.md](CHANGELOG.md)
for the release notes.

## 2.1.0 Security update

Update both WPiko AI Chatbot and WPiko AI Chatbot Pro to 2.1.0. Conversation rendering
now filters unsafe HTML, including previously saved messages. Pro contact-form
buttons use delegated event listeners instead of inline JavaScript, and the PWA
shares the base plugin's message allowlist. Thanks to **Ali Hidayat** for
responsibly reporting the stored XSS vulnerability in assistant messages.

## 🤖 About WPiko AI Chatbot Pro

WPiko AI Chatbot Pro is a premium WordPress plugin that seamlessly integrates OpenAI's powerful language models into websites, providing an intelligent and highly customizable chat interface. It extends the base WPiko AI Chatbot plugin with advanced features for professional websites and e-commerce stores.

### Core Features

- **Mobile App (PWA)** - Monitor and reply to conversations from your phone without opening WordPress
- **Admin Takeover** - Pause AI and chat with users live in real-time from the mobile app or WP admin
- **Push Notifications** - Get notified instantly when a visitor sends a message
- **AI Configuration Management** - Complete control over OpenAI Assistants with custom training and instructions
- **Advanced Analytics** - Detailed conversation insights, user tracking, and performance metrics
- **WooCommerce Integration** - Product cards, shopping assistance, and e-commerce optimization
- **Conversation Management** - Full conversation history, user details, and interaction tracking
- **Contact Form System** - Lead generation with file attachments and reCAPTCHA protection
- **Website Content Scanning** - AI-written Q&A from your pages, and clean Markdown from your posts and documentation plugin articles, with bulk scanning, rescans of changed content and optional automatic updates of posts and docs
- **Multi-deployment Options** - Floating chatbot and shortcode embedding

## 🚀 Installation & Updates

### Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- **WPiko AI Chatbot** base plugin 2.1.2 or newer (required)
- Valid license key for premium features

### Automatic Updates

The plugin checks wpiko.com for updates every 12 hours (also during WordPress's background
updates). Updates are delivered through WordPress's native update system, for sites with a
valid license activated on them.

#### For Administrators:
1. Go to **WPiko AI Chatbot > License**
2. The **Plugin updates** card shows the installed and latest version
3. Click **Check for updates** to check right away

## 📋 System Requirements

### WordPress Environment
- **WordPress Version:** 6.0 or newer
- **PHP Version:** 7.4 or newer  
- **Base Plugin:** WPiko AI Chatbot (latest version)

### External Services
- **OpenAI API:** Required for AI functionality
- **WooCommerce:** Optional (for e-commerce features)

## ⚙️ Configuration

### Updates

`includes/updater.php` talks to `wpiko-keymaster/v1/update-check` and `/download` on the
license server. Before installing, the ZIP must match the server's SHA-256 and an Ed25519
signature made with a key listed in `WPiko_Chatbot_Pro_Updater::RELEASE_KEYS`. The release
workflow signs every ZIP with the `WPIKO_RELEASE_SIGNING_KEY` secret and refuses to publish
without it (or with a key that is not in `RELEASE_KEYS`).

### License Activation

1. Purchase a license from [WPiko.com](https://wpiko.com)
2. Navigate to **WPiko AI Chatbot > License Activation**
3. Enter your license key
4. Activate to unlock premium features

## 🔧 Development & Releases

### Release Process

1. Bump the version in the plugin header and `WPIKO_CHATBOT_PRO_VERSION`, add a
   `## X.Y.Z` entry to `CHANGELOG.md`, push to `main`.
2. Run **Actions → Publish Pro Release** on `main` (tick "Test only" first if you like).
   It checks the package, signs the ZIP, attaches `wpiko-chatbot-pro-X.Y.Z.zip` and
   `.zip.sig` to a GitHub release and publishes it.
3. WPiko Keymaster on wpiko.com fetches the release (hourly, or right away when the
   `KEYMASTER_PING_URL` / `KEYMASTER_PING_SECRET` secrets are set), checks it again and
   waits for **Publish** in Keymaster → Releases. Then licensed sites, new buyers and trial
   users get it.

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
- **Website Scanning** - Pages, posts and docs from any documentation plugin, with bulk scanning and change detection
- **Q&A Builder** - Up to 50 exact question-and-answer pairs
- **Conversation Cleanup** - Automated data management
- **Transcript Generation** - Beautifully formatted conversation exports

## 🔒 Security & Privacy

### Data Protection
- **Encrypted Storage** - API keys and sensitive data encryption
- **Secure Transmission** - HTTPS-only communications
- **WordPress Standards** - Nonce verification and sanitization
- **User Privacy** - GDPR-compliant data handling

### Update Security
- **License-checked downloads** - Only sites with a valid, activated license get a download link (valid 24 hours)
- **Signed updates** - Every update must carry the WPiko Ed25519 release signature and match its checksum
- **Backup Recommendations** - Pre-update safety measures

## 📞 Support & Documentation

### Getting Help
- **Documentation:** [wpiko.com/docs/chatbot](https://wpiko.com/docs/chatbot/)
- **Support Portal:** Available to licensed users
- **Community:** WordPress.org forums
- **Updates:** wpiko.com (WordPress → Plugins)

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
- **wpiko.com:** License checks and update delivery
- **WordPress.org:** Base platform requirements

---

## 🏷️ Latest Release

Check the [Releases](../../releases) section for the most recent version, changelog, and download links.

**Current Version:** See plugin header or admin dashboard for installed version.

---

*WPiko AI Chatbot Pro is developed by [WPiko](https://wpiko.com) - Enhancing WordPress websites with intelligent AI interactions.*
