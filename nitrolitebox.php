<?php
/**
 * Plugin Name:       NitroLiteBox
 * Plugin URI:        https://nitroshock.ai/nitrolitebox/
 * Description:       A blazing-fast, zero-dependency lightbox for full-size images. Works with any WordPress theme or Oxygen Builder. Auto-detects linked images — no setup needed.
 * Version:           1.1.2
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Nitroshock
 * Author URI:        https://nitroshock.ai/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nitrolitebox
 */

defined( 'ABSPATH' ) || exit;

define( 'NLB_VERSION',     '1.1.2' );
define( 'NLB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'NLB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Self-hosted updater.
require_once NLB_PLUGIN_DIR . 'includes/class-updater.php';
new NitroLiteBox_Updater( __FILE__, NLB_VERSION );

// Reusable Nitroshock promo banner (shared across all Nitroshock plugins).
require_once NLB_PLUGIN_DIR . 'includes/nitroshock-banner.php';
define( 'NS_BANNER_PLUGIN_URL', NLB_PLUGIN_URL );

/* -----------------------------------------------------------------------
 * Assets — CSS loaded async (non-render-blocking). JS deferred in footer.
 * ----------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'nlb_enqueue_assets' );
function nlb_enqueue_assets() {
    if ( is_admin() ) {
        return;
    }

    wp_enqueue_style(
        'nitrolitebox',
        NLB_PLUGIN_URL . 'assets/css/nitrolitebox.css',
        [],
        NLB_VERSION
    );

    wp_enqueue_script(
        'nitrolitebox',
        NLB_PLUGIN_URL . 'assets/js/nitrolitebox.js',
        [], // zero dependencies — no jQuery
        NLB_VERSION,
        [ 'strategy' => 'defer', 'in_footer' => true ]
    );

    // Pass user-configurable options to JS
    $options = nlb_get_options();
    wp_localize_script( 'nitrolitebox', 'nlbConfig', $options );
}

/* -----------------------------------------------------------------------
 * Non-render-blocking CSS — rewrite the <link> tag to use rel="preload".
 * The lightbox overlay starts hidden (opacity:0 / pointer-events:none) so
 * there is zero visual impact if the stylesheet arrives after first paint.
 * A <noscript> fallback covers JS-disabled browsers.
 * ----------------------------------------------------------------------- */
add_filter( 'style_loader_tag', 'nlb_async_stylesheet', 10, 2 );
function nlb_async_stylesheet( $html, $handle ) {
    if ( 'nitrolitebox' !== $handle ) {
        return $html;
    }

    preg_match( "/href='([^']+)'/", $html, $matches );
    if ( empty( $matches[1] ) ) {
        return $html; // safety fallback
    }
    $href = esc_url( $matches[1] );

    return sprintf(
        '<link rel="preload" href="%1$s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n" .
        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- This IS the enqueued stylesheet; the filter rewrites its tag for async loading. The noscript fallback is required for accessibility.
        '<noscript><link rel="stylesheet" href="%1$s"></noscript>' . "\n",
        $href
    );
}

/* -----------------------------------------------------------------------
 * Options — sensible defaults, easily filterable.
 * ----------------------------------------------------------------------- */
function nlb_get_options() {
    $defaults = [
        'autoInit'         => true,
        'selector'         => '',
        'captionSource'    => 'auto',  // 'auto' | 'title' | 'alt' | 'none'
        'loop'             => true,
        'keyboard'         => true,
        'swipe'            => true,
        'zoomIn'           => true,
        'zoom'             => true,
        'zoomMax'          => 4,
        'zoomStep'         => 0.4,
        'groupClassPrefix' => 'nlb-group-',
        'counterPos'       => 'top',   // 'top' | 'bottom' | 'none'
        'bgColor'          => 'rgba(0,0,0,0.94)',
        'accentColor'      => '#ffffff',
    ];

    /**
     * Filter nitrolitebox config passed to JavaScript.
     *
     * @param array $options Default option values.
     */
    return apply_filters( 'nitrolitebox_options', $defaults );
}

/* -----------------------------------------------------------------------
 * Oxygen Builder compatibility — re-init after dynamic render.
 * ----------------------------------------------------------------------- */
add_action( 'wp_footer', 'nlb_oxygen_compat', 99 );
function nlb_oxygen_compat() {
    if ( ! defined( 'CT_VERSION' ) && ! class_exists( 'Oxygen_VSB_Backend' ) ) {
        return;
    }
    ?>
    <script>
    /* NitroLiteBox — Oxygen Builder re-init after dynamic render */
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof NitroLiteBox !== 'undefined') {
            NitroLiteBox.refresh();
        }
    });
    /* Re-scan if Oxygen fires a custom 'oxygen-done' event */
    document.addEventListener('oxygen-done', function () {
        if (typeof NitroLiteBox !== 'undefined') {
            NitroLiteBox.refresh();
        }
    });
    </script>
    <?php
}

/* -----------------------------------------------------------------------
 * Shortcode: [nitrolitebox src="https://..." caption="..." group="set1"]
 * ----------------------------------------------------------------------- */
add_shortcode( 'nitrolitebox', 'nlb_shortcode' );
function nlb_shortcode( $atts ) {
    $a = shortcode_atts( [
        'src'     => '',
        'thumb'   => '',
        'caption' => '',
        'group'   => '',
        'class'   => '',
        'width'   => '',
        'height'  => '',
    ], $atts, 'nitrolitebox' );

    if ( empty( $a['src'] ) ) {
        return '';
    }

    $thumb   = ! empty( $a['thumb'] ) ? esc_url( $a['thumb'] ) : esc_url( $a['src'] );
    $src     = esc_url( $a['src'] );
    $caption = esc_attr( $a['caption'] );
    $group   = ! empty( $a['group'] ) ? esc_attr( $a['group'] ) : 'nlb-sc-' . get_the_ID();
    $class   = 'nitrolitebox-link ' . esc_attr( $a['class'] );
    $w_attr  = $a['width']  ? ' width="'  . absint( $a['width']  ) . '"' : '';
    $h_attr  = $a['height'] ? ' height="' . absint( $a['height'] ) . '"' : '';

    return sprintf(
        '<a href="%s" data-nitrolitebox data-nlb-group="%s" data-nlb-caption="%s" class="%s">'
        . '<img src="%s" alt="%s"%s%s loading="lazy" class="nitrolitebox-thumb">'
        . '</a>',
        $src, $group, $caption, $class,
        $thumb, $caption, $w_attr, $h_attr
    );
}

/* -----------------------------------------------------------------------
 * REST endpoint — lets JS fetch attachment caption/alt by URL.
 * ----------------------------------------------------------------------- */
add_action( 'rest_api_init', 'nlb_register_rest_route' );
function nlb_register_rest_route() {
    register_rest_route( 'nitrolitebox/v1', '/caption', [
        'methods'             => 'GET',
        'callback'            => 'nlb_rest_caption',
        'permission_callback' => '__return_true',
        'args'                => [
            'url' => [ 'required' => true, 'sanitize_callback' => 'esc_url_raw' ],
        ],
    ] );
}

function nlb_rest_caption( WP_REST_Request $request ) {
    $url = $request->get_param( 'url' );
    $id  = attachment_url_to_postid( $url );
    if ( ! $id ) {
        $url_no_size = preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $url );
        $id = attachment_url_to_postid( $url_no_size );
    }
    if ( ! $id ) {
        return new WP_REST_Response( [ 'caption' => '', 'alt' => '', 'title' => '' ] );
    }
    return new WP_REST_Response( [
        'caption' => wp_get_attachment_caption( $id ),
        'alt'     => get_post_meta( $id, '_wp_attachment_image_alt', true ),
        'title'   => get_the_title( $id ),
    ] );
}

/* -----------------------------------------------------------------------
 * Settings page.
 * ----------------------------------------------------------------------- */
add_action( 'admin_menu', 'nlb_admin_menu' );
function nlb_admin_menu() {
    add_options_page(
        __( 'NitroLiteBox Settings', 'nitrolitebox' ),
        'NitroLiteBox',
        'manage_options',
        'nitrolitebox',
        'nlb_settings_page'
    );
}

add_action( 'admin_init', 'nlb_register_settings' );
function nlb_register_settings() {
    register_setting( 'nitrolitebox_settings', 'nlb_options', [
        'sanitize_callback' => 'nlb_sanitize_options',
        'default'           => [],
    ] );
}

function nlb_sanitize_options( $input ) {
    $clean = [];
    $clean['autoInit']         = ! empty( $input['autoInit'] );
    $clean['loop']             = ! empty( $input['loop'] );
    $clean['keyboard']         = ! empty( $input['keyboard'] );
    $clean['swipe']            = ! empty( $input['swipe'] );
    $clean['zoomIn']           = ! empty( $input['zoomIn'] );
    $clean['zoom']             = ! empty( $input['zoom'] );
    $clean['zoomMax']          = isset( $input['zoomMax'] ) ? max( 2, min( 10, (float) $input['zoomMax'] ) ) : 4;
    $clean['groupClassPrefix'] = isset( $input['groupClassPrefix'] )
                                 ? sanitize_html_class( $input['groupClassPrefix'] )
                                 : 'nlb-group-';
    $clean['captionSource']    = in_array( $input['captionSource'] ?? '', [ 'auto', 'title', 'alt', 'none' ] )
                                 ? $input['captionSource'] : 'auto';
    $clean['counterPos']       = in_array( $input['counterPos'] ?? '', [ 'top', 'bottom', 'none' ] )
                                 ? $input['counterPos'] : 'top';
    $clean['selector']         = sanitize_text_field( $input['selector'] ?? '' );
    return $clean;
}

