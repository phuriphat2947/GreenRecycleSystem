<!-- Chat Widget CSS -->
<style>
    .chat-widget-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--primary, #27ae60);
        color: white;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        z-index: 9999;
        transition: transform 0.3s;
    }

    .chat-widget-btn:hover {
        transform: scale(1.1);
    }

    .chat-box {
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 350px;
        max-width: 90%;
        height: 450px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        z-index: 9999;
        display: none;
        overflow: hidden;
    }

    .chat-header {
        background: var(--primary, #27ae60);
        color: white;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
    }

    .chat-messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #f9f9f9;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .message {
        max-width: 80%;
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .msg-user {
        align-self: flex-end;
        background: var(--primary, #27ae60);
        color: white;
        border-bottom-right-radius: 2px;
    }

    .msg-admin {
        align-self: flex-start;
        background: #e0e0e0;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .chat-input-area {
        padding: 10px;
        border-top: 1px solid #ddd;
        background: white;
        display: flex;
        gap: 10px;
    }

    .chat-input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
    }

    .chat-send-btn {
        background: none;
        border: none;
        color: var(--primary, #27ae60);
        font-size: 1.2rem;
        cursor: pointer;
    }
</style>

<!-- Chat UI -->
<div class="chat-widget-btn" onclick="toggleChat()">
    <i class="fas fa-comment-dots"></i>
</div>

<div class="chat-box" id="chatBox">
    <div class="chat-header">
        <span style="display:flex; align-items:center; gap:8px;"><i class="fas fa-headset"></i> Support</span>
        <div style="display:flex; align-items:center; gap:10px;">
            <button onclick="endChat()" style="background:rgba(255,255,255,0.2); border:none; color:white; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.8rem;">
                <i class="fas fa-times-circle"></i> จบสนทนา
            </button>
            <i class="fas fa-minus" style="cursor:pointer;" onclick="toggleChat()"></i>
        </div>
    </div>
    <div class="chat-messages" id="chatMessages">
        <!-- Messages will load here -->
    </div>
    <div class="chat-input-area">
        <input type="text" id="chatInput" class="chat-input" placeholder="พิมพ์ข้อความ..." onkeypress="handleEnter(event)">
        <button class="chat-send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Chat Logic -->
<script>
    let chatVisible = false;
    let lastMsgId = 0;
    let pollingInterval;

    function toggleChat() {
        const box = document.getElementById('chatBox');
        chatVisible = !chatVisible;
        box.style.display = chatVisible ? 'flex' : 'none';

        if (chatVisible) {
            scrollToBottom();
            startPolling();
        } else {
            stopPolling();
        }
    }

    function scrollToBottom() {
        const container = document.getElementById('chatMessages');
        container.scrollTop = container.scrollHeight;
    }

    function handleEnter(e) {
        if (e.key === 'Enter') sendMessage();
    }

    function sendMessage() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg) return;

        // Optimistic UI Append
        // appendMessage(msg, 'user'); // Disable optimistic append to avoid duplicate on quick poll
        // input.value = '';

        // Send to API
        fetch('../services/chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=send&message=' + encodeURIComponent(msg)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    input.value = ''; // Clear only on success
                    fetchMessages(); // Fetch immediately
                } else {
                    console.error('Send failed');
                }
            });
    }

    function appendMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'message ' + (type === 'user' ? 'msg-user' : 'msg-admin');
        div.textContent = text;
        document.getElementById('chatMessages').appendChild(div);
        scrollToBottom();
    }

    function fetchMessages() {
        fetch(`../services/chat_api.php?action=fetch&last_id=${lastMsgId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Check if history was cleared (archive) by verifying if we got empty messages but had lastId
                    // Actually, if we archived, fetch returns empty array, but that's fine.
                    // If backend return empty, we do nothing.

                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            // Append ALL messages (User & Admin) that are new
                            if (msg.sender_type === 'admin') {
                                appendMessage(msg.message, 'admin');
                            } else {
                                appendMessage(msg.message, 'user');
                            }
                            if (msg.id > lastMsgId) lastMsgId = msg.id;
                        });
                    }
                }
            });
    }

    function endChat() {
        if (confirm('ต้องการจบการสนทนาใช่หรือไม่? ประวัติการแชทจะถูกล้างออกจากหน้าจอนี้')) {
            fetch('../services/chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=end_chat'
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    document.getElementById('chatMessages').innerHTML = '<div style="text-align:center; padding:20px; color:#999;"><i class="fas fa-check-circle"></i> การสนทนาจบลงแล้ว</div>';
                    lastMsgId = 0; // Reset
                }
            });
        }
    }

    function startPolling() {
        fetchMessages(); // First fetch
        pollingInterval = setInterval(fetchMessages, 3000);
    }

    function stopPolling() {
        clearInterval(pollingInterval);
    }
</script>