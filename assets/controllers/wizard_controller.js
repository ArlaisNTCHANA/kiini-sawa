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
        
        // Optionnel : Validation de l'étape courante ici
        
        if (this.currentStepValue < this.totalStepsValue) {
            this.currentStepValue++;
            this.showCurrentStep();
        }
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
