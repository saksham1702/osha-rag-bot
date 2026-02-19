<?php
/**
 * Plugin Name: OSHA Safety Chat Widget
 * Plugin URI: https://github.com/saksham1702/osha-rag-bot
 * Description: AI-powered chat widget that answers OSHA workplace safety questions using 500+ regulation pages
 * Version: 1.1.0
 * Author: Saksham
 * Author URI: https://github.com/saksham1702
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class OSHA_Chat_Widget {

    private $api_url;

    public function __construct() {
        // OSHA API endpoint - update this if deployment changes
        $this->api_url = get_option('osha_chat_api_url', 'https://apirg.knowella.com:8443/chat');

        // Only initialize if Knowella plugin is not causing conflicts
        add_action('init', array($this, 'init_plugin'));
    }

    /**
     * Initialize plugin after checking for conflicts
     */
    public function init_plugin() {
        // Add admin menu with unique slug
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // Add shortcode for full-page chat
        add_shortcode('osha_fullpage_chat', array($this, 'render_fullpage_chat'));
        
        // Enqueue styles only when shortcode is used
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_styles'));
        
        // Add custom page template support
        add_filter('template_include', array($this, 'load_custom_template'));
        add_filter('theme_page_templates', array($this, 'add_page_template'));
    }

    /**
     * Enqueue styles only on pages that use the shortcode
     */
    public function maybe_enqueue_styles() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'osha_fullpage_chat')) {
            wp_enqueue_style(
                'osha-chat-widget-styles',
                plugins_url('assets/osha-widget.css', __FILE__),
                array(),
                '1.1.0'
            );
        }
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            'OSHA Chat Widget Settings',
            'OSHA Safety Chat',
            'manage_options',
            'osha-chat-widget',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('osha_chat_settings', 'osha_chat_api_url');
        register_setting('osha_chat_settings', 'osha_chat_theme');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include plugin_dir_path(__FILE__) . 'templates/settings.php';
    }

    /**
     * Render full-page chat interface (shortcode)
     */
    public function render_fullpage_chat() {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/fullpage-chat.php';
        return ob_get_clean();
    }

    /**
     * Add custom page template to theme templates
     */
    public function add_page_template($templates) {
        $templates['page-osha-fullscreen.php'] = 'OSHA Chat Fullscreen';
        return $templates;
    }

    /**
     * Load custom template if selected
     */
    public function load_custom_template($template) {
        global $post;
        
        if (is_page() && $post) {
            $page_template = get_page_template_slug($post->ID);
            if ($page_template === 'page-osha-fullscreen.php') {
                $custom_template = plugin_dir_path(__FILE__) . 'templates/page-osha-fullscreen.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }
}

// Initialize plugin
new OSHA_Chat_Widget();
