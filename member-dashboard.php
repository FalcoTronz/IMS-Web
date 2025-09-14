<?php
session_start();

// Prevent browser from caching the page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Role check
$requiredRole = basename(__FILE__) === "staff-dashboard.php" ? "staff" : "member";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
  header("Location: index.html");
  exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>
  <link rel="stylesheet" href="css/home.css">
  <link rel="stylesheet" href="css/member-dashboard.css">
  <link rel="icon" type="image/x-icon" href="img/book.png">
</head>
<body>

<header class="navbar">
  <a href="index.html" class="logo">📚 Library Management System</a>
  <nav class="nav-links" id="navLinks">
    <!-- <a href="index.html">Home</a> -->
    <a href="php/logout.php">Logout</a>
  </nav>
  <div class="hamburger" onclick="toggleMenu()">☰</div>
</header>

<div class="dashboard-layout">
  <!-- Sidebar Navigation -->
  <aside class="sidebar">
    <h2>📋 Member Panel</h2>
    <ul>
      <li onclick="showSection('search-section')" class="active">🔍 Search Items</li>
      <li onclick="showSection('members')">👥 My Profile</li>
      <li onclick="showSection('logs')">📖 Borrowing History</li>
    </ul>
  </aside>

  <!-- Main Dashboard Content -->
  <main class="dashboard-content">

  <!-- Book Recommendations -->
  <section id="recs-panel" class="recs-panel" style="margin:0 0 16px;">
    <h3 style="margin:0 0 8px;">✨ Recommended Books For You</h3>
    <div id="recs-list" class="recs-list" style="display:flex;gap:8px;flex-wrap:wrap;"></div>
    <div id="recs-error" style="color:#b22;font-size:0.95rem;"></div>
  </section>
    
    <!-- 🔍 Search Items Section -->
    <section id="search-section" class="dashboard-section active">
      <h2>🔍 Search Items</h2>


      
      <div class="search-bar" style="margin-bottom: 20px;">
        <input type="text" id="search-query" placeholder="Search by Title, Author, or Category..." style="padding: 10px; width: 300px;">
        <button id="clear-button" style="padding: 10px 20px;">Clear</button>
      </div>

      <div class="item-table">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Book Name</th>
                <th>Author(s)</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>ISBN</th>
                <th>Year</th>
                <th>Location</th>
                <!-- <th>Status</th> -->
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="search-results">
              <!-- Search results will appear here -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- 👥 My Profile -->
    <section id="members" class="dashboard-section">
      <h2>👥 My Profile</h2>
      <?php
// Connect to database (reuse the same connection format as in login.php)
$conn = pg_connect("host=aws-0-eu-west-2.pooler.supabase.com port=5432 dbname=postgres user=postgres.dfyivadvrlpakujebnqf password=WjdiV/BVT2q8g2k");

if (!$conn) {
  echo "<p style='color: red;'>Failed to connect to the database.</p>";
} else {
  $userId = $_SESSION['user_id'];
  $result = pg_query_params($conn, "SELECT id, full_name, email, phone, address, role, created_at FROM users WHERE id = $1 LIMIT 1
", [$userId]);

if ($result && pg_num_rows($result) === 1) {
  $user = pg_fetch_assoc($result);

  // Styled profile display
  echo "<div class='profile-section'>";
  echo "<ul class='profile-list'>";
  echo "<li><strong>Library ID Number:</strong> " . htmlspecialchars($user['id']) . "</li>";
  echo "<li><strong>Name:</strong> " . htmlspecialchars($user['full_name']) . "</li>";
  echo "<li><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</li>";
  echo "<li><strong>Phone:</strong> " . htmlspecialchars($user['phone']) . "</li>";
  echo "<li><strong>Address:</strong> " . htmlspecialchars($user['address']) . "</li>";
  echo "<li><strong>Role:</strong> " . ($user['role'] === 'member' ? 'Library Member' : 'Staff') . "</li>";
  echo "<li><strong>Member Since:</strong> " . date("d M Y", strtotime($user['created_at'])) . "</li>";
  echo "</ul>";
  echo '<button id="editProfileBtn" class="edit-btn">Edit Profile</button>';
  echo "</div>";

  // Hidden edit form
  echo '
  <div id="editProfileForm" style="display: none;" class="edit-profile-form">
    <form method="POST" action="php/update-profile.php">
      <label>Email:</label>
      <input type="email" name="email" value="' . htmlspecialchars($user['email']) . '" required>

      <label>Phone:</label>
      <input type="text" name="phone" value="' . htmlspecialchars($user['phone']) . '" required>

      <label>Address:</label>
      <input type="text" name="address" value="' . htmlspecialchars($user['address']) . '" required>

      <label>New Password (optional):</label>
      <input type="password" name="password" placeholder="Leave blank to keep current password">

      <button type="submit" class="update-btn">Update Profile</button>
      <button type="button" id="cancelEditBtn" class="cancel-btn">Cancel</button>
    </form>
  </div>';
} else {
  echo "<p style='color: red;'>User not found.</p>";
}

}
?>

    </section>

    <!-- 🔄 Borrowing Logs -->
    <section id="logs" class="dashboard-section">
      <h2>📖 Borrowing History</h2>
      <table class="borrowing-history-table">
  <thead>
    <tr>
      <th>Title</th>
      <th>Author</th>
      <th>ISBN</th>
      <th>Borrow Date</th>
      <th>Due Date</th>
      <th>Return Date</th>
      <th>Overdue Status</th>
    </tr>
  </thead>
  <tbody id="borrowing-history-body">
    <!-- Borrowing records will be inserted here dynamically -->
  </tbody>
</table>

    </section>
  </main>
</div>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-links">
    </div>
    <div class="footer-copy">
      &copy; 2025 Library Management System
        <span style="color: white; font-weight: bold;">Student Number: HE19955</span>
    </div>
  </footer>

<div id="toast"></div>

<script>
  const toggleBtn = document.getElementById("editProfileBtn");
  if (toggleBtn) {
    toggleBtn.addEventListener("click", function () {
      const form = document.getElementById("editProfileForm");
      if (form) {
        form.style.display = form.style.display === "none" ? "block" : "none";
      }
    });
  }

    const cancelBtn = document.getElementById("cancelEditBtn");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", function () {
      const form = document.getElementById("editProfileForm");
      if (form) {
        form.style.display = "none";
      }
    });
  }
window.CURRENT_USER_ID = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
  
</script>

<script src="js/recs.js?v=1"></script>
<script src="js/home.js"></script>
<script src="js/member-dashboard.js"></script>
</body>
</html>





