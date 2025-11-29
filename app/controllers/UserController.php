<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Controller: UserController
 * 
 * Automatically generated via CLI.
 */


class UserController extends Controller {
    public function __construct()
    {
        parent::__construct();
        // Load the datetime helper for Manila timezone formatting
        require_once __DIR__ . '/../helpers/datetime_helper.php';
        // Datetime helper is loaded via autoload.php
        require_once __DIR__ . '/../helpers/location.php';

        
    }

    // inside UserController class
protected $allowedFonts = [
    'Roboto', 
    'Poppins', 
    'Lora', 
    'Montserrat', 
    'Playfair Display', 
    'Open Sans'
];


    /**
     * Ensure PHP session is available before accessing $_SESSION.
     */
    private function ensureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Helper for standard JSON responses.
     */
    private function jsonResponse($success, $message = '', $extra = [], $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $extra));
    }


    /**
     * Ensure that the current session belongs to an authenticated admin.
     */
    private function requireAdmin()
    {
        $this->ensureSession();

        if (!isset($_SESSION['user'])) {
            $this->jsonResponse(false, 'Not logged in', [], 401);
            exit;
        }

        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            $this->jsonResponse(false, 'Forbidden', [], 403);
            exit;
        }

        return $_SESSION['user'];
    }

    /**
     * Attempt to verify a code for the pending email address.
     */
    private function attemptVerification($code, $email)
    {
        $this->call->model('UsersModel');
        $this->ensureSession();

        if (!$email) {
            return [
                'success' => false,
                'message' => 'Your verification session expired. Please register again.'
            ];
        }

        $user = $this->UsersModel->get_user_by_email($email);

        if (!$user) {
            unset($_SESSION['pending_email']);
            return [
                'success' => false,
                'message' => 'Account not found. Please register again.'
            ];
        }

        if ($user['verification_code'] == $code) {
            $this->UsersModel->verify_email($email);
            unset($_SESSION['pending_email']);
            $_SESSION['success_message'] = '✅ Email verified successfully! You can now log in.';

            return [
                'success' => true,
                'message' => 'Email verified successfully!'
            ];
        }

        return [
            'success' => false,
            'message' => '❌ Invalid verification code. Please try again.'
        ];
    }
    
    public function index()
    {
         $this->call->model('UsersModel');

         // Check if user is logged in
         if (!isset($_SESSION['user'])) {
             redirect('/auth/login');
             exit;
         }

         // Get logged-in user info
         $logged_in_user = $_SESSION['user']; 
         $data['logged_in_user'] = $logged_in_user;

         // Redirect regular users to user page
         if ($logged_in_user['role'] !== 'admin') {
             redirect('/users/user-page');
             exit;
         }


        $page = 1;
        if(isset($_GET['page']) && ! empty($_GET['page'])) {
            $page = $this->io->get('page');
        }

        $q = '';
        if(isset($_GET['q']) && ! empty($_GET['q'])) {
            $q = trim($this->io->get('q'));
        }

        $records_per_page = 5;

        $user = $this->UsersModel->page($q, $records_per_page, $page);
        $data['users'] = $user['records'];
        $total_rows = $user['total_rows'];

        $this->pagination->set_options([
            'first_link'     => '⏮ First',
            'last_link'      => 'Last ⏭',
            'next_link'      => 'Next →',
            'prev_link'      => '← Prev',
            'page_delimiter' => '&page='
        ]);
        $this->pagination->set_theme('custom');
        $this->pagination->set_custom_classes([

        'nav'    => 'flex justify-center mt-6',
         'ul'     => 'flex space-x-2',
         'li'     => 'list-none',
         'a'      => 'px-3 py-1 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-blue-500 hover:text-white transition',
        'active' => 'bg-blue-600 text-white font-bold border-blue-600'

        ] );


        $this->pagination->initialize($total_rows, $records_per_page, $page, 'users?q='.$q);
        $data['page'] = $this->pagination->paginate();
        $this->call->view('users/index', $data);
    }

    function create()
    {
        if($this->io->method()==='post')
        {
           $username = $this->io->post('username');
           $email = $this->io->post('email');
           $password = password_hash($this->io->post('password'), PASSWORD_BCRYPT);
           $role = $this->io->post('role');

           // Validate username uniqueness
           if (!$this->UsersModel->is_username_unique($username)) {
               $data['error'] = 'Username already exists. Please choose a different username.';
               $this->call->view('users/create', $data);
               return;
           }

           $data = [
               'username' => $username,
               'email' => $email,
               'password' => $password,
               'role' => $role,
               'created_at' => current_manila_datetime()
           ];

           if($this->UsersModel->insert($data))
            {
              redirect('/users');
            } else 
            {
                $data['error'] = 'Error creating user';
                $this->call->view('users/create', $data);
            }
        }
         else
        {
           $this->call->view('users/create');
        }
    }

    
     public function update($id)
{
    $this->call->model('UsersModel');

    // Get logged-in user from session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $logged_in_user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

    // Fetch the user to be edited
    $user = $this->UsersModel->get_user_by_id($id);
    if (!$user) {
        echo "User not found.";
        return;
    }

    if ($this->io->method() === 'post') {
        $username = $this->io->post('username');
        $email = $this->io->post('email');
        $role = $this->io->post('role');

        // Validate username uniqueness
        if (!$this->UsersModel->is_username_unique($username, $id)) {
            $data['user'] = $user;
            $data['logged_in_user'] = $logged_in_user;
            $data['error'] = 'Username already exists. Please choose a different username.';
            $this->call->view('users/update', $data);
            return;
        }

        // Prepare data for update - no password changes allowed
        $data = [
            'username' => $username,
            'email' => $email,
            'role' => $role
        ];

        if ($this->UsersModel->update($id, $data)) {
            redirect('/users');
        } else {
            $data['user'] = $user;
            $data['logged_in_user'] = $logged_in_user;
            $data['error'] = 'Failed to update user.';
            $this->call->view('users/update', $data);
        }
    } else {
        // Pass both the user being edited and the logged-in user to the view
        $data['user'] = $user;
        $data['logged_in_user'] = $logged_in_user;
        $this->call->view('users/update', $data);
    }
}

   function delete($id)
   {
    if($this->UsersModel->delete($id))
    {
        redirect('/users');
    }
    else{
        echo "Error deleting";
    }
   }
   
 public function register()
{
    header('Content-Type: application/json');
    
    $this->call->model('UsersModel'); 
    $this->call->library('session');

    if ($this->io->method() == 'post') {
        $username = $this->io->post('username');
        $email = $this->io->post('email');
        $password = password_hash($this->io->post('password'), PASSWORD_BCRYPT);
        $role = $this->io->post('role');

        // Check if username or email already exists
        $usernameUnique = $this->UsersModel->is_username_unique($username);
        $emailExists = $this->UsersModel->is_email_exists($email);
        
        if (!$usernameUnique) {
            echo json_encode([
                'success' => false,
                'message' => 'Username already exists.'
            ]);
            return;
        }
        if ($emailExists) {
            echo json_encode([
                'success' => false,
                'message' => 'Email already registered.'
            ]);
            return;
        }

        // Generate code and insert
        $verification_code = rand(100000, 999999);
        $data = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'verification_code' => $verification_code,
            'is_verified' => 0,
            'created_at' => current_manila_datetime()
        ];

        if ($this->UsersModel->insert($data)) {
            // Send verification email
            $this->call->library('email');
            
            $email_instance = null;
            if (isset($this->email) && is_object($this->email)) {
                $email_instance = $this->email;
            } else {
                $LAVA =& lava_instance();
                if (isset($LAVA->properties['email']) && is_object($LAVA->properties['email'])) {
                    $email_instance = $LAVA->properties['email'];
                }
            }
            
            if (!$email_instance) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email library failed to load. Please contact support.'
                ]);
                return;
            }
            
            $email_instance->sender('rochelleuchi38@gmail.com', 'Blogflow');
            
            if (!$email_instance->recipient($email)) {
                $email_error = $email_instance->get_error();
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email address: ' . htmlspecialchars($email_error)
                ]);
                return;
            }
            
            $email_instance->subject('Verify your Blogflow account');
            
            $htmlContent = "<h2>Hello " . htmlspecialchars($username) . ",</h2>";
            $htmlContent .= "<p>Your verification code is:</p>";
            $htmlContent .= "<h3 style='color:#014421;'>{$verification_code}</h3>";
            $htmlContent .= "<p>Please enter this code to verify your account.</p>";
            
            $email_instance->email_content($htmlContent, 'html');
            
            $email_sent = $email_instance->send();
            
            if ($email_sent) {
                $_SESSION['pending_email'] = $email;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Registration successful! Please check your email for verification code.',
                    'redirect' => '/login'
                ]);
                return;
            } else {
                $email_error = $email_instance->get_error();
                error_log("Email sending failed for user: {$email}. Error: " . $email_error);
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Registration successful, but we could not send the verification email. Error: ' . htmlspecialchars($email_error)
                ]);
                return;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Registration failed. Try again.'
            ]);
            return;
        }
    }

    // GET request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}



