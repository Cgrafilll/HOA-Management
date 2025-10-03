// mobileSidebar.js - Reusable Mobile Sidebar Toggle Module

const MobileSidebar = {
    init: function (options = {}) {
        const config = {
            menuBtnId: options.menuBtnId || 'mobileMenuBtn',
            sidebarSelector: options.sidebarSelector || '.sidebar',
            overlayId: options.overlayId || 'sidebarOverlay',
            breakpoint: options.breakpoint || 768,
            showClass: options.showClass || 'show'
        };

        const menuBtn = document.getElementById(config.menuBtnId);
        const sidebar = document.querySelector(config.sidebarSelector);
        const overlay = document.getElementById(config.overlayId);

        if (!menuBtn || !sidebar || !overlay) {
            console.warn('MobileSidebar: Required elements not found');
            return;
        }

        // Toggle sidebar on button click
        menuBtn.addEventListener('click', function () {
            sidebar.classList.toggle(config.showClass);
            overlay.classList.toggle(config.showClass);
        });

        // Close sidebar on overlay click
        overlay.addEventListener('click', function () {
            MobileSidebar.close(sidebar, overlay, config.showClass);
        });

        // Close sidebar when clicking any link on mobile
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= config.breakpoint) {
                    MobileSidebar.close(sidebar, overlay, config.showClass);
                }
            });
        });
    },

    close: function (sidebar, overlay, showClass = 'show') {
        sidebar.classList.remove(showClass);
        overlay.classList.remove(showClass);
    },

    open: function (sidebar, overlay, showClass = 'show') {
        sidebar.classList.add(showClass);
        overlay.classList.add(showClass);
    }
};

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    MobileSidebar.init();
});