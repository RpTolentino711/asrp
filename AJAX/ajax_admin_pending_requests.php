let isTabActive = true;
let isLoading = false;

// Stop polling when tab is not visible
document.addEventListener('visibilitychange', function() {
    isTabActive = !document.hidden;
});

function fetchPendingRequests() {
    if (isLoading || !isTabActive) return;
    
    isLoading = true;
    
    // Add cache-busting parameter
    const url = '../AJAX/ajax_admin_pending_requests.php?t=' + Date.now();
    
    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.text();
        })
        .then(html => {
            document.getElementById('pendingRequestsContainer').innerHTML = html;
            const container = document.getElementById('pendingRequestsContainer');
            const countElement = container.querySelector('[data-count]');
            if (countElement) {
                document.getElementById('pendingCount').textContent = countElement.getAttribute('data-count');
            }
            isLoading = false;
        })
        .catch(err => {
            console.error('Error fetching requests:', err);
            isLoading = false;
        });
}

function confirmAccept(requestId, clientName, spaceName) {
    Swal.fire({
        title: 'Accept Rental Request?',
        html: `
            <p>You are about to accept the rental request from:</p>
            <p class="fw-bold">${clientName}</p>
            <p>For property: <span class="fw-bold">${spaceName}</span></p>
            <p class="text-muted mt-3">
                <i class="fas fa-envelope me-2"></i>
                A welcome email will be automatically sent to the client.
            </p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Accept & Send Welcome Email',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('acceptForm_' + requestId).submit();
        }
    });
}

function confirmReject(requestId, clientName, spaceName) {
    Swal.fire({
        title: 'Reject Rental Request?',
        html: `
            <p>You are about to reject the rental request from:</p>
            <p class="fw-bold">${clientName}</p>
            <p>For property: <span class="fw-bold">${spaceName}</span></p>
            <p class="text-danger mt-3">This action cannot be undone.</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-times me-2"></i>Reject Request',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('rejectForm_' + requestId).submit();
        }
    });
}

// Load immediately, then every 5 seconds for live updates
document.addEventListener('DOMContentLoaded', function() {
    fetchPendingRequests();
    setInterval(fetchPendingRequests, 5000); // 5 seconds for live updates
});

document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }
    }, 5000);
});