public function verify()
{
    $this->ensureSession();

    // Prevent direct access without pending email
    if (!isset($_SESSION['pending_email'])) {
        redirect('/auth/register');
        exit;
    }

    // Display verification form
    $this->call->view('/auth/verify');
}

public function verify_code()
{
    $this->ensureSession();

    if ($this->io->method() == 'post') {
        $code = trim($this->io->post('code'));
        $email = $_SESSION['pending_email'] ?? null;
        $result = $this->attemptVerification($code, $email);

        if ($result['success']) {
            redirect('/auth/login');
        } else {
            $data['error'] = $result['message'];
            $this->call->view('/auth/verify', $data);
        }
    } else {
        redirect('/auth/verify');
    }
}


public function api_pending_email()
{
    $this->ensureSession();
    $pendingEmail = $_SESSION['pending_email'] ?? null;

    if ($pendingEmail) {
        $this->jsonResponse(true, 'Pending verification email found.', [
            'pending_email' => $pendingEmail
        ]);
    } else {
        $this->jsonResponse(false, 'No pending verification found.', [
            'pending_email' => null
        ], 404);
    }
}

public function api_verify_code()
{
    $this->ensureSession();

    if ($this->io->method() !== 'post') {
        $this->jsonResponse(false, 'Invalid request method', [], 405);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $code = trim($payload['code'] ?? $this->io->post('code') ?? '');
    $email = $_SESSION['pending_email'] ?? null;

    $result = $this->attemptVerification($code, $email);
    $extra = [];

    if ($result['success']) {
        $extra['redirect'] = '/login';
    }

    $this->jsonResponse($result['success'], $result['message'], $extra, $result['success'] ? 200 : 422);
}


        public function login()
{
    header('Content-Type: application/json');
    
    $this->call->model('UsersModel');
    $this->call->library('auth');

    if ($this->io->method() == 'post') {
        // Handle both JSON and form data
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $username = $input['username'] ?? null;
            $password = $input['password'] ?? null;
        } else {
            $username = $this->io->post('username');
            $password = $this->io->post('password');
        }

        if (empty($username) || empty($password)) {
            echo json_encode([
                'success' => false,
                'message' => 'Username and password are required'
            ]);
            return;
        }

        // Get user info first (either by username or email)
        $user = $this->UsersModel->get_user_by_username($username);
        if (!$user) {
            $user = $this->UsersModel->get_user_by_email($username);
        }

        // Check if user exists
        if ($user) {
            // Check if verified
            if ($user['is_verified'] == 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Your account is not verified yet. Please check your email for the code.'
                ]);
                return;
            } else {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Create user data array (don't use Auth::userdata() to avoid nesting)
                    $user_data = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'logged_in' => true
                    ];
                    
                    // Set session data using Auth library
                    $this->auth->login($user['username'], $password);
                    
                    // Store clean user data in session
                    $_SESSION['user'] = $user_data;

                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => $user_data,
                        'redirect' => '/home'
                    ]);
                    return;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Incorrect username or password!'
                    ]);
                    return;
                }
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Account not found!'
            ]);
            return;
        }
    }

    // GET request - return error
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}


    public function dashboard()
    {
        $this->call->model('UsersModel');
        $data['user'] = $this->UsersModel->get_all_users(); // fetch all users

        $this->call->model('UsersModel');

        $page = 1;
        if(isset($_GET['page']) && ! empty($_GET['page'])) {
            $page = $this->io->get('page');
        }

        $q = '';
        if(isset($_GET['q']) && ! empty($_GET['q'])) {
            $q = trim($this->io->get('q'));
        }

        $records_per_page = 10;

        $user = $this->UsersModel->page($q, $records_per_page, $page);
        $data['user'] = $user['records'];
        $total_rows = $user['total_rows'];

        $this->pagination->set_options([
            'first_link'     => '⏮ First',
            'last_link'      => 'Last ⏭',
            'next_link'      => 'Next →',
            'prev_link'      => '← Prev',
            'page_delimiter' => '&page='
        ]);
        $this->pagination->set_theme('bootstrap');
        $this->pagination->initialize($total_rows, $records_per_page, $page, 'users?q='.$q);
        $data['page'] = $this->pagination->paginate();

        $this->call->view('users/dashboard', $data);
    }


    public function logout()
    {
        header('Content-Type: application/json');
        
        $this->call->library('auth');
        $this->auth->logout();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully',
            'redirect' => '/login'
        ]);
    }

