#!/bin/bash

# Navigate to the script's directory
cd "$(dirname "$0")"

# Start in background, redirect output to log, and ignore hangup signal
nohup node index.js > server.log 2>&1 &

# Save the PID (Optional, but good practice)
echo $! > server.pid

echo "✅ Server started in background."
echo "📝 Logs are being written to server.log"
echo "❌ Run StopConsole.command to stop it."

# Give the user a moment to see the message, then close
sleep 2
exit 0
