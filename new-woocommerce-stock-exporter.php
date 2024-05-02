<?php
/**
 * Plugin Name: Stock Exporter for WooCommerce
 * Description: Simple stock report CSV exporter for WooCommerce
 * Version: 1.2.0
 * Author: PT Woo Plugins (by Webdados)
 * Author URI: https://ptwooplugins.com
 * Text Domain: stock-exporter-for-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 3.0
 * WC tested up to: 7.6
 */

/* Partially WooCommerce CRUD ready - Products are still fetched from the database using WP_Query for filtering and performance reasons */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}




/**
 * Check if WooCommerce is active and do it
 */
add_action( 'plugins_loaded', 'wc_stock_reporter_init' );
function wc_stock_reporter_init() {
	if ( class_exists( 'WooCommerce' ) && version_compare( WC_VERSION, '3.0.0', '>=' ) ) {

		class WC_Stock_Reporter {

			public $version            = '1.2.0';
			public $exclude_from_count = 0;
			public $sort_field         = '';

			/**
			 * Init the class
			 */
			public function __construct() {
				// Load translation files
				add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
				// Init internal variables
				add_action( 'after_setup_theme', array( $this, 'init_internal_variables' ) );
				// Load fields options
				add_action( 'after_setup_theme', array( $this, 'load_fields_options' ) );
				// Add admin menu item
				add_action( 'admin_menu', array( $this, 'add_admin_menu_item' ) );
				// Process
				add_action( 'admin_init', array( $this, 'woocommerce_stock_exporter_page_process' ) );
				// Screen new
				add_action( 'wse_screen_new_header', array( $this, 'screen_new_header' ) );
				add_action( 'wse_screen_new_footer', array( $this, 'screen_new_footer' ) );
				// Some settings
				$this->sep         = '|';
				$this->sep_replace = '-';
				// Defaults - Options saved by the user
				$this->defaults = get_option( 'woocoomerce_stock_export' );
				if ( ! $this->defaults ) {
					$this->defaults                                      = array();
					$this->defaults['woocoomerce_stock_export_products'] = 'all';
					$this->defaults['woocoomerce_stock_export_fields']   = array();
					$this->defaults['woocoomerce_stock_export_output']   = 'csv';
					$this->defaults['woocoomerce_stock_export_fields_custom']         = '';
					$this->defaults['woocoomerce_stock_export_exclude_stock_compare'] = '=';
					$this->defaults['woocoomerce_stock_export_exclude_stock']         = '';
					$this->defaults['woocoomerce_stock_export_exclude_meta_key']      = '';
					$this->defaults['woocoomerce_stock_export_exclude_meta_value']    = '';
				}
				$this->defaults['woocoomerce_stock_export_fields_custom'] = $this->explode_custom_fields( $this->defaults['woocoomerce_stock_export_fields_custom'] );
			}

			public function load_textdomain() {
				load_plugin_textdomain( 'stock-exporter-for-woocommerce' );
			}

			/**
			 * Init internal variables
			 */
			public function init_internal_variables() {
				$this->sort_field = apply_filters( 'wse_sort_field', 'product' );
			}

			/**
			 * Load fields options
			 */
			public function load_fields_options() {
				$this->export_fields_options = array(
					array(
						'value' => 'id',
						'label' => __( 'ID', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'sku',
						'label' => __( 'SKU', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'image',
						'label' => __( 'Image', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'type',
						'label' => __( 'Product type', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'product',
						'label' => __( 'Product', 'stock-exporter-for-woocommerce' ),
						'type'  => 'fixed',
					),
					array(
						'value' => 'product_cat',
						'label' => __( 'Categories', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'regular_price',
						'label' => __( 'Regular price', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'price',
						'label' => __( 'Price', 'stock-exporter-for-woocommerce' ),
						'type'  => 'optional',
					),
					array(
						'value' => 'custom_fields',
						'label' => __( 'Custom fields (comma separated)', 'stock-exporter-for-woocommerce' ),
						'type'  => 'custom_fields',
					),
					array(
						'value' => 'stock',
						'label' => __( 'Stock', 'stock-exporter-for-woocommerce' ),
						'type'  => 'fixed',
					),
					array(
						'value' => 'purchase_price',
						'label' => __( 'Purchase price', 'stock-exporter-for-woocommerce' ),
						'type'  => 'fixed',
					),
				);
			}

			/**
			 * Check capabilities
			 */
			public function check_capabilities() {
				// Maybe a bit redundant
				return ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'view_woocommerce_reports' ) );
			}

			/**
			 * Add admin menu item
			 */
			public function add_admin_menu_item() {
				if ( $this->check_capabilities() ) {
					add_submenu_page( 'edit.php?post_type=product', _x( 'Stock Exporter for WooCommerce', 'admin page title', 'stock-exporter-for-woocommerce' ), _x( 'Stock Exporter', 'admin menu item', 'stock-exporter-for-woocommerce' ), 'view_woocommerce_reports', 'woocommerce_stock_exporter', array( $this, 'woocommerce_stock_exporter_page' ) );
				}
			}

			/**
			 * Admin screen
			 */
			public function woocommerce_stock_exporter_page() {
				$show_products_options = array(
					array(
						'value' => 'all',
						'label' => __( 'All products', 'stock-exporter-for-woocommerce' ),
					),
					array(
						'value' => 'managed',
						'label' => __( 'Products with managed stock', 'stock-exporter-for-woocommerce' ),
					),
				);
				$output_options        = array(
					array(
						'value' => 'csv',
						'label' => __( 'CSV file', 'stock-exporter-for-woocommerce' ),
					),
					array(
						'value' => 'screen',
						'label' => __( 'HTML table on screen', 'stock-exporter-for-woocommerce' ),
					),
					array(
						'value' => 'screen_new',
						'label' => __( 'HTML table on new window', 'stock-exporter-for-woocommerce' ),
					),
				);

				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.BlockComment.NoEmptyLineBefore
				/*
				$exclude_meta_compare_options = array(
					'=',
					'LIKE %%',
					'!=',
					'>',
					'<',
					'>=',
					'<=',
				);
				*/

				?>
				<div class="wrap">
					<h2>
						<?php echo esc_html_x( 'Stock Exporter for WooCommerce', 'admin page title', 'stock-exporter-for-woocommerce' ); ?>
						<?php echo $this->version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</h2>
					<p><?php echo esc_html_e( 'Click the button below o generate a WooCommerce stock report, in CSV, of all the products on this website where stock is managed.', 'stock-exporter-for-woocommerce' ); ?></p>
					<?php
					// WPML
					if ( function_exists( 'icl_object_id' ) ) {
						?>
						<p><?php echo esc_html_e( 'WPML users: You can export the report on a different language by changing it on this page top bar.', 'stock-exporter-for-woocommerce' ); ?></p>
						<?php
					}
					?>
					<form method="post" id="woocoomerce-stock-export-form" action="">
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row" class="titledesc"><?php echo esc_html_e( 'Products to return', 'stock-exporter-for-woocommerce' ); ?></th>
									<td>
										<select name="woocoomerce_stock_export_products">
											<?php
											foreach ( $show_products_options as $option ) {
												?>
												<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $this->show_products, $option['value'] ); ?>>
													<?php echo $option['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</option>
												<?php
											}
											?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row" class="titledesc"><?php echo esc_html_e( 'Exclude products', 'stock-exporter-for-woocommerce' ); ?></th>
									<td>
										<?php echo esc_html_e( 'By stock', 'stock-exporter-for-woocommerce' ); ?>:
										<br/>
										<select name="woocoomerce_stock_export_exclude_stock_compare">
											<option value=">="<?php selected( '>=', $this->defaults['woocoomerce_stock_export_exclude_stock_compare'] ); ?>>&gt;=</option>
											<option value="<="<?php selected( '<=', $this->defaults['woocoomerce_stock_export_exclude_stock_compare'] ); ?>>&lt;=</option>
											<option value="="<?php selected( '=', $this->defaults['woocoomerce_stock_export_exclude_stock_compare'] ); ?>>=</option>
											<option value="!="<?php selected( '!=', $this->defaults['woocoomerce_stock_export_exclude_stock_compare'] ); ?>>!=</option>
										</select>
										<input type="number" name="woocoomerce_stock_export_exclude_stock" value="<?php echo esc_attr( $this->defaults['woocoomerce_stock_export_exclude_stock'] ); ?>" min="0" step="1"/>
										<br/>
										<?php echo esc_html_e( 'By meta key / value', 'stock-exporter-for-woocommerce' ); ?>:
										<br/>
										<input type="text" name="woocoomerce_stock_export_exclude_meta_key" size="20" value="<?php echo esc_attr( $this->defaults['woocoomerce_stock_export_exclude_meta_key'] ); ?>" placeholder="<?php echo esc_attr( __( 'meta key', 'stock-exporter-for-woocommerce' ) ); ?>"/>
										<!--<select name="woocoomerce_stock_export_exclude_meta_compare">
											<?php

											// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.BlockComment.NoEmptyLineBefore, Squiz.Commenting.BlockComment.NoCapital
											/*
											/ phpcs:ignore Squiz.Commenting.BlockComment.NoCapital
											foreach ( $exclude_meta_compare_options as $option ) {
												?>
												<option value="<?php echo $option; ?>" <?php selected( $option, $this->defaults['woocoomerce_stock_export_exclude_meta_compare'] ); ?>><?php echo htmlentities($option); ?></option>
												<?php
											}
											*/

											?>
										</select>-->
										=
										<input type="text" name="woocoomerce_stock_export_exclude_meta_value" size="20" value="<?php echo esc_attr( $this->defaults['woocoomerce_stock_export_exclude_meta_value'] ); ?>" placeholder="<?php echo esc_attr( __( 'meta value', 'stock-exporter-for-woocommerce' ) ); ?>"/>
									</td>
								</tr>
								<tr>
									<th scope="row" class="titledesc"><?php echo esc_html_e( 'Fields', 'stock-exporter-for-woocommerce' ); ?></th>
									<td>
										<?php
										foreach ( $this->export_fields_options as $option ) {
											?>
											<div>
												<?php
												switch ( $option['type'] ) {
													case 'fixed':
														?>
														<input type="hidden" name="woocoomerce_stock_export_fields[]" id="export_fields_options_<?php echo esc_attr( $option['value'] ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>"/>
														<span class="dashicons dashicons-yes"></span>
														<?php
														break;
													case 'optional':
													case 'custom_fields':
														?>
														<input type="checkbox" name="woocoomerce_stock_export_fields[]" id="export_fields_options_<?php echo esc_attr( $option['value'] ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>"
														<?php
														if ( in_array( $option['value'], $this->defaults['woocoomerce_stock_export_fields'], true ) ) {
															echo ' checked="checked"';
														}
														?>
														/>
														<?php
														break;
												}
												?>
												<label for="export_fields_options_<?php echo esc_attr( $option['value'] ); ?>"><?php echo esc_html( $option['label'] ); ?></label>
												<?php
												if ( $option['type'] === 'custom_fields' ) {
													?>
													<input type="text" name="woocoomerce_stock_export_fields_custom" size="35" value="<?php echo esc_attr( implode( ' , ', $this->defaults['woocoomerce_stock_export_fields_custom'] ) ); ?>"/>
													<?php
												}
												?>
											</div>
											<?php
										}
										?>
									</td>
								</tr>
								<tr>
									<th scope="row" class="titledesc"><?php echo esc_html_e( 'Output', 'stock-exporter-for-woocommerce' ); ?></th>
									<td>
										<select name="woocoomerce_stock_export_output" id="woocoomerce_stock_export_output">
											<?php
											foreach ( $output_options as $option ) {
												?>
												<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $this->output_type, $option['value'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
												<?php
											}
											?>
										</select>
										<script type="text/javascript">
											jQuery( '#woocoomerce_stock_export_output' ).change(function() {
												wse_init_form();
											});
											jQuery( document ).ready(function() {
												wse_init_form();
											});
											function wse_init_form() {
												var value = jQuery( '#woocoomerce_stock_export_output' ).val();
												if ( value == 'screen_new' ) {
													jQuery( '#woocoomerce-stock-export-form' ).attr( 'target', '_wse_export' );
												} else {
													jQuery( '#woocoomerce-stock-export-form' ).attr( 'target', '' );
												}
											}
										</script>
									</td>
								</tr>
							</tbody>
						</table>
						<?php wp_nonce_field( 'stock_export_nonce', 'stock_export_nonce_field' ); ?>
						<?php submit_button( __( 'Export WooCommerce Stock', 'stock-exporter-for-woocommerce' ), 'primary', 'woocoomerce_stock_export_button' ); ?>
					</form>
					<?php
					if ( ( $this->output_type === 'screen' ) && isset( $this->screen_output ) ) {
						?>
						<hr/>
						<?php
						echo $this->screen_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
				<?php
			}
			/**
			 * Admin screen - export
			 */
			public function woocommerce_stock_exporter_page_process() {
				global $plugin_page;
				if ( ( $plugin_page === 'woocommerce_stock_exporter' ) && $this->check_capabilities() ) {
					if ( isset( $_POST['woocoomerce_stock_export_button'] ) ) {
						if ( wp_verify_nonce( $_POST['stock_export_nonce_field'], 'stock_export_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
							update_option( 'woocoomerce_stock_export', $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
							$this->defaults = get_option( 'woocoomerce_stock_export' );
							$this->defaults['woocoomerce_stock_export_fields_custom'] = $this->explode_custom_fields( $this->defaults['woocoomerce_stock_export_fields_custom'] );
						}
					}
					$this->show_products = ( isset( $_POST['woocoomerce_stock_export_products'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_products'] ) ) ) : $this->defaults['woocoomerce_stock_export_products'] );
					$this->output_type   = ( isset( $_POST['woocoomerce_stock_export_output'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_output'] ) ) ) : $this->defaults['woocoomerce_stock_export_output'] );
					if ( isset( $_POST['woocoomerce_stock_export_button'] ) ) {
						$this->make_csv();
					}
				}
			}

			public function explode_custom_fields( $fields ) {
				$fields = trim( $fields );
				$fields = explode( ',', $fields );
				foreach ( $fields as $key => $field ) {
					$fields[ $key ] = trim( $field );
					if ( trim( $fields[ $key ] ) === '' ) {
						unset( $fields[ $key ] );
					}
				}
				return $fields;
			}

			/**
			 * Terms - This could be improved to use new WooCommerce 3.0 methods like get_category_ids or get_tag_ids
			 */
			public function get_terms( $product_id, $tax = 'product_cat' ) {
				$terms = get_the_terms( $product_id, $tax );
				$txt   = '';
				if ( $terms ) {
					foreach ( $terms as $term ) {
						$txt .= str_replace( $this->sep, $this->sep_replace, trim( $term->name ) ) . ' ' . $this->sep . ' ';
					}
				}
				return trim( $txt, ' ' . $this->sep );
			}

			/**
			 * Each value
			 */
			public function get_value( $field, $output_type, $product, $variation = null ) {
				$product_type = $product->get_type();
				$id           = $product->get_id();
				$product_id   = $id;
				switch ( $product_type ) {
					case 'variable':
						if ( $variation ) {
							$id = $variation->get_id();
						}
						break;
				}
				switch ( $field ) {
					case 'id':
						return array( $id );
					case 'sku':
						return array( trim( str_replace( $this->sep, $this->sep_replace, $product->get_sku() ) . ( ( $product_type === 'variable' ) && $variation ? ' ' . $this->sep . ' ' . str_replace( $this->sep, $this->sep_replace, $variation->get_sku() ) : '' ), ' ' . $this->sep ) );
					case 'image':
						if ( apply_filters( 'wse_include_base_product', false ) && ( $product_type === 'variable' ) && $variation ) {
							return array( '' ); // Only show image on variable and not on the variation itself
						} else {
							if ( $image = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'thumbnail' ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
								if ( $output_type === 'csv' ) {
									return array( $image[0] );
								} else {
									return array( '<img src="' . $image[0] . '" width="' . $image[1] . '" height="' . $image[2] . '"/>' );
								}
							} else {
								if ( $product_type === 'variable' ) {
									// Default to base product image
									if ( $image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'thumbnail' ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
										if ( $output_type === 'csv' ) {
											return array( $image[0] );
										} else {
											return array( '<img src="' . $image[0] . '" width="' . $image[1] . '" height="' . $image[2] . '"/>' );
										}
									} else {
										return array( '' );
									}
								} else {
									return array( '' );
								}
							}
						}
						break;
					case 'type':
						if ( $variation ) {
							return array( 'variation' );
						} else {
							return array( $product_type );
						}
						break;

					case 'product':
						return array( trim( str_replace( $this->sep, $this->sep_replace, $product->get_title() ) . ( ( $product_type === 'variable' ) && $variation ? ' ' . $this->sep . ' ' . str_replace( $this->sep, $this->sep_replace, get_the_title( $id ) ) : '' ), ' ' . $this->sep ) );
					case 'product_cat':
						return array( $this->get_terms( $product_id ) );
					case 'regular_price':
						return array( ( $product_type === 'variable' ) && $variation ? $variation->get_regular_price() : $product->get_regular_price() );
					case 'price':
						return array( ( $product_type === 'variable' ) && $variation ? $variation->get_price() : $product->get_price() );
					case 'custom_fields':
						$temp = array();
						foreach ( $this->defaults['woocoomerce_stock_export_fields_custom'] as $key ) {
							$temp[] = (
										( $product_type === 'variable' ) && $variation
										?
										$variation->get_meta( $key )
										:
										$product->get_meta( $key )
									);
						}
						return $temp;
					case 'stock':
						return array(
							( $product_type === 'variable' ) && $variation
							?
							( $variation->managing_stock() ? $variation->get_stock_quantity() : __( 'not managed', 'stock-exporter-for-woocommerce' ) )
							:
							( $product->managing_stock() ? $product->get_stock_quantity() : __( 'not managed', 'stock-exporter-for-woocommerce' ) ),
						);
					case 'purchase_price':
						return array(get_post_meta($id, 'purchase_price', true)??'');
				}
			}

			/**
			 * Exclude by stock - Maybe we must get them all and then compare the stock, or we'll have problems with the variations??
			 * WordPress.Security.ValidatedSanitizedInput.InputNotValidated and WordPress.Security.NonceVerification.Missing because whe know the indexes exist
			 */
			public function exclude_stock_meta_query() {
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
				switch ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock_compare'] ) ) ) ) {
					case '>=':
						$compare = '<';
						break;
					case '<=':
						$compare = '>';
						break;
					case '=':
						$compare = '!=';
						break;
					case '!=':
						$compare = '=';
						break;
				}
				return array(
					'key'     => '_stock',
					'value'   => intval( $_POST['woocoomerce_stock_export_exclude_stock'] ),
					'type'    => 'NUMERIC',
					'compare' => $compare,
				);
				// phpcs:enable
			}
			public function exclude_stock_filter( $wp_query_args, $query_vars, $obj ) {
				$wp_query_args['meta_query'][] = $this->exclude_stock_meta_query();
				return $wp_query_args;
			}

			/**
			 * Exclude by meta key / field
			 * WordPress.Security.ValidatedSanitizedInput.InputNotValidated and WordPress.Security.NonceVerification.Missing because whe know the indexes exist
			 */
			public function exclude_meta_key_value_meta_query() {
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
				// Can sanitize_text_field or wp_unslash create issues and influence results?
				return array(
					'relation' => 'OR',
					array(
						'key'     => trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_key'] ) ) ),
						'compare' => 'NOT EXISTS', // works!
						'value'   => '', // This is ignored, but is necessary...
					),
					array(
						'key'     => trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_key'] ) ) ),
						'value'   => trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_value'] ) ) ),
						'compare' => '!=',
					),
				);
				// phpcs:enable
			}
			public function exclude_meta_key_value_filter( $wp_query_args, $query_vars, $obj ) {
				$wp_query_args['meta_query'][] = $this->exclude_meta_key_value_meta_query();
				return $wp_query_args;
			}

			/**
			 * Order the products
			 */
			public function order_products( $output_array, $sort_index_1, $sort_index_2 ) {
				$sorter          = array();
				$sort_by_product = array();
				foreach ( $output_array as $key => $row ) {
					$sorter[ $key ] = $row[ $sort_index_1 ];
					if ( $sort_index_1 !== $sort_index_2 ) {
						$sort_by_product[ $key ] = $row[ $sort_index_2 ];
					}
				}
				if ( $sort_index_1 !== $sort_index_2 ) {
					// Sort by choosen field and then by product
					array_multisort( $sorter, SORT_ASC, $sort_by_product, SORT_ASC, $output_array );
				} else {
					// Sort by choosen field
					array_multisort( $sorter, SORT_ASC, $output_array );
				}
				return $output_array;
			}

			/**
			 * Get the products - variatons after
			 * WordPress.Security.ValidatedSanitizedInput.InputNotValidated and WordPress.Security.NonceVerification.Missing because whe know the indexes exist
			 */
			public function get_products() {
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
				// Init the output array and add column headers for the CSV
				$output_array        = array();
				$output_array[0]     = array();
				$output_array_header = array();
				$i                   = 0;
				foreach ( $this->export_fields_options as $field ) {
					if ( in_array( $field['value'], $_POST['woocoomerce_stock_export_fields'], true ) ) {
						$output_array[0][] = ''; // Blank so it's the first when ordered later on
						switch ( $field['value'] ) {
							case 'custom_fields':
								foreach ( $this->defaults['woocoomerce_stock_export_fields_custom'] as $key ) {
									$output_array_header[] = $key;
								}
								break;
							default:
								$output_array_header[] = $field['label'];
								if ( $field['value'] === $this->sort_field ) {
									$sort_index_1 = $i;
								}
								if ( $field['value'] === 'product' ) {
									$sort_index_2 = $i;
								}
								break;
						}
						$i++;
					}
				}
				// Get all products
				$args = array(
					'status'  => 'publish',
					'limit'   => -1, // This is not a very good idea
					'orderby' => 'title',
					'order'   => 'ASC',
					'stock_status' => 'instock',
				);
				if (
					( $this->show_products === 'managed' )
					||
					( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 )
				) {
					$args['manage_stock'] = true;
				}
				if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 ) {
					add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_stock_filter' ), 10, 3 );
				}
				if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_key'] ) ) ) !== '' ) && trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_value'] ) ) ) !== '' ) {
					add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_meta_key_value_filter' ), 10, 3 );
				}
				$products = wc_get_products( $args );
				if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 ) {
					remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_stock_filter' ), 10, 3 );
				}
				if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_key'] ) ) ) !== '' ) && trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_meta_value'] ) ) ) !== '' ) {
					remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_meta_key_value_filter' ), 10, 3 );
				}


				// Products
				$shown_variable_products     = array();
				$shown_variable_products_ids = array();
				$shown_variations            = array();
				foreach ( $products as $product ) {
					$product_type = $product->get_type();
					if ( $product_type === 'variable' ) {
						$variations = $product->get_available_variations();
						foreach ( $variations as $temp ) {
							$variation = new WC_Product_Variation( $temp['variation_id'] );
							if ( ( $this->show_products === 'all' ) || ( ( $this->show_products === 'managed' ) && $variation->managing_stock() ) ) {
									$include_variation = true;
								if ( ( ! in_array( intval( $product->get_id() ), $shown_variable_products_ids, true ) ) && apply_filters( 'wse_include_base_product', false ) ) {
									$shown_variable_products_ids[] = intval( $product->get_id() );
									$shown_variable_products[]     = $product;
								}
									// Filter by stock?
								if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 ) {
									$exclude_stock_compare = trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock_compare'] ) ) );
									$exclude_stock         = intval( $_POST['woocoomerce_stock_export_exclude_stock'] );
									$variation_stock       = $this->get_value( 'stock', 'csv', $product, $variation );
									$variation_stock       = $variation_stock[0];
									if ( $exclude_stock_compare === '>=' ) {
										if ( $variation_stock >= $exclude_stock ) {
											$include_variation = false;
										}
									} elseif ( $exclude_stock_compare === '<=' ) {
										if ( $variation_stock <= $exclude_stock ) {
											$include_variation = false;
										}
									} elseif ( $exclude_stock_compare === '=' ) {
										if ( $variation_stock === $exclude_stock ) {
											$include_variation = false;
										}
									} elseif ( $exclude_stock_compare === '!=' ) {
										if ( $variation_stock !== $exclude_stock ) {
											$include_variation = false;
										}
									}
								}
								if ( $include_variation ) {
									$shown_variations[] = $variation->get_id();
									$temp               = array();
									foreach ( $this->export_fields_options as $field ) {
										if ( in_array( $field['value'], $_POST['woocoomerce_stock_export_fields'], true ) ) {
											$temp = array_merge( $temp, $this->get_value( $field['value'], $this->output_type, $product, $variation ) );
										}
									}
									$output_array[] = $temp;
								}
							}
						}
					} else {
						if ( ( $this->show_products === 'all' ) || ( ( $this->show_products === 'managed' ) && $product->managing_stock() ) ) {
							$temp = array();
							foreach ( $this->export_fields_options as $field ) {
								if ( in_array( $field['value'], $_POST['woocoomerce_stock_export_fields'], true ) ) {
									$temp = array_merge( $temp, $this->get_value( $field['value'], $this->output_type, $product, null ) );
								}
							}
							$output_array[] = $temp;
						}
					}
				}

				// Do we need to go and get missing variations?
				if (
					( $this->show_products === 'managed' )
					||
					( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 )
				) {
					$args = array(
						'type'         => 'variation',
						'status'       => 'publish',
						'limit'        => -1, // This is not a very good idea
						'orderby'      => 'title',
						'order'        => 'ASC',
						'manage_stock' => true, // If is set on parent, we already got it up there
						'exclude'      => $shown_variations,
					);
					if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 ) {
						add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_stock_filter' ), 10, 3 );
					}
					$products = wc_get_products( $args );
					if ( ( trim( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_exclude_stock'] ) ) ) !== '' ) && intval( $_POST['woocoomerce_stock_export_exclude_stock'] ) >= 0 ) {
						remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_stock_filter' ), 10, 3 );
					}

					foreach ( $products as $variation ) {
						$product = new WC_Product_Variable( $variation->get_parent_id() );
						$temp    = array();
						if ( ( ! in_array( intval( $product->get_id() ), $shown_variable_products_ids, true ) ) && apply_filters( 'wse_include_base_product', false ) ) {
							$shown_variable_products_ids[] = intval( $product->get_id() );
							$shown_variable_products[]     = $product;
						}
						foreach ( $this->export_fields_options as $field ) {
							if ( in_array( $field['value'], $_POST['woocoomerce_stock_export_fields'], true ) ) {
								$temp = array_merge( $temp, $this->get_value( $field['value'], $this->output_type, $product, $variation ) );
							}
						}
						$output_array[] = $temp;
					}
				}
				// Add base product (for Rasmus / karmamiacph.com)
				if ( apply_filters( 'wse_include_base_product', false ) && count( $shown_variable_products ) > 0 ) {
					$this->exclude_from_count = count( $shown_variable_products );
					foreach ( $shown_variable_products as $product ) {
						$temp = array();
						foreach ( $this->export_fields_options as $field ) {
							if ( in_array( $field['value'], $_POST['woocoomerce_stock_export_fields'], true ) ) {
								switch ( $field['value'] ) {
									case 'stock':
										$temp = array_merge( $temp, array( '' ) );
										break;

									default:
										$temp = array_merge( $temp, $this->get_value( $field['value'], $this->output_type, $product, null ) );
										break;
								}
							}
						}
						$output_array[] = $temp;
					}
				}
				// Order them
				$output_array = $this->order_products( $output_array, $sort_index_1, $sort_index_2 );
				// Set the header (after order)
				$output_array[0] = $output_array_header;

				// Return
				return $output_array;
				// phpcs:enable
			}

			/**
			 * The CSV or HTML itself
			 * WordPress.Security.ValidatedSanitizedInput.InputNotValidated and WordPress.Security.NonceVerification.Missing because whe know the indexes exist
			 */
			public function make_csv() {
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
				// Options
				switch ( $this->output_type ) {
					case 'csv':
						// Correct headers
						header( 'Content-Type: text/csv; charset = utf-8' );
						header( 'Content-Disposition: attachment; filename = woocommerce_stock_exporter_' . current_time( 'Y_m_d' ) . '.csv' );
						// Create a file pointer connected to the output stream
						$output = fopen( 'php://output', 'w' );
						break;
					default:
						// Nothing really
						break;
				}
				// Get the products
				$output_array = $this->get_products();
				// Output
				switch ( $this->output_type ) {
					case 'csv':
						// CSV'it
						foreach ( $output_array as $i => $temp ) {
							$output_array[ $i ] = '"' . implode( '","', $temp ) . '"';
						}
						fwrite( $output, chr( 255 ) . chr( 254 ) . iconv( 'UTF-8', 'UTF-16LE//IGNORE', implode( "\n", $output_array ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
						die(); // phpcs:ignore Generic.WhiteSpace.ArbitraryParenthesesSpacing.FoundEmpty
					case 'screen':
					case 'screen_new':
						ob_start();
						?>
						<p><b><?php echo count( $output_array ) - 1 - $this->exclude_from_count; ?> <?php echo esc_html_e( 'products', 'stock-exporter-for-woocommerce' ); ?></b></p>
						<table id="stock-export-table" class="widefat">
							<thead>
								<tr>
									<?php
									$index = 0;
									foreach ( $output_array[0] as $value ) {
										?>
										<th scope="col" class="column-header column-header-<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_fields'][ $index ] ) ) ); ?>"><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
										<?php
										$index++;
									}
									?>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $output_array as $key => $values ) {
									if ( $key > 0 ) {
										?>
										<tr class="<?php echo ( $key % 2 === 0 ? '' : 'alternate' ); ?>">
										<?php
										$index = 0;
										foreach ( $values as $value ) {
											?>
											<td class="column-value column-value-<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['woocoomerce_stock_export_fields'][ $index ] ) ) ); ?>"><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
											<?php
											$index++;
										}
										?>
										</tr>
										<?php
									}
								}
								?>
							</tbody>
						</table>
						<?php
						$this->screen_output = ob_get_clean();
						break;
				}
				// Show in a new window?
				if ( $this->output_type === 'screen_new' ) {
					do_action( 'wse_screen_new_header' );
					echo $this->screen_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					do_action( 'wse_screen_new_footer' );
					die();
				}
				// phpcs:enable
			}

			/**
			 * Screen new header
			 */
			public function screen_new_header() {
				?>
				<!DOCTYPE html>
				<html>
					<head>
						<meta http-equiv="Content-Type" content="text/html; charset = UTF-8" />
						<title><?php echo esc_html_x( 'Stock Exporter for WooCommerce', 'admin page title', 'stock-exporter-for-woocommerce' ); ?></title>
						<style type="text/css">
							body {
								font-family: sans-serif;
							}
							#stock-export-table {
								width: 100%;
								border-collapse: collapse;
							}
							#stock-export-table th, 
							#stock-export-table td {
								padding: 5px;
								border: 1px solid #CCC;
							}
							#stock-export-table .column-value-image img {
								max-width: 100px;
								max-height: 100px;
							}
							#stock-export-table .column-value-stock {
								text-align: right;
							}
						</style>
					</head>
					<body>
				<?php
			}

			/**
			 * Screen new footer
			 */
			public function screen_new_footer() {
				?>
					</body>
				</html>
				<?php
			}

		}

		if ( is_admin() ) {
			$wse = new WC_Stock_Reporter();
		}
	}
}

/* InvoiceXpress nag */
add_action(
	'admin_init',
	function() {
		if (
		( ! defined( 'WEBDADOS_INVOICEXPRESS_NAG' ) )
		&&
		( ! class_exists( '\Webdados\InvoiceXpressWooCommerce\Plugin' ) )
		&&
		empty( get_transient( 'webdados_invoicexpress_nag' ) )
		) {
			define( 'WEBDADOS_INVOICEXPRESS_NAG', true );
			require_once 'webdados_invoicexpress_nag/webdados_invoicexpress_nag.php';
		}
	}
);

/* HPOS Compatible */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */
