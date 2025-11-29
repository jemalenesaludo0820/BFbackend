<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? $page_title : 'User Page'; ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f3f3f3;
  margin: 0;
  color: #333;
}

/* Header Bar */
.header {
  background-color: #195c28;
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.8rem 1.5rem;
  flex-wrap: wrap;
}

.logo-name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-name img {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background: white;
  padding: 3px;
}

.logo-name h2 {
  font-weight: 600;
  font-size: 1.1rem;
}

.search-bar {
  display: flex;
  align-items: center;
  background-color: #e9f0e6;
  border-radius: 1rem;
  overflow: hidden;
  padding: 0.3rem 0.6rem;
}

.search-bar input {
  border: none;
  outline: none;
  background: none;
  padding: 0.3rem 0.6rem;
  color: #333;
  width: 140px;
}

.search-bar i {
  color: #195c28;
  font-size: 1rem;
}

.nav-links {
  font-size: 0.9rem;
  font-weight: 600;
}

.nav-links a {
  color: white;
  text-decoration: none;
  margin: 0 0.4rem;
}

.nav-links a:hover {
  text-decoration: underline;
}

.notification-container {
  position: relative;
  display: inline-block;
}

.notification-badge {
  position: absolute;
  top: -10px;
  right: -15px;
  background-color: #ff0000;
  color: white;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.notification-badge.hidden {
  display: none;
}

.notification-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  min-width: 350px;
  max-width: 400px;
  max-height: 500px;
  overflow-y: auto;
  z-index: 1000;
  margin-top: 10px;
  display: none;
}

.notification-dropdown.active {
  display: block;
}

.notification-header {
  padding: 15px;
  border-bottom: 1px solid #e0e0e0;
  font-weight: bold;
  color: #195c28;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notification-item {
  padding: 12px 15px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background-color 0.2s;
}

.notification-item:hover {
  background-color: #f9f9f9;
}

.notification-item.unread {
  background-color: #e8f5e9;
}

.notification-message {
  font-size: 14px;
  color: #333;
  margin-bottom: 5px;
}

.notification-time {
  font-size: 12px;
  color: #999;
}

.no-notifications {
  padding: 20px;
  text-align: center;
  color: #999;
}
</style>
</head>

<body>

<!-- Header -->
<header class="header">
  <div class="logo-name">
    <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" alt="profile">
    <h2><?= htmlspecialchars($logged_in_user['username'] ?? 'Guest'); ?></h2>
  </div>

  <div class="search-bar">
    <input type="text" placeholder="Search">
    <i class="fa fa-search"></i>
  </div>

  <nav class="nav-links">
    <a href="<?= site_url('users/user_page'); ?>">HOME</a> |
    <a href="<?= site_url('categories'); ?>">CATEGORIES</a> |
    <div class="notification-container">
      <a href="#" onclick="toggleNotifications(); return false;" style="color: white; text-decoration: none; position: relative;">
        NOTIFICATION
        <span class="notification-badge <?= ($unread_notifications ?? 0) > 0 ? '' : 'hidden'; ?>" id="notification-badge">
          <?= min($unread_notifications ?? 0, 99); ?>
        </span>
      </a>
      <div class="notification-dropdown" id="notification-dropdown">
        <div class="notification-header">
          <span>Notifications</span>
          <a href="<?= site_url('notifications'); ?>" style="font-size: 12px; color: #195c28;">View All</a>
        </div>
        <div id="notification-list">
          <div class="no-notifications">Loading...</div>
        </div>
      </div>
    </div> |
    <a href="<?= site_url('users/profile'); ?>">PROFILE</a>
  </nav>
</header>

<script>
  let unreadCount = <?= $unread_notifications ?? 0; ?>;
  const USER_PAGE_URL = '<?= site_url("users/user_page"); ?>';
  window.MANILA_TIMEZONE = window.MANILA_TIMEZONE || 'Asia/Manila';
  
  function toggleNotifications() {
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.classList.toggle('active');
    
    if (dropdown.classList.contains('active')) {
      loadNotifications();
    }
  }

  function loadNotifications() {
    fetch('<?= site_url("api/get_notifications"); ?>')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateNotificationBadge(data.unread_count);
          displayNotifications(data.notifications);
        }
      })
      .catch(error => {
        console.error('Error loading notifications:', error);
      });
  }

  function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    unreadCount = count;
    
    if (count > 0) {
      badge.classList.remove('hidden');
      badge.textContent = count > 99 ? '99+' : count;
    } else {
      badge.classList.add('hidden');
    }
  }

  function displayNotifications(notifications) {
    const list = document.getElementById('notification-list');
    
    if (!notifications || notifications.length === 0) {
      list.innerHTML = '<div class="no-notifications">No notifications</div>';
      return;
    }

    let html = '';
    notifications.forEach(notif => {
      const isUnread = notif.is_read == 0;
      const className = isUnread ? 'notification-item unread' : 'notification-item';
      const timeAgo = getTimeAgo(notif.created_at);
      
      html += `
        <div class="${className}" onclick="handleNotificationClick(${notif.notification_id}, ${notif.post_id ?? 'null'}, ${notif.comment_id ?? 'null'}, ${notif.reply_id ?? 'null'})">
          <div class="notification-message">${escapeHtml(notif.message)}</div>
          <div class="notification-time">${timeAgo}</div>
        </div>
      `;
    });
    
    list.innerHTML = html;
  }

  function handleNotificationClick(notificationId, postId, commentId = null, replyId = null) {
    // Mark as read
    const formData = new FormData();
    formData.append('notification_id', notificationId);
    fetch('<?= site_url("api/mark_notification_read"); ?>', {
      method: 'POST',
      body: formData
    });

    // Navigate to post
    window.location.href = buildNotificationUrl(postId, commentId, replyId);
    
    // Close dropdown
    document.getElementById('notification-dropdown').classList.remove('active');
  }

  function getTimeAgo(dateString) {
    const date = convertToManilaDate(dateString);
    if (!date) {
      return 'Just now';
    }
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
    return date.toLocaleDateString();
  }

  function convertToManilaDate(dateString) {
    if (!dateString) return null;
    const normalized = dateString.replace(' ', 'T');
    const hasTimezone = /([+-]\d{2}:?\d{2}|Z)$/i.test(normalized);
    const isoString = hasTimezone ? normalized : `${normalized}+08:00`;
    const parsed = new Date(isoString);
    return isNaN(parsed.getTime()) ? null : parsed;
  }

  function buildNotificationUrl(postId, commentId, replyId) {
    const params = new URLSearchParams();

    if (postId) {
      params.set('focus_post', postId);
    }
    if (commentId) {
      params.set('focus_comment', commentId);
    }
    if (replyId) {
      params.set('focus_reply', replyId);
    }

    let hash = '';
    if (replyId) {
      hash = `#reply-${replyId}`;
    } else if (commentId) {
      hash = `#comment-${commentId}`;
    } else if (postId) {
      hash = `#post-${postId}`;
    }

    const query = params.toString();
    return `${USER_PAGE_URL}${query ? `?${query}` : ''}${hash}`;
  }

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    const container = document.querySelector('.notification-container');
    if (!container.contains(e.target)) {
      document.getElementById('notification-dropdown').classList.remove('active');
    }
  });

  // Auto-refresh notifications every 30 seconds
  setInterval(() => {
    if (document.getElementById('notification-dropdown').classList.contains('active')) {
      loadNotifications();
    } else {
      // Just update count
      fetch('<?= site_url("api/get_notifications"); ?>')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateNotificationBadge(data.unread_count);
          }
        });
    }
  }, 30000);
</script>
