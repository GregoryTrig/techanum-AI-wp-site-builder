<?php
/**
 * Plugin Name: Prompt To Page
 * Description: Allow users with administrator privileges to create new pages through a conversation with an LLM.
 * Version: 1.0.0
 * Author: Gregory Triglidis
 * Text Domain: techanum.com
 *
 * @package Prompt_To_Page
 */

// Prevent direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

// Include the LLM connector classes
require_once plugin_dir_path(__FILE__) . 'includes/class-llm-connector.php';

/**
 * Activation hook - currently does nothing
 */
function prompt_to_page_activate() {
    // Nothing to do on activation for now
}
register_activation_hook(__FILE__, 'prompt_to_page_activate');

/**
 * Deactivation hook - currently does nothing
 */
function prompt_to_page_deactivate() {
    // Nothing to do on deactivation for now
}
register_deactivation_hook(__FILE__, 'prompt_to_page_deactivate');

/**
 * Get affiliate links for LLM providers
 *
 * @return array Array of provider information with name, url, and description
 */
function ptp_get_affiliate_links() {
    return [
        [
            'name' => 'OpenRouter',
            'url' => 'https://openrouter.ai/?ref=YOURID',
            'description' => 'Access a wide range of LLMs through OpenRouter with competitive pricing.'
        ]
    ];
}

/**
 * Check if API key is configured
 *
 * @return bool True if API key exists, false otherwise
 */
function ptp_is_api_key_configured() {
    $api_key = get_option('ptp_api_key');
    if (empty($api_key)) {
        return false;
    }
    
    // Try to decrypt the API key to verify it's valid
    $decrypted = Prompt_To_Page::decrypt_api_key($api_key);
    return !empty($decrypted);
}

/**
 * Main plugin class
 */
class Prompt_To_Page {

     /**
      * Initialize the plugin
      */
     public static function init() {
         add_action('admin_menu', [self::class, 'add_settings_page']);
         add_action('admin_init', [self::class, 'settings_init']);
         add_action('wp_ajax_create_page_from_prompt', [self::class, 'create_page_from_prompt']);
         add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_scripts']);
         add_action('rest_api_init', [self::class, 'register_rest_routes']);
         add_action('admin_enqueue_scripts', [self::class, 'enqueue_chat_scripts']);
     }

     /**
      * Register REST API routes
      */
     public static function register_rest_routes() {
         register_rest_route('prompt-to-page/v1', '/generate', [
             'methods' => 'POST',
             'callback' => [self::class, 'generate_page_from_prompt'],
             'permission_callback' => [self::class, 'rest_permission_check'],
         ]);
     }

     /**
      * Check if user has permission to access the REST endpoint
      */
     public static function rest_permission_check($request) {
         // Verify nonce
         $nonce = $request->get_header('X-WP-Nonce');
         if (!wp_verify_nonce($nonce, 'wp_rest')) {
             return new WP_Error('rest_forbidden', 'Invalid nonce', ['status' => 403]);
         }

         // Check user capability
         if (!current_user_can('edit_pages')) {
             return new WP_Error('rest_forbidden', 'Insufficient permissions', ['status' => 403]);
         }

         return true;
     }

    /**
     * Add settings page to WordPress admin menu
     */
    public static function add_settings_page() {
        add_options_page(
            __('Prompt To Page Settings', 'techanum.com'),
            __('Prompt To Page', 'techanum.com'),
            'manage_options',
            'prompt-to-page',
            [self::class, 'settings_page_html']
        );
        
        // Add chat page to admin menu
        add_menu_page(
            __('Prompt To Page Chat', 'techanum.com'),
            __('Prompt To Page Chat', 'techanum.com'),
            'edit_pages',
            'prompt-to-page-chat',
            [self::class, 'chat_page_html'],
            'dashicons-editor-paragraph',
            30
        );
    }

    /**
     * Initialize settings
     */
    public static function settings_init() {
        register_setting('prompt_to_page_settings', 'ptp_provider');
        register_setting('prompt_to_page_settings', 'ptp_api_key', [self::class, 'validate_api_key']);
    }

