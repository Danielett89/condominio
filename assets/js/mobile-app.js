/**
 * JavaScript per esperienza simile a un'app mobile
 */

document.addEventListener('DOMContentLoaded', function() {
    // Gestione Floating Action Button
    const fab = document.getElementById('fab');
    const fabMenu = document.getElementById('fabMenu');
    
    if (fab && fabMenu) {
        fab.addEventListener('click', function() {
            fabMenu.classList.toggle('show');
        });
        
        // Chiudi il menu quando si tocca altrove
        document.addEventListener('click', function(event) {
            if (!fab.contains(event.target) && !fabMenu.contains(event.target)) {
                fabMenu.classList.remove('show');
            }
        });
    }
    
    // Gestione menu dell'header
    const btnMenu = document.getElementById('btnMenu');
    const headerMenu = document.getElementById('headerMenu');
    
    if (btnMenu && headerMenu) {
        btnMenu.addEventListener('click', function(event) {
            event.stopPropagation();
            headerMenu.classList.toggle('show');
        });
        
        // Chiudi il menu quando si tocca altrove
        document.addEventListener('click', function(event) {
            if (!btnMenu.contains(event.target) && !headerMenu.contains(event.target)) {
                headerMenu.classList.remove('show');
            }
        });
    }
    
    // Gestione Toast
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(toast => {
        // Chiudi il toast dopo 3 secondi
        setTimeout(() => {
            const closeBtn = toast.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 3000);
        
        // Animazione di entrata
        toast.classList.add('fade-in');
    });
    
    // Effetto touch sui card e list-item
    const touchableElements = document.querySelectorAll('.app-card, .list-item, .btn');
    touchableElements.forEach(element => {
        element.addEventListener('touchstart', function() {
            this.classList.add('touched');
        });
        
        element.addEventListener('touchend', function() {
            this.classList.remove('touched');
        });
    });
    
    // Pull to refresh (simulazione)
    let startY = 0;
    let endY = 0;
    const content = document.querySelector('.app-content');
    
    if (content) {
        content.addEventListener('touchstart', function(e) {
            startY = e.touches[0].pageY;
        });
        
        content.addEventListener('touchmove', function(e) {
            endY = e.touches[0].pageY;
        });
        
        content.addEventListener('touchend', function(e) {
            const distance = endY - startY;
            const isAtTop = window.scrollY <= 0;
            
            if (distance > 100 && isAtTop) {
                // Simula refresh
                const refreshIndicator = document.createElement('div');
                refreshIndicator.className = 'refresh-indicator';
                refreshIndicator.innerHTML = '<div class="spinner"></div><div>Aggiornamento...</div>';
                
                content.prepend(refreshIndicator);
                
                setTimeout(() => {
                    refreshIndicator.remove();
                    window.location.reload();
                }, 1000);
            }
        });
    }
    
    // Applica effetto tab view (se presente)
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Rimuovi la classe active da tutti i pulsanti
            tabButtons.forEach(b => b.classList.remove('active'));
            // Aggiungi la classe active al pulsante cliccato
            this.classList.add('active');
            
            // Nascondi tutti i contenuti
            tabContents.forEach(content => content.classList.remove('active'));
            // Mostra il contenuto corrispondente
            const targetId = this.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
});