public function user_page()
{
    // This is now handled by api_get_posts - redirect API calls there
    // Keep for backward compatibility but return JSON
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $logged_in_user = $_SESSION['user'];
    $user_id = $logged_in_user['id'];

    $posts = $this->UsersModel->get_all_posts();

    if (!empty($posts)) {
        foreach ($posts as &$post) {
            $post['is_liked'] = $this->UsersModel->is_post_liked($post['post_id'], $user_id);
            $post['like_count'] = $this->UsersModel->get_like_count($post['post_id']);
            $post['comments'] = $this->UsersModel->get_comments_by_post($post['post_id']);
            
            if (!empty($post['comments'])) {
                foreach ($post['comments'] as &$comment) {
                    $comment['replies'] = $this->UsersModel->get_replies_by_comment($comment['comment_id']);
                }
            }
        }
    }

    $unread_notifications = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'posts' => $posts ?: [],
        'unread_notifications' => $unread_notifications
    ]);
}


      public function post_page()
    {
        // This endpoint is no longer needed - Vue handles post creation
        // Return JSON for backward compatibility
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Use /post/create route in Vue frontend'
        ]);
    }

    
    public function create_post()
{
    header('Content-Type: application/json');
    
    $this->call->model('UsersModel');

    // Require login
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() === 'post') {
        $user_id = $_SESSION['user']['id'];
        $category = $_POST['category'] ?? null;

        // Get font from POST
        $font_family = $this->io->post('font_family') ?? null;

        // Validate font
        if ($font_family && !in_array($font_family, $this->allowedFonts)) {
            $font_family = null; // prevent invalid fonts
        }

        if (!$category) {
            echo json_encode(['success' => false, 'message' => 'Please select a category before posting.']);
            return;
        }

        $content = $this->io->post('content');
        $media_paths = [];

        // Handle multiple media uploads (max 5 files)
        if (!empty($_FILES['media']['name'])) {
            $files = $_FILES['media'];
            $is_multiple = is_array($files['name']);
            $file_count = $is_multiple ? count($files['name']) : 1;

            // Check maximum file limit
            if ($file_count > 5) {
                echo json_encode(['success' => false, 'message' => 'Maximum 5 files allowed per post.']);
                return;
            }

            $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif', 'bmp'];
            $allowed_videos = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'flv'];
            $allowed_types = array_merge($allowed_images, $allowed_videos);

            for ($i = 0; $i < $file_count; $i++) {
                if ($is_multiple) {
                    $file_name = $files['name'][$i];
                    $file_tmp = $files['tmp_name'][$i];
                    $file_size = $files['size'][$i];
                    $file_error = $files['error'][$i];
                } else {
                    $file_name = $files['name'];
                    $file_tmp = $files['tmp_name'];
                    $file_size = $files['size'];
                    $file_error = $files['error'];
                    if ($i > 0) break;
                }

                if ($file_error !== UPLOAD_ERR_OK || empty($file_name)) {
                    if ($file_error !== UPLOAD_ERR_NO_FILE) {
                        $error_messages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
                        ];
                        $error_msg = $error_messages[$file_error] ?? 'Unknown upload error.';
                        echo json_encode(['success' => false, 'message' => 'Upload error for ' . htmlspecialchars($file_name) . ': ' . $error_msg]);
                        return;
                    }
                    continue;
                }

                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_types)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type for ' . htmlspecialchars($file_name)]);
                    return;
                }

                if ($file_size > 50 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'File ' . htmlspecialchars($file_name) . ' exceeds 50MB limit.']);
                    return;
                }

                $upload_dir = in_array($file_ext, $allowed_images) ? 'public/uploads/images/' : 'public/uploads/videos/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

                $unique_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file_name));
                $target_path = $upload_dir . $unique_name;

                if (move_uploaded_file($file_tmp, $target_path)) {
                    $media_paths[] = $target_path;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file: ' . htmlspecialchars($file_name)]);
                    return;
                }
            }
        }

        // Store media paths as JSON (or null if empty)
        $media_path = null;
        if (!empty($media_paths)) {
            $json_encoded = json_encode($media_paths, JSON_UNESCAPED_SLASHES);
            if ($json_encoded === false) {
                error_log('JSON encode error: ' . json_last_error_msg());
                echo json_encode(['success' => false, 'message' => 'Error processing media files.']);
                return;
            }
            $media_path = $json_encoded;
        }

        // --- NEW CODE: Get location from POST and reverse geocode ---
        $latitude = $this->io->post('latitude') ?? null;
        $longitude = $this->io->post('longitude') ?? null;

        $city = null;
        $country = null;

        if ($latitude && $longitude) {
            $locationData = reverse_geocode($latitude, $longitude);
            if ($locationData && isset($locationData['address'])) {
                $city = $locationData['address']['city'] 
                        ?? $locationData['address']['town'] 
                        ?? $locationData['address']['village'] 
                        ?? null;
                $country = $locationData['address']['country'] ?? null;
            }
        }

        // Prepare data to insert
        $data = [
            'user_id' => $user_id,
            'category' => $category,
            'content' => $content,
            'media_path' => $media_path,
            'font_family' => $font_family,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'city' => $city,
            'country' => $country,
            'created_at' => current_manila_datetime()
        ];

        // Log for debugging
        error_log("Creating post with data: " . print_r($data, true));

        $insert_result = $this->UsersModel->add_post($data);

        if ($insert_result) {
            echo json_encode([
                'success' => true,
                'message' => 'Your post was successfully published!',
                'redirect' => '/home'
            ]);
            return;
        } else {
            $db_error = $this->db->error();
            error_log("Database error: " . print_r($db_error, true));
            echo json_encode(['success' => false, 'message' => 'Something went wrong while saving your post. Please try again.']);
            return;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        return;
    }
}

public function edit_post($id)
{
    header('Content-Type: application/json');
    
    $this->call->model('UsersModel');

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $logged_in_user = $_SESSION['user'];

    $post = $this->UsersModel->get_post_by_id($id);
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        return;
    }

    if ($logged_in_user['role'] !== 'admin' && $post['user_id'] != $logged_in_user['id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        return;
    }

    if ($this->io->method() === 'post') {
        $category = $this->io->post('category');
        $content = $this->io->post('content');

        // Handle font selection
        $font_family = $this->io->post('font_family') ?? null;
        if ($font_family && !in_array($font_family, $this->allowedFonts)) {
            $font_family = null;
        }

        // Parse removed media from POST
        $removed_media = $this->io->post('removed_media');
        $removed_media = $removed_media ? json_decode($removed_media, true) : [];

        // Load old media
        $old_media = [];
        if (!empty($post['media_path'])) {
            $media_path = $post['media_path'];
            if (is_string($media_path)) {
                // First try to decode as JSON
                $decoded = json_decode($media_path, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $old_media = $decoded;
                } else {
                    // If it's not valid JSON, check if it's a serialized array
                    $unserialized = @unserialize($media_path);
                    if ($unserialized !== false && is_array($unserialized)) {
                        $old_media = $unserialized;
                    } else {
                        // If it's not JSON or serialized, treat it as a single path
                        $old_media = [$media_path];
                    }
                }
            } elseif (is_array($media_path)) {
                $old_media = $media_path;
            }
        }
        // Ensure we only have strings and they're not empty
        $old_media = array_values(array_filter($old_media, function($item) {
            return is_string($item) && !empty(trim($item));
        }));

        // Delete only removed media files
        if (!empty($removed_media) && is_array($removed_media)) {
            foreach ($removed_media as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            // Remove them from old media array
            $old_media = array_filter($old_media, function($m) use ($removed_media) {
                return !in_array($m, $removed_media);
            });
        }

        // Handle new media uploads
        $new_media_paths = [];
        if (!empty($_FILES['media']['name'])) {
            $files = $_FILES['media'];
            $file_count = is_array($files['name']) ? count($files['name']) : 1;

            // Validate max total files
            if (($file_count + count($old_media)) > 5) {
                echo json_encode(['success' => false, 'message' => 'Maximum 5 files allowed per post.']);
                return;
            }

            $allowed_images = ['jpg','jpeg','png','gif','webp','jfif','bmp'];
            $allowed_videos = ['mp4','webm','ogg','mov','avi','mkv','flv'];
            $allowed_types = array_merge($allowed_images, $allowed_videos);

            for ($i=0; $i<$file_count; $i++) {
                $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $file_tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $file_error= is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($file_error !== UPLOAD_ERR_OK || empty($file_name)) continue;

                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_types)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type for ' . htmlspecialchars($file_name)]);
                    return;
                }

                if ($file_size > 50 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'File ' . htmlspecialchars($file_name) . ' exceeds 50MB limit.']);
                    return;
                }

                $upload_dir = in_array($file_ext, $allowed_images) ? 'public/uploads/images/' : 'public/uploads/videos/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

                $unique_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file_name));
                $target_path = $upload_dir . $unique_name;

                if (move_uploaded_file($file_tmp, $target_path)) {
                    $new_media_paths[] = $target_path;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file: ' . htmlspecialchars($file_name)]);
                    return;
                }
            }
        }

        // Merge old media (remaining) with new uploads, ensuring unique values
        $all_media = array_unique(array_merge($old_media, $new_media_paths));
        $media_path = null;
        
        if (!empty($all_media)) {
            // Clean and validate all paths
            $all_media = array_map(function($path) {
                // Remove any potential null bytes or invalid characters
                $path = str_replace("\0", '', $path);
                // Trim and ensure it's a valid string
                return is_string($path) ? trim($path) : '';
            }, $all_media);
            
            // Remove any empty values
            $all_media = array_filter($all_media, function($path) {
                return !empty($path);
            });
            
            // Reset array keys
            $all_media = array_values($all_media);
            
            // Encode the array to JSON with proper error handling
            $json_encoded = json_encode($all_media, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            if ($json_encoded === false) {
                error_log('JSON encode error in edit_post: ' . json_last_error_msg() . ' Data: ' . print_r($all_media, true));
                // Clean up newly uploaded files if JSON encoding fails
                foreach ($new_media_paths as $file) {
                    if (file_exists($file)) @unlink($file);
                }
                echo json_encode(['success' => false, 'message' => 'Error processing media files.']);
                return;
            }
            
            // Verify the JSON is valid by decoding it back
            $decoded = json_decode($json_encoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON verification failed after encoding. Error: ' . json_last_error_msg());
                echo json_encode(['success' => false, 'message' => 'Error processing media files.']);
                return;
            }
            
            $media_path = $json_encoded;
        }

        $data = [
            'category' => $category,
            'content' => $content,
            'media_path' => $media_path,
            'font_family' => $font_family
        ];

        if ($this->UsersModel->update_post($id, $data)) {
            echo json_encode([
                'success' => true,
                'message' => 'Post updated successfully!',
                'redirect' => '/home'
            ]);
            return;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update post.']);
            return;
        }

    } else {
        // GET request - return post data
        echo json_encode([
            'success' => true,
            'post' => $post,
            'logged_in_user' => $logged_in_user
        ]);
        return;
    }
}


