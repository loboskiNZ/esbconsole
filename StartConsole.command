#!/bin/bash

# Navigate to the script's directory
cd "$(dirname "$0")"

echo "========================================"
echo "    Starting X32 Controller Server      "
echo "========================================"
echo ""

# Check for node
if ! command -v node &> /dev/null; then
    echo "Error: Node.js is not installed or not in PATH."
    read -p "Press Enter to close..."
    exit 1
fi

# Run the server
node index.js

# Keep window open if it crashes
echo ""
echo "Server stopped."
read -p "Press Enter to close..."
