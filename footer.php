<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tippy.js@6.3.1/dist/tippy-bundle.umd.min.js"></script>

    <footer class="bg-gray-800 text-white mt-8 py-4">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm">© <?php echo date('Y'); ?> Call Center Management System</p>
                </div>
                <div class="text-sm">
                    <?php if ($attendance && !$attendance['check_out']): ?>
                        <span class="text-green-400">
                            <i class="fas fa-circle text-xs"></i>
                            Working for <?php echo number_format($workingHours, 1); ?> hours
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Global notifications setup
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 left-4 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} 
                                   text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-500 translate-y-full`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateY(0)';
            }, 100);

            // Animate out
            setTimeout(() => {
                notification.style.transform = 'translateY(full)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 500);
            }, 3000);
        }

        // Initialize tooltips
        tippy('[data-tippy-content]', {
            theme: 'light-border'
        });

        // Handle session timeout
        let sessionTimeout;
        function resetSessionTimeout() {
            clearTimeout(sessionTimeout);
            sessionTimeout = setTimeout(() => {
                showNotification('Your session will expire in 5 minutes. Please save your work.', 'error');
            }, 25 * 60 * 1000); // 25 minutes
        }

        document.addEventListener('mousemove', resetSessionTimeout);
        document.addEventListener('keypress', resetSessionTimeout);
        resetSessionTimeout();
    </script>
</body>
</html>