public function delete_post($id)
{
    header('Content-Type: application/json');
    
    $this->call->model('UsersModel');

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $logged_in_user = $_SESSION['user'];

    $post = $this->UsersModel->get_post_by_id($id);
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found.']);
        return;
    }

    if ($logged_in_user['role'] !== 'admin' && $post['user_id'] != $logged_in_user['id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. You can only delete your own posts.']);
        return;
    }

    // Delete associated media files
    if (!empty($post['media_path'])) {
        $media_files = [];
        try {
            $decoded = json_decode($post['media_path'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $media_files = $decoded;
            } else {
                $media_files = [$post['media_path']];
            }
        } catch (Exception $e) {
            $media_files = [$post['media_path']];
        }
        
        foreach ($media_files as $media_path) {
            if (file_exists($media_path)) {
                @unlink($media_path);
            }
        }
    }

    if ($this->UsersModel->delete_post($id)) {
        echo json_encode([
            'success' => true,
            'message' => 'Post deleted successfully!',
            'redirect' => '/home'
        ]);
        return;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete post.']);
        return;
    }
}

public function categories()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $logged_in_user = $_SESSION['user'];
    $user_id = $logged_in_user['id'];

    $unread_notifications = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'unread_notifications' => $unread_notifications,
        'categories' => ['Food', 'Travel', 'Technology', 'Lifestyle']
    ]);
}

public function filter_by_category($category)
{
    // This is now handled by api_get_posts_by_category - redirect there
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $logged_in_user = $_SESSION['user'];
    $user_id = $logged_in_user['id'];

    $valid_categories = ['Food', 'Travel', 'Technology', 'Lifestyle'];
    if (!in_array($category, $valid_categories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category.']);
        return;
    }

    $posts = $this->UsersModel->get_posts_by_category($category);

    if (!empty($posts)) {
        foreach ($posts as &$post) {
            $post['is_liked'] = $this->UsersModel->is_post_liked($post['post_id'], $user_id);
            $post['like_count'] = $this->UsersModel->get_like_count($post['post_id']);
            $post['comments'] = $this->UsersModel->get_comments_by_post($post['post_id']);
            
            if (!empty($post['comments'])) {
                foreach ($post['comments'] as &$comment) {
                    $comment['replies'] = $this->UsersModel->get_replies_by_comment($comment['comment_id']);
                }
            }
        }
    }

    $unread_notifications = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'posts' => $posts ?: [],
        'category' => $category,
        'unread_notifications' => $unread_notifications
    ]);
}

// ========== LIKES ENDPOINTS ==========
public function toggle_like()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() !== 'post') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $this->call->model('UsersModel');
    
    // Handle both FormData and JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['post_id'])) {
        $post_id = $input['post_id'];
    } else {
        $post_id = $this->io->post('post_id');
    }
    
    if (empty($post_id)) {
        echo json_encode(['success' => false, 'message' => 'Post ID is required']);
        return;
    }
    
    $user_id = $_SESSION['user']['id'];

    // Get post owner for notification
    $post = $this->UsersModel->get_post_by_id($post_id);
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        return;
    }

    $post_owner_id = $post['user_id'];
    $is_liked = $this->UsersModel->is_post_liked($post_id, $user_id);

    // Toggle like
    $result = $this->UsersModel->like_post($post_id, $user_id);
    
    if ($result !== false) {
        $new_liked_state = !$is_liked;
        $like_count = $this->UsersModel->get_like_count($post_id);

        // Create notification only if liking (not unliking) and not own post
        if ($new_liked_state && $post_owner_id != $user_id) {
            $this->UsersModel->create_notification([
                'user_id' => $post_owner_id,
                'actor_id' => $user_id,
                'post_id' => $post_id,
                'type' => 'like',
                'message' => htmlspecialchars($_SESSION['user']['username']) . ' liked your post',
                'created_at' => current_manila_datetime()
            ]);
        }

        echo json_encode([
            'success' => true,
            'liked' => $new_liked_state,
            'like_count' => $like_count
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to toggle like']);
    }
}

// ========== COMMENTS ENDPOINTS ==========
public function add_comment()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() !== 'post') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $this->call->model('UsersModel');
    
    // Handle both FormData and JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['post_id'])) {
        $post_id = $input['post_id'];
        $content = trim($input['content'] ?? '');
    } else {
        $post_id = $this->io->post('post_id');
        $content = trim($this->io->post('content'));
    }
    
    if (empty($post_id)) {
        echo json_encode(['success' => false, 'message' => 'Post ID is required']);
        return;
    }
    
    $user_id = $_SESSION['user']['id'];

    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
        return;
    }

    // Get post owner for notification
    $post = $this->UsersModel->get_post_by_id($post_id);
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        return;
    }

    $post_owner_id = $post['user_id'];

    $comment_data = [
        'post_id' => $post_id,
        'user_id' => $user_id,
        'content' => $content,
        'created_at' => current_manila_datetime()
    ];

    $comment_id = $this->UsersModel->add_comment($comment_data);

    if ($comment_id) {
        // Get the comment with user info
        $comment = $this->UsersModel->get_comment_by_id($comment_id);
        
        // Format created_at date for frontend
        if (isset($comment['created_at'])) {
            $comment['created_at_formatted'] = format_manila_datetime($comment['created_at']);
        }

        // Create notification (only if not own post)
        if ($post_owner_id != $user_id) {
            $this->UsersModel->create_notification([
                'user_id' => $post_owner_id,
                'actor_id' => $user_id,
                'post_id' => $post_id,
                'comment_id' => $comment_id,
                'type' => 'comment',
                'message' => htmlspecialchars($_SESSION['user']['username']) . ' commented on your post',
                'created_at' => current_manila_datetime()
            ]);
        }

        echo json_encode([
            'success' => true,
            'comment' => $comment
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
    }
}

public function delete_comment()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() !== 'post') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $this->call->model('UsersModel');
    $comment_id = $this->io->post('comment_id');
    $user_id = $_SESSION['user']['id'];

    $comment = $this->UsersModel->get_comment_by_id($comment_id);
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        return;
    }

    // Check if user owns the comment
    if ($comment['user_id'] != $user_id && $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $result = $this->UsersModel->delete_comment($comment_id);
    echo json_encode(['success' => $result !== false]);
}

// ========== REPLIES ENDPOINTS ==========
public function add_reply()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() !== 'post') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $this->call->model('UsersModel');
    $comment_id = $this->io->post('comment_id');
    $content = trim($this->io->post('content'));
    $user_id = $_SESSION['user']['id'];

    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
        return;
    }

    // Get comment to find post owner
    $comment = $this->UsersModel->get_comment_by_id($comment_id);
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        return;
    }

    $post_id = $comment['post_id'];
    $post = $this->UsersModel->get_post_by_id($post_id);
    $post_owner_id = $post['user_id'];
    $comment_owner_id = $comment['user_id'];

    $reply_data = [
        'comment_id' => $comment_id,
        'user_id' => $user_id,
        'content' => $content,
        'created_at' => current_manila_datetime()
    ];

    $reply_id = $this->UsersModel->add_reply($reply_data);

    if ($reply_id) {
        // Get reply with user info
        $replies = $this->UsersModel->get_replies_by_comment($comment_id);
        $reply = end($replies); // Get the last one (the one we just added)
        
        // Format created_at date for frontend
        if (isset($reply['created_at'])) {
            $reply['created_at_formatted'] = format_manila_datetime($reply['created_at']);
        }

        // Create notification for post owner (if not same as comment owner and not own reply)
        if ($post_owner_id != $user_id && $post_owner_id != $comment_owner_id) {
            $this->UsersModel->create_notification([
                'user_id' => $post_owner_id,
                'actor_id' => $user_id,
                'post_id' => $post_id,
                'comment_id' => $comment_id,
                'reply_id' => $reply_id,
                'type' => 'reply',
                'message' => htmlspecialchars($_SESSION['user']['username']) . ' replied to your post',
                'created_at' => current_manila_datetime()
            ]);
        }

        // Create notification for comment owner (if not own reply)
        if ($comment_owner_id != $user_id) {
            $this->UsersModel->create_notification([
                'user_id' => $comment_owner_id,
                'actor_id' => $user_id,
                'post_id' => $post_id,
                'comment_id' => $comment_id,
                'reply_id' => $reply_id,
                'type' => 'reply',
                'message' => htmlspecialchars($_SESSION['user']['username']) . ' replied to your comment',
                'created_at' => current_manila_datetime()
            ]);
        }

        echo json_encode([
            'success' => true,
            'reply' => $reply
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add reply']);
    }
}

public function delete_reply()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() !== 'post') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $this->call->model('UsersModel');
    $reply_id = $this->io->post('reply_id');
    $user_id = $_SESSION['user']['id'];

    // Get reply to check ownership
    $this->call->database();
    $reply = $this->db->table('replies')->where('reply_id', $reply_id)->get();
    
    if (!$reply) {
        echo json_encode(['success' => false, 'message' => 'Reply not found']);
        return;
    }

    // Check if user owns the reply
    if ($reply['user_id'] != $user_id && $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $result = $this->UsersModel->delete_reply($reply_id);
    echo json_encode(['success' => $result !== false]);
}

public function api_get_posts()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $logged_in_user = $_SESSION['user'];
    $user_id = $logged_in_user['id'];

    // Debug log
    error_log("=== DEBUG: Starting api_get_posts ===");
    error_log("Current time (Manila): " . current_manila_datetime());

    // Fetch all posts
    $posts = $this->UsersModel->get_all_posts();
    error_log("Number of posts found: " . count($posts));

   if (!empty($posts)) {
    foreach ($posts as &$post) { // note the & to modify in place
        $post['font_family'] = $post['font_family'] ?? null;

        // Format post datetime using Manila timezone
        if (!empty($post['created_at'])) {
            $post['created_at_formatted'] = format_manila_datetime($post['created_at']);
        }

        // Like info
        $post['is_liked'] = $this->UsersModel->is_post_liked($post['post_id'], $user_id);
        $post['like_count'] = $this->UsersModel->get_like_count($post['post_id']);

        // Comments
        $post['comments'] = $this->UsersModel->get_comments_by_post($post['post_id']);

        if (!empty($post['comments'])) {
            foreach ($post['comments'] as &$comment) {
                if (!empty($comment['created_at'])) {
                    $comment['created_at_formatted'] = format_manila_datetime($comment['created_at']);
                }

                $comment['replies'] = $this->UsersModel->get_replies_by_comment($comment['comment_id']);

                if (!empty($comment['replies'])) {
                    foreach ($comment['replies'] as &$reply) {
                        if (!empty($reply['created_at'])) {
                            $reply['created_at_formatted'] = format_manila_datetime($reply['created_at']);
                        }
                    }
                    unset($reply); // break reference
                }
            }
            unset($comment); // break reference
        }
    }
    unset($post); // break reference
}

    // Unread notifications
    $unread_notifications = $this->db->table('notifications')
        ->where('user_id', $user_id)
        ->where('is_read', 0)
        ->select_count('*', 'count')
        ->get()['count'];

    // Remove sensitive data
    unset($logged_in_user['password']);

    // Debug log
    error_log("=== DEBUG: Ending api_get_posts ===");

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'posts' => $posts ?: [],
        'unread_notifications' => $unread_notifications
    ]);
}
public function api_get_post($id)
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $logged_in_user = $_SESSION['user'];
    
    $post = $this->UsersModel->get_post_by_id($id);
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        return;
    }
    $post['font_family'] = $post['font_family'] ?? null;


    if ($logged_in_user['role'] !== 'admin' && $post['user_id'] != $logged_in_user['id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        return;
    }

    echo json_encode([
        'success' => true,
        'post' => $post,
        'logged_in_user' => $logged_in_user
    ]);
}

public function api_get_user()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    
    // Get clean user data (avoid nested structure)
    $user_data = $_SESSION['user'];
    
    // If user_data is nested, extract the actual user data
    if (isset($user_data['user']) && is_array($user_data['user'])) {
        // Unwind nested structure
        while (isset($user_data['user']) && is_array($user_data['user'])) {
            $user_data = $user_data['user'];
        }
        // Update session with clean data
        $_SESSION['user'] = $user_data;
    }
    
    $user_id = isset($user_data['id']) ? $user_data['id'] : null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid session data']);
        return;
    }
    
    $unread_notifications = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'user' => $user_data,
        'unread_notifications' => $unread_notifications
    ]);
}

public function api_admin_users()
{
    $admin = $this->requireAdmin();
    $this->call->model('UsersModel');

    $page = (int) ($this->io->get('page') ?? 1);
    $per_page = (int) ($this->io->get('per_page') ?? 5);
    $q = trim($this->io->get('q') ?? '');

    if ($page < 1) {
        $page = 1;
    }
    if ($per_page < 1) {
        $per_page = 5;
    }

    $result = $this->UsersModel->page($q, $per_page, $page);
    $total_rows = $result['total_rows'];
    $total_pages = max(1, (int) ceil($total_rows / $per_page));

    $this->jsonResponse(true, 'Members fetched successfully.', [
        'users' => $result['records'],
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages
        ],
        'search' => $q,
        'logged_in_user' => $admin
    ]);
}

public function api_admin_user($id)
{
    $this->requireAdmin();
    $this->call->model('UsersModel');

    $user = $this->UsersModel->get_user_by_id($id);

    if (!$user) {
        $this->jsonResponse(false, 'User not found.', [], 404);
        return;
    }

    $this->jsonResponse(true, 'User fetched successfully.', [
        'user' => $user
    ]);
}

public function api_admin_create_user()
{
    $this->requireAdmin();

    if ($this->io->method() !== 'post') {
        $this->jsonResponse(false, 'Invalid request method', [], 405);
        return;
    }

    $this->call->model('UsersModel');

    $payload = json_decode(file_get_contents('php://input'), true);
    $username = trim($payload['username'] ?? $this->io->post('username') ?? '');
    $email = trim($payload['email'] ?? $this->io->post('email') ?? '');
    $password = $payload['password'] ?? $this->io->post('password') ?? '';
    $role = trim($payload['role'] ?? $this->io->post('role') ?? 'user');

    if ($username === '' || $email === '' || $password === '' || $role === '') {
        $this->jsonResponse(false, 'All fields are required.');
        return;
    }

    if (!$this->UsersModel->is_username_unique($username)) {
        $this->jsonResponse(false, 'Username already exists.');
        return;
    }

    if ($this->UsersModel->is_email_exists($email)) {
        $this->jsonResponse(false, 'Email already exists.');
        return;
    }

    $data = [
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'role' => $role,
        'is_verified' => 1,
        'created_at' => current_manila_datetime()
    ];

    if ($this->UsersModel->insert($data)) {
        $this->jsonResponse(true, 'User created successfully.');
    } else {
        $this->jsonResponse(false, 'Error creating user.');
    }
}

public function api_admin_update_user($id)
{
    $this->requireAdmin();

    if ($this->io->method() !== 'post') {
        $this->jsonResponse(false, 'Invalid request method', [], 405);
        return;
    }

    $this->call->model('UsersModel');

    $payload = json_decode(file_get_contents('php://input'), true);
    $username = trim($payload['username'] ?? $this->io->post('username') ?? '');
    $email = trim($payload['email'] ?? $this->io->post('email') ?? '');
    $role = trim($payload['role'] ?? $this->io->post('role') ?? '');

    if ($username === '' || $email === '' || $role === '') {
        $this->jsonResponse(false, 'All fields are required.');
        return;
    }

    if (!$this->UsersModel->is_username_unique($username, $id)) {
        $this->jsonResponse(false, 'Username already exists.');
        return;
    }

    if ($this->UsersModel->update($id, [
        'username' => $username,
        'email' => $email,
        'role' => $role
    ])) {
        $this->jsonResponse(true, 'User updated successfully.');
    } else {
        $this->jsonResponse(false, 'Failed to update user.');
    }
}

public function api_admin_delete_user($id)
{
    $admin = $this->requireAdmin();

    if ($this->io->method() !== 'post') {
        $this->jsonResponse(false, 'Invalid request method', [], 405);
        return;
    }

    if ((int) $admin['id'] === (int) $id) {
        $this->jsonResponse(false, 'You cannot delete your own account.');
        return;
    }

    $this->call->model('UsersModel');

    if ($this->UsersModel->delete($id)) {
        $this->jsonResponse(true, 'User deleted successfully.');
    } else {
        $this->jsonResponse(false, 'Failed to delete user.');
    }
}

public function api_get_posts_by_category()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $logged_in_user = $_SESSION['user'];
    $user_id = $logged_in_user['id'];
    $category = $this->io->get('category');

    if (!$category) {
        echo json_encode(['success' => false, 'message' => 'Category required']);
        return;
    }

    // Fetch posts by category
    $posts = $this->UsersModel->get_posts_by_category($category);

    // For each post, get likes and comments data
    if (!empty($posts)) {
        foreach ($posts as &$post) {
            $post['is_liked'] = $this->UsersModel->is_post_liked($post['post_id'], $user_id);
            $post['like_count'] = $this->UsersModel->get_like_count($post['post_id']);
            $post['comments'] = $this->UsersModel->get_comments_by_post($post['post_id']);
            
            // Format created_at date for frontend (keep original for reference)
            // Database stores dates in Manila time, format directly without conversion
            if (isset($post['created_at'])) {
                $post['created_at_formatted'] = format_manila_datetime($post['created_at']);
            }
            
            // Get replies for each comment
            if (!empty($post['comments'])) {
                foreach ($post['comments'] as &$comment) {
                    // Format comment created_at
                    if (isset($comment['created_at'])) {
                        $comment['created_at_formatted'] = format_manila_datetime($comment['created_at']);
                    }
                    
                    $comment['replies'] = $this->UsersModel->get_replies_by_comment($comment['comment_id']);
                    
                    // Format reply created_at
                    if (!empty($comment['replies'])) {
                        foreach ($comment['replies'] as &$reply) {
                            if (isset($reply['created_at'])) {
                                $reply['created_at_formatted'] = format_manila_datetime($reply['created_at']);
                            }
                        }
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'posts' => $posts ?: [],
        'category' => $category
    ]);
}

// ========== NOTIFICATIONS ENDPOINTS ==========
public function get_notifications()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $user_id = $_SESSION['user']['id'];
    $notifications = $this->UsersModel->get_notifications($user_id);
    $unread_count = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications ?: [],
        'unread_count' => $unread_count
    ]);
}

public function mark_notification_read()
{
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($this->io->method() !== 'post') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $this->call->model('UsersModel');
    $notification_id = $this->io->post('notification_id');
    $result = $this->UsersModel->mark_notification_as_read($notification_id);

    echo json_encode(['success' => $result !== false]);
}

public function notifications_page()
{
    // This is now handled by get_notifications API - return JSON
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $user_id = $_SESSION['user']['id'];
    $logged_in_user = $_SESSION['user'];

    $notifications = $this->UsersModel->get_notifications($user_id, 50);
    $unread_count = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'notifications' => $notifications ?: [],
        'unread_count' => $unread_count
    ]);
}

// ========== PROFILE METHODS ==========
public function profile()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $user_id = $_SESSION['user']['id'];
    $logged_in_user = $_SESSION['user'];

    $user = $this->UsersModel->get_user_by_id($user_id);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }

    $unread_notifications = $this->UsersModel->get_unread_notifications_count($user_id);

    echo json_encode([
        'success' => true,
        'logged_in_user' => $logged_in_user,
        'user' => $user,
        'unread_notifications' => $unread_notifications
    ]);
}

public function update_profile()
{
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $user_id = $_SESSION['user']['id'];
    $logged_in_user = $_SESSION['user'];

    if ($this->io->method() === 'post') {
        $username = $this->io->post('username');
        $email = $this->io->post('email');

        $user = $this->UsersModel->get_user_by_id($user_id);

        if (!$this->UsersModel->is_username_unique($username, $user_id)) {
            echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose a different username.']);
            return;
        }

        $data = [
            'username' => $username,
            'email' => $email
        ];

        if ($this->UsersModel->update($user_id, $data)) {
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['email'] = $email;
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => $_SESSION['user']
            ]);
            return;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
            return;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        return;
    }
}

public function api_search()
{
    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $this->call->model('UsersModel');
    $q = trim($this->io->get('q') ?? '');

    if ($q === '') {
        echo json_encode(['success' => true, 'users' => [], 'posts' => []]);
        return;
    }

    // Search users by username only
    $user_results = $this->UsersModel->db->table('users')
        ->like('username', '%'.$q.'%')
        ->get_all();

    // Search posts by category OR by author username
    $post_results = $this->UsersModel->db->table('posts')
        ->join('users', 'users.id = posts.user_id')
        ->group_start() // open parentheses for OR condition
            ->like('posts.category', '%'.$q.'%')
            ->or_like('users.username', '%'.$q.'%')
        ->group_end() // close parentheses
        ->order_by('posts.created_at', 'DESC')
        ->get_all();

    echo json_encode([
        'success' => true,
        'query' => $q,
        'users' => $user_results ?: [],
        'posts' => $post_results ?: []
    ]);
}




}
