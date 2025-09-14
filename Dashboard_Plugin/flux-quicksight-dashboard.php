<?php
/**
 * Plugin Name: Example Dashboard Embed
 * Description: Embed a QuickSight (or other BI) dashboard and manage device locations.
 * Version: 1.0.0
 * Author: Example Author
 */

if (!defined('ABSPATH')) exit;

// Register Admin Menu
add_action('admin_menu', function () {
    add_menu_page(
        'Dashboard Embed',
        'Dashboard',
        'manage_options',
        'example-dashboard-embed',
        'example_dashboard_page',
        'dashicons-visibility',
        3
    );
});

// Enqueue Scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook != 'toplevel_page_example-dashboard-embed') return;
    wp_enqueue_style('example_dashboard_style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('example_dashboard_script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
    wp_localize_script('example_dashboard_script', 'ExampleDashboard', [
        'embed_url_api' => admin_url('admin-ajax.php?action=get_embed_url'),
        'api_url' => rest_url('example/v1/locations'),
    ]);
});

// Render Admin Page
function example_dashboard_page()
{
    ?>
    <div class="wrap">
        <h1>Device Dashboard Embed</h1>
        <div class="dashboard-embed">
            <!-- The iframe src will be set dynamically via JS using the embed URL -->
            <iframe id="dashboard-frame" width="100%" height="600" frameborder="0" src=""></iframe>
        </div>
        <h2>Assign Devices to Locations</h2>
        <div id="location-assignment"></div>
    </div>
    <?php
}

// AJAX handler for getting embed URL
add_action('wp_ajax_get_embed_url', 'example_get_embed_url');

function example_get_embed_url()
{
    require_once __DIR__ . '/vendor/autoload.php';

    use Aws\Sdk;

    $sdk = new Sdk([
        'region' => 'your-region-here',
        'version' => 'latest',
        // Credentials via env variables, IAM role, or config
    ]);

    $client = $sdk->createClient('quicksight');

    $params = [
        'AwsAccountId' => 'YOUR_AWS_ACCOUNT_ID',
        'DashboardId'  => 'YOUR_DASHBOARD_ID',
        'IdentityType' => 'QUICKSIGHT',
        'UserArn'      => 'arn:aws:quicksight:your-region:YOUR_AWS_ACCOUNT_ID:user/default/your-user', 
        'SessionLifetimeInMinutes' => 600,
        'ResetDisabled' => false,
        'UndoRedoDisabled' => false,
    ];

    try {
        $result = $client->generateEmbedUrlForRegisteredUser($params);
        wp_send_json_success(['embedUrl' => $result['EmbedUrl']]);
    } catch (Aws\Exception\AwsException $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }

    wp_die();
}
