<?php
/**
 * @package DTA-Flux_PHP
 *
 * Plugin Name: CFlux_PHP
 * Description: Plugin to pull from DeviceHeartbeat and dta_device_location_map to combine the data together. 
 * Version: 1.0.0
 * Author: Parkway Digital
 */

// Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent running inside WordPress
if (defined('WPINC')) {
    die("This script should not run directly inside WordPress.\n");
}

// --------------------
// Dependencies
// --------------------
require __DIR__ . '/vendor/autoload.php';

use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Dotenv\Dotenv;

// --------------------
// STEP 1: Load .env
// --------------------
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
echo "[STEP 1] .env loaded successfully\n";

// Use $_ENV to reliably read variables
$awsAccessKeyId = $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
$awsSecretAccessKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;

if (empty($awsAccessKeyId) || empty($awsSecretAccessKey)) {
    die("[ERROR] AWS credentials not found in .env\n");
}
echo "[STEP 1] AWS credentials loaded (masked).\n";

// --------------------
// STEP 2: Initialize DynamoDB client
// --------------------
echo "[STEP 2] Initializing DynamoDB client...\n";
$region = "us-east-2";

try {
    $dynamoDb = new DynamoDbClient([
        'region' => $region,
        'version' => 'latest',
        'credentials' => [
            'key' => $awsAccessKeyId,
            'secret' => $awsSecretAccessKey,
        ],
    ]);
    echo "[STEP 2] DynamoDB client initialized successfully.\n";

    // Optional: list tables to verify connection
    $tables = $dynamoDb->listTables();
    echo "[STEP 2] DynamoDB tables: ";
    print_r($tables['TableNames']);

} catch (AwsException $e) {
    die("[ERROR] AWS Exception: " . $e->getMessage() . "\n");
}

// --------------------
// STEP 3: Fetch DynamoDB table items
// --------------------
echo "[STEP 3] Scanning table 'dta_device_location_map'...\n";
try {
    $result = $dynamoDb->scan(['TableName' => 'dta_device_location_map']);
    $items = $result['Items'] ?? [];
    echo "[STEP 3] Number of items retrieved: " . count($items) . "\n";

} catch (AwsException $e) {
    die("[ERROR] AWS Exception during scan: " . $e->getMessage() . "\n");
}

// --------------------
// STEP 4: Display DynamoDB items in a table
// --------------------
echo "[STEP 4] Preparing device table...\n";
$tableByDeviceId = [];
foreach ($items as $item) {
    $payload = $item['payload']['M'] ?? [];
    $deviceId = $payload['device_id']['S'] ?? ($item['device_id']['S'] ?? 'unknown');
    $uname = $payload['uname']['S'] ?? '';
    $timestamp = $payload['timestamp']['S'] ?? '';
    $uptime_start = $payload['uptime_start']['S'] ?? '';

    $tableByDeviceId[$deviceId] = [
        'uname' => $uname,
        'timestamp' => $timestamp,
        'uptime_start' => $uptime_start,
    ];
}

echo "[STEP 4] Device Table:\n";
echo str_pad("Device ID", 15) . str_pad("Uname", 15) . str_pad("Timestamp", 25) . str_pad("Uptime Start", 25) . "\n";
echo str_repeat("-", 80) . "\n";
foreach ($tableByDeviceId as $deviceId => $data) {
    echo str_pad($deviceId, 15) .
         str_pad($data['uname'], 15) .
         str_pad($data['timestamp'], 25) .
         str_pad($data['uptime_start'], 25) . "\n";
}

// --------------------
// STEP 5: CURL request to the Flux API
// --------------------
echo "[STEP 5] Making CURL request to Flux API...\n";

$device_id = '';
$start = '';
$end = '';
$errors = [];

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = $_POST['device_id'] ?? '';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';

    // Validate device_id: must match "pi-" + 3+ digits
    if (!preg_match('/^pi-\d{3,}$/', $device_id)) {
        $errors[] = "Device ID must be in format 'pi-###' or longer digits.";
    }

    // Validate dates: YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $errors[] = "Start date must be in format YYYY-MM-DD.";
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $errors[] = "End date must be in format YYYY-MM-DD.";
    }

    // If valid, append time for API
    if (empty($errors)) {
        $start .= 'T00:00:00Z';
        $end .= 'T00:00:00Z';
    }
}

// Display form
?>
<h3>Fetch Flux Data</h3>
<form method="POST">
    <label>
        Device ID (format: pi-001):
        <input type="text" name="device_id" value="<?= htmlspecialchars($device_id) ?>" required>
    </label><br><br>
    <label>
        Start Date (format: YYYY-MM-DD):
        <input type="text" name="start" value="<?= htmlspecialchars($start ? substr($start,0,10) : '') ?>" required>
    </label><br><br>
    <label>
        End Date (format: YYYY-MM-DD):
        <input type="text" name="end" value="<?= htmlspecialchars($end ? substr($end,0,10) : '') ?>" required>
    </label><br><br>
    <button type="submit">Fetch Data</button>
</form>

<?php
// Display errors if any
if (!empty($errors)) {
    echo "<div style='color:red;'><ul>";
    foreach ($errors as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul></div>";
}

// If valid, proceed to call Flux API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    echo "<p>Fetching data for <strong>$device_id</strong> from <strong>$start</strong> to <strong>$end</strong></p>";
    // Continue with your existing CURL / Flux API logic...
}


$device_id = 'pi-001';
$start = '2025-08-01T00:00:00Z';
$end = '2025-08-20T00:00:00Z';
$url = "https://44u538o4lf.execute-api.us-east-2.amazonaws.com/Stage-1/Flux?device_id={$device_id}&start={$start}&end={$end}";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
]);
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo "[STEP 5] HTTP status: $httpCode\n";
echo "[STEP 5] Raw response:\n$response\n";

// --------------------
// STEP 6: Parse JSON and display tables
// --------------------
echo "[STEP 6] Parsing JSON response...\n";
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("[ERROR] JSON decode error: " . json_last_error_msg() . "\n");
}

$show_debug = true;

// Device info
echo "<h2>Device: " . htmlspecialchars($data['device_id'] ?? 'Unknown') . "</h2>";

// Records
if (!empty($data['records'])) {
    echo "<h3>Records</h3>";
    echo "<table border='1' cellpadding='5'><tr>";
    foreach ($data['records'][0] as $key => $value) {
        echo "<th>" . htmlspecialchars($key) . "</th>";
    }
    echo "</tr>";
    foreach ($data['records'] as $record) {
        echo "<tr>";
        foreach ($record as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records found.</p>";
}

// Summary
if (!empty($data['summary'])) {
    echo "<h3>Summary</h3>";
    echo "<table border='1' cellpadding='5'>";
    foreach ($data['summary'] as $key => $value) {
        echo "<tr><th>" . htmlspecialchars($key) . "</th><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
}

// Debug
if ($show_debug && !empty($data['debug'])) {
    echo "<h3>Debug Info</h3><ul>";
    foreach ($data['debug'] as $line) {
        echo "<li>" . htmlspecialchars($line) . "</li>";
    }
    echo "</ul>";
}

echo "[STEP 6] JSON parsing and table display complete.\n";
echo "=== Script finished ===\n";


// --------------------
// STEP 7: Combine DynamoDB and Flux API data into one table
// --------------------
echo "[STEP 7] Combining DynamoDB and Flux API data...\n";

echo "<h2>Combined Device & Flux Data</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr>";
echo "<th>Device ID</th><th>Uname</th><th>Timestamp</th><th>Uptime Start</th>";

// If Flux API has records, add those keys as table headers
$fluxKeys = [];
if (!empty($data['records'][0])) {
    $fluxKeys = array_keys($data['records'][0]);
    foreach ($fluxKeys as $key) {
        echo "<th>" . htmlspecialchars($key) . "</th>";
    }
}
echo "</tr>";

// Loop through DynamoDB devices
foreach ($tableByDeviceId as $deviceId => $deviceData) {
    // If Flux API has records for this device, merge them
    $matchedRecords = [];
    if (!empty($data['records'])) {
        foreach ($data['records'] as $record) {
            if (($data['device_id'] ?? '') === $deviceId) {
                $matchedRecords[] = $record;
            }
        }
    }

    // If no matching Flux records, still show DynamoDB data
    if (empty($matchedRecords)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($deviceId) . "</td>";
        echo "<td>" . htmlspecialchars($deviceData['uname']) . "</td>";
        echo "<td>" . htmlspecialchars($deviceData['timestamp']) . "</td>";
        echo "<td>" . htmlspecialchars($deviceData['uptime_start']) . "</td>";
        // Fill empty cells for Flux columns
        foreach ($fluxKeys as $key) {
            echo "<td></td>";
        }
        echo "</tr>";
    } else {
        // Show a row for each Flux record
        foreach ($matchedRecords as $record) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($deviceId) . "</td>";
            echo "<td>" . htmlspecialchars($deviceData['uname']) . "</td>";
            echo "<td>" . htmlspecialchars($deviceData['timestamp']) . "</td>";
            echo "<td>" . htmlspecialchars($deviceData['uptime_start']) . "</td>";
            foreach ($fluxKeys as $key) {
                echo "<td>" . htmlspecialchars($record[$key] ?? '') . "</td>";
            }
            echo "</tr>";
        }
    }
}

echo "</table>";
echo "[STEP 7] Combined table generated.\n";
