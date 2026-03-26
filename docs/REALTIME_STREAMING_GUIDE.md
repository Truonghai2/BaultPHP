# Real-Time & Streaming Guide

## Tổng quan

Hệ thống Real-Time & Streaming đã được triển khai với:

1. **Server-Sent Events (SSE)** - Advanced SSE với automatic reconnection
2. **WebRTC** - P2P communication cho video/audio streaming

## 1. Server-Sent Events (SSE)

### Cấu hình

Thêm vào `.env`:
```env
SSE_ENABLED=true
SSE_HEARTBEAT_INTERVAL=30
SSE_CONNECTION_TIMEOUT=60
SSE_MAX_BUFFER_SIZE=1000
SSE_RETRY_INTERVAL=3000
```

### Features

- ✅ **Real-time streaming** - Stream events to clients in real-time
- ✅ **Automatic reconnection** - Client tự động reconnect khi mất kết nối
- ✅ **Backpressure handling** - Xử lý buffer overflow
- ✅ **Channel-based** - Subscribe to specific channels
- ✅ **Event filtering** - Filter events theo type
- ✅ **Heartbeat/ping** - Keep connection alive

### Sử dụng

#### Client-side (JavaScript)

```javascript
// Connect to SSE stream
const eventSource = new EventSource('/sse/notifications');

// Listen for events
eventSource.addEventListener('message', (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
});

// Listen for specific event types
eventSource.addEventListener('notification', (event) => {
    const data = JSON.parse(event.data);
    console.log('Notification:', data);
});

// Handle connection open
eventSource.onopen = () => {
    console.log('SSE connection opened');
};

// Handle errors (automatic reconnection)
eventSource.onerror = (error) => {
    console.error('SSE error:', error);
    // Client tự động reconnect sau SSE_RETRY_INTERVAL
};
```

#### Server-side (PHP)

```php
use Core\Realtime\SSEStream;

$sseStream = app(SSEStream::class);

// Publish event to channel
$sseStream->publish('notifications', 'notification', [
    'title' => 'New message',
    'body' => 'You have a new message',
    'user_id' => 123,
]);

// Broadcast to all channels
$sseStream->broadcast('system', [
    'message' => 'System maintenance in 5 minutes',
]);

// Get statistics
$stats = $sseStream->getStats();
// ['total_connections' => 10, 'total_channels' => 3, ...]
```

#### Controller Usage

```php
use App\Http\Controllers\SSEController;

// Stream events from channel
GET /sse/{channel}

// Publish event to channel
POST /sse/{channel}/publish
{
    "type": "notification",
    "data": {
        "title": "New message",
        "body": "You have a new message"
    }
}

// Get statistics
GET /sse/stats
```

### Event Format

SSE events follow the standard format:
```
id: 1234567890-abc123
event: notification
data: {"title":"New message","body":"..."}
retry: 3000

```

### Backpressure Handling

Khi buffer đầy (> max_buffer_size):
- Oldest events được drop
- Warning log được ghi
- Connection vẫn hoạt động bình thường

### Automatic Reconnection

Client tự động reconnect khi:
- Connection bị mất
- Server restart
- Network issues

Reconnection interval: `SSE_RETRY_INTERVAL` (default: 3000ms)

## 2. WebRTC

### Cấu hình

Thêm vào `.env`:
```env
WEBRTC_ENABLED=true
WEBRTC_STUN_SERVERS=stun:stun.l.google.com:19302
WEBRTC_TURN_SERVERS=
WEBRTC_SESSION_TIMEOUT=3600
WEBRTC_MAX_PEERS=10
```

### Features

- ✅ **Signaling server** - Handle SDP offer/answer exchange
- ✅ **ICE candidate exchange** - NAT traversal support
- ✅ **Session management** - Manage P2P sessions
- ✅ **Multi-peer support** - Support multiple peers per session

### Use Cases

- **Video/audio streaming** - Real-time video/audio calls
- **Screen sharing** - Share screen between peers
- **Real-time collaboration** - Collaborative editing, whiteboarding
- **Gaming** - P2P game connections

### Sử dụng

#### Server-side (PHP)

```php
use Core\Realtime\WebRTCManager;

$webrtc = app(WebRTCManager::class);

// Create session
$session = $webrtc->createSession('session-123', [
    'type' => 'video-call',
]);

// Join session as peer
$session = $webrtc->joinSession('session-123', 'peer-1');

// Handle SDP offer
$webrtc->handleOffer('session-123', 'peer-1', [
    'type' => 'offer',
    'sdp' => '...',
]);

// Handle SDP answer
$webrtc->handleAnswer('session-123', 'peer-2', [
    'type' => 'answer',
    'sdp' => '...',
]);

// Handle ICE candidate
$webrtc->handleIceCandidate('session-123', 'peer-1', [
    'candidate' => '...',
    'sdpMLineIndex' => 0,
]);
```

#### Client-side (JavaScript)

