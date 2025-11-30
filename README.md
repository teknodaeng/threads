# Mini Threads

A lightweight social media platform clone built with native PHP, MySQL, and Tailwind CSS. This application allows users to share updates, interact with others, and explore content in a familiar "threads" style interface.

## Features

### 👤 User Experience

- **Authentication**: Secure Login and Registration system.
- **Feeds**:
  - **For You**: Discover content from all users.
  - **Following**: Personalized feed showing only users you follow.
- **Threads**: Create posts with text and images.
- **Interactions**:
  - Like and Unlike threads.
  - Reply to threads (nested discussions).
  - Follow/Unfollow users.
- **Search**: Real-time search for users and threads.
- **Notifications**: Updates on likes, replies, and new followers.
- **Profile**: User profiles with avatar upload, follower counts, and post history.

### 🛡️ Administration

- **Report System**: Users can report inappropriate content.
- **Admin Dashboard**: Manage reports and delete violating threads.

## Tech Stack

- **Backend**: PHP (Native/Vanilla)
- **Database**: MySQL
- **Frontend**: HTML5, JavaScript (Vanilla)
- **Styling**: Tailwind CSS v4

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web Server (Apache/Nginx) or PHP built-in server

### Setup Steps

1. **Clone the repository**

   ```bash
   git clone https://github.com/teknodaeng/threads.git
   cd threads
   ```

2. **Database Configuration**

   - Create a new MySQL database (e.g., `threads`).
   - Import the database schema. Ensure the following tables exist:
     - `users`
     - `threads`
     - `thread_likes`
     - `thread_images`
     - `follows`
     - `notifications`
     - `reports`
   - _Note: You may need to run the provided setup scripts (e.g., `setup_likes.php`, `create_reports_table.php`) if a full SQL dump is not provided._

3. **Configure Connection**

   - Open `core/db.php`.
   - Update the database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'threads';
     $username = 'root';
     $password = ''; // Your MySQL password
     ```

4. **Run the Application**
   - **Using PHP Built-in Server**:
     ```bash
     php -S localhost:8000
     ```
   - Open your browser and navigate to `http://localhost:8000`.

## Project Structure

```
mini-threads/
├── actions/            # Backend logic (API endpoints, form handling)
├── admin/              # Admin dashboard and management logic
├── assets/             # Static files (CSS, JS, Images)
├── core/               # Core configurations (DB connection, Helpers)
├── includes/           # Reusable UI components (Header, Footer)
├── index.php           # Main Feed
├── login.php           # User Login
├── register.php        # User Registration
├── profile.php         # User Profile
├── search.php          # Search Page
├── thread.php          # Single Thread View
└── README.md           # Project Documentation
```

## License

This project is open-source and available under the MIT License.