    /**
     * Display the settings page HTML
     */
    public static function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('prompt_to_page_settings');
                do_settings_sections('prompt-to-page');
                ?>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('LLM Provider', 'techanum.com'); ?></th>
                        <td>
                            <select name="ptp_provider" id="ptp_provider" class="regular-text">
                                <option value="openrouter" <?php selected(get_option('ptp_provider'), 'openrouter'); ?>>
                                    <?php esc_html_e('OpenRouter', 'techanum.com'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Don\'t have an API key?', 'techanum.com'); ?></th>
                        <td>
                            <div class="ptp-affiliate-links">
                                <?php
                                $affiliate_links = ptp_get_affiliate_links();
                                foreach ($affiliate_links as $link) {
                                    echo '<div class="ptp-affiliate-link">';
                                    echo '<a href="' . esc_url($link['url']) . '" target="_blank" class="button button-primary">' . esc_html($link['name']) . '</a>';
                                    echo '<p class="description">' . esc_html($link['description']) . '</p>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('API Key', 'techanum.com'); ?></th>
                        <td>
                            <input type="password" name="ptp_api_key" id="ptp_api_key" value="<?php echo esc_attr(get_option('ptp_api_key')); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Enter your LLM API key here.', 'techanum.com'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display the chat page HTML
     */
    public static function chat_page_html() {
        if (!current_user_can('edit_pages')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="ptp-chat-container"></div>
        </div>
        <?php
    }

     /**
      * Get the encryption key from WordPress security keys
      */
     private static function get_encryption_key() {
         // Try to use SECURE_AUTH_KEY first, fall back to AUTH_KEY if not available
         if (defined('SECURE_AUTH_KEY') && !empty(SECURE_AUTH_KEY)) {
             $key = SECURE_AUTH_KEY;
         } elseif (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
             $key = AUTH_KEY;
         } else {
             // If no security key is defined, return false to indicate encryption can't be used
             return false;
         }

         // Use the first 32 characters for AES-256-CBC (256 bits = 32 bytes)
         return substr(hash('sha256', $key, true), 0, 32);
     }

     /**
      * Get the LLM connector based on current settings
      */
     private static function get_connector() {
         // Get the selected provider and API key
         $provider = get_option('ptp_provider', 'openrouter');
         $api_key = self::decrypt_api_key(get_option('ptp_api_key'));

         if (empty($api_key)) {
             return false;
         }

         // Initialize the appropriate connector
         $connector = null;
         switch ($provider) {
             case 'openrouter':
                 $connector = new OpenRouter_Connector($api_key);
                 break;
             default:
                 return false;
         }

         return $connector;
     }

    /**
     * Encrypt API key
     */
    public static function encrypt_api_key($api_key) {
        $encryption_key = self::get_encryption_key();
        if ($encryption_key === false) {
            return false;
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $encryption_key, 0, $iv);
        
        if ($encrypted === false) {
            return false;
        }

        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt API key
     */
    public static function decrypt_api_key($encrypted_api_key) {
        $encryption_key = self::get_encryption_key();
        if ($encryption_key === false) {
            return false;
        }

        $data = base64_decode($encrypted_api_key);
        if ($data === false) {
            return false;
        }

        // Extract IV (first 16 bytes for AES-256-CBC)
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $encryption_key, 0, $iv);
        
        return $decrypted;
    }

    /**
     * Validate and encrypt API key before saving
     */
    public static function validate_api_key($api_key) {
        // If the API key is empty, just return it (don't encrypt empty values)
        if (empty($api_key)) {
            return $api_key;
        }
        
        // Encrypt the API key before saving
        $encrypted = self::encrypt_api_key($api_key);
        if ($encrypted === false) {
            // If encryption fails, return original value to avoid data loss
            return $api_key;
        }
        
        return $encrypted;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on the settings page
        if ($hook !== 'settings_page_prompt-to-page') {
            return;
        }

        wp_enqueue_script(
            'ptp-admin-script',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script(
            'ptp-admin-script',
            'ptp_ajax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ptp_create_page'),
            ]
        );
        
        // Enqueue CSS for settings page
        wp_enqueue_style(
            'ptp-admin-css',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            [],
            '1.0.0'
        );
    }
    
    /**
     * Enqueue chat scripts and styles
     */
    public static function enqueue_chat_scripts($hook) {
        // Only load on the chat page
        if ($hook !== 'toplevel_page_prompt-to-page-chat') {
            return;
        }

        wp_enqueue_script(
            'ptp-chat-script',
            plugin_dir_url(__FILE__) . 'assets/js/chat.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'ptp-chat-script',
            'ptp_chat_ajax',
            [
                'rest_url' => rest_url('prompt-to-page/v1/generate'),
                'nonce' => wp_create_nonce('wp_rest'),
                'api_key_configured' => ptp_is_api_key_configured(),
                'settings_url' => admin_url('options-general.php?page=prompt-to-page'),
            ]
        );
    }

    /**
     * Create a page from a prompt using the LLM
     */
    public static function create_page_from_prompt() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'ptp_create_page')) {
            wp_die(__('Security check failed', 'techanum.com'));
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'techanum.com'));
        }

        // Get the prompt from POST data
        $prompt = sanitize_textarea_field($_POST['prompt']);
        if (empty($prompt)) {
            wp_die(__('Prompt cannot be empty', 'techanum.com'));
        }

        // Get the selected provider and API key
        $provider = get_option('ptp_provider', 'openrouter');
        $api_key = self::decrypt_api_key(get_option('ptp_api_key'));

        if (empty($api_key)) {
            wp_die(__('API key not found', 'techanum.com'));
        }

        // Initialize the appropriate connector
        $connector = null;
        switch ($provider) {
            case 'openrouter':
                $connector = new OpenRouter_Connector($api_key);
                break;
            default:
                wp_die(__('Unsupported provider', 'techanum.com'));
        }

        if (!$connector) {
            wp_die(__('Failed to initialize connector', 'techanum.com'));
        }

        // Prepare messages for the LLM
        $messages = [
            [
                'role' => 'user',
                'content' => $prompt,
            ]
        ];

        // Send prompt to LLM
        $response = $connector->send_prompt($messages);

        // Handle errors from the LLM
        if (is_wp_error($response)) {
            wp_die(__('LLM Error: ' . $response->get_error_message(), 'techanum.com'));
        }

        // Create the page with the response as content
        $page_title = 'Generated Page';
        $page_content = $response;
        
        // Create the page as a draft
        $page_id = wp_insert_post([
            'post_title'   => $page_title,
            'post_content' => $page_content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
        ]);

        if (is_wp_error($page_id)) {
            wp_die(__('Failed to create page: ' . $page_id->get_error_message(), 'techanum.com'));
        }

        // Return success response
        wp_send_json_success([
            'message' => __('Page created successfully', 'techanum.com'),
            'page_id' => $page_id,
        ]);
    }
    
    /**
     * Generate a page from messages using the LLM via REST API
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function generate_page_from_prompt($request) {
        // Get the JSON body data
        $params = $request->get_json_params();
        
        // Retrieve messages and title from request
        $messages = isset($params['messages']) ? $params['messages'] : [];
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : 'AI Draft';
        
        // Validate that we have messages
        if (empty($messages) || !is_array($messages)) {
            return new WP_Error('invalid_request', 'Messages are required and must be an array', ['status' => 400]);
        }

        // Get the connector
        $connector = self::get_connector();
        if (!$connector) {
            return new WP_Error('connector_error', 'Failed to initialize connector', ['status' => 500]);
        }
        
        // Add system prompt to guide the LLM for WordPress content creation
        $system_prompt = [
            'role' => 'system',
            'content' => 'You are an assistant that creates content for WordPress. Respond with clean HTML or a JSON structure of WordPress blocks. Your output will become the content of a page.'
        ];
        array_unshift($messages, $system_prompt);
        
        // Send prompt to LLM
        $response = $connector->send_prompt($messages);
        
        // Handle errors from the LLM
        if (is_wp_error($response)) {
            return new WP_Error('llm_error', 'LLM Error: ' . $response->get_error_message(), ['status' => 500]);
        }

        // Create a new page with the response as content
        $page_content = wp_kses_post($response);
        
        // Create the page as a draft
        $page_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $page_content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
        ]);

        if (is_wp_error($page_id)) {
            return new WP_Error('insert_error', 'Failed to create page: ' . $page_id->get_error_message(), ['status' => 500]);
        }

        // Return the preview URL of the new page in JSON
        $preview_url = get_preview_post_link($page_id);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Page created successfully', 'techanum.com'),
            'page_id' => $page_id,
            'preview_url' => $preview_url
        ]);
    }
}

// Initialize the plugin
Prompt_To_Page::init();
