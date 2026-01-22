<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
$current_page = 'chat';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Chat Support - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 100px);
            /* Adjust based on header */
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin: 20px;
        }

        /* Left: User List */
        .user-list {
            width: 300px;
            border-right: 1px solid #eee;
            background: #fdfdfd;
            display: flex;
            flex-direction: column;
        }

        .user-list-header {
            padding: 15px;
            font-weight: bold;
            border-bottom: 1px solid #eee;
            background: #f9f9f9;
        }

        .user-items {
            flex: 1;
            overflow-y: auto;
        }

        .user-item {
            padding: 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .user-item:hover {
            background: #f0f9f0;
        }

        .user-item.active {
            background: #e8f5e9;
            border-left: 4px solid var(--primary);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            background: #ddd;
        }

        .user-info {
            flex: 1;
            overflow: hidden;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #333;
        }

        .last-msg {
            font-size: 0.8rem;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
            margin-left: 5px;
        }

        /* Right: Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            display: flex;
            align-items: center;
            background: #fff;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f4f6f9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 12px;
            font-size: 0.95rem;
            line-height: 1.4;
            position: relative;
        }

        .msg-user {
            align-self: flex-start;
            background: white;
            color: #333;
            border: 1px solid #eee;
            border-bottom-left-radius: 2px;
        }

        .msg-admin {
            align-self: flex-end;
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 2px;
        }

        .msg-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 5px;
            display: block;
            text-align: right;
        }

        .chat-input-box {
            padding: 20px;
            border-top: 1px solid #eee;
            background: white;
            display: flex;
            gap: 15px;
        }

        .chat-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
            font-family: inherit;
        }

        .chat-input:focus {
            border-color: var(--primary);
        }

        .btn-send {
            background: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
        }

        .btn-send:hover {
            background: #219150;
            transform: scale(1.05);
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main" style="height: 100vh; overflow: hidden; display:flex; flex-direction:column;">
        <header class="admin-header" style="flex-shrink:0;">
            <div class="admin-title">
                <h2><i class="fas fa-comments"></i> Chat Support</h2>
                <span class="admin-subtitle">ตอบข้อความลูกค้า</span>
            </div>
        </header>

        <div class="chat-container">
 
            <div class="user-list">
                <div class="user-list-header">รายการแชทล่าสุด</div>
                <div class="user-items" id="userList">
 
                    <div style="padding:20px; text-align:center; color:#999;">Loading...</div>
                </div>
            </div>

 
            <div class="chat-area">
                <div class="chat-header" id="chatHeader" style="display:none; justify-content: space-between;">
                    <div style="display:flex; align-items:center;">
                        <img id="activeAvatar" src="" class="user-avatar">
                        <span id="activeName">Username</span>
                    </div>
                    <button class="btn-end" onclick="endChatSession()" style="background:#e74c3c; color:white; border:none; padding:8px 15px; border-radius:20px; cursor:pointer; font-size:0.85rem;">
                        <i class="fas fa-times-circle"></i> จบสนทนา (End Chat)
                    </button>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <p>เลือกผู้ใช้งานทางซ้ายเพื่อเริ่มสนทนา</p>
                    </div>
                </div>

                <div class="chat-input-box" id="inputBox" style="display:none;">
                    <input type="text" id="msgInput" class="chat-input" placeholder="พิมพ์ข้อความตอบกลับ..." onkeypress="handleEnter(event)">
                    <button class="btn-send" onclick="sendReply()"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

 
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        let selectedUserId = null;
        let lastMsgId = 0;
        let chatInterval;
 
        function loadUsers() {
            $.get('../services/chat_api.php?action=get_users', function(res) {
                if (res.status === 'success') {
                    let html = '';
                    res.users.forEach(u => {
                        let activeClass = (u.id == selectedUserId) ? 'active' : '';
                        let avatar = u.profile_image ? '../assets/images/uploads/' + u.profile_image : '../assets/images/default_avatar.png'; // Fix path if needed
                        if (u.profile_image === 'default_avatar.png') avatar = 'https://via.placeholder.com/40';

                        html += `
                            <div class="user-item ${activeClass}" onclick="selectUser(${u.id}, '${u.username}', '${avatar}')">
                                <img src="${avatar}" class="user-avatar">
                                <div class="user-info">
                                    <div class="user-name">${u.username} 
                                        ${u.unread_count > 0 ? `<span class="unread-badge">${u.unread_count}</span>` : ''}
                                    </div>
                                    <div class="last-msg">${u.last_msg_text || 'Sent an image'}</div>
                                </div>
                            </div>
                        `;
                    });
                    $('#userList').html(html);
                }
            });
        }

 
        function selectUser(uid, name, avatar) {
            selectedUserId = uid;
            lastMsgId = 0;  
 
            $('#chatHeader').show();
            $('#inputBox').show();
            $('#activeName').text(name);
            $('#activeAvatar').attr('src', avatar);
            $('#chatMessages').html('<div style="text-align:center; padding:20px;">Loading...</div>');

            clearInterval(chatInterval); 
            fetchConversation();  
            chatInterval = setInterval(fetchConversation, 3000);  
            loadUsers();  
        }

    
        function fetchConversation() {
            if (!selectedUserId) return;

            $.get(`../services/chat_api.php?action=get_conversation&target_user_id=${selectedUserId}&last_id=${lastMsgId}`, function(res) {
                if (res.status === 'success') {
                    if (lastMsgId === 0) $('#chatMessages').empty(); // Clear if first load

                    if (res.messages.length > 0) {
                        res.messages.forEach(msg => {
                            let typeClass = (msg.sender_type === 'admin') ? 'msg-admin' : 'msg-user';
                            let html = `
                                <div class="message ${typeClass}">
                                    ${msg.message}
                                    <span class="msg-time">${msg.created_at}</span>
                                </div>
                            `;
                            $('#chatMessages').append(html);
                            lastMsgId = msg.id;
                        });
                        scrollToBottom();
                    }
                }
            });
        }

        // 4. Send Reply
        function sendReply() {
            let txt = $('#msgInput').val().trim();
            if (!txt || !selectedUserId) return;

            $.post('../services/chat_api.php', {
                action: 'admin_send',
                target_user_id: selectedUserId,
                message: txt
            }, function(res) {
                if (res.status === 'success') {
                    $('#msgInput').val('');
                    fetchConversation(); // Update immediately
                    loadUsers(); // Update list order
                }
            });
        }

        function handleEnter(e) {
            if (e.key === 'Enter') sendReply();
        }

        function scrollToBottom() {
            let div = document.getElementById('chatMessages');
            div.scrollTop = div.scrollHeight;
        }

        function endChatSession() {
            if (!selectedUserId) return;
            if (confirm('ยืนยันจบการสนทนากับผู้ใช้นี้? ประวัติการแชทจะถูกย้ายไประบบ Archive')) {
                $.post('../services/chat_api.php', {
                    action: 'end_chat',
                    user_id: selectedUserId
                }, function(res) {
                    if (res.status === 'success') {
                        $('#chatMessages').html('<div class="empty-state"><i class="fas fa-check-circle" style="color:#27ae60"></i><p>การสนทนาจบลงแล้ว</p></div>');
                        $('#inputBox').hide();
                        loadUsers(); // Refresh list
                    }
                }, 'json');
            }
        }

        // Init
        $(document).ready(function() {
            loadUsers();
            setInterval(loadUsers, 5000); // Refresh user list every 5s
        });
    </script>
</body>

</html>