=== NitroLiteBox ===
Contributors: nitrolitebox
Tags: lightbox, gallery, images, fullscreen, scrolling lightbox
Requires at least: 5.5
Tested up to: 6.9
Stable tag: 1.1.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A blazing-fast, zero-dependency scrolling lightbox for full-size images. Works with any WordPress theme or Oxygen Builder. No setup required.

== Description ==

**NitroLiteBox** gives every linked image on your site an instant full-screen lightbox — no jQuery, no icon fonts, no external requests. Just two tiny files (CSS + JS) that load once and stay out of the way.

= Why NitroLiteBox? =

* **Zero dependencies** — pure vanilla JS, nothing extra to load.
* **Auto-detect** — wraps any `<a href="image.jpg"><img></a>` automatically, exactly as WordPress outputs them.
* **Oxygen Builder ready** — fires a re-init hook after Oxygen's dynamic render.
* **Scrolling gallery** — group images by CSS class, `rel`, or `data-nlb-group` and swipe/arrow through them.
* **Pan & zoom** — scroll-wheel, double-click, pinch-to-zoom, drag-to-pan with pointer-anchored zooming.
* **Keyboard + touch** — Arrow keys, Escape, and left/right swipe all work out of the box.
* **Preloads** adjacent images in the background so navigation feels instant.
* **GPU-only animations** — smooth 60 fps on mobile; respects `prefers-reduced-motion`.
* **Accessible** — `role="dialog"`, `aria-modal`, focus management, focus-visible ring.
* **Shortcode** — `[nitrolitebox]` for full control over any image.

= Works with =

Standard WordPress themes (Twenty Twenty-Five, Astra, GeneratePress, Kadence, Blocksy, OceanWP, etc.), Oxygen Builder, Elementor, Beaver Builder, Bricks, and any page builder that outputs standard linked images.

= Shortcode example =

`[nitrolitebox src="https://example.com/full.jpg" thumb="https://example.com/thumb.jpg" caption="My caption" group="gallery1"]`

= Developer API =

```js
// Open programmatically
NitroLiteBox.open('https://example.com/image.jpg');

// Open a gallery
NitroLiteBox.open([
  { src: 'https://example.com/a.jpg', caption: 'First' },
  { src: 'https://example.com/b.jpg', caption: 'Second' },
]);

// Re-scan after dynamic content loads
NitroLiteBox.refresh();
// or dispatch the custom event:
document.dispatchEvent(new CustomEvent('nlb:refresh'));
```

== Installation ==

1. Upload the `nitrolitebox` folder to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.
3. That's it — linked images become lightboxes automatically.

For settings, visit **Settings → NitroLiteBox**.

== Frequently Asked Questions ==

= Will it conflict with my theme's existing lightbox? =

NitroLiteBox only activates on links it detects. If your theme ships its own lightbox and already handles those links, disable auto-detect in Settings and use `data-nitrolitebox` attributes or the shortcode on specific elements instead.

= Does it work with Oxygen Builder galleries? =

Yes. When Oxygen is active the plugin fires an extra re-init after the page renders. Oxygen's gallery links already follow the standard `<a href="full.jpg"><img></a>` pattern, so NitroLiteBox picks them up automatically.

= How do I group images into a scrolling gallery? =

Three ways: add a shared CSS class like `nlb-group-portfolio` (new in 1.1), use `data-nlb-group="my-set"`, or let WordPress add the `rel` attribute automatically for gallery blocks.

= Can I open the lightbox with JavaScript? =

Yes: `NitroLiteBox.open('https://example.com/image.jpg')` or pass an array of objects with `src` and `caption` keys.

== Screenshots ==

1. Lightbox open on a single image with caption and close button.
2. Gallery browsing with image counter and arrow navigation.
3. Settings page under Settings → NitroLiteBox.

== Changelog ==

= 1.1.2 =
* Fixed plugin checker: added `/* translators: */` comments above all `esc_html__()` calls with placeholders in `class-updater.php`.
* Fixed plugin checker: wrapped updater notice `$msg` in `wp_kses_post()`.
* Fixed plugin checker: wrapped `$banner_url` and `$banner_2x` in `esc_url()` at output point.
* Fixed Nitroshock footer "Visit Plugin Site" link pointing to wrong URL when multiple Nitroshock plugins are active — renamed banner function to `nitrolitebox_render_nitroshock_banner()` to prevent `function_exists` collision.

= 1.1.0 =
* Added full pan & zoom -- scroll-wheel, double-click, pinch-to-zoom, drag-to-pan.
* Added class-based gallery grouping -- any shared `nlb-group-*` class auto-groups images.
* Added zoom toggle button (magnifier icon, top-right of lightbox).
* Added keyboard shortcuts: +/- zoom, 0 reset zoom.
* Esc now resets zoom first before closing.
* Navigation arrows dim and disable while zoomed.
* Added `NitroLiteBox.setZoom()` and `NitroLiteBox.resetZoom()` to the JS API.
* Settings: added Zoom enable/disable, Max zoom level, Group class prefix fields.
* PHP: added `zoom`, `zoomMax`, `zoomStep`, `groupClassPrefix` to options.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Adds pan & zoom, class-based gallery grouping, zoom button, and keyboard zoom shortcuts.

= 1.0.0 =
Initial release.