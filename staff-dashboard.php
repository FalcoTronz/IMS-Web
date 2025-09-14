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
  <title>Staff Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>
  <link rel="stylesheet" href="css/home.css">
  <link rel="stylesheet" href="css/staff-dashboard.css">
  <link rel="icon" type="image/x-icon" href="img/book.png">
</head>
<body>

<header class="navbar">
  <a href="index.html" class="logo">📚  Library Management System</a>
  <nav class="nav-links" id="navLinks">
    <!-- <a href="index.html">Home</a> -->
    <a href="php/logout.php">Logout</a>
  </nav>
  <div class="hamburger" onclick="toggleMenu()">☰</div>
</header>

<div class="dashboard-layout">
  <!-- Sidebar Navigation -->
  <aside class="sidebar">
    <h2>📋 Staff Panel</h2>
    <ul>
      <li onclick="showDashboardSection('add')">➕ Add Items</li>
      <li onclick="showDashboardSection('search')">🔍 Search Items</li>
      <li onclick="showSection('members')">👥 Manage Members</li>
      <li onclick="showSection('logs')">🔄 Borrowing Logs</li>
      <li onclick="showSection('reports')">📊 View Reports</li>
      <li onclick="showSection('audit-log')">🔗 Audit Log</li>
    </ul>
  </aside>

  <!-- Main Dashboard Content -->
  <main class="dashboard-content">

    <!-- ➕ Add Items Section -->
    <section id="add-section" class="dashboard-section active">
      <h2>➕ Add Items</h2>

      <!-- Add New Item Form -->
      <div class="item-form">
        <h3>Add New Book</h3>
        <!-- ✅ point to add-item.php and POST -->
        <form id="add-item-form" action="php/add-item.php" method="POST">
          <input id="title-input" name="name" type="text" placeholder="Book Name" required minlength="2" maxlength="100">
          <input id="author-input" name="details" type="text" placeholder="Author(s)" required minlength="2" maxlength="100">
          <input id="category-input" name="category" type="text" placeholder="Category" required minlength="2" maxlength="50">
          <input id="quantity-input" name="quantity" type="number" placeholder="Quantity" required min="1" max="1000">
          <input id="item-code-input" name="item_code" type="text" placeholder="ISBN" required minlength="4" maxlength="20">
          <input id="year-input" name="year" type="number" placeholder="Publication Year" required min="1000" max="2100">
          <input id="location-input" name="location" type="text" placeholder="Location" required minlength="1" maxlength="50">
          <!-- <select id="status-input" name="status">
            <option value="Available">Available</option>
            <option value="Unavailable">Unavailable</option>
          </select> -->
          <button type="submit">Add Item</button>
        </form>
      </div>

      <h2 style="text-align: center; margin-bottom: 10px; font-weight: bold; color: #1f4e79;">
        Recently Added Item
      </h2>

      <!-- Item Table -->
      <div class="item-table">
        <!-- <h3>Item List</h3> -->
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Book ID</th>
                <th>Book Name</th>
                <th>Author(s)</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>ISBN</th>
                <th>Year</th>
                <th>Location</th>
                <!-- <th>Status</th> -->
                <th>Last Update</th>
                <!-- <th>Actions</th> -->
              </tr>
            </thead>
            <!-- ✅ only show the most recently added item here -->
            <tbody id="add-latest-tbody">
              <!-- leave empty; JS will insert the single latest item -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- 🔍 Search Items Section -->
    <section id="search-section" class="dashboard-section">
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
                <th>Book ID</th>
                <th>Book Name</th>
                <th>Author(s)</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>ISBN</th>
                <th>Year</th>
                <th>Location</th>
                <!-- <th>Status</th> -->
                <th>Last Update</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="search-results">
              <!-- Search results will appear here -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- 👥 Manage Members Section -->
    <section id="members" class="dashboard-section">
      <h2>👥 Manage Members</h2>

      <!-- Search Bar -->
      <div class="search-bar" style="margin-bottom: 20px;">
        <input type="text" id="member-search" placeholder="Search by ID, name, email, or phone..." style="padding: 10px; width: 300px;">
        <button id="member-clear-button" style="padding: 10px 20px;">Clear</button>
      </div>

      <!-- Members Table -->
      <div class="item-table">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Library ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Address</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Registered</th>
                <!-- <th>Borrowing</th> -->
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="member-table-body">
              <!-- User rows will be inserted here by JS -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

<!-- 📊 View Reports -->
<section id="reports" class="dashboard-section">
  <h2>📊 View Reports</h2>

  <div class="item-table" style="max-width: 900px;">
    <div class="table-responsive" style="padding: 12px;">
      <h3 style="margin: 0 0 12px;">Top Borrowed Books</h3>
      <canvas id="topBooksChart" height="180"></canvas>
      <div id="topBooksError" style="margin-top:8px; color:#b22; font-size:0.95rem;"></div>
    </div>
  </div>

<div class="reports-kpis">
  <div class="kpi-card overdue">
    <div class="kpi-title">Overdue now</div>
    <div id="kpi-overdue" class="kpi-value">—</div>
  </div>

  <div class="kpi-card borrowed">
    <div class="kpi-title">Currently borrowed</div>
    <div id="kpi-borrowed" class="kpi-value">—</div>
  </div>

  <div class="kpi-card returned">
    <div class="kpi-title">Returned this month</div>
    <div id="kpi-returned" class="kpi-value">—</div>
  </div>
</div>


<!-- Charts -->
<div class="reports-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
  <div style="background:#fff;border-radius:12px;padding:12px;">
    <h4 style="margin:0 0 8px;">Borrowings — last 30 days</h4>
    <canvas id="borrowingsTrendChart" height="140"></canvas>
  </div>
  <div style="background:#fff;border-radius:12px;padding:12px;">
    <h4 style="margin:0 0 8px;">Top Categories</h4>
    <canvas id="topCategoriesChart" height="140"></canvas>
  </div>
</div>


  
</section>

<!-- Audit Log -->
    <section id="audit-log" class="dashboard-section">
  <h2>🔗 Audit Log</h2>

  <div style="margin:12px 0; display:flex; gap:8px; flex-wrap:wrap;">
    <input type="text" id="audit-filter-text" placeholder="Search user/item/action…" style="padding:8px; min-width:260px;">
    <input type="date" id="audit-filter-from" style="padding:8px;">
    <input type="date" id="audit-filter-to" style="padding:8px;">
    <button id="audit-clear" style="padding:8px 12px;">Clear</button>
  </div>

  <div class="table-wrapper">
    <table class="styled-table" style="width:100%;">
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Action</th>
          <th>User</th>
          <th>Item</th>
          <th>Borrow Date</th>
          <th>Return Date</th>
          <th>Chain</th>
        </tr>
      </thead>
      <tbody id="audit-log-body">
        <tr><td colspan="8">Loading…</td></tr>
      </tbody>
    </table>
  </div>

  <div id="audit-toast" style="visibility:hidden; min-width:200px; background:#333; color:#fff; text-align:center; border-radius:4px; padding:10px; position:fixed; z-index:9999; left:50%; bottom:30px; transform:translateX(-50%);">
    <!-- toast -->
  </div>
</section>


    <!-- 📖 Borrowing Logs -->
    <section id="logs" class="dashboard-section">
      <h2>📖 Borrowing Logs</h2>

      <div class="search-bar" style="margin-bottom: 20px;">
        <input type="text" id="borrow-search-query" placeholder="Search by Member, Book, or ISBN..." style="padding: 10px; width: 300px;" />
        <button id="borrow-clear-button" style="padding: 10px 20px;">Clear</button>
      </div>

      <div class="item-table">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Library ID</th>
                <th>Member Name</th>
                <th>Book Name</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Approval Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="staff-borrowing-logs-body">
              <!-- Borrowing requests will appear here -->
            </tbody>
          </table>
        </div>
      </div>
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

<!-- Custom confirm modal -->
<div id="confirm-box" class="confirm-overlay">
  <div class="confirm-content">
    <p id="confirm-message">Are you sure you want to delete this item?</p>
    <div class="confirm-buttons">
      <button id="confirm-yes">Yes</button>
      <button id="confirm-no">No</button>
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div id="toast"></div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/home.js"></script>
<script src="js/staff-dashboard.js"></script>
<script src="js/borrowing-logs.js"></script>
<script src="js/reports.js?v=3"></script>
<script src="js/audit-log.js"></script>


</body>
</html>






