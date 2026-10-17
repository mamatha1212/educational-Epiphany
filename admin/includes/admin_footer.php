        </div> <!-- End .admin-content -->
    </div> <!-- End .admin-main -->
</div> <!-- End .admin-shell -->

<script>
// Sidebar Toggle on Mobile
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('sidebar-open');
        });

        // Close on tap outside
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('sidebar-open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                sidebar.classList.remove('sidebar-open');
            }
        });
    }
});
</script>

</body>
</html>
