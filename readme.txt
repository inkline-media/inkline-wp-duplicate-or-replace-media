=== Inkline Duplicate or Replace Media ===
Contributors: inklinemedia
Tags: media, duplicate, replace, copy, media library
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 4.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Duplicate and Replace actions to the WordPress Media Library. Duplicate creates an independent copy; Replace swaps the underlying file and optionally updates all URLs site-wide.

== Description ==

Inkline Duplicate or Replace Media adds two powerful actions to three locations in the WordPress admin:

1. **Media Library list view** — "Duplicate" and "Replace media" links appear in the row actions when hovering over any media item.
2. **Edit Media page** — "Duplicate Media" and "Replace Media" meta boxes in the sidebar.
3. **Media modal** — When browsing media from a post/page editor, both actions appear alongside "Edit Image" and "Delete Permanently".

**Duplicate Features:**

* Creates an independent copy of any media file
* Copies the full-resolution file and generates all thumbnail sizes
* Carries over alt text, caption, and description
* Appends "(Copy)" to the title for easy identification
* Success notification with a link to the new copy

**Replace Features:**

* Upload a new file to replace the current one in place
* Two modes: "Just replace the file" (keep URL) or "Replace and update all links" (new filename, all URLs updated site-wide)
* Side-by-side preview of current and new file with dimensions and file size
* Drag-and-drop file upload
* File size and type validation before upload
* Three date options: set to current date, keep original date, or set a custom date
* Remembers your last-used settings

**Integrations:**

* Elementor — handles slash-escaped URLs in Elementor's JSON storage and clears Elementor's file cache
* Cache flushing for W3 Total Cache, WP Super Cache, WP Engine, WP Fastest Cache, SiteGround, LiteSpeed, and Kinsta

**Security:**

* Nonce verification on all actions
* Capability checks (requires `upload_files` + `edit_post` per-attachment)
* Path traversal protection — only files within the uploads directory
* File type validation via `wp_check_filetype_and_ext()`
* SQL safety via `$wpdb->prepare()` and `$wpdb->esc_like()`
* No frontend impact — only loads in wp-admin

== Installation ==

1. Upload the `inkline-duplicate-media` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. That's it — no configuration needed. Duplicate and Replace options will appear in your Media Library.

== Frequently Asked Questions ==

= Does duplicating affect the original file? =

No. A completely independent copy is created. The original file and all its existing usage remain untouched.

= Where does the duplicated file go? =

It's saved in the same uploads directory as the original, with "-copy" appended to the filename (e.g. `photo.jpg` becomes `photo-copy.jpg`).

= What's the difference between the two replace modes? =

"Just replace the file" keeps the same filename and URL — only the file content changes. "Replace file, use new file name, and update all links" uses the new filename and updates all references across the site (post content, postmeta, termmeta, commentmeta).

= Does Replace work with Elementor? =

Yes. The plugin handles Elementor's JSON-encoded URLs (which use escaped slashes) and clears Elementor's file cache after replacement.

= Can non-admin users use this? =

Any user with the `upload_files` capability (Author role and above by default) can duplicate and replace media, provided they also have `edit_post` permission for the specific attachment.

== Screenshots ==

1. Duplicate link in Media Library list view row actions.
2. Duplicate Media meta box on the Edit Media page.
3. Duplicate link in the media modal alongside Edit Image and Delete Permanently.
4. Replace Media form with side-by-side file preview.

== Changelog ==

= 4.0.0 =
* Added: Replace Media functionality — upload a new file to replace an existing media item.
* Added: Two replace modes: "Just replace" (keep URL) and "Replace and update all links" (new filename, site-wide URL update).
* Added: Side-by-side file preview with drag-and-drop upload on the replace form.
* Added: File size and type validation before upload.
* Added: Three date options when replacing: current date, keep original, or custom date.
* Added: URL search/replace across post content, postmeta, termmeta, and commentmeta.
* Added: Serialized and JSON data handling for URL replacement.
* Added: Thumbnail size matching — maps old thumbnail sizes to nearest new sizes.
* Added: Elementor integration — handles escaped-slash URLs in JSON storage, clears Elementor file cache.
* Added: Cache flushing for W3TC, WP Super Cache, WP Engine, WP Fastest Cache, SiteGround, LiteSpeed, and Kinsta.
* Added: "Replace media" link in Media Library row actions, Edit Media sidebar meta box, and media modal.
* Added: Remembers user's last-used replace settings.
* Improved: Plugin restructured into multiple files (includes/, views/, assets/) for maintainability.
* Improved: WP 5.3+ scaled image handling via `wp_get_original_image_path()`.
* Changed: Plugin renamed from "Inkline Duplicate Media" to "Inkline Duplicate or Replace Media".

= 3.2.1 =
* Added: Minimum WordPress (6.9) and PHP (8.2) version requirements in plugin header.

= 3.2.0 =
* Added: Automatic update checking from GitHub using plugin-update-checker. Updates now appear in the WordPress dashboard like any other plugin.

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

= 4.0.0 =
Major update: adds Replace Media functionality (replace file in place, update URLs site-wide, Elementor support, cache flushing). Plugin renamed to "Inkline Duplicate or Replace Media".

= 3.2.0 =
Automatic updates from GitHub — plugin updates now appear in the WordPress dashboard.

= 3.1.0 =
Security hardening: per-post capability checks, wp_safe_redirect, sanitize_file_name, proper HTTP status codes. Uses WordPress core wp_unique_filename. Full EMR parity on injection points.