```javascript
// Create WebRTC connection
const peerConnection = new RTCPeerConnection({
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' }
    ]
});

// Create session
const response = await fetch('/webrtc/sessions', {
    method: 'POST',
    body: JSON.stringify({
        session_id: 'session-123',
        options: { type: 'video-call' }
    })
});
const session = await response.json();

// Join session
const joinResponse = await fetch(`/webrtc/sessions/${session.id}/join`, {
    method: 'POST',
    body: JSON.stringify({ peer_id: 'peer-1' })
});

// Create offer
const offer = await peerConnection.createOffer();
await peerConnection.setLocalDescription(offer);

// Send offer
await fetch(`/webrtc/sessions/${session.id}/offer`, {
    method: 'POST',
    body: JSON.stringify({
        peer_id: 'peer-1',
        offer: {
            type: offer.type,
            sdp: offer.sdp
        }
    })
});

// Listen for ICE candidates
peerConnection.onicecandidate = async (event) => {
    if (event.candidate) {
        await fetch(`/webrtc/sessions/${session.id}/ice`, {
            method: 'POST',
            body: JSON.stringify({
                peer_id: 'peer-1',
                candidate: event.candidate
            })
        });
    }
};

// Poll for answer
const pollAnswer = async () => {
    const response = await fetch(`/webrtc/sessions/${session.id}/answer/peer-2`);
    const answer = await response.json();
    
    if (answer.type) {
        await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
    } else {
        setTimeout(pollAnswer, 1000);
    }
};
pollAnswer();
```

#### API Endpoints

```
POST   /webrtc/sessions                    - Create session
POST   /webrtc/sessions/{id}/join          - Join session
POST   /webrtc/sessions/{id}/offer         - Send SDP offer
POST   /webrtc/sessions/{id}/answer         - Send SDP answer
POST   /webrtc/sessions/{id}/ice            - Send ICE candidate
GET    /webrtc/sessions/{id}/offer/{peerId} - Get offer
GET    /webrtc/sessions/{id}/answer/{peerId} - Get answer
GET    /webrtc/sessions/{id}/ice/{peerId}   - Get ICE candidates
GET    /webrtc/sessions/{id}                - Get session info
POST   /webrtc/sessions/{id}/leave          - Leave session
GET    /webrtc/stats                        - Get statistics
```

### Signaling Flow

1. **Peer A** creates offer → sends to server
2. **Peer B** polls for offer → receives offer
3. **Peer B** creates answer → sends to server
4. **Peer A** polls for answer → receives answer
5. **Both peers** exchange ICE candidates
6. **P2P connection** established

### Session Management

- Sessions tự động expire sau `WEBRTC_SESSION_TIMEOUT`
- Max peers per session: `WEBRTC_MAX_PEERS`
- Cleanup expired sessions định kỳ

## Examples

### Example 1: Real-time Notifications với SSE

```php
// Publish notification
$sseStream = app(SSEStream::class);
$sseStream->publish('notifications', 'notification', [
    'user_id' => 123,
    'title' => 'New message',
    'body' => 'You have a new message from John',
    'url' => '/messages/456',
]);

// Client receives automatically
// Event: notification
// Data: {"user_id":123,"title":"New message",...}
```

### Example 2: Live Chat với SSE

```php
// Publish chat message
$sseStream->publish('chat-room-1', 'message', [
    'user' => 'John Doe',
    'text' => 'Hello everyone!',
    'timestamp' => time(),
]);

// Client filters by event type
// EventSource.addEventListener('message', ...)
```

### Example 3: Video Call với WebRTC

```php
// Create video call session
$webrtc = app(WebRTCManager::class);
$session = $webrtc->createSession('call-123', [
    'type' => 'video-call',
    'participants' => ['user-1', 'user-2'],
]);

// Peers join and exchange SDP/ICE
// P2P connection established
// Video/audio streams flow directly between peers
```

## Best Practices

### SSE

1. **Channel Naming**: Use consistent naming convention
2. **Event Types**: Use specific event types for filtering
3. **Heartbeat**: Keep heartbeat interval reasonable (30s)
4. **Buffer Size**: Tune buffer size based on event frequency
5. **Error Handling**: Handle connection errors gracefully

### WebRTC

1. **STUN/TURN**: Configure proper STUN/TURN servers
2. **Session Cleanup**: Cleanup expired sessions regularly
3. **Error Handling**: Handle signaling errors
4. **ICE Candidates**: Exchange all ICE candidates
5. **Connection State**: Monitor connection state

## Troubleshooting

### SSE Issues

**Connection drops frequently:**
- Check network stability
- Increase `SSE_CONNECTION_TIMEOUT`
- Check server timeout settings

**Events not received:**
- Verify channel name matches
- Check event filter logic
- Verify publish was successful

**High memory usage:**
- Reduce `SSE_MAX_BUFFER_SIZE`
- Cleanup old connections
- Monitor connection count

### WebRTC Issues

**Connection fails:**
- Check STUN/TURN server configuration
- Verify firewall rules
- Check NAT traversal

**Signaling errors:**
- Verify session exists
- Check peer IDs match
- Verify SDP format

**ICE candidates not exchanged:**
- Check polling frequency
- Verify candidate format
- Check network connectivity

## Performance Tips

1. **SSE**: Use channel-based filtering to reduce bandwidth
2. **WebRTC**: Use TURN servers for difficult NATs
3. **Connection Pooling**: Reuse connections when possible
4. **Monitoring**: Monitor connection counts and bandwidth

## Kết luận

Real-Time & Streaming cung cấp:

- ✅ **SSE** với automatic reconnection và backpressure handling
- ✅ **WebRTC** signaling server cho P2P communication
- ✅ **Channel-based** messaging cho SSE
- ✅ **Session management** cho WebRTC
- ✅ **Easy integration** với existing codebase

Enable các features theo nhu cầu và use cases cụ thể.
