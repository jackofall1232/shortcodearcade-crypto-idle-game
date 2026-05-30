=== Shortcode Arcade Crypto Idle Game ===
Contributors: jackofall1232
Author: ZillHa Games
Author URI: https://zillha.com
Donate link: https://zillha.com
Tags: game, idle game, crypto, clicker game, mining game
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 2.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A crypto-themed idle clicker game with balanced progression, prestige mechanics, and optional leaderboards.

== Description ==

**Shortcode Arcade Crypto Idle Game** is a fully-featured crypto-themed idle clicker game from **ZillHa Games** — and every single feature is **100% free**. No upgrades, no Pro version, no paywalls. Everything that was previously locked behind a premium license is now included for everyone.

Players grow a virtual crypto mining operation by clicking to generate in-game currency, purchasing upgrades, and unlocking passive income systems. The game is designed for **fair, long-term progression**, using a carefully tuned Elo scaling curve combined with a prestige ("Hard Fork") system to prevent runaway inflation and repetitive upgrade stacking.

This plugin is self-contained and runs entirely inside WordPress, making it ideal for gaming sites, crypto communities, membership sites, or any site looking to boost player engagement. Want to see it in action? Visit [ZillHa.com](https://zillha.com) for live examples and setup guides.

**Core Features:**

* **Click-to-Mine Gameplay** – Generate in-game currency through active clicking
* **Balanced Progression Curve** – Upgrade costs scale dynamically for long-term play
* **Multiple Upgrade Paths** – Unlock and stack production upgrades
* **Prestige System ("Hard Fork")** – Reset progress for permanent production bonuses
* **Auto-Save** – Progress saves automatically at regular intervals
* **Offline Progress** – Earn limited passive income while away
* **Modern UI** – Clean, game-focused interface
* **Mobile Responsive** – Fully playable on desktop, tablet, and mobile

**Optional Advanced Features:**

* **Cloud Saves** – Store player progress in the WordPress database (login required)
* **Leaderboards** – Rank players using prestige-weighted scores
* **Ad Integration** – Optional ad placement via shortcode attribute or admin Ad Space
* **REST API** – Public endpoints for leaderboard data

**Gameplay Settings:**

* **Difficulty Level** - Easy (0.6x) / Medium (0.8x) / Hard (1.0x) intensity
* **Allow Player Difficulty** - Let players choose difficulty (creates per-difficulty leaderboards)
* **Button Mode** - 1 (standard), 2 (1 real + 1 decoy), 3 (1 real + 2 decoys) anti-bot protection
* **Movement Trigger** - None / Click / Timer / Both (button position swapping)
* **Enable Self-Reset** - Let players start a new run keeping prestige and best score
* **Ad Space** - Optional ad HTML placement within the game

**Shortcodes:**

Display the game:
`[sacig_crypto_idle_game]`

Display the game with custom ad code:
`[sacig_crypto_idle_game ad_code="<your ad network code>"]`

Display the leaderboard (requires cloud saves):
`[sacig_crypto_idle_leaderboard]`

Display a branded login form:
`[sacig_crypto_idle_login]`

Display a branded registration form:
`[sacig_crypto_idle_register]`

**Cloud Saves & Leaderboards:**

When enabled in **Settings → Crypto Idle Game**, cloud saves allow you to:

- Store player progress in your WordPress database
- Require user login for saving/loading games
- Enable competitive leaderboards
- Keep all player data on your own server

== External Services ==

This plugin uses the following external services:

**Google Fonts CDN**

* Service: Google Fonts API
* Purpose: Loads custom fonts (Orbitron, Rajdhani) for game UI styling
* Endpoint: https://fonts.googleapis.com/
* Privacy Policy: https://policies.google.com/privacy
* Data Shared: Your IP address and browser information when loading fonts
* When Used: Only on pages where the game shortcode is displayed
* User Choice: No opt-out available (required for proper game display)

All font requests are made directly from the user's browser to Google's servers. No personal data is collected or stored by this plugin.

**AI Storyline (Optional)**

The AI Storyline feature is optional and disabled by default. When enabled:

* Service: Anthropic Claude Haiku 4.5, OpenAI GPT-4o Mini / GPT-5 Mini, or xAI Grok 4.1
* Purpose: Generates short narrative popup messages when players hit upgrade milestones
* Data Shared: Only the event type and upgrade name are sent (no user data, no personal information)
* When Used: Only when a player triggers an upgrade or prestige event and AI Storyline is enabled
* API Keys: Stored in WordPress options, never exposed to the frontend
* Caching: Responses cached for 24 hours — minimal API calls
* Fallback: If the API call fails, the popup is silently skipped — no errors shown to players

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "Shortcode Arcade Crypto Idle Game"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `shortcodearcade-crypto-idle-game` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu

= After Installation =

1. Add `[sacig_crypto_idle_game]` to any page or post
2. (Optional) Enable cloud saves and leaderboards in plugin settings
3. (Optional) Add ad code using the shortcode attribute

== Frequently Asked Questions ==

= Does the game save progress? =

Yes. Progress is saved locally in the browser at regular intervals. Optional cloud saves can be enabled by the site administrator.

= What's the difference between local saves and cloud saves? =

**Local Saves:** Stored in the browser. No login required.  
**Cloud Saves:** Stored in the WordPress database. Login required. Enables leaderboards.

= How do I enable the leaderboard? =

1. Go to Settings → Crypto Idle Game
2. Enable Cloud Saves
3. Enable Leaderboards
4. Add `[sacig_crypto_idle_leaderboard]` to a page  

= Is the game mobile-friendly? =

Yes. The interface is fully responsive and touch-friendly.

= Where is player data stored? =

Local saves are stored in browser localStorage. Cloud saves are stored in WordPress custom database tables when enabled.

= Is it compatible with WordPress 7.0? =

Yes. Version 2.0.0 is fully tested and compatible with WordPress 7.0, with all code updated to align with modern WordPress standards and best practices.

= Does the plugin send data to external servers? =

Only if the optional AI Storyline feature is enabled. When enabled, the upgrade event type
and upgrade name are sent to your configured AI provider (Anthropic, OpenAI, or xAI).
No user data or personal information is transmitted. The feature is disabled by default.

= Which AI providers are supported? =

Claude Haiku 4.5 (Anthropic), GPT-4o Mini and GPT-5 Mini (OpenAI), and Grok 4.1 Fast (xAI).
You must supply your own API key in Settings → AI Storyline.

== Screenshots ==

1. Main game interface  
2. Upgrade progression panel  
3. Prestige ("Hard Fork") system  
4. Mobile responsive layout  
5. Leaderboard view  
6. Admin settings panel  

== Changelog ==

= 2.0.2 =
* NEW: Custom upgrade names via Branding settings (all 10 tiers)
* NEW: UI label system — game strings now localizable via PHP
* NEW: Leaderboard difficulty tabs when player difficulty is enabled
* NEW: Miner timeout — passive miners pause after 48 hours of inactivity, with an in-game Restart control
* FIXED: Branding upgrade names were hardcoded; now read from admin settings
* FIXED: Leaderboard showed flat list even when per-difficulty data existed

= 2.0.1 =
* NEW: Difficulty system (Easy/Medium/Hard) with per-difficulty leaderboards
* NEW: Player-selectable difficulty with separate competitive rankings
* NEW: Anti-bot button mode (1/2/3 buttons with decoys)
* NEW: Movement trigger system for button position swapping
* NEW: Self-reset feature preserving prestige and best scores
* NEW: Ad Space integration with sanitized HTML support
* NEW: AI Storyline wired to game frontend — popups now fire for players
* FIXED: DB schema updated with difficulty and per-difficulty best score columns
* FIXED: Cloud save now stores and returns player difficulty
* FIXED: Leaderboard supports per-difficulty filtering

= 2.0.0 =
* NEW: All features now free — no Pro version required
* NEW: Top-level admin menu with Arcade-themed UI
* NEW: Branding settings (game title, currency name, coin image, colors, footer) now apply to the live game
* NEW: Login and registration shortcodes — [sacig_crypto_idle_login] and [sacig_crypto_idle_register] — with configurable titles and redirects
* NEW: Leaderboard display settings (title, avatars, highlight color) now apply on the frontend
* NEW: Purple neon arcade admin design
* NEW: About page with shortcode reference and plugin info
* UPDATED: WordPress 7.0 compatibility confirmed
* FIXED: GitHub Actions deploy workflow env var correction

= 1.0.0 =
* Stable release
* Full WordPress 7.0 compatibility and compliance
* Code optimizations and quality improvements
* Tested and verified across all core gameplay and admin features
* Production-ready for wide distribution

= 0.4.6 - 2026-01-15 =
* Critical reviewer-risk cleanup and schema unification
* Fixed JS header and version metadata (0.4.0 → 0.4.6)
* Unified database schema column naming (total_currency → total_satoshis)
* Updated all file headers to match exact plugin branding
* Removed legacy package references and outdated comments
* Added documentation for frontend globals and localStorage usage
* No gameplay changes or data migrations required

= 0.4.5 - 2026-01-15 =
* Completed full namespace and prefixing audit for WordPress.org compliance
* Updated all shortcodes to use sacig_ prefix for clear attribution
* Standardized all CSS classes, IDs, and JavaScript functions with sacig prefix
* Renamed asset files to match plugin namespace
* Removed legacy shortcode aliases per review guidelines
* No gameplay or data changes

= 0.4.4 - 2026-01-14 =
* Completed full namespace and prefix refactor using a unique `SACIG` prefix
* Renamed internal class files to match new naming conventions
* Updated root plugin loader to reflect new structure
* Verified compliance with WordPress.org Plugin Review requirements
* No gameplay changes
* No data migrations

= 0.4.3 - 2026-01-14 =
* Refactored plugin-specific functions, classes, constants, and options to use a uniform prefix
* Resolved naming collisions identified during manual review
* Corrected plugin ZIP filename to meet WordPress.org requirements
* No gameplay changes

= 0.4.2 - 2026-01-13 =
* Renamed plugin to Shortcode Arcade Crypto Idle Game
* Updated admin and frontend UI labels
* Updated readme, plugin headers, and branding
* No gameplay changes

= 0.4.0 - 2025-01-06 =
* Stable release
* Improved progression balancing
* Added prestige scaling refinements
* Improved admin UI clarity
* No breaking changes

= 0.3.4 - 2025-01-06 =
* Gameplay balance refinements

= 0.3.3 - 2025-01-04 =
* Fixed Plugin Checker and PHPCS warnings
* Improved database handling

== Upgrade Notice ==

= 2.0.0 =
Major update — all Pro features are now 100% free. Full arcade admin UI, branding system, AI storyline, login pages, and leaderboards included at no cost. Upgrade recommended for all sites.

= 1.0.0 =
Stable release with full WordPress 7.0 compatibility. Recommended for all sites.

= 0.4.6 =
Critical reviewer cleanup - unifies database schema, updates metadata, and removes legacy branding. No gameplay or data changes.

= 0.4.5 =
Final namespace and prefix audit for WordPress.org compliance. Shortcodes updated to sacig_ prefix. Update shortcode references in your pages. No gameplay or data changes.

== Credits ==

Developed by: ZillHa Games
Website: https://zillha.com

== Privacy Policy ==

Shortcode Arcade Crypto Idle Game respects user privacy:

* Local saves are stored in browser localStorage
* Cloud saves (optional) are stored in WordPress custom tables
* No external analytics, tracking, or telemetry
* Site administrators retain full control over all stored data

Site owners are responsible for updating their privacy policy if cloud saves are enabled.
