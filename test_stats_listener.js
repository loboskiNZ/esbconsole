const io = require('socket.io-client');
const socket = io('http://localhost:3000');

console.log('🔌 Connecting to Socket.IO...');

socket.on('connect', () => {
    console.log('✅ Connected with ID:', socket.id);
});

socket.on('system_stats', (data) => {
    console.log('📊 Received Stats:', data);
    process.exit(0); // Exit after first success
});

socket.on('connect_error', (err) => {
    console.error('❌ Connection Error:', err.message);
});

// Timeout
setTimeout(() => {
    console.log('⏰ Timeout waiting for stats');
    process.exit(1);
}, 5000);
