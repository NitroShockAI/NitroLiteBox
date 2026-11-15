<?php
/**
 * NitroLiteBox - Self-Hosted Updater
 *
 * Checks a NitroShock-hosted endpoint for plugin updates and injects
 * them into the WordPress update system. Users see and install updates
 * from Dashboard → Updates exactly like wordpress.org-hosted plugins.
 *
 * @package NitroLiteBox
 */

defined( 'ABSPATH' ) || exit;

class NitroLiteBox_Updater {

	/**
	 * Remote endpoint that returns update/info JSON.
	 *
	 * @var string
	 */
	const UPDATE_URL = 'https://nitroshock.ai/wp-json/nitroshock/v1/wp-updates';

	/** @var string */
	private $slug;

	/** @var string */
	private $basename;

	/** @var string */
	private $version;

	/** @var string */
	private $license_key;

	/**
	 * Cached remote response for this request cycle.
	 *
	 * @var object|false|null  null = not fetched, false = fetch failed.
	 */
	private $remote_data = null;

	/**
	 * Boot the updater.
	 *
	 * @param string $plugin_file Full path to the main plugin file.
	 * @param string $version     Current version string (fallback only).
	 */
	public function __construct( string $plugin_file, string $version ) {
		$this->basename    = plugin_basename( $plugin_file );
		$this->slug        = dirname( $this->basename );
		// Read the live Version: header so a stale constant inside a
		// shipped zip can't trigger a permanent "update available" loop.
		$this->version     = self::read_header_version( $plugin_file, $version );
		$this->license_key = get_option( 'nitrolitebox_license_key', '' );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 2 );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'maybe_force_check' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_check_notice' ] );
	}

	/* ── Manual force-check ──────────────────────────────── */

	public function maybe_force_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['nitrolitebox_force_check'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		check_admin_referer( 'nitrolitebox_force_check' );

		delete_transient( 'nitrolitebox_update_data' );
		$this->remote_data = null;
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		wp_safe_redirect( add_query_arg(
			[ 'nitrolitebox_checked' => 1 ],
			self_admin_url( 'plugins.php' )
		) );
		exit;
	}

	public function maybe_show_check_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['nitrolitebox_checked'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'plugins' ) {
			return;
		}

		$remote = $this->get_remote_data();
		$name   = 'NitroLiteBox';

		if ( $remote && ! empty( $remote->version ) && version_compare( $this->version, $remote->version, '<' ) ) {
			$class = 'notice-warning';
			/* translators: 1: plugin name, 2: new version number */
			$msg   = sprintf(
				esc_html__( '%1$s: version %2$s is available - see the row above to update.', 'nitrolitebox' ),
				esc_html( $name ), esc_html( $remote->version )
			);
		} elseif ( $remote ) {
			$class = 'notice-success';
			/* translators: 1: plugin name, 2: current version number */
			$msg   = sprintf(
				esc_html__( '%1$s is up to date (version %2$s).', 'nitrolitebox' ),
				esc_html( $name ), esc_html( $this->version )
			);
		} else {
			$class = 'notice-error';
			/* translators: %s: plugin name */
			$msg   = sprintf(
				esc_html__( '%s: could not reach the update server. Try again later.', 'nitrolitebox' ),
				esc_html( $name )
			);
		}
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			wp_kses_post( $msg )
		);
	}

	private static function read_header_version( string $plugin_file, string $fallback ): string {
		if ( ! is_readable( $plugin_file ) ) {
			return $fallback;
		}
		$fp = @fopen( $plugin_file, 'r' );
		if ( ! $fp ) {
			return $fallback;
		}
		$head = fread( $fp, 8192 );
		fclose( $fp );
		if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $head, $m ) ) {
			$v = trim( $m[1] );
			if ( $v !== '' ) {
				return $v;
			}
		}
		return $fallback;
	}

	/* ── WordPress Filter: Inject Update ────────────────── */

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->get_remote_data();
		if ( ! $remote ) {
			return $transient;
		}

		if ( isset( $remote->version ) && version_compare( $this->version, $remote->version, '<' ) ) {
			$update               = new \stdClass();
			$update->slug         = $this->slug;
			$update->plugin       = $this->basename;
			$update->new_version  = $remote->version;
			$update->url          = $remote->homepage ?? '';
			$update->package      = $remote->download_url ?? '';
			$update->tested       = ! empty( $remote->tested ) ? $remote->tested : get_bloginfo( 'version' );
			$update->requires     = $remote->requires ?? '';
			$update->requires_php = $remote->requires_php ?? '';

			if ( ! empty( $remote->icons ) ) {
				$update->icons = (array) $remote->icons;
			}
			if ( ! empty( $remote->banners ) ) {
				$update->banners = (array) $remote->banners;
			}

			$transient->response[ $this->basename ] = $update;
		} else {
			$no_update              = new \stdClass();
			$no_update->slug        = $this->slug;
			$no_update->plugin      = $this->basename;
			$no_update->new_version = $this->version;
			$no_update->url         = $remote->homepage ?? '';
			$no_update->package     = '';

			$transient->no_update[ $this->basename ] = $no_update;
		}

		return $transient;
	}

	/* ── WordPress Filter: Plugin Info Modal ─────────────── */

	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$remote = $this->get_remote_data();
		if ( ! $remote ) {
			return $result;
		}

		$info                = new \stdClass();
		$info->name          = $remote->name ?? 'NitroLiteBox';
		$info->slug          = $this->slug;
		$info->version       = $remote->version ?? $this->version;
		$info->author        = $remote->author ?? '<a href="https://nitroshock.ai">NitroShock</a>';
		$info->homepage      = $remote->homepage ?? 'https://nitroshock.ai';
		$info->requires      = $remote->requires ?? '5.5';
		$info->tested        = ! empty( $remote->tested ) ? $remote->tested : get_bloginfo( 'version' );
		$info->requires_php  = $remote->requires_php ?? '7.4';
		$info->download_link = $remote->download_url ?? '';
		$info->trunk         = $remote->download_url ?? '';
		$info->last_updated  = $remote->last_updated ?? '';

		if ( ! empty( $remote->sections ) ) {
			$info->sections = (array) $remote->sections;
		} else {
			$info->sections = [
				'description' => $remote->description ?? '',
				'changelog'   => $remote->changelog ?? '',
			];
		}
		if ( ! empty( $remote->icons ) ) {
			$info->icons = (array) $remote->icons;
		}
		if ( ! empty( $remote->banners ) ) {
			$info->banners = (array) $remote->banners;
		}

		return $info;
	}

	/* ── Clear Cache After Update ────────────────────────── */

	public function clear_cache( $upgrader, $options ) {
		if (
			isset( $options['action'], $options['type'] ) &&
			$options['action'] === 'update' &&
			$options['type'] === 'plugin'
		) {
			$plugins = $options['plugins'] ?? [];
			if ( in_array( $this->basename, $plugins, true ) ) {
				$this->remote_data = null;
				delete_transient( 'nitrolitebox_update_data' );
			}
		}
	}

	/* ── Plugin Row Meta ─────────────────────────────────── */

	public function plugin_row_meta( $meta, $file ) {
		if ( $file !== $this->basename ) {
			return $meta;
		}
		$meta[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'plugins.php?nitrolitebox_force_check=1' ), 'nitrolitebox_force_check' ) ),
			esc_html__( 'Check for updates', 'nitrolitebox' )
		);
		return $meta;
	}

	/* ── Remote Data Fetching ────────────────────────────── */

	private function get_remote_data() {
		if ( $this->remote_data !== null ) {
			return $this->remote_data;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$force = ! empty( $_GET['nitrolitebox_force_check'] ) &&
		         wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'nitrolitebox_force_check' );

		if ( ! $force ) {
			$cached = get_transient( 'nitrolitebox_update_data' );
			if ( $cached !== false ) {
				$this->remote_data = $cached;
				return $this->remote_data;
			}
		}

		$response = wp_remote_get( self::UPDATE_URL, [
			'timeout' => 15,
			'headers' => [
				'X-Plugin-Slug'    => $this->slug,
				'X-Plugin-Version' => $this->version,
				'X-API-Key'        => $this->license_key,
				'X-Site-URL'       => home_url(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			$this->remote_data = false;
			return false;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$this->remote_data = false;
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $body ) || empty( $body->version ) ) {
			$this->remote_data = false;
			return false;
		}

		set_transient( 'nitrolitebox_update_data', $body, 6 * HOUR_IN_SECONDS );
		$this->remote_data = $body;
		return $this->remote_data;
	}
}
