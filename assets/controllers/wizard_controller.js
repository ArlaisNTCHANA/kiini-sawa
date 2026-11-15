import { Controller } from '@hotwired/stimulus';

/*
 * Wizard Controller
 * Gère l'affichage d'un formulaire multi-étapes avec barre de progression.
 */
export default class extends Controller {
    static targets = ["step", "progress", "progressText", "prevButton", "nextButton", "submitButton"];
    static values = {
        currentStep: { type: Number, default: 1 },
        totalSteps: Number
    };

    connect() {
        this.totalStepsValue = this.stepTargets.length;
        this.showCurrentStep();
    }

    next(event) {
        if (event) event.preventDefault();

        // Enregistre l'étape quittée, sans bloquer la navigation
        this.autosave(this.stepTargets[this.currentStepValue - 1]);

        if (this.currentStepValue < this.totalStepsValue) {
            this.currentStepValue++;
            this.showCurrentStep();
        }
    }

    /**
     * Envoie l'étape au serveur (autosave), puis reporte dans le formulaire les identifiants
     * des éléments créés : un second enregistrement met à jour au lieu de dupliquer.
     */
    async autosave(stepElement) {
        const form = this.element.querySelector('form');
        const stepNumber = stepElement?.dataset.stepNumber;
        if (!form || !stepNumber) return;

        const data = new FormData(form);
        data.append('autosave', '1');
        data.append('autosave_step', stepNumber);
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const { ids } = await response.json();
            this.applyIds(form, ids || {});
            this.showSaved();
        } catch (e) {
            // Hors ligne : l'enregistrement final au dernier écran prendra le relais
        }
    }

    applyIds(form, ids) {
        Object.entries(ids).forEach(([field, rows]) => {
            const simple = form.querySelectorAll(`[name="${field}_ids[]"]`);
            if (simple.length) {
                Object.entries(rows).forEach(([i, id]) => { if (simple[i] && id) simple[i].value = id; });
                return;
            }
            const idField = field === 'hygiene_domain_name' ? 'hygiene_id' : field.replace(/_(title|name)$/, '_id');
            Object.entries(rows).forEach(([i, id]) => {
                if (!id) return;
                const title = form.querySelector(`[name="${field}[${i}]"]`);
                if (!title) return;
                let hidden = title.parentElement.querySelector(`[name^="${idField}["]`);
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    title.after(hidden);
                }
                hidden.name = `${idField}[${i}]`;
                hidden.value = id;
            });
        });
    }

    showSaved() {
        if (!this.hasProgressTextTarget) return;
        const label = this.progressTextTarget;
        label.dataset.saved = '1';
        label.textContent = `Étape ${this.currentStepValue} sur ${this.totalStepsValue} · enregistré ✓`;
        clearTimeout(this.savedTimer);
        this.savedTimer = setTimeout(() => {
            label.textContent = `Étape ${this.currentStepValue} sur ${this.totalStepsValue}`;
        }, 2500);
    }

    previous(event) {
        if (event) event.preventDefault();
        
        if (this.currentStepValue > 1) {
            this.currentStepValue--;
            this.showCurrentStep();
        }
    }

    showCurrentStep() {
        // Mettre à jour l'affichage des étapes
        this.stepTargets.forEach((element, index) => {
            if (index + 1 === this.currentStepValue) {
                element.classList.remove('hidden');
                // Animation simple (peut être gérée par CSS)
                element.style.animation = 'none';
                element.offsetHeight; // trigger reflow
                element.style.animation = null; 
            } else {
                element.classList.add('hidden');
            }
        });

        // Mettre à jour la barre de progression si présente
        if (this.hasProgressTarget) {
            const percentage = ((this.currentStepValue - 1) / (this.totalStepsValue - 1)) * 100;
            this.progressTarget.style.width = `${percentage}%`;
        }

        // Mettre à jour le texte de progression
        if (this.hasProgressTextTarget) {
            this.progressTextTarget.textContent = `Étape ${this.currentStepValue} sur ${this.totalStepsValue}`;
        }

        // Mettre à jour la visibilité des boutons
        if (this.hasPrevButtonTarget) {
            if (this.currentStepValue === 1) {
                this.prevButtonTarget.classList.add('invisible');
            } else {
                this.prevButtonTarget.classList.remove('invisible');
            }
        }

        if (this.hasNextButtonTarget && this.hasSubmitButtonTarget) {
            if (this.currentStepValue === this.totalStepsValue) {
                this.nextButtonTarget.classList.add('hidden');
                this.submitButtonTarget.classList.remove('hidden');
            } else {
                this.nextButtonTarget.classList.remove('hidden');
                this.submitButtonTarget.classList.add('hidden');
            }
        }
    }
}
