/**
 * Safiya's Makeover — In-CRM WhatsApp Auto-Send Handler
 * Sends messages directly via the connected WhatsApp Web session
 */

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', async function (e) {
        const btn = e.target.closest('.send-wa');
        if (!btn) return;

        e.preventDefault();

        const phone = btn.dataset.phone;
        const msg = btn.dataset.msg;
        const customerId = btn.dataset.customerId || '';
        const originalHtml = btn.innerHTML;

        if (!phone || !msg) {
            alert('Phone number or message is missing.');
            return;
        }

        // Disable button & show spinner
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';

        try {
            // Determine relative path to api/whatsapp_send.php
            const rootPath = window.APP_ROOT || '';
            const apiUrl = rootPath + (rootPath.endsWith('/') ? '' : '/') + 'api/whatsapp_send.php';

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    phone: phone,
                    msg: msg,
                    customer_id: customerId
                })
            });

            const data = await response.json();

            if (data.sent) {
                btn.className = btn.className.replace('btn-whatsapp-sm', 'btn-success').replace('btn-whatsapp-eve', 'btn-success');
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Sent!';
                
                // Show notification toast if available, or brief alert
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }, 4000);
            } else {
                alert('⚠️ WhatsApp Send Error: ' + (data.error || 'Could not send message. Please ensure WhatsApp is linked in Settings.'));
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (err) {
            console.error('WhatsApp dispatch error:', err);
            alert('Network or server error while connecting to WhatsApp API.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
});
