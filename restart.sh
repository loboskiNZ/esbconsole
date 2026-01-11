#!/bin/bash
echo "🛑 Stopping any running server instances..."
# Use || true to ignore error if no process is found
pkill -f "node index.js" || true
# Wait a moment to ensure port is freed
sleep 1
echo "🚀 Starting server..."
node index.js
