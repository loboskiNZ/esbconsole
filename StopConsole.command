#!/bin/bash

echo "Stopping X32 Controller..."
pkill -f "node index.js"

echo "✅ Server Stopped."
sleep 1
exit 0
