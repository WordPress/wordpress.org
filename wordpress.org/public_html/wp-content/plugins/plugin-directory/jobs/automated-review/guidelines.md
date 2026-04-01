# WordPress.org Plugin Directory Guidelines — Review Reference

These are the 18 official guidelines for plugin directory compliance. For each: what to check, patterns to search for, and verdict logic.

---

## Guideline 1: GPL-Compatible License

All code, data, and images must be GPL-2.0-or-later or GPL-compatible.

**Check:**
- `License` header in main plugin file
- `License` field in readme.txt
- `LICENSE` or `license.txt` file
- Third-party library licenses (`composer.json`, `package.json`, library headers)
- GPL-compatible: MIT, BSD-2-Clause, BSD-3-Clause, Apache-2.0, ISC, MPL-2.0, LGPL-*
- NOT compatible: CC-BY-NC-*, proprietary, "all rights reserved", no license declared

**Verdict:** FAIL if non-GPL-compatible code included or license missing/ambiguous.

## Guideline 2: Developer Responsibility

Developer is accountable for everything in their plugin including third-party code.

**Check:** N/A for automated review — policy acknowledgment.

**Verdict:** N/A

## Guideline 3: Stable Version Available

Plugin must be complete and functional at the version submitted.

**Check:**
- Version number exists in plugin header
- Stable tag in readme matches plugin header version
- Plugin contains actual functionality (not a placeholder)

**Verdict:** FAIL if stub/placeholder. FAIL if version missing or stable tag mismatch.

## Guideline 4: Human-Readable Code

No obfuscation. Minification acceptable with source available.

**Check:**
- `eval()` with encoded strings
- `base64_decode()` used to decode executable code
- `gzinflate()`, `gzuncompress()`, `str_rot13()` on code
- JavaScript packer patterns (`p,a,c,k,e,r`)
- Hex-encoded strings (`\x` sequences) for obfuscation
- `goto` label spaghetti
- Minified JS/CSS: acceptable IF unminified source or build config present

**Verdict:** FAIL if obfuscation detected.

## Guideline 5: No Trialware

No trial periods, payment walls, or quotas on functionality present in the code.

**Check:**
- Time-based feature lockouts (`time()`, `strtotime()` comparisons disabling features)
- License key checks that disable code already in the plugin
- Feature flags controlled by external license servers
- Exception: SaaS (G6) where external service provides real value

**Verdict:** FAIL if trialware pattern detected. WARN if license checks exist but may be legitimate.

## Guideline 6: Software-as-Service Permitted

External service connections are allowed if the service provides legitimate functionality.

**Check:**
- All external HTTP requests documented in readme
- External services provide real value (not just license checking)
- Privacy implications disclosed

**Verdict:** WARN if external requests not documented in readme. N/A if no external requests.

## Guideline 7: No Tracking Without Consent

No analytics, tracking, or data collection without explicit user opt-in.

**Check:**
- Analytics/tracking code sent without opt-in (Google Analytics, Mixpanel, etc.)
- Site data (URL, admin email, plugin list) transmitted without consent
- Usage tracking active by default
- Hidden pixels or beacon requests

**Verdict:** FAIL if tracking without opt-in found.

## Guideline 8: No Executable Code via Third Parties

No remote PHP includes, external CDNs for non-font assets, admin iframes to external sites, installing plugins from non-WP.org sources.

**Check:**
- `include`/`require` with URL paths
- External update checkers (non-WordPress.org update servers)
- `eval()` of remote content
- CDN loading of JS/CSS (except Google Fonts, web fonts)
- Admin pages loading external iframes
- Code that downloads/installs plugins from external sources
- External script/style enqueues from CDNs

**Verdict:** FAIL if any pattern detected.

## Guideline 9: Legal and Ethical Conduct

No search manipulation, fake reviews, cryptocurrency mining, etc.

**Check:**
- Cryptocurrency mining code (`CoinHive`, `coinhive`, `cryptonight`, Web Workers doing hash computations)
- SEO spam injection
- Hidden links or content
- Code manipulating WordPress.org reviews/ratings

**Verdict:** FAIL if found.

## Guideline 10: Optional Credits

"Powered by" credits must be opt-in, hidden by default.

**Check:**
- Footer credits, "powered by" links
- Default visibility state (should be hidden/off)
- Affiliate parameters in default links

**Verdict:** WARN if credits visible by default.

## Guideline 11: Dashboard Respect

No intrusive admin behavior.

**Check:**
- Admin notices without `is-dismissible` class
- Admin notices on all pages instead of plugin-specific pages
- Full-screen welcome/onboarding on activation
- Dashboard widgets that are overly promotional
- Activation redirects (acceptable if one-time)

**Verdict:** WARN if intrusive dashboard behavior detected.

## Guideline 12: README Anti-Spam

Readme must not contain spam, keyword stuffing, or excessive self-promotion.

**Check:**
- More than 5 tags (WARN), more than 12 (FAIL)
- Keyword stuffing in short description or description
- Excessive external links
- Promotional content unrelated to plugin

**Verdict:** WARN for mild issues, FAIL for blatant spam.

## Guideline 13: Use WordPress Default Libraries

Don't bundle libraries already in WordPress core.

**WordPress bundled libraries to check:**
- jQuery, jQuery UI and components
- Backbone.js, Underscore.js
- React, ReactDOM
- Lodash
- Moment.js
- TinyMCE, Plupload, Thickbox
- Check if plugin registers own copy vs using WP handle

**Verdict:** WARN if bundled copies detected.

## Guideline 14: Reasonable Commit Frequency

Applies post-publication only.

**Verdict:** N/A for pre-submission review.

## Guideline 15: Version Number Increments

Each release requires a version number increase.

**Check:**
- Version in plugin header exists and is valid format
- Version matches stable tag in readme

**Verdict:** FAIL if version missing or mismatched.

## Guideline 16: Complete Plugin at Submission

Plugin must be fully functional.

**Check:**
- Plugin has actual functionality
- Main plugin file has required headers
- Plugin does something when activated

**Verdict:** FAIL if plugin appears incomplete.

## Guideline 17: Trademark and Copyright Respect

Plugin must not misuse trademarks.

**Check:**
- Plugin name starts with trademarked term
- Slug contains trademarked terms
- Use "Block Editor" not "Gutenberg" for the editor
- Integration naming: "X for WooCommerce" not "WooCommerce X"

**Trademarked prefixes** — plugin slugs cannot start with or contain these (terms ending in `-` block slugs that start with them; others block exact matches or containment):

adobe-, adsense-, advanced-custom-fields-, adwords-, akismet-, all-in-one-wp-migration, amazon-, android-, apple-, applenews-, applepay-, aws-, azon-, bbpress-, bing-, booking-com, bootstrap-, buddypress-, chatgpt-, chat-gpt-, cloudflare-, contact-form-7-, cpanel-, disqus-, divi-, dropbox-, easy-digital-downloads-, elementor-, envato-, fbook, facebook, fb-, fb-messenger, fedex-, feedburner, firefox-, fontawesome-, font-awesome-, ganalytics-, gberg, github-, givewp-, google-, googlebot-, googles-, gravity-form-, gravity-forms-, gravityforms-, gtmetrix-, gutenberg, guten-, hubspot-, ig-, insta-, instagram, internet-explorer-, ios-, jetpack-, macintosh-, macos-, mailchimp-, microsoft-, ninja-forms-, oculus, onlyfans-, only-fans-, opera-, paddle-, paypal-, pinterest-, plugin, skype-, stripe-, tiktok-, tik-tok-, trustpilot, twitch-, twitter-, tweet, ups-, usps-, vvhatsapp, vvcommerce, vva-, vvoo, wa-, webpush-vn, wh4tsapps, whatsapp, whats-app, watson, windows-, wocommerce, woocom-, woocommerce, woocomerce, woo-commerce, woo-, wo-, wordpress, wordpess, wpress, wp-, wp-mail-smtp-, yandex-, yahoo-, yoast, youtube-, you-tube-

**For-use-only exceptions:** "woocommerce" is allowed only as a suffix in the pattern "{name}-for-woocommerce".

**Portmanteau restrictions:** "woo" cannot be combined with other trademarked terms (e.g., "woopress" is blocked).

**Commonly abused terms** (extra scrutiny during review):
apple, contact-form-7, facebook, google, instagram, ios, jetpack, jquery, microsoft, paypal, twitter, woocommerce, wordpress, yoast, youtube

**Restricted generic slugs** (high-value terms that are restricted):
booking, bookmark, cookie, gallery, lightbox, seo, sitemap, slide, social, autoblog, auto-blog, framework, library, plugin, spinning

**Verdict:** FAIL if name/slug starts with or primarily consists of a trademarked term.

## Guideline 18: Directory Maintenance Authority

WordPress.org reserves the right to enforce guidelines.

**Verdict:** N/A for automated review.
