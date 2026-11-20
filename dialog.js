// Dialog Box Functionality
class NotificationDialog {
    constructor() {
        this.overlay = null;
        this.initialized = false;
        this.init();
    }

    init() {
        // Create dialog elements if they don't exist
        if (!this.initialized) {
            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'dialog-overlay';
            
            // Create dialog box
            const dialogBox = document.createElement('div');
            dialogBox.className = 'dialog-box';
            
            // Create dialog content
            dialogBox.innerHTML = `
                <div class="dialog-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                </div>
                <h3 class="dialog-title">Success!</h3>
                <p class="dialog-message"></p>
                <button class="dialog-button">Continue</button>
            `;
            
            // Append dialog box to overlay
            this.overlay.appendChild(dialogBox);
            
            // Append overlay to body
            document.body.appendChild(this.overlay);
            
            // Add event listener to close button
            const closeButton = dialogBox.querySelector('.dialog-button');
            closeButton.addEventListener('click', () => this.hide());
            
            this.initialized = true;
        }
    }

    show(message, title = 'Success!') {
        // Set dialog content
        const dialogTitle = this.overlay.querySelector('.dialog-title');
        const dialogMessage = this.overlay.querySelector('.dialog-message');
        
        dialogTitle.textContent = title;
        dialogMessage.textContent = message;
        
        // Show dialog
        this.overlay.classList.add('active');
        
        // Prevent scrolling on body
        document.body.style.overflow = 'hidden';
    }

    hide() {
        // Hide dialog
        this.overlay.classList.remove('active');
        
        // Allow scrolling on body
        document.body.style.overflow = '';
    }
}

// Initialize dialog
const notificationDialog = new NotificationDialog();

// Check for success message in session storage (set by PHP)
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = sessionStorage.getItem('success_message');
    if (successMessage) {
        // Show dialog with success message
        notificationDialog.show(successMessage);
        
        // Clear success message from session storage
        sessionStorage.removeItem('success_message');
    }
});