function nlb_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $opts       = wp_parse_args( get_option( 'nlb_options', [] ), nlb_get_options() );
    $banner_url = esc_url( NLB_PLUGIN_URL . 'assets/images/nlb-banner.png' );
    $banner_2x  = esc_url( NLB_PLUGIN_URL . 'assets/images/nlb-banner-2x.png' );
    $docs_url   = 'https://nitroshock.ai/docs/nitrolitebox/';

    // Inline SVG icons — monochrome, inherits currentColor.
    $icon_detection = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
    $icon_behaviour = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
    $icon_shortcode = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
    $icon_docs      = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
    $icon_globe     = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
    ?>
    <style>
    /* ── NitroLiteBox admin page ─────────────────────────────────────── */
    #nlb-settings-wrap { max-width: 860px; }

    /* Banner — bleed to edges of .wrap (counters WP's 20px right margin + 2px left) */
    .nlb-banner-wrap {
        display: block;
        line-height: 0;
        margin: -10px -20px 20px -2px;
        overflow: hidden;
        border-radius: 0;
        border: none;
    }
    .nlb-banner-wrap img { display: block; width: 100%; height: auto; }

    /* Page header row */
    .nlb-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .nlb-page-header h1 {
        margin: 0;
        padding: 0;
        font-size: 21px;
        font-weight: 600;
        color: #1d2327;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .nlb-version-badge {
        font-size: 10px;
        font-weight: 600;
        color: #fff;
        background: #3c434a;
        padding: 3px 7px;
        border-radius: 3px;
        letter-spacing: 0.05em;
        font-family: monospace;
    }
    .nlb-header-links { display: flex; gap: 8px; align-items: center; }
    .nlb-header-links a {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: #50575e;
        text-decoration: none;
        padding: 5px 11px;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        background: #fff;
        transition: border-color 0.15s, color 0.15s;
        line-height: 1;
    }
    .nlb-header-links a svg { flex-shrink: 0; }
    .nlb-header-links a:hover { color: #135e96; border-color: #72aee6; }

    /* Settings card */
    .nlb-settings-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .nlb-card-header {
        padding: 13px 20px 12px;
        border-bottom: 1px solid #f0f0f1;
        display: flex;
        align-items: center;
        gap: 9px;
        background: #f9f9f9;
    }
    .nlb-card-header h2 {
        margin: 0;
        padding: 0;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
        border: none;
        line-height: 1;
    }
    .nlb-card-icon {
        width: 24px;
        height: 24px;
        border-radius: 4px;
        background: #fff;
        border: 1px solid #dcdcde;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #50575e;
        flex-shrink: 0;
    }

    /* form-table tweaks */
    .nlb-settings-card .form-table { margin: 0; }
    .nlb-settings-card .form-table th {
        padding: 13px 20px;
        font-size: 13px;
        color: #3c434a;
        font-weight: 600;
        width: 200px;
        vertical-align: top;
        padding-top: 15px;
    }
    .nlb-settings-card .form-table td {
        padding: 12px 20px 12px 0;
        font-size: 13px;
    }
    .nlb-settings-card .form-table tr + tr td,
    .nlb-settings-card .form-table tr + tr th {
        border-top: 1px solid #f6f7f7;
    }
    .nlb-settings-card .form-table input[type="text"],
    .nlb-settings-card .form-table input[type="number"],
    .nlb-settings-card .form-table select { border-radius: 4px; }

    /* Checkbox group */
    .nlb-check-group label {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        font-size: 13px;
        color: #3c434a;
        cursor: pointer;
        line-height: 1.4;
    }
    .nlb-check-group label:last-child { margin-bottom: 0; }

    /* Shortcode display */
    .nlb-sc-block {
        background: #f6f7f7;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        padding: 12px 14px;
        font-family: Consolas, Monaco, monospace;
        font-size: 12.5px;
        color: #2c3338;
        line-height: 1.65;
        display: block;
        white-space: pre;
        overflow-x: auto;
        max-width: 100%;
    }

    /* Save bar */
    .nlb-save-bar {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        background: #f6f7f7;
        border: 1px solid #c3c4c7;
        border-radius: 6px;
        margin-bottom: 8px;
    }
    .nlb-save-bar .button-primary { height: 32px; line-height: 30px; padding: 0 16px; font-size: 13px; }
    .nlb-save-bar .nlb-save-note { font-size: 12px; color: #8c8f94; }
    </style>

    <div class="wrap" id="nlb-settings-wrap">

        <!-- Banner -->
        <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener" class="nlb-banner-wrap">
            <img
                src="<?php echo esc_url( $banner_url ); ?>"
                srcset="<?php echo esc_url( $banner_url ); ?> 1x, <?php echo esc_url( $banner_2x ); ?> 2x"
                alt="NitroLiteBox — Zero-Dependency Image Lightbox"
                width="772" height="250"
            >
        </a>

        <!-- Page header -->
        <div class="nlb-page-header">
            <h1>
                <?php esc_html_e( 'NitroLiteBox Settings', 'nitrolitebox' ); ?>
                <span class="nlb-version-badge">v<?php echo esc_html( NLB_VERSION ); ?></span>
            </h1>
            <div class="nlb-header-links">
                <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener">
                    <?php echo $icon_docs; // phpcs:ignore WordPress.Security.EscapeOutput ?> Documentation
                </a>
                <a href="https://nitroshock.ai/" target="_blank" rel="noopener">
                    <?php echo $icon_globe; // phpcs:ignore WordPress.Security.EscapeOutput ?> Nitroshock.ai
                </a>
            </div>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields( 'nitrolitebox_settings' ); ?>

            <!-- Detection & Display -->
            <div class="nlb-settings-card">
                <div class="nlb-card-header">
                    <span class="nlb-card-icon"><?php echo $icon_detection; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <h2><?php esc_html_e( 'Detection &amp; Display', 'nitrolitebox' ); ?></h2>
                </div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-detect images', 'nitrolitebox' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="nlb_options[autoInit]" value="1" <?php checked( $opts['autoInit'] ); ?>>
                                <?php esc_html_e( 'Automatically lightbox all linked images in content', 'nitrolitebox' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Extra selectors', 'nitrolitebox' ); ?></th>
                        <td>
                            <input type="text" name="nlb_options[selector]" value="<?php echo esc_attr( $opts['selector'] ); ?>" class="regular-text" placeholder=".my-gallery a, .hero-img">
                            <p class="description"><?php esc_html_e( 'Additional CSS selectors to lightbox (comma-separated).', 'nitrolitebox' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Caption source', 'nitrolitebox' ); ?></th>
                        <td>
                            <select name="nlb_options[captionSource]">
                                <?php foreach ( [ 'auto' => 'Auto (title > alt > figcaption)', 'title' => 'Title attribute', 'alt' => 'Alt attribute', 'none' => 'None' ] as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['captionSource'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Counter position', 'nitrolitebox' ); ?></th>
                        <td>
                            <select name="nlb_options[counterPos]">
                                <?php foreach ( [ 'top' => 'Top', 'bottom' => 'Bottom', 'none' => 'Hidden' ] as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['counterPos'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Position of the "1 / 4" gallery counter.', 'nitrolitebox' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Behaviour -->
            <div class="nlb-settings-card">
                <div class="nlb-card-header">
                    <span class="nlb-card-icon"><?php echo $icon_behaviour; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <h2><?php esc_html_e( 'Behaviour', 'nitrolitebox' ); ?></h2>
                </div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Options', 'nitrolitebox' ); ?></th>
                        <td>
                            <div class="nlb-check-group">
                            <?php foreach ( [
                                'loop'     => 'Loop through gallery (wrap around at ends)',
                                'keyboard' => 'Keyboard navigation (&larr; &rarr; Esc)',
                                'swipe'    => 'Touch swipe navigation',
                                'zoomIn'   => 'Zoom-in entrance animation',
                                'zoom'     => 'Enable pan &amp; zoom (scroll-wheel, double-click, pinch)',
                            ] as $key => $label ) : ?>
                                <label>
                                    <input type="checkbox" name="nlb_options[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $opts[ $key ] ?? true ); ?>>
                                    <?php echo wp_kses_post( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Max zoom level', 'nitrolitebox' ); ?></th>
                        <td>
                            <input type="number" name="nlb_options[zoomMax]" value="<?php echo esc_attr( $opts['zoomMax'] ?? 4 ); ?>" min="2" max="10" step="0.5" class="small-text">
                            <span style="margin-left:5px;color:#646970;">&times;</span>
                            <p class="description"><?php esc_html_e( 'Maximum multiplier for scroll / pinch zoom (default: 4&times;).', 'nitrolitebox' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Group class prefix', 'nitrolitebox' ); ?></th>
                        <td>
                            <input type="text" name="nlb_options[groupClassPrefix]" value="<?php echo esc_attr( $opts['groupClassPrefix'] ?? 'nlb-group-' ); ?>" class="regular-text" placeholder="nlb-group-">
                            <p class="description"><?php esc_html_e( 'Links sharing a class starting with this prefix are auto-grouped. Example: class="nlb-group-portfolio"', 'nitrolitebox' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Shortcode -->
            <div class="nlb-settings-card">
                <div class="nlb-card-header">
                    <span class="nlb-card-icon"><?php echo $icon_shortcode; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <h2><?php esc_html_e( 'Shortcode', 'nitrolitebox' ); ?></h2>
                </div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Usage', 'nitrolitebox' ); ?></th>
                        <td>
<pre class="nlb-sc-block">[nitrolitebox
  src="https://example.com/full.jpg"
  thumb="https://example.com/thumb.jpg"
  caption="My photo"
  group="gallery1"]</pre>
                            <p class="description" style="margin-top:8px;"><?php esc_html_e( 'Or add data-nitrolitebox to any &lt;a&gt; tag linking directly to an image.', 'nitrolitebox' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Save bar -->
            <div class="nlb-save-bar">
                <?php submit_button( __( 'Save Settings', 'nitrolitebox' ), 'primary', 'submit', false ); ?>
                <span class="nlb-save-note"><?php esc_html_e( 'Changes take effect immediately on the frontend.', 'nitrolitebox' ); ?></span>
            </div>
        </form>

        <!-- Nitroshock promo banner (reusable include) -->
        <?php nitrolitebox_render_nitroshock_banner( NLB_PLUGIN_URL ); ?>

    </div>
    <?php
}