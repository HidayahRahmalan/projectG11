// js/app.js

// This event listener waits for the HTML document to be fully loaded before running the script.
document.addEventListener("DOMContentLoaded", () => {
  // =================================================================
  // GLOBAL DATA & AUTH (Needed on all pages)
  // =================================================================
  let userData = [];
  let currentUser = null;

  // Load user data and current user from localStorage
  if (localStorage.getItem('userData')) {
    userData = JSON.parse(localStorage.getItem('userData'));
  }
  if (localStorage.getItem('currentUser')) {
    currentUser = JSON.parse(localStorage.getItem('currentUser'));
  }
  
  // Sample Data (only add if localStorage is empty)
  if (!localStorage.getItem('recipesData')) {
    localStorage.setItem('recipesData', JSON.stringify([
      {id: 1, title: "Classic Spaghetti Carbonara", cuisine: "italian", dietary: "", time: 20, difficulty: "easy", description: "A traditional Italian pasta dish.", imageUrl: "https://images.unsplash.com/photo-1551892374-ecf8285cf542?w=400&h=200&fit=crop", user_id: 1},
      {id: 2, title: "Rainbow Vegetable Stir Fry", cuisine: "chinese", dietary: "vegetarian", time: 15, difficulty: "easy", description: "A colorful mix of fresh vegetables.", imageUrl: "https://images.unsplash.com/photo-1512058564366-18510be2db19?w=400&h=200&fit=crop", user_id: 2}
    ]));
  }
  if (userData.length === 0) {
      userData.push({id: 1, name: "John Doe", email: "john@example.com", password: "password123", role: "admin"});
      userData.push({id: 2, name: "Jane Smith", email: "jane@example.com", password: "password123", role: "user"});
      localStorage.setItem('userData', JSON.stringify(userData));
  }
  
  // Load recipes data from localStorage
  let recipesData = JSON.parse(localStorage.getItem('recipesData')) || [];

  // Function to update the UI based on login status (e.g., show/hide Login button)
  function updateAuthUI() {
    const authLink = document.getElementById("authLink");
    const userMenu = document.getElementById("userMenu");
    const uploadNavLink = document.getElementById("uploadNavLink");

    if (currentUser) {
      authLink.style.display = "none";
      userMenu.style.display = "flex";
      document.getElementById("userAvatar").textContent = currentUser.name.charAt(0).toUpperCase();
      uploadNavLink.classList.remove("disabled");

      // Dropdown menu logic
      const userAvatar = document.getElementById("userAvatar");
      const dropdownMenu = document.getElementById("dropdownMenu");
      userAvatar.addEventListener('click', () => dropdownMenu.classList.toggle('show'));
      window.addEventListener('click', (e) => {
        if (!userAvatar.contains(e.target) && !dropdownMenu.contains(e.target)) {
          dropdownMenu.classList.remove('show');
        }
      });

      // Dropdown item listeners
      document.getElementById('logoutBtn').addEventListener('click', logout);
      document.getElementById('myRecipesBtn').addEventListener('click', showMyRecipes);
      document.getElementById('myProfileBtn').addEventListener('click', () => alert('Profile page coming soon!'));

    } else {
      authLink.style.display = "block";
      userMenu.style.display = "none";
      uploadNavLink.classList.add("disabled");
    }

    // Highlight the active navigation link
    const currentPage = window.location.pathname.split("/").pop();
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
  }
  
  function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = "index.html";
  }

  // Always update the auth UI on every page load
  updateAuthUI();

  // =================================================================
  // PAGE-SPECIFIC LOGIC
  // =================================================================

  // ------------------
  // HOME PAGE (index.html)
  // ------------------
  if (document.getElementById('recipesGrid')) {
    const recipesGrid = document.getElementById('recipesGrid');
    
    function renderRecipes(recipesToRender = recipesData) {
      recipesGrid.innerHTML = "";
      if (recipesToRender.length === 0) {
          recipesGrid.innerHTML = `<p style="color: white; text-align: center; grid-column: 1 / -1;">No recipes found. Try adjusting your filters!</p>`;
          return;
      }
      recipesToRender.forEach(recipe => {
        const card = document.createElement("div");
        card.className = "recipe-card";
        
        let buttonsHTML = '';
        if (currentUser && (currentUser.role === "admin" || currentUser.id === recipe.user_id)) {
            buttonsHTML = `
                <button class="edit-btn" data-id="${recipe.id}">Edit</button>
                <button class="delete-btn" data-id="${recipe.id}">Delete</button>
            `;
        }

        card.innerHTML = `
            ${buttonsHTML}
            <img src="${recipe.imageUrl}" alt="${recipe.title}" class="recipe-image">
            <div class="recipe-content">
                <h3 class="recipe-title">${recipe.title}</h3>
                <div class="recipe-meta">
                    <span class="meta-tag">${recipe.cuisine}</span>
                    <span class="meta-tag">⏱️ ${recipe.time} min</span>
                    <span class="meta-tag">${recipe.difficulty}</span>
                    ${recipe.dietary ? `<span class="meta-tag">${recipe.dietary}</span>` : ''}
                </div>
                <p class="recipe-description">${recipe.description}</p>
                <button class="view-recipe-btn">View Recipe</button>
            </div>
        `;
        recipesGrid.appendChild(card);
      });
    }

    function filterRecipes() {
      const searchText = document.getElementById("searchInput").value.toLowerCase();
      const cuisineFilter = document.getElementById("cuisineFilter").value;
      const dietaryFilter = document.getElementById("dietaryFilter").value;
      const difficultyFilter = document.getElementById("difficultyFilter").value;
      const timeFilter = parseInt(document.getElementById("timeFilter").value, 10);

      const filtered = recipesData.filter(r => {
        const matchesSearch = r.title.toLowerCase().includes(searchText) || r.description.toLowerCase().includes(searchText);
        const matchesCuisine = !cuisineFilter || r.cuisine === cuisineFilter;
        const matchesDietary = !dietaryFilter || r.dietary === dietaryFilter;
        const matchesDifficulty = !difficultyFilter || r.difficulty === difficultyFilter;
        const matchesTime = !timeFilter || r.time <= timeFilter;
        return matchesSearch && matchesCuisine && matchesDietary && matchesDifficulty && matchesTime;
      });
      renderRecipes(filtered);
    }
    
    // Event listeners for homepage
    document.getElementById('searchInput').addEventListener('input', filterRecipes);
    document.getElementById('cuisineFilter').addEventListener('change', filterRecipes);
    document.getElementById('dietaryFilter').addEventListener('change', filterRecipes);
    document.getElementById('difficultyFilter').addEventListener('change', filterRecipes);
    document.getElementById('timeFilter').addEventListener('change', filterRecipes);
    
    recipesGrid.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-btn')) {
            const id = parseInt(e.target.dataset.id, 10);
            if (confirm("Are you sure you want to delete this recipe?")) {
                recipesData = recipesData.filter(r => r.id !== id);
                localStorage.setItem('recipesData', JSON.stringify(recipesData));
                filterRecipes(); // Re-render
            }
        }
        if (e.target.classList.contains('edit-btn')) {
            const id = parseInt(e.target.dataset.id, 10);
            window.location.href = `upload.html?editId=${id}`;
        }
    });

    // Check if URL has a filter for "my recipes"
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('user') === 'me' && currentUser) {
        const myRecipes = recipesData.filter(r => r.user_id === currentUser.id);
        renderRecipes(myRecipes);
        document.querySelector('.hero-title').textContent = "My Recipes";
    } else {
        renderRecipes(); // Initial render of all recipes
    }
  }

  function showMyRecipes() {
    if (currentUser) {
        window.location.href = `index.html?user=me`;
    }
  }

  // ------------------
  // UPLOAD PAGE (upload.html)
  // ------------------
  if (document.getElementById('uploadPage')) {
    if (!currentUser) {
      document.getElementById('authRequiredPage').style.display = 'block';
    } else {
      document.getElementById('uploadPage').style.display = 'block';

      // Form logic here
      const recipeForm = document.getElementById('recipeForm');
      const urlParams = new URLSearchParams(window.location.search);
      const editId = urlParams.get('editId') ? parseInt(urlParams.get('editId'), 10) : null;
      
      // Populate form if we are editing
      if (editId) {
          const recipe = recipesData.find(r => r.id === editId);
          if (recipe) {
              document.querySelector('.upload-title').textContent = "Edit Your Recipe";
              document.getElementById('recipeTitle').value = recipe.title;
              document.getElementById('cuisineType').value = recipe.cuisine;
              document.getElementById('dietaryRestrictions').value = recipe.dietary;
              document.getElementById('difficulty').value = recipe.difficulty;
              document.getElementById('prepTime').value = Math.floor(recipe.time / 2); // Simple split
              document.getElementById('cookTime').value = Math.ceil(recipe.time / 2);
              document.getElementById('recipeDescription').value = recipe.description;
          }
      }
      
      recipeForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const title = document.getElementById('recipeTitle').value.trim();
          if (!title) {
              document.getElementById('errorMessage').textContent = "Title is required.";
              document.getElementById('errorMessage').style.display = "block";
              return;
          }

          const newData = {
              title,
              cuisine: document.getElementById('cuisineType').value,
              dietary: document.getElementById('dietaryRestrictions').value,
              difficulty: document.getElementById('difficulty').value,
              time: parseInt(document.getElementById('prepTime').value || 0) + parseInt(document.getElementById('cookTime').value || 0),
              description: document.getElementById('recipeDescription').value,
              imageUrl: "https://images.unsplash.com/photo-1495521821757-a1efb6729352?w=400&h=200&fit=crop", // Placeholder
              user_id: currentUser.id
          };

          if (editId !== null) {
              const idx = recipesData.findIndex(r => r.id === editId);
              recipesData[idx] = { id: editId, ...newData };
          } else {
              const newId = recipesData.length ? Math.max(...recipesData.map(r => r.id)) + 1 : 1;
              recipesData.push({ id: newId, ...newData });
          }
          
          localStorage.setItem('recipesData', JSON.stringify(recipesData));
          alert("Recipe saved successfully!");
          window.location.href = "index.html";
      });
      // (Simplified: Add ingredient/step logic would go here)
    }
  }


  // ------------------
  // LOGIN PAGE (login.html)
  // ------------------
  if (document.getElementById('loginForm')) {
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      const errorMessage = document.getElementById('loginErrorMessage');
      
      const user = userData.find(u => u.email === email && u.password === password);
      
      if (!user) {
        errorMessage.textContent = "Invalid email or password.";
        errorMessage.style.display = 'block';
        return;
      }
      
      currentUser = { ...user };
      delete currentUser.password;
      localStorage.setItem('currentUser', JSON.stringify(currentUser));
      
      alert("Login successful!");
      window.location.href = "index.html";
    });
  }

  // ------------------
  // REGISTER PAGE (register.html)
  // ------------------
  if (document.getElementById('registerForm')) {
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const name = document.getElementById('registerName').value.trim();
      const email = document.getElementById('registerEmail').value.trim();
      const password = document.getElementById('registerPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      const errorMessage = document.getElementById('registerErrorMessage');
      
      if (!name || !email || !password) {
        errorMessage.textContent = "All fields are required.";
        errorMessage.style.display = 'block';
        return;
      }
      if (password !== confirmPassword) {
        errorMessage.textContent = "Passwords do not match.";
        errorMessage.style.display = 'block';
        return;
      }
      if (userData.some(user => user.email === email)) {
        errorMessage.textContent = "Email already registered.";
        errorMessage.style.display = 'block';
        return;
      }

      const newUser = {
        id: userData.length ? Math.max(...userData.map(u => u.id)) + 1 : 1,
        name,
        email,
        password,
        role: "user"
      };

      userData.push(newUser);
      localStorage.setItem('userData', JSON.stringify(userData));
      
      currentUser = { ...newUser };
      delete currentUser.password;
      localStorage.setItem('currentUser', JSON.stringify(currentUser));
      
      alert("Registration successful! You are now logged in.");
      window.location.href = "index.html";
    });
  }
});