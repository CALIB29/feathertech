document.addEventListener('DOMContentLoaded', function() {
    // Mark notification as read when clicked
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const notificationId = this.dataset.id;
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.closest('li').remove();
                    updateNotificationCount();
                }
            });
        });
    });
    
    // Live update notifications every 30 seconds
    setInterval(updateNotifications, 30000);
});

function updateNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const dropdown = document.querySelector('.notification-dropdown');
                const countBadge = document.querySelector('.notification-count');
                
                // Update count
                countBadge.textContent = data.length;
                
                // Update dropdown items
                dropdown.innerHTML = data.map(notification => `
                    <li>
                        <a class="dropdown-item notification-item" href="#" data-id="${notification.id}">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>${notification.animal_type}</strong>
                                    <p class="mb-0 small">${notification.message}</p>
                                </div>
                                <small class="text-muted">${timeAgo(notification.created_at)}</small>
                            </div>
                        </a>
                    </li>
                `).join('');
            }
        });
}

function timeAgo(dateString) {
    // Implement time ago function
    // ...
}

function updateNotificationCount() {
    const count = document.querySelectorAll('.notification-item').length;
    document.querySelector('.notification-count').textContent = count;
}