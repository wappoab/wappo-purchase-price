<?php
/*
Plugin Name: Wappo Purchase Price
Description: Adds custom fields to WooCommerce products and variations, and exports them in product exports.
Version: 1.0
Author: Johan Linnér & Lucas Larsson
URL: https://wappo.se
*/


// Add custom menu item under "Products"
function wappo_custom_menu() {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php'); // Include the necessary WordPress file

    add_submenu_page(
        'edit.php?post_type=product', // parent slug
        'Wappo Settings', // page title
        'Wappo', // menu title
        'manage_options', // capability
        'wappo-settings', // menu slug
        'wappo_settings_page' // callback function to display the settings page
    );
}
add_action('admin_menu', 'wappo_custom_menu');


/*********************************************************/



//Hook the plugin initialization function to the 'init' action hook
add_action('init', 'wappo_init');

    function wappo_init() {
        // Callback function to display the settings page
        function wappo_settings_page() {
            // Add your settings page content here
            echo '<div class="wrap">';
            echo '<h1>Wappo Settings</h1>';
            echo '<p>This is where you can configure Wappo settings.</p>';
            echo '<p>By activating this plugin we will overide the existing <strong>stock-exporter</strong> with our modified version.</p>';
    
            // Display the activation form
            //Require the ppatch.php file
            require_once(ABSPATH . 'wp-content/plugins/wappo-purhcase-price/ppatch.php');
            // Variable to store error message
            $error_message = '';

            // Check if the form is submitted
            if (isset($_POST['submit'])) {
                if ($_POST['submit'] == 'activate') {
                    // Call the patch function to activate the patch
                    $result = wappo_ppatch();
                    // Handle the result (you may display messages accordingly)
                    if ($result != PATCH_SUCCESS) {
                        $error_message = "Error activating patch: " . $errorMessages[$result];
                    }
                } elseif ($_POST['submit'] == 'deactivate') {
                    // Call the deactivate function to deactivate the patch
                    $deactivate_result = deactivate_patch();
                    // Handle the result of deactivation
                    if (!$deactivate_result) {
                        $error_message = "Error deactivating patch.";
                    }
                }
            }
            // Check if patch is already activated
            $patch_activated = (strpos(file_get_contents(WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/woocommerce-stock-exporter.php'), '// wappo purchase price') !== false);
            ?>
            <!-- Display error message if exists -->
            <?php if (!empty($error_message)) : ?>
                <div class="error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Display the form -->
            <form method="post">
                <?php if ($patch_activated) : ?>
                    <!-- If patch is activated, show Deactivate Patch button -->
                    <button type="submit" name="submit" value="deactivate">Deactivate Patch</button>
                <?php else : ?>
                    <!-- If patch is not activated, show Activate Patch button -->
                    <button type="submit" name="submit" value="activate">Activate Patch</button>
                <?php endif; ?>
            </form>
            <?php
            echo '</div>';
        }
    
        // Activation hook
        register_activation_hook(__FILE__, 'activate_custom_stock_exporter_plugin');
        function activate_custom_stock_exporter_plugin() {
            $original_plugin_path = WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/woocommerce-stock-exporter.php';
            $backup_plugin_path = WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/backup-woocommerce-stock-exporter.php';
            $custom_plugin_path = plugin_dir_path(__FILE__) . 'new-woocommerce-stock-exporter.php';
    
            // Check if the original file exists before proceeding with the modification.
            if (file_exists($original_plugin_path)) {
                // Rename the original file to create a backup.
                rename($original_plugin_path, $backup_plugin_path);
                // Copy the modified file over the original file.
                copy($custom_plugin_path, $original_plugin_path);
            }
        }
    
        // Deactivation hook
        register_deactivation_hook(__FILE__, 'deactivate_custom_stock_exporter_plugin');
        function deactivate_custom_stock_exporter_plugin() {
            $original_plugin_path = WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/woocommerce-stock-exporter.php';
            $backup_plugin_path = WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/backup-woocommerce-stock-exporter.php';
    
            // Check if the backup file exists before proceeding
            if (file_exists($backup_plugin_path)) {
                // First, delete the original plugin file if it exists
                if (file_exists($original_plugin_path)) {
                    $delete_result = unlink($original_plugin_path);
                    if ($delete_result) {
                        // Rename the backup file to restore the original plugin file
                        $rename_result = rename($backup_plugin_path, $original_plugin_path);
                        if ($rename_result) {
                            echo 'Changes reverted successfully.';
                        } else {
                            echo 'Failed to restore backup plugin file.';
                        }
                    } else {
                        echo 'Failed to delete original plugin file.';
                    }
                } else {
                    echo 'Original plugin file does not exist.';
                }
            } else {
                echo 'Backup plugin file does not exist.';
            }
        }
    
        // Handle form submission for deactivate button
        if (isset($_POST['deactivate_changes'])) {
            // Check nonce
            if (isset($_POST['deactivate_changes_nonce']) && wp_verify_nonce($_POST['deactivate_changes_nonce'], 'deactivate_changes_nonce')) {
                // Deactivate changes
                deactivate_custom_stock_exporter_plugin();
                echo 'Changes have been deactivated.';
            } else {
                // Nonce verification failed
                echo 'Nonce verification failed.';
            }
        }
    
        // Handle form submission for activate button
        if (isset($_POST['activate_changes'])) {
            // Check nonce
            if (isset($_POST['activate_changes_nonce']) && wp_verify_nonce($_POST['activate_changes_nonce'], 'activate_changes_nonce')) {
                // Activate changes
                activate_custom_stock_exporter_plugin();
                echo 'Changes have been activated.';
            } else {
                // Nonce verification failed
                echo 'Nonce verification failed.';
            }
        }
    }


/***************************************************************************/
// Add field to product settings.
function add_custom_field() {
    //echo '<div class="show_if_simple hidden">';
    woocommerce_wp_text_input(
        array(
            'id' => 'purchase_price',
            'label' => 'Inköpspris',
            'desc_tip' => true,
            'description' => __('Inköpspris för enkel produkt, sätts även på alla produktvarianter utan inköpspris', 'woocommerce'),
        )
    );
    //echo '</div >';
}
add_action('woocommerce_product_options_general_product_data', 'add_custom_field');

function save_custom_field($post_id) {
    $custom_field_value = isset($_POST['purchase_price']) ? sanitize_text_field($_POST['purchase_price']) : '';

    $variations = wc_get_product($post_id)->get_children()??[];
    foreach($variations as $variation) {
        if(!is_numeric(get_post_meta( $variation, "purchase_price_variant", true)) && $custom_field_value != '')
            update_post_meta($variation, 'purchase_price_variant', $custom_field_value);
    }

    if($custom_field_value != '' && get_post($post_id)->post_type == 'product')
        update_post_meta($post_id, 'purchase_price', $custom_field_value);
}
add_action('woocommerce_process_product_meta', 'save_custom_field');

//See if we can add this after the purchase price or sale price.
function add_custom_variant_field( $loop, $variation_data, $variation ) {

    woocommerce_wp_text_input(
            array(
                    'id'            => "purchase_price_variant[$loop]",
                    'label'         => 'Inköpspris',
                    'wrapper_class' => 'form-row',
                    'placeholder'   => '',
                    'desc_tip'      => false,
                    'description'   => '',
                    'value'         => get_post_meta( $variation->ID, "purchase_price_variant", true )
            )
    );
}
add_action( 'woocommerce_product_after_variable_attributes', 'add_custom_variant_field', 10, 3 );

function save_custom_variant_field( $variation_id, $loop ) {
    $text_field = $_POST[ "purchase_price_variant" ][$loop] ?? '';
    update_post_meta( $variation_id, "purchase_price_variant", sanitize_text_field( $text_field ) );
}
add_action( 'woocommerce_save_product_variation', 'save_custom_variant_field', 10, 2 );

// Add custom column to product exports.
add_filter('woocommerce_product_export_product_default_columns', function ($columns) {
    $columns['purchase_price'] = __('Inköpspris', 'woocommerce');
    return $columns;
}, 11);

// Add custom column values to product exports.
add_filter('woocommerce_product_export_product_column_purchase_price', function ($value, $product, $column_id) {
    $id = $product->get_id();
    $value = get_post_meta($id, "purchase_price", true);
    return $value;
}, 11, 3);
