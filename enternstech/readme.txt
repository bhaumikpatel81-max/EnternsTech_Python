=== EnternsTech ===
Contributors: enternstech
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: dark, technology, education, corporate, full-width-template, one-page

EnternsTech — From Learning to Employment.

== Description ==

EnternsTech is a modern, dark-themed WordPress theme for IT solutions and
technology training companies. The front page is powered by the Design Canvas
(DC) reactive micro-framework, delivering a rich single-page experience with
scroll-reveal animations, 3D card effects, pricing tables, a payment gateway
section, and contact/partner forms.

Key features:
* Dark design system (#05080F background, #22D3EE cyan accent)
* Design Canvas bundled front-page (no build step required)
* Scroll-triggered reveal animations
* Animated counters
* 3D mouse-tilt cards
* Pricing & combo plan tables
* Contact and partner registration forms (FormSubmit integration)
* Full WordPress admin for pages, posts, and menus
* Custom logo support
* Primary & footer navigation menus
* Footer widget area

== Installation ==

1. Download enternstech-wordpress-theme.zip
2. Log into your WordPress admin dashboard.
3. Go to Appearance › Themes › Add New › Upload Theme.
4. Choose the ZIP file and click "Install Now".
5. Click "Activate".

== Setting up the Front Page ==

1. Create a new Page (Pages › Add New) titled "Home". Leave the content empty.
2. Go to Settings › Reading.
3. Under "Your homepage displays", select "A static page".
4. Set "Homepage" to the "Home" page you just created.
5. Save Changes.

The theme will now serve the full Design Canvas site on the homepage.

== Activating FormSubmit ==

Contact and partner forms use FormSubmit (https://formsubmit.co/).
After deploying, submit any form once and click the confirmation link sent
to info@enternstech.com. Forms will then be fully operational.

== Customization ==

Content (plans, combos, pricing, team info) is stored in browser localStorage
and can be edited via the on-site admin panel (bottom-right lock icon).

For a production upgrade, migrate the localStorage data layer to a real backend
using the WordPress REST API + a plugin like WPGraphQL or a custom plugin with
Drizzle ORM + a managed Postgres database.

== Design Colors ==

Primary Accent : #22D3EE  (Cyan)
Secondary Blue : #3BA4FF
Background     : #05080F
Surface        : #0C1426
Text           : #ECF2FF
Muted          : #6B7280

== Support ==

Email: info@enternstech.com
Website: https://enternstech.com

== Changelog ==

= 1.0.0 =
* Initial release — Design Canvas bundled theme for WordPress.
