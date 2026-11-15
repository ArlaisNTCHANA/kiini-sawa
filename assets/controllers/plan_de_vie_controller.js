import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tabContent', 'tabLink', 'goalProgress', 'goalPercent'];

    connect() {
        // Restore active tab from localStorage if it exists
        const activeTab = localStorage.getItem('ks_active_plan_tab') || 'mission';
        this._showTab(activeTab);
    }

    switchTab(event) {
        event.preventDefault();
        const tabName = event.currentTarget.dataset.tab;
        localStorage.setItem('ks_active_plan_tab', tabName);
        this._showTab(tabName);
    }

    _showTab(tabName) {
        // Update tab buttons
        this.tabLinkTargets.forEach(link => {
            if (link.dataset.tab === tabName) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // Update tab contents
        this.tabContentTargets.forEach(content => {
            if (content.id === `tab-${tabName}`) {
                content.style.display = 'block';
            } else {
                content.style.display = 'none';
            }
        });
    }

    // Toggle milestone completion state via AJAX
    async toggleMilestone(event) {
        const checkbox = event.currentTarget;
        const milestoneId = checkbox.dataset.milestoneId;
        const toggleUrl = `/admin/plan-de-vie/milestone/toggle/${milestoneId}`;

        try {
            checkbox.disabled = true;
            const response = await fetch(toggleUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (!response.ok) {
                throw new Error('Erreur de communication avec le serveur.');
            }

            const data = await response.json();

            if (data.success) {
                // Find and update progress indicators for the goal
                const goalId = data.goalId;
                
                // Update progress bar width
                const progressBar = this.goalProgressTargets.find(el => el.dataset.goalId == goalId);
                if (progressBar) {
                    progressBar.style.width = `${data.goalProgress}%`;
                }

                // Update progress text
                const progressText = this.goalPercentTargets.find(el => el.dataset.goalId == goalId);
                if (progressText) {
                    progressText.textContent = `${data.goalProgress}%`;
                }

                // Visual feedback on the list item
                const label = checkbox.closest('label');
                if (label) {
                    if (data.completed) {
                        label.classList.add('completed');
                    } else {
                        label.classList.remove('completed');
                    }
                }
            } else {
                alert(data.message || 'Une erreur est survenue.');
                checkbox.checked = !checkbox.checked; // Revert checkbox
            }
        } catch (error) {
            console.error('Error toggling milestone:', error);
            alert('Impossible de mettre à jour le jalon. Veuillez vérifier votre connexion.');
            checkbox.checked = !checkbox.checked; // Revert checkbox
        } finally {
            checkbox.disabled = false;
        }
    }

    // Modal helpers
    openModal(event) {
        const modalId = event.currentTarget.dataset.modalId;
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(event) {
        const modalId = event.currentTarget.dataset.modalId;
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
}
