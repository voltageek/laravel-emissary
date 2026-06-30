<div id="emissary-web-widget" style="position:fixed;bottom:20px;right:20px;z-index:9999;">
    <button id="emissary-chat-toggle"
        style="width:60px;height:60px;border-radius:50%;border:none;background:#4f46e5;color:white;font-size:24px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.3);">
        💬
    </button>
</div>
<script>
(function() {
    var widget = document.getElementById('emissary-web-widget');
    var toggle = document.getElementById('emissary-chat-toggle');
    var isOpen = false;
    var chatBox = null;

    toggle.addEventListener('click', function() {
        isOpen = !isOpen;
        if (isOpen) {
            chatBox = document.createElement('div');
            chatBox.id = 'emissary-chat-box';
            chatBox.style.cssText = 'position:fixed;bottom:90px;right:20px;width:380px;height:520px;background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.2);display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,sans-serif;';
            chatBox.innerHTML = '<div style="background:#4f46e5;color:white;padding:16px;border-radius:12px 12px 0 0;font-weight:600;">Emissary</div><div id="emissary-messages" style="flex:1;overflow-y:auto;padding:16px;"></div><div style="padding:12px;border-top:1px solid #e5e7eb;"><input id="emissary-input" type="text" placeholder="Type a message..." style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;" /></div>';
            document.body.appendChild(chatBox);
            toggle.textContent = '✕';
            document.getElementById('emissary-input').focus();
        } else {
            if (chatBox) chatBox.remove();
            toggle.textContent = '💬';
        }
    });
})();
</script>
