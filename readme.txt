=== Inkline Duplicate Media ===
Contributors: inklinemedia
Tags: media, duplicate, copy, clone, media library
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Duplicate" action to the WordPress Media Library, allowing you to create an independent copy of any media file without affecting the original.

== Description ==

Inkline Duplicate Media adds a simple duplicate function to three locations in the WordPress admin:

1. **Media Library list view** — A "Duplicate" link appears in the row actions when hovering over any media item.
2. **Edit Media page** — A "Duplicate Media" meta box in the sidebar lets you copy the file you're currently viewing.
3. **Media modal** — When browsing media from a post/page editor (e.g. setting a featured image or inserting media), a "Duplicate" link appears next to "Edit Image" and "Delete Permanently".

**Why use this?**

WordPress modifies the original file when you crop or edit an image. If that image is used in multiple places, all of them are affected. This plugin lets you create a copy first, then safely edit the copy without changing the original.

**Features:**

* Copies the full-resolution file and generates all thumbnail sizes
* Carries over alt text, caption, and description
* Appends "(Copy)" to the title for easy identification
* Success notification with a link to the new copy
* No settings page — just activate and go

**Security:**

* Nonce verification on all actions
* Capability checks (requires `upload_files` permission)
* Path traversal protection — only files within the uploads directory can be duplicated
* No frontend impact — only loads in wp-admin

== Installation ==

1. Upload the `inkline-duplicate-media` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. That's it — no configuration needed. The duplicate option will appear in your Media Library.

== Frequently Asked Questions ==

= Does duplicating affect the original file? =

No. A completely independent copy is created. The original file and all its existing usage remain untouched.

= Where does the duplicated file go? =

It's saved in the same uploads directory as the original, with "-copy" appended to the filename (e.g. `photo.jpg` becomes `photo-copy.jpg`).

= Does it copy the metadata? =

Yes — alt text, caption (excerpt), and description are all copied. A new set of thumbnails is generated for the duplicate.

= Can non-admin users use this? =

Any user with the `upload_files` capability (Author role and above by default) can duplicate media.

== Screenshots ==

1. Duplicate link in Media Library list view row actions.
2. Duplicate Media meta box on the Edit Media page.
3. Duplicate link in the media modal alongside Edit Image and Delete Permanently.

== Changelog ==

= 3.1.0 =
* Security: Added per-post `edit_post` capability check (defense in depth, mirrors Enable Media Replace).
* Security: Switched from `wp_redirect` to `wp_safe_redirect` to prevent open redirects.
* Security: New filenames are now run through `sanitize_file_name()`.
* Security: Proper HTTP status codes on all `wp_die()` calls (403, 404, 500).
* Security: All user-facing strings use `esc_html__()` / `esc_html_e()`.
* Improved: Filename uniqueness now uses WordPress core `wp_unique_filename()` instead of manual loop.
* Improved: Path traversal check now appends `DIRECTORY_SEPARATOR` to prevent prefix-matching sibling directories.
* Improved: Centralized permission checks into `inkline_dm_can_duplicate()` helper.
* Improved: Centralized URL building into `inkline_dm_get_duplicate_url()` helper.
* Improved: Added `aria-label` to list view link for accessibility.
* Improved: Meta box position set to `low` to sit below core boxes, matching EMR placement.
* Improved: Meta box button changed to `button-secondary` for consistency with EMR.

= 3.0.0 =
* Rewritten: Media modal duplicate now uses the `attachment_fields_to_edit` filter (same approach as Enable Media Replace) instead of JavaScript injection. Zero JS, fully reliable.
* Removed: All JavaScript and AJAX code for the media modal — no longer needed.
* The plugin is now 100% server-side PHP with no frontend scripts.

= 2.3.1 =
* Fixed: JS error "parameter 1 is not of type Node" caused by script running before document.body exists.

= 2.3.0 =
* Rewritten: Media modal duplicate link now uses MutationObserver instead of prototype overrides for reliable injection regardless of how the modal renders.
* Fixed: Duplicate link not appearing when clicking an image in the media modal.

= 2.2.1 =
* Fixed: Removed overly strict script registration check that prevented the media modal duplicate link from loading.

= 2.2.0 =
* Added: "Duplicate" link in the media modal (post/page editor media browser) next to Edit Image and Delete Permanently.
* Added: AJAX-based duplication for the media modal with inline success/error feedback.

= 2.1.0 =
* Added: "Duplicate Media" meta box on the Edit Media page sidebar.
* Includes description text and full-width primary button.

= 2.0.0 =
* Complete rewrite — removed JavaScript-based approach in favor of server-side hooks.
* Added: "Duplicate" row action link in Media Library list view using `media_row_actions` filter.
* Added: Admin notice with link to new copy after successful duplication.
* Uses `admin-post.php` handler with nonce and capability checks.

= 1.2.1 =
* Removed screen whitelist restriction to fix button not appearing.
* Kept capability check to prevent loading for unauthorized users.

= 1.2.0 =
* Performance: Added screen whitelist to only enqueue JS on relevant admin pages.
* Performance: Added capability check to skip loading for non-uploaders.

= 1.1.1 =
* Security: Added path traversal protection using `realpath()` validation.
* Security: Capped filename uniqueness loop at 100 iterations.

= 1.1.0 =
* Moved Duplicate button placement to below "Copy URL to clipboard" in attachment details pane.

= 1.0.0 =
* Initial release with Duplicate button in the media library attachment details pane.

== Upgrade Notice ==

= 3.1.0 =
Security hardening: per-post capability checks, wp_safe_redirect, sanitize_file_name, proper HTTP status codes. Uses WordPress core wp_unique_filename. Full EMR parity on injection points.
