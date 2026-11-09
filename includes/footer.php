</main>

<footer class="footer">
    <div style="max-width:1240px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <strong><?= e(APP_NAME) ?></strong> &copy; <?= date('Y') ?> &mdash; Token-Based Telehealth & Live Queue Consultation System
        </div>
        <div>
            <span style="display:inline-flex; align-items:center; gap:6px;">
                <span class="pulse-dot"></span> System Live &bull; Polling: 3s &bull; Jitsi WebRTC Enabled
            </span>
        </div>
    </div>
</footer>

<script>
// Attach CSRF token automatically to all native fetch calls
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    const originalFetch = window.fetch;
    window.fetch = function(url, init = {}) {
        init.headers = init.headers || {};
        if (init.headers instanceof Headers) {
            if (!init.headers.has('X-CSRF-Token')) {
                init.headers.append('X-CSRF-Token', csrfMeta.content);
            }
        } else if (!init.headers['X-CSRF-Token']) {
            init.headers['X-CSRF-Token'] = csrfMeta.content;
        }
        return originalFetch(url, init);
    };
}
</script>

</body>
</html>
