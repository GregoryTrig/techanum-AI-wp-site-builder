<?php
/**
 * Uninstall script for Prompt To Page plugin.
 *
 * This file is called when the plugin is uninstalled from WordPress.
 * It removes the plugin options from the database.
 */

// Prevent direct access to this file
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete the plugin options
delete_option('ptp_provider');
delete_option('ptp_api_key');