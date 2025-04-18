<?php
/**
 * Notification API Endpoints
 * 
 * This file contains API endpoints for handling notifications via AJAX
 */

// Start session
session_start();

// Include required files
require_once 'config.php';
require_once 'notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle different API actions
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_count':
        // Get unread notifications count
        $count = getUnreadNotificationsCount($conn, $user_id);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'get_notifications':
        // Get notifications list
        $limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 20;
        $offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;
        
        $notifications = getNotifications($conn, $user_id, $limit, $offset);
        
        // Format timestamps
        foreach ($notifications as &$notification) {
            $notification['time_formatted'] = formatNotificationTime($notification['created_at']);
        }
        
        echo json_encode([
            'success' => true, 
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
        break;
        
    case 'mark_read':
        // Mark a notification as read
        if (!isset($_REQUEST['notification_id'])) {
            echo json_encode(['success' => false, 'message' => 'Notification ID required']);
            break;
        }
        
        $notification_id = intval($_REQUEST['notification_id']);
        $success = markNotificationAsRead($conn, $notification_id, $user_id);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
        ]);
        break;
        
    case 'mark_all_read':
        // Mark all notifications as read
        $success = markAllNotificationsAsRead($conn, $user_id);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'All notifications marked as read' : 'Failed to mark notifications as read'
        ]);
        break;
        
    case 'delete':
        // Delete a notification
        if (!isset($_REQUEST['notification_id'])) {
            echo json_encode(['success' => false, 'message' => 'Notification ID required']);
            break;
        }
        
        $notification_id = intval($_REQUEST['notification_id']);
        $success = deleteNotification($conn, $notification_id, $user_id);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Notification deleted' : 'Failed to delete notification'
        ]);
        break;
        
    case 'clear_all':
        // Clear all notifications
        $success = clearAllNotifications($conn, $user_id);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'All notifications cleared' : 'Failed to clear notifications'
        ]);
        break;
        
    case 'update_settings':
        // Update notification settings
        $settings = [
            'email_messages' => isset($_REQUEST['email_messages']) ? (bool)$_REQUEST['email_messages'] : null,
            'email_likes' => isset($_REQUEST['email_likes']) ? (bool)$_REQUEST['email_likes'] : null,
            'email_matches' => isset($_REQUEST['email_matches']) ? (bool)$_REQUEST['email_matches'] : null,
            'browser_notifications' => isset($_REQUEST['browser_notifications']) ? (bool)$_REQUEST['browser_notifications'] : null,
            'sound_enabled' => isset($_REQUEST['sound_enabled']) ? (bool)$_REQUEST['sound_enabled'] : null
        ];
        
        // Remove null values
        $settings = array_filter($settings, function($value) {
            return $value !== null;
        });
        
        if (empty($settings)) {
            echo json_encode(['success' => false, 'message' => 'No settings provided']);
            break;
        }
        
        $success = updateNotificationSettings($conn, $user_id, $settings);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Settings updated' : 'Failed to update settings'
        ]);
        break;
        
    case 'get_settings':
        // Get notification settings
        $settings = getNotificationSettings($conn, $user_id);
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

exit();