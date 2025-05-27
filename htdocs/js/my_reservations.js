document.addEventListener('DOMContentLoaded', function() {
    // Auto-close success messages after delay
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.message.success');
        successMessages.forEach(msg => {
            msg.style.opacity = '0';
            setTimeout(() => msg.style.display = 'none', 500);
        });
    }, 5000);
    
    // Check if any active reservation/trip needs highlighting
    const activeReservation = document.querySelector('.active-reservation');
    const activeTrip = document.querySelector('.active-trip');
    
    // Scroll to active item for better visibility
    if (activeReservation) {
        activeReservation.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else if (activeTrip) {
        activeTrip.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
