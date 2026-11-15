import { Controller } from '@hotwired/stimulus';

/**
 * Sidebar toggle controller for admin layout.
 * Handles expand/collapse on desktop, slide in/out on mobile.
 */
export default class extends Controller {
    static targets = ['sidebar', 'overlay', 'toggleIcon'];

    connect() {
        // Check stored preference
        const stored = localStorage.getItem('ks_sidebar_collapsed');
        if (stored === 'true') {
            this.sidebarTarget.classList.add('collapsed');
        }

        // Listen for responsive changes
        this._mediaQuery = window.matchMedia('(max-width: 1024px)');
        this._handleResize = this._onResize.bind(this);
        this._mediaQuery.addEventListener('change', this._handleResize);
    }

    disconnect() {
        this._mediaQuery?.removeEventListener('change', this._handleResize);
    }

    toggle() {
        const sidebar = this.sidebarTarget;
        const isMobile = window.innerWidth <= 1024;

        if (isMobile) {
            sidebar.classList.toggle('mobile-open');
            this.overlayTarget.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        } else {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('ks_sidebar_collapsed', sidebar.classList.contains('collapsed'));
        }
    }

    closeOverlay() {
        this.sidebarTarget.classList.remove('mobile-open');
        this.overlayTarget.classList.remove('active');
        document.body.style.overflow = '';
    }

    _onResize(e) {
        if (!e.matches) {
            // Going to desktop
            this.sidebarTarget.classList.remove('mobile-open');
            this.overlayTarget.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
}
