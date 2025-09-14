#!/bin/bash

set -euo pipefail  # Exit on error, undefined variables, or failed commands

echo "=== Starting MQTT publish script ==="

# Get the directory of the script
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
NAME_FILE="$SCRIPT_DIR/name.txt"

# Load device info safely
if [ ! -f "$NAME_FILE" ]; then
    echo "Error: $NAME_FILE not found"
    exit 1
fi

echo "Loading device info from $NAME_FILE"
source "$NAME_FILE"

# Ensure required variables are defined
: "${device_id:=unknown_device}"
: "${CA_FILE:?Missing CA_FILE in name.txt}"
: "${CERT_FILE:?Missing CERT_FILE in name.txt}"
: "${KEY_FILE:?Missing KEY_FILE in name.txt}"

# Get system info
full_uname=$(uname -a)
uptime_start=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

echo "System info:"
echo "  uname: $full_uname"
echo "  uptime_start: $uptime_start"

# Save updated info back to name.txt
cat > "$NAME_FILE" <<EOL
device_id="$device_id"
CA_FILE="$CA_FILE"
CERT_FILE="$CERT_FILE"
KEY_FILE="$KEY_FILE"
uname="$full_uname"
uptime_start="$uptime_start"
EOL

echo "Updated $NAME_FILE with latest system info."

# Debug: show loaded values
echo "Loaded values:"
echo "  device_id: $device_id"
echo "  CA_FILE: $CA_FILE"
echo "  CERT_FILE: $CERT_FILE"
echo "  KEY_FILE: $KEY_FILE"
echo "  uname: $full_uname"
echo "  uptime_start: $uptime_start"

# Verify mosquitto_pub exists
if ! command -v mosquitto_pub >/dev/null; then
    echo "Error: mosquitto_pub not found. Install with: sudo apt install mosquitto-clients"
    exit 1
fi
echo "mosquitto_pub found."

# Verify certificate files exist
for file in "$CA_FILE" "$CERT_FILE" "$KEY_FILE"; do
    if [ ! -f "$file" ]; then
        echo "Error: Missing file $file"
        exit 1
    fi
    echo "Found file: $file"
done

# Check if certificate and private key match
echo "Checking if certificate matches private key..."
CERT_MOD=$(openssl x509 -noout -modulus -in "$CERT_FILE" | openssl md5)
KEY_MOD=$(openssl rsa -noout -modulus -in "$KEY_FILE" | openssl md5)
if [ "$CERT_MOD" != "$KEY_MOD" ]; then
    echo "Error: Certificate and private key do not match!"
    exit 1
fi
echo "Certificate and private key match."

# Infinite heartbeat publish
i=1
while true; do
    timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    echo "[$i] Publishing heartbeat for device $device_id at $timestamp"

    mosquitto_pub -d \
        --cafile "$CA_FILE" \
        --cert "$CERT_FILE" \
        --key "$KEY_FILE" \
        -h "endpoint" \
        -p 8883 \
        -t "topic" \
        -m "{ \"device_id\": \"$device_id\", \"timestamp\": \"$timestamp\", \"uname\": \"$full_uname\", \"uptime_start\": \"$uptime_start\" }" \
        || echo "Warning: mosquitto_pub failed for iteration $i"

    sleep 5
    ((i++))
done

