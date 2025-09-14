<?php
/**
* @package survey-plugin
*
* Plugin Name: Unique Survey URL
* Plugin URI: 
* Description: WordPress plugin to fetch a unique survey URL from an external API via shortcode.
* Version: 1.0.0
* Author: Example Author
* Author URI: https://example.com
* Text Domain: survey-plugin
*/

# Load Composer autoload for Dotenv
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/'); 
$dotenv->load();

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Function to generate and display the unique survey URL.
 *
 * @return string The survey URL.
 */
function generate_survey_url_unique_fn() {
    # Use environment variables for credentials
    $credentials = $_ENV['API_CREDENTIALS'] ?? '';
    $signature   = $_ENV['API_SIGNATURE'] ?? '';

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://example-api.com/fetch-url?items=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'X-Amz-Date: 20250101T000000Z',
            "Authorization: AWS4-HMAC-SHA256 Credential=$credentials, SignedHeaders=host;x-amz-date, Signature=$signature"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    // Default fallback survey URL
    $default_survey_url = 'https://example.com/default-survey';
    $survey_url = $default_survey_url;

    // Try to get survey URL from WordPress options
    if (function_exists('get_field') && get_field('survey_url_option', 'option')) {
        $survey_url = get_field('survey_url_option', 'option');
    }

    // If API response is valid, override with it
    if (!empty($response)) {
        $survey_url = $response;
    }

    return esc_url($survey_url);
} 

/**
 * Register the shortcode [unique_survey_url].
 */
function register_survey_shortcode() {
    add_shortcode('unique_survey_url', 'generate_survey_url_unique_fn');
}
add_action('init', 'register_survey_shortcode');
