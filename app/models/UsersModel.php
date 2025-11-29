<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: UsersModel
 * 
 * Automatically generated via CLI.
 */
class UsersModel extends Model {
    protected $table = 'users';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

       public function page($q = '', $records_per_page = null, $page = null) {
 
            if (is_null($page)) {
                return $this->db->table('users')->get_all();
            } else {
                $query = $this->db->table('users');

                // Build LIKE conditions
                $query->like('id', '%'.$q.'%')
                    ->or_like('username', '%'.$q.'%')
                    ->or_like('email', '%'.$q.'%')
                    ->or_like('role', '%'.$q.'%');
                    
                // Clone before pagination
                $countQuery = clone $query;

                $data['total_rows'] = $countQuery->select_count('*', 'count')
                                                ->get()['count'];

                $data['records'] = $query->pagination($records_per_page, $page)
                                        ->get_all();

                return $data;
            }
        }
        
         public function get_user_by_id($id)
    {
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->get();
    }

    public function get_user_by_username($username)
    {
        return $this->db->table($this->table)
                        ->where('username', $username)
                        ->get();
    }

    public function update_password($user_id, $new_password) {
    return $this->db->table($this->table)
                    ->where('id', $user_id)
                    ->update([
                        'password' => password_hash($new_password, PASSWORD_BCRYPT)
                    ]);
    }


    public function get_all_users()
    {
        return $this->db->table($this->table)->get_all();
    }

    public function get_logged_in_user()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user']['id'])) {
            return $this->get_user_by_id($_SESSION['user']['id']);
        }

        return null;
    }

    public function is_username_unique($username, $exclude_id = null)
    {
        if ($exclude_id !== null) {
            // Use raw SQL to avoid syntax issues
            $sql = "SELECT * FROM users WHERE username = ? AND id != ?";
            $stmt = $this->db->raw($sql, [$username, $exclude_id]);
            $result = $stmt->fetch();
        } else {
            $result = $this->db->table($this->table)->where('username', $username)->get();
        }
        
        // In LavaLust, get() returns false when no record is found, or an array when found
        // We return true if no record exists (username is unique)
        // We return false if a record exists (username already taken)
        return ($result === false);
    }

     public function get_all_posts()
{
    $posts = $this->db->table('posts')
                     ->join('users', 'users.id = posts.user_id')
                     ->order_by('posts.created_at', 'DESC')
                     ->get_all();

    // Format dates for all posts
    if (is_array($posts)) {
        foreach ($posts as &$post) {
            $post['created_at_formatted'] = format_manila_datetime($post['created_at']);
        }
    }

    return $posts;
}

    public function get_posts_by_category($category)
{
    return $this->db->table('posts')
                    ->join('users', 'users.id = posts.user_id')
                    ->where('posts.category', $category)
                    ->order_by('posts.created_at', 'DESC')
                    ->get_all();
}


    public function add_post($data)
    {
        return $this->db->table('posts')->insert($data);
    }

    public function get_post_by_id($id)
{
    $post = $this->db->table('posts')
                    ->join('users', 'users.id = posts.user_id')
                    ->where('post_id', $id)
                    ->get();
    
    if ($post) {
        // Format the date using the helper function
        $post['created_at_formatted'] = format_manila_datetime($post['created_at']);
    }
    
    return $post;
}

    public function update_post($id, $data)
    {
        return $this->db->table('posts')
                        ->where('post_id', $id)
                        ->update($data);
    }

    public function delete_post($id)
    {
        return $this->db->table('posts')
                        ->where('post_id', $id)
                        ->delete();
    }

 
        // ✅ Check if an email already exists
