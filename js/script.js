// Confirm delete actions
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
    
    // Calculate total score
    const caInputs = document.querySelectorAll('.ca-input');
    const examInputs = document.querySelectorAll('.exam-input');
    
    function calculateTotal() {
        const test = parseFloat(document.getElementById('test').value) || 0;
        const assignment = parseFloat(document.getElementById('assignment').value) || 0;
        const attendance = parseFloat(document.getElementById('attendance').value) || 0;
        const exam = parseFloat(document.getElementById('exam').value) || 0;
        
        const caTotal = test + assignment + attendance;
        const total = caTotal + exam;
        
        document.getElementById('ca_total').value = caTotal.toFixed(2);
        document.getElementById('total').value = total.toFixed(2);
    }
    
    if (caInputs.length > 0) {
        caInputs.forEach(input => {
            input.addEventListener('input', calculateTotal);
        });
    }
    
    if (examInputs.length > 0) {
        examInputs.forEach(input => {
            input.addEventListener('input', calculateTotal);
        });
    }
    
    // Auto-calculate when page loads
    if (document.getElementById('test') && document.getElementById('assignment') && 
        document.getElementById('attendance') && document.getElementById('exam')) {
        calculateTotal();
    }
    
    // Handle browser back button
    window.addEventListener('popstate', function(event) {
        // Check if we're going back to a valid page
        if (event.state && event.state.page) {
            window.location.href = event.state.page;
        }
    });
    
    // Add current page to history
    if (window.history && window.history.pushState) {
        window.history.pushState({page: window.location.href}, '', window.location.href);
    }
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});

