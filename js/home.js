
// Toggle the hamburger menu open/close state
function toggleMenu() {
  document.getElementById("navLinks").classList.toggle("active");
}

// Close the mobile menu when clicking outside of nav or hamburger
document.addEventListener("click", function (e) {
  const nav = document.getElementById("navLinks");
  const hamburger = document.querySelector(".hamburger");

  if (!nav.contains(e.target) && !hamburger.contains(e.target)) {
    nav.classList.remove("active");
  }
});

// Automatically close mobile menu when screen is resized to desktop
window.addEventListener("resize", function () {
  const nav = document.getElementById("navLinks");
  if (window.innerWidth > 768) {
    nav.classList.remove("active");
  }
});

// Open the login popup and update its content based on role
function openPopup(role) {
  const popupId = role === "Staff" ? "staffLoginPopup" : "memberLoginPopup";
  document.getElementById(popupId).style.display = "block";
}

// Close all login popups
function closePopup() {
  document.querySelectorAll(".popup-overlay").forEach(p => p.style.display = "none");
}

// Close the popup if clicking outside of the popup content
window.addEventListener("click", function (e) {
  document.querySelectorAll(".popup-overlay").forEach(popup => {
    if (e.target === popup) {
      popup.style.display = "none";
    }
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.querySelector(".search-input");
  const resultsTable = document.getElementById("search-results");

if (searchInput && resultsTable) {
  const COLS = 7; // ID, Name, Details, Category, ISBN, Year, Location

  searchInput.addEventListener("input", function () {
    const query = searchInput.value.trim();
    if (!query) {
      resultsTable.innerHTML = `<tr><td colspan="${COLS}">Start typing to search...</td></tr>`;
      return;
    }

    fetch("php/get-items.php")
      .then(res => res.json())
      .then(items => {
        // Let Fuse handle ALL queries (numeric or text). Add string fields for id/year/status.
        const data = items.map(i => ({
          ...i,
          id_text: String(i.id ?? ""),
          year_text: String(i.year ?? ""),
          status_text: String(i.status ?? "")
        }));

        const fuse = new Fuse(data, {
          keys: ["id_text", "name", "details", "category", "item_code", "year_text", "status_text"],
          threshold: 0.5,
          includeMatches: true
        });

        const results = fuse.search(query);
        updateResultsTable(results);
      })
      .catch(err => {
        console.error("Search error:", err);
        resultsTable.innerHTML = `<tr><td colspan="${COLS}">Search failed.</td></tr>`;
      });
  });

  const clearButton = document.getElementById("clear-button");
  if (clearButton) {
    clearButton.addEventListener("click", function () {
      searchInput.value = "";
      resultsTable.innerHTML = `<tr><td colspan="${COLS}">Start typing to search...</td></tr>`;
    });
  }

  function highlight(text, match) {
    const s = (text ?? '').toString();
    if (!match?.indices?.length) return s;

    let out = "", last = 0;
    match.indices.forEach(([start, end]) => {
      out += s.slice(last, start);
      out += `<span class='highlight'>${s.slice(start, end + 1)}</span>`;
      last = end + 1;
    });
    out += s.slice(last);
    return out;
  }

  function updateResultsTable(results) {
    if (!results.length) {
      resultsTable.innerHTML = `<tr><td colspan="${COLS}">No items found.</td></tr>`;
      return;
    }

    resultsTable.innerHTML = "";

    results.forEach(result => {
      const item = result.item || result;
      const matches = result.matches || [];

      const getMatch = (key) => matches.find(m => m.key === key);

      resultsTable.innerHTML += `
        <tr>
          <td>${highlight(item.id,        getMatch("id_text"))}</td>
          <td>${highlight(item.name,      getMatch("name"))}</td>
          <td>${highlight(item.details,   getMatch("details"))}</td>
          <td>${highlight(item.category,  getMatch("category"))}</td>
          <td>${highlight(item.item_code, getMatch("item_code"))}</td>
          <td>${highlight(item.year,      getMatch("year_text"))}</td>
          <td>${item.location ?? ""}</td>
        </tr>
      `;
    });
  }
}


  // Staff login form
  const staffLoginForm = document.getElementById("staff-login-form");
  if (staffLoginForm) {
    staffLoginForm.addEventListener("submit", function (e) {
      e.preventDefault();
      handleLogin(staffLoginForm, "staff");
    });
  }

  // Member login form
  const memberLoginForm = document.getElementById("member-login-form");
  if (memberLoginForm) {
    memberLoginForm.addEventListener("submit", function (e) {
      e.preventDefault();
      handleLogin(memberLoginForm, "member");
    });
  }

  // Generic login handler
  async function handleLogin(form, role) {
    const email = form.querySelector('input[name="username"]').value.trim();
    const password = form.querySelector('input[name="password"]').value;
    const recaptcha = form.querySelector('textarea[name="g-recaptcha-response"]')?.value;

    const formData = new FormData();
    formData.append("username", email);
    formData.append("password", password);
    formData.append("role", role);
    formData.append("g-recaptcha-response", recaptcha);

    try {
      const res = await fetch("php/login.php", {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (data.success) {
        if (data.role === "staff") {
          window.location.href = "staff-dashboard.php";
        } else if (data.role === "member") {
          window.location.href = "member-dashboard.php";
        }
      } else {
        alert(data.error || "Login failed.");
      }
    } catch (err) {
      console.error("Login error:", err);
      alert("Something went wrong during login.");
    }
  }
});

// === Home: Trending Now (Top borrowed books from analytics API) ===
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.getElementById('trending-panel');
  if (!panel) return;

  const listEl = document.getElementById('trending-list');
  const errEl  = document.getElementById('trending-error');

  errEl.textContent = 'Loading…';

  fetch('php/top-books-proxy.php', { cache: 'no-store' })
    .then(r => r.json())
    .then(raw => {
      // Normalize API shapes:
      // - Flask: [{id, name, borrow_count}]
      // - Old shape: { top_books: [[title, count], ...] }
      let rows = [];
      if (Array.isArray(raw)) {
        rows = raw.map(d => ({ title: d.name ?? d.title ?? '', count: Number(d.borrow_count ?? d.count ?? 0) }));
      } else if (raw && Array.isArray(raw.top_books)) {
        rows = raw.top_books.map(([title, count]) => ({ title, count: Number(count || 0) }));
      }

      listEl.innerHTML = '';
      if (!rows.length) {
        errEl.textContent = 'No data yet.';
        return;
      }

      rows.forEach(({ title, count }) => {
        const chip = document.createElement('div');
        chip.style.cssText =
          'padding:8px 10px;border:3px solid green;border-radius:8px;background:#fff;display:flex;align-items:center;gap:8px;';
        chip.innerHTML = `<span>${title}</span><span style="font-size:.85rem;padding:2px 6px;border-radius:999px;background:#e8f9e8;border:1px solid #90ee90;">${count}</span>`;
        listEl.appendChild(chip);
      });

      errEl.textContent = '';
    })
    .catch((e) => {
      console.error('Trending fetch failed:', e);
      errEl.textContent = 'Could not load trending data.';
    });
});










