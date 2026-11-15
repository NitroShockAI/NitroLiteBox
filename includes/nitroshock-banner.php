<?php
/**
 * Nitroshock promotional banner - NitroLiteBox copy.
 *
 * Uses a plugin-prefixed function name to avoid fatal "Cannot redeclare"
 * errors when multiple Nitroshock plugins are active simultaneously.
 *
 * Usage: require_once NLB_PLUGIN_DIR . 'includes/nitroshock-banner.php';
 *        nitrolitebox_render_nitroshock_banner( NLB_PLUGIN_URL );
 *
 * @param string $plugin_url Plugin URL with trailing slash (used to locate assets/images/).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nitrolitebox_render_nitroshock_banner' ) ) {
	function nitrolitebox_render_nitroshock_banner( $plugin_url = '' ) {
		$logo_url = esc_url( rtrim( $plugin_url, '/' ) . '/assets/images/nitroshock-logo.png' );
		?>
		<div class="nlb-promo-card">
			<div class="nlb-promo-inner">
				<div class="nlb-promo-logo">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="Nitroshock AI" width="150" height="auto">
				</div>
				<div class="nlb-promo-content">
					<h3 class="nlb-promo-title">More from Nitroshock</h3>
					<p class="nlb-promo-desc">
						Nitroshock builds performance-first WordPress plugins and AI-powered web tools.
						Browse our free utilities, SEO tools, and premium plugins at nitroshock.ai.
					</p>
					<div class="nlb-promo-links">
						<a href="https://nitroshock.ai/plugins/" target="_blank" rel="noopener" class="nlb-promo-btn nlb-btn-primary">Browse All Plugins</a>
						<a href="https://nitroshock.ai/nitrolitebox/" target="_blank" rel="noopener" class="nlb-promo-btn nlb-btn-ghost">Visit Plugin Site &rarr;</a>
					</div>
				</div>
			</div>
		</div>

		<style>
		.nlb-promo-card {
			margin-top: 24px;
			background: #000;
			border: 1px solid rgba(255,255,255,0.09);
			border-radius: 8px;
			overflow: hidden;
		}
		.nlb-promo-inner {
			display: flex;
			align-items: center;
			gap: 28px;
			padding: 24px 28px;
		}
		.nlb-promo-logo { flex-shrink: 0; display: flex; align-items: center; }
		.nlb-promo-logo img { display: block; width: 150px; height: auto; }
		.nlb-promo-content { flex: 1; min-width: 0; }
		.nlb-promo-title {
			font-size: 14px; font-weight: 700; color: #ffffff;
			margin: 0 0 6px; padding: 0; border: none; letter-spacing: -0.01em;
		}
		.nlb-promo-desc {
			font-size: 12.5px; color: #8a9099; line-height: 1.6; margin: 0 0 14px; padding: 0;
		}
		.nlb-promo-links { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
		.nlb-promo-btn {
			display: inline-flex; align-items: center; padding: 6px 14px;
			border-radius: 4px; font-size: 12.5px; font-weight: 600;
			text-decoration: none; transition: opacity 0.15s, transform 0.15s; line-height: 1;
		}
		.nlb-promo-btn:hover { opacity: 0.85; text-decoration: none; transform: translateY(-1px); }
		.nlb-btn-primary { background: #f59e0b; color: #000 !important; }
		.nlb-btn-ghost {
			background: rgba(255,255,255,0.06); color: #b0b7c3 !important;
			border: 1px solid rgba(255,255,255,0.12);
		}
		@media (max-width: 640px) {
			.nlb-promo-inner { flex-direction: column; align-items: flex-start; }
			.nlb-promo-logo img { width: 120px; }
		}
		</style>
		<?php
	}
}
