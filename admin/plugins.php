<?php
/**
 * Admin settings helper functions.
 *
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets plugin info for the given plugin slug from WordPress.org.
 *
 * @since n.e.x.t
 *
 * @param string $plugin_slug The string identifier for the plugin in questions slug.
 *
 * @return array Array of plugin data, or empty if none/error.
 */
function perflab_query_plugin_info( string $plugin_slug ) {
	$plugin = plugins_api(
		'plugin_information',
		array(
			'slug'   => $plugin_slug,
			'fields' => array(
				'short_description' => true,
				'icons'             => true,
			),
		)
	);

	if ( is_wp_error( $plugin ) ) {
		return array();
	}

	if ( is_object( $plugin ) ) {
		$plugin = (array) $plugin;
	}

	return $plugin;
}

/**
 * Returns an array of WPP standalone plugins.
 *
 * @since n.e.x.t
 *
 * @return array of wpp standalone plugins as slugs.
 */
function perflab_get_standalone_plugins() {
	return array(
		'webp-uploads',
		'performant-translations',
		'dominant-color-images',
	);
}

/**
 * Renders plugin UI for managing standalone plugins within PL Settings screen.
 *
 * @since n.e.x.t
 */
function perflab_render_plugins_ui() {
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$standalone_plugins = array();
	foreach ( perflab_get_standalone_plugins() as $managed_standalone_plugin_slug ) {
		$standalone_plugins[ $managed_standalone_plugin_slug ] = array(
			'plugin_data' => perflab_query_plugin_info( $managed_standalone_plugin_slug ),
		);
	}

	if ( empty( $standalone_plugins ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Performance Plugins', 'performance-lab' ); ?></h1>
		<p><?php esc_html_e( 'The following standalone performance plugins are available for installation.', 'performance-lab' ); ?></p>
		<div class="wrap">
			<form id="plugin-filter" method="post">
				<div class="wp-list-table widefat plugin-install wpp-standalone-plugins">
					<h2 class="screen-reader-text"><?php esc_html_e( 'Plugins list', 'default' ); ?></h2>
					<div id="the-list">
						<?php
						foreach ( $standalone_plugins as $standalone_plugin ) {
							perflab_render_plugin_card( $standalone_plugin['plugin_data'] );
						}
						?>
					</div>
				</div>
			</form>
		</div>
		<div class="clear"></div>
	</div>
	<?php
}

/**
 * Renders individual plugin cards.
 *
 * @since n.e.x.t
 *
 * @param array $plugin_data Plugin data from the WordPress.org API.
 */
function perflab_render_plugin_card( array $plugin_data ) {
	// If no plugin data is returned, return.
	if ( empty( $plugin_data ) ) {
		return;
	}

	// Remove any HTML from the description.
	$description = wp_strip_all_tags( $plugin_data['short_description'] );
	$title       = $plugin_data['name'];

	/** This filter is documented in wp-admin/includes/class-wp-plugin-install-list-table.php */
	$description = apply_filters( 'plugin_install_description', $description, $plugin_data );
	$version     = $plugin_data['version'];
	$name        = wp_strip_all_tags( $title . ' ' . $version );
	$author      = $plugin_data['author'];
	if ( ! empty( $author ) ) {
		/* translators: %s: Plugin author. */
		$author = ' <cite>' . esc_html( sprintf( __( 'By %s', 'default' ), $author ) ) . '</cite>';
	}

	$requires_php = isset( $plugin_data['requires_php'] ) ? $plugin_data['requires_php'] : null;
	$requires_wp  = isset( $plugin_data['requires'] ) ? $plugin_data['requires'] : null;

	$compatible_php = is_php_version_compatible( $requires_php );
	$compatible_wp  = is_wp_version_compatible( $requires_wp );
	$tested_wp      = ( empty( $plugin_data['tested'] ) || version_compare( get_bloginfo( 'version' ), $plugin_data['tested'], '<=' ) );
	$action_links   = array();

	$status = install_plugin_install_status( $plugin_data );

	switch ( $status['status'] ) {
		case 'install':
			if ( $status['url'] ) {
				if ( $compatible_php && $compatible_wp && current_user_can( 'install_plugins' ) ) {
					$action_links[] = sprintf(
						'<a class="install-now button" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
						esc_attr( $plugin_data['slug'] ),
						esc_url( $status['url'] ),
						/* translators: %s: Plugin name and version. */
						esc_attr( sprintf( _x( 'Install %s now', 'plugin', 'default' ), $name ) ),
						esc_attr( $name ),
						esc_html__( 'Install Now', 'default' )
					);
				} else {
					$action_links[] = sprintf(
						'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
						esc_html( _x( 'Cannot Install', 'plugin', 'default' ) )
					);
				}
			}
			break;

		case 'update_available':
		case 'latest_installed':
		case 'newer_installed':
			if ( is_plugin_active( $status['file'] ) ) {
				$action_links[] = sprintf(
					'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
					esc_html( _x( 'Active', 'plugin', 'default' ) )
				);
				if ( current_user_can( 'deactivate_plugin', $status['file'] ) ) {
					global $page;
					$s       = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$context = $status['status'];

					$action_links[] = sprintf(
						'<a href="%s" id="deactivate-%s" aria-label="%s" style="color:red;text-decoration: underline;">%s</a>',
						add_query_arg(
							array(
								'_wpnonce' => wp_create_nonce( 'perflab_deactivate_plugin_' . $status['file'] ),
								'action'   => 'perflab_deactivate_plugin',
								'plugin'   => $status['file'],
							),
							network_admin_url( 'plugins.php' )
						),
						esc_attr( $plugin_data['slug'] ),
						/* translators: %s: Plugin name. */
						esc_attr( sprintf( _x( 'Deactivate %s', 'plugin', 'default' ), $plugin_data['slug'] ) ),
						__( 'Deactivate', 'default' )
					);
				}
			} elseif ( current_user_can( 'activate_plugin', $status['file'] ) ) {
				if ( $compatible_php && $compatible_wp ) {
					$button_text = __( 'Activate', 'default' );
					/* translators: %s: Plugin name. */
					$button_label = _x( 'Activate %s', 'plugin', 'default' );
					$activate_url = add_query_arg(
						array(
							'_wpnonce' => wp_create_nonce( 'perflab_activate_plugin_' . $status['file'] ),
							'action'   => 'perflab_activate_plugin',
							'plugin'   => $status['file'],
						),
						network_admin_url( 'plugins.php' )
					);

					$action_links[] = sprintf(
						'<a href="%1$s" class="button activate-now" aria-label="%2$s">%3$s</a>',
						esc_url( $activate_url ),
						esc_attr( sprintf( $button_label, $plugin_data['name'] ) ),
						$button_text
					);
				} else {
					$action_links[] = sprintf(
						'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
						_x( 'Cannot Activate', 'plugin', 'default' )
					);
				}
			} else {
				$action_links[] = sprintf(
					'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
					esc_html( _x( 'Installed', 'plugin', 'default' ) )
				);
			}
			break;
	}

	$details_link = esc_url_raw(
		add_query_arg(
			array(
				'tab'       => 'plugin-information',
				'plugin'    => $plugin_data['slug'],
				'TB_iframe' => 'true',
				'width'     => 600,
				'height'    => 550,
			),
			admin_url( 'plugin-install.php' )
		)
	);

	$action_links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
		esc_url( $details_link ),
		/* translators: %s: Plugin name and version. */
		esc_attr( sprintf( __( 'More information about %s', 'default' ), $name ) ),
		esc_attr( $name ),
		__( 'More Details', 'default' )
	);

	if ( ! empty( $plugin_data['icons']['svg'] ) ) {
		$plugin_icon_url = $plugin_data['icons']['svg'];
	} elseif ( ! empty( $plugin_data['icons']['2x'] ) ) {
		$plugin_icon_url = $plugin_data['icons']['2x'];
	} elseif ( ! empty( $plugin_data['icons']['1x'] ) ) {
		$plugin_icon_url = $plugin_data['icons']['1x'];
	} else {
		$plugin_icon_url = $plugin_data['icons']['default'];
	}

	/**
	 * Filters the install action links for a plugin.
	 *
	 * @since n.e.x.t
	 *
	 * @param string[] $action_links An array of plugin action links.
	 *                               Defaults are links to Details and Install Now.
	 * @param array    $plugin       An array of plugin data. See {@see plugins_api()}
	 *                               for the list of possible values.
	 */
	$action_links = apply_filters( 'plugin_install_action_links', $action_links, $plugin_data );

	$last_updated_timestamp = strtotime( $plugin_data['last_updated'] );
	?>
	<div class="plugin-card plugin-card-<?php echo sanitize_html_class( $plugin_data['slug'] ); ?>">
		<?php
		if ( ! $compatible_php || ! $compatible_wp ) {
			echo '<div class="notice inline notice-error notice-alt"><p>';
			if ( ! $compatible_php && ! $compatible_wp ) {
				esc_html_e( 'This plugin does not work with your versions of WordPress and PHP.', 'default' );
				if ( current_user_can( 'update_core' ) && current_user_can( 'update_php' ) ) {
					echo wp_kses_post(
						/* translators: 1: URL to WordPress Updates screen, 2: URL to Update PHP page. */
						' ' . __( '<a href="%1$s">Please update WordPress</a>, and then <a href="%2$s">learn more about updating PHP</a>.', 'default' ),
						esc_url( self_admin_url( 'update-core.php' ) ),
						esc_url( wp_get_update_php_url() )
					);
					wp_update_php_annotation( '</p><p><em>', '</em>' );
				} elseif ( current_user_can( 'update_core' ) ) {
					echo wp_kses_post(
					/* translators: %s: URL to WordPress Updates screen. */
						' ' . __( '<a href="%s">Please update WordPress</a>.', 'default' ),
						esc_url( self_admin_url( 'update-core.php' ) )
					);
				} elseif ( current_user_can( 'update_php' ) ) {
					echo wp_kses_post(
						/* translators: %s: URL to Update PHP page. */
						' ' . __( '<a href="%s">Learn more about updating PHP</a>.', 'default' ),
						esc_url( wp_get_update_php_url() )
					);
					wp_update_php_annotation( '</p><p><em>', '</em>' );
				}
			} elseif ( ! $compatible_wp ) {
				esc_html_e( 'This plugin does not work with your version of WordPress.', 'default' );
				if ( current_user_can( 'update_core' ) ) {
					echo wp_kses_post(
					/* translators: %s: URL to WordPress Updates screen. */
						' ' . __( '<a href="%s">Please update WordPress</a>.', 'default' ),
						esc_url( self_admin_url( 'update-core.php' ) )
					);
				}
			} elseif ( ! $compatible_php ) {
				esc_html_e( 'This plugin does not work with your version of PHP.', 'default' );
				if ( current_user_can( 'update_php' ) ) {
					echo wp_kses_post(
					/* translators: %s: URL to Update PHP page. */
						' ' . __( '<a href="%s">Learn more about updating PHP</a>.', 'default' ),
						esc_url( wp_get_update_php_url() )
					);
					wp_update_php_annotation( '</p><p><em>', '</em>' );
				}
			}
			echo '</p></div>';
		}
		?>
		<div class="plugin-card-top">
			<div class="name column-name">
				<h3>
					<a href="<?php echo esc_url( $details_link ); ?>" class="thickbox open-plugin-details-modal">
						<?php echo wp_kses_post( $title ); ?>
						<img src="<?php echo esc_url( $plugin_icon_url ); ?>" class="plugin-icon" alt="" />
					</a>
				</h3>
			</div>
			<div class="action-links">
				<?php
				if ( ! empty( $action_links ) ) {
					echo wp_kses_post( '<ul class="plugin-action-buttons"><li>' . implode( '</li><li>', $action_links ) . '</li></ul>' );
				}
				?>
			</div>
			<div class="desc column-description">
				<p><?php echo wp_kses_post( $description ); ?></p>
				<p class="authors"><?php echo wp_kses_post( $author ); ?></p>
			</div>
		</div>
		<div class="plugin-card-bottom">
			<div class="vers column-rating">
				<?php
				wp_star_rating(
					array(
						'rating' => $plugin_data['rating'],
						'type'   => 'percent',
						'number' => $plugin_data['num_ratings'],
					)
				);
				?>
				<span class="num-ratings" aria-hidden="true">(<?php echo esc_html( number_format_i18n( $plugin_data['num_ratings'] ) ); ?>)</span>
			</div>
			<div class="column-updated">
				<strong><?php esc_html_e( 'Last Updated:', 'default' ); ?></strong>
				<?php
				printf(
					/* translators: %s: Human-readable time difference. */
					esc_html__( '%s ago', 'performance-lab' ),
					esc_html( human_time_diff( $last_updated_timestamp ) )
				);
				?>
			</div>
			<div class="column-downloaded">
				<?php
				if ( $plugin_data['active_installs'] >= 1000000 ) {
					$active_installs_millions = floor( $plugin_data['active_installs'] / 1000000 );
					$active_installs_text     = sprintf(
						/* translators: %s: Number of millions. */
						_nx( '%s+ Million', '%s+ Million', $active_installs_millions, 'Active plugin installations', 'default' ),
						number_format_i18n( $active_installs_millions )
					);
				} elseif ( 0 === $plugin_data['active_installs'] ) {
					$active_installs_text = _x( 'Less Than 10', 'Active plugin installations', 'default' );
				} else {
					$active_installs_text = number_format_i18n( $plugin_data['active_installs'] ) . '+';
				}
				/* translators: %s: Number of installations. */
				printf( esc_html__( '%s Active Installations', 'default' ), esc_html( $active_installs_text ) );
				?>
			</div>
			<div class="column-compatibility">
				<?php
				if ( ! $tested_wp ) {
					echo '<span class="compatibility-untested">' . esc_html__( 'Untested with your version of WordPress', 'default' ) . '</span>';
				} elseif ( ! $compatible_wp ) {
					echo '<span class="compatibility-incompatible">' . wp_kses_post( __( '<strong>Incompatible</strong> with your version of WordPress', 'default' ) ) . '</span>';
				} else {
					echo '<span class="compatibility-compatible">' . wp_kses_post( __( '<strong>Compatible</strong> with your version of WordPress', 'default' ) ) . '</span>';
				}
				?>
			</div>
		</div>
	</div>
	<?php
}