public function is_email_exists($email)
{
    $result = $this->db->table($this->table)
                      ->where('email', $email)
                      ->get();

    // In LavaLust, get() returns false when no record is found, or an array when found
    // We return true if record exists (email is taken), false if not (email is available)
    return ($result !== false);
}



    // ✅ Get user details by email
    public function get_user_by_email($email)
    {
        return $this->db->table($this->table)
                        ->where('email', $email)
                        ->get();
    }

    // ✅ Mark user as verified and clear the code
    public function verify_email($email)
    {
        return $this->db->table($this->table)
                        ->where('email', $email)
                        ->update([
                            'is_verified' => 1,
                            'verification_code' => null
                        ]);
    }

    // ========== LIKES METHODS ==========
    public function like_post($post_id, $user_id)
    {
        // Check if already liked
        $existing = $this->db->table('likes')
                            ->where('post_id', $post_id)
                            ->where('user_id', $user_id)
                            ->get();
        
        if ($existing) {
            // Unlike - remove the like
            return $this->db->table('likes')
                            ->where('post_id', $post_id)
                            ->where('user_id', $user_id)
                            ->delete();
        } else {
            // Like - add the like
            return $this->db->table('likes')->insert([
                'post_id' => $post_id,
                'user_id' => $user_id,
                'created_at' => current_manila_datetime()
            ]);
        }
    }

    public function is_post_liked($post_id, $user_id)
    {
        $like = $this->db->table('likes')
                        ->where('post_id', $post_id)
                        ->where('user_id', $user_id)
                        ->get();
        return $like !== false;
    }

    public function get_like_count($post_id)
    {
        $result = $this->db->raw("SELECT COUNT(*) as count FROM likes WHERE post_id = ?", [$post_id]);
        $row = $result->fetch();
        return $row ? (int)$row['count'] : 0;
    }

    // ========== COMMENTS METHODS ==========
    public function add_comment($data)
    {
        return $this->db->table('comments')->insert($data);
    }

    public function get_comments_by_post($post_id)
    {
        return $this->db->table('comments')
                        ->join('users', 'users.id = comments.user_id')
                        ->where('comments.post_id', $post_id)
                        ->order_by('comments.created_at', 'ASC')
                        ->get_all();
    }

    public function get_comment_by_id($comment_id)
    {
        return $this->db->table('comments')
                        ->join('users', 'users.id = comments.user_id')
                        ->where('comments.comment_id', $comment_id)
                        ->get();
    }

    public function delete_comment($comment_id)
    {
        return $this->db->table('comments')
                        ->where('comment_id', $comment_id)
                        ->delete();
    }

    // ========== REPLIES METHODS ==========
    public function add_reply($data)
    {
        return $this->db->table('replies')->insert($data);
    }

    public function get_replies_by_comment($comment_id)
    {
        return $this->db->table('replies')
                        ->join('users', 'users.id = replies.user_id')
                        ->where('replies.comment_id', $comment_id)
                        ->order_by('replies.created_at', 'ASC')
                        ->get_all();
    }

    public function delete_reply($reply_id)
    {
        return $this->db->table('replies')
                        ->where('reply_id', $reply_id)
                        ->delete();
    }

    // ========== NOTIFICATIONS METHODS ==========
    public function create_notification($data)
    {
        // Ensure nullable fields are handled
        if (!isset($data['comment_id'])) {
            $data['comment_id'] = null;
        }
        if (!isset($data['reply_id'])) {
            $data['reply_id'] = null;
        }
        if (!isset($data['post_id'])) {
            $data['post_id'] = null;
        }
        
        return $this->db->table('notifications')->insert($data);
    }

    public function get_notifications($user_id, $limit = 20)
    {
        return $this->db->table('notifications')
                        ->join('users', 'users.id = notifications.actor_id')
                        ->where('notifications.user_id', $user_id)
                        ->order_by('notifications.created_at', 'DESC')
                        ->limit($limit)
                        ->get_all();
    }

    public function get_unread_notifications_count($user_id)
    {
        $result = $this->db->raw("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
        $row = $result->fetch();
        return $row ? (int)$row['count'] : 0;
    }

    public function mark_notification_as_read($notification_id)
    {
        return $this->db->table('notifications')
                        ->where('notification_id', $notification_id)
                        ->update(['is_read' => 1]);
    }

    public function mark_all_notifications_as_read($user_id)
    {
        return $this->db->table('notifications')
                        ->where('user_id', $user_id)
                        ->update(['is_read' => 1]);
    }

}