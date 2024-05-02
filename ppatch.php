<?php
spl_autoload_register(function ($class) {
    $fname =  __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';;
    if(file_exists($fname))
            require_once $fname;
});
use DiffMatchPatch\DiffMatchPatch;

// JKL 240321

    // Constants for error codes
    define('PATCH_SUCCESS', 0);
    define('PATCH_NO_APPLIED', 1);
    define('PATCH_NOT_ALL_APPLIED', 2);
    define('PATCH_WRITE_ACCESS_ERROR', 3);
    define('PATCH_FILE_OPERATION_ERROR', 4);
    define('PATCH_ALREADY_APPLIED', 5);
    define('PATCH_WRONG_PLUGIN_VERSION', 6);

    // Error messages corresponding to error codes
    $errorMessages = [
        PATCH_SUCCESS => "Success - patch applied display message.",
        PATCH_NO_APPLIED => "No patch applied at all.",
        PATCH_NOT_ALL_APPLIED => "Not all patches applied successfully. No write access error message.",
        PATCH_WRITE_ACCESS_ERROR => "Unable to write to the plugin directory. Display error message.",
        PATCH_FILE_OPERATION_ERROR => "Unable to perform file operation. Display error message.",
        PATCH_ALREADY_APPLIED => "Patch already applied.",
        PATCH_WRONG_PLUGIN_VERSION => "Incorrect version of the plugin. Do not display any button."
    ];
    function wappo_ppatch()
    {
    // Patch string (base64 encoded)
    $patch_string = "QEAgLTQ2MjgsMzIgKzQ2MjgsNDQ4IEBACiBmaXhlZCcsJTBBJTA5JTA5JTA5JTA5JTA5KSwlMEEKKyAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAvLyB3YXBwbyBwdXJjaGFzZSBwcmljZSUwQSAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBhcnJheSglMEEgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAndmFsdWUnID0lM0UgJ3B1cmNoYXNlX3ByaWNlJywlMEEgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnbGFiZWwnID0lM0UgX18oICdQdXJjaGFzZSBwcmljZScsICdzdG9jay1leHBvcnRlci1mb3Itd29vY29tbWVyY2UnICksJTBBICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3R5cGUnICA9JTNFICdmaXhlZCcsJTBBICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICksJTBBCiAlMDklMDklMDklMDkpOyUwQSUwOSUwOSUwOSU3RCUwQSUwQSUwOSUwOSUwOQpAQCAtMjA0OTUsMjQgKzIwNDk1LDYyMCBAQAogKSwlMEElMDklMDklMDklMDklMDklMDkpOyUwQQorICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJTBBICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIC8vIHdhcHBvIHB1cmNoYXNlIHByaWNlJTBBICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNhc2UgJ3B1cmNoYXNlX3ByaWNlJzolMEEgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAkdmFsdWUgPSBnZXRfcG9zdF9tZXRhKCRpZCwgJ3B1cmNoYXNlX3ByaWNlJywgdHJ1ZSk7JTBBICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgaWYoaXNfbnVtZXJpYygkdmFsdWUpKSUwQSAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGFycmF5KCR2YWx1ZSk7JTBBICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgZWxzZSUwQSAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGFycmF5KGdldF9wb3N0X21ldGEoJGlkLCAncHVyY2hhc2VfcHJpY2VfdmFyaWFudCcsIHRydWUpPz8nJyk7JTBBCiAlMDklMDklMDklMDklN0QlMEElMDklMDklMDklN0QlMEElMEEK";

    // File paths
    $org_file = WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/woocommerce-stock-exporter.php';
    $backup_file = $org_file . '.org';

    // Check if patch is already applied
    if (strpos(file_get_contents($org_file), '// wappo purchase price') !== false) {
        return PATCH_ALREADY_APPLIED;
    }

    // Check if correct version of the plugin
    if (strpos(file_get_contents($org_file), 'Version: 1.3') === false) {
        return PATCH_WRONG_PLUGIN_VERSION;
    }

    // Decode patch string
    $patch = base64_decode($patch_string);

    // Initialize DiffMatchPatch
    $dmp = new DiffMatchPatch();

    // Apply patch
    $org = file_get_contents($org_file);
    $patches = $dmp->patch_fromText($patch);
    $result = $dmp->patch_apply($patches, $org);

    // Check if any patch applied
    if (!count($result[1])) {
        return PATCH_NO_APPLIED;
    }

    // Check if all patches applied successfully
    foreach ($result[1] as $status) {
        if (!$status) {
            return PATCH_NOT_ALL_APPLIED;
        }
    }

    // Backup original file
    if (!copy($org_file, $backup_file)) {
        return PATCH_WRITE_ACCESS_ERROR;
    }

    // Write patched content to file
    if (!file_put_contents($org_file, $result[0])) {
        // Restore original file if failed to write patched content
        if (!copy($backup_file, $org_file)) {
            return PATCH_FILE_OPERATION_ERROR;
        }
        return PATCH_FILE_OPERATION_ERROR;
    }

    return PATCH_SUCCESS;
}
// Function to deactivate the patch
function deactivate_patch() {
    // File paths
    $org_file = WP_PLUGIN_DIR . '/stock-exporter-for-woocommerce/woocommerce-stock-exporter.php';
    $backup_file = $org_file . '.org'; // Assuming the original file was backed up with this extension

    // Check if the backup file exists
    if (!file_exists($backup_file)) {
        // If the backup file doesn't exist, return false indicating failure
        return false;
    }

    // Restore the original file from the backup
    if (!copy($backup_file, $org_file)) {
        // If failed to copy the backup file back to the original file path, return false
        return false;
    }

    // If everything went well, return true to indicate successful deactivation
    return true;
}

?>