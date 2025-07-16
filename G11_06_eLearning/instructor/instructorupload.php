<?php
// This line checks if an instructor is logged in.
// It uses the same guard file as your dashboard.
require_once '../backend/auth_instructor.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Instructor Module Upload - Refined</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    /* Your CSS is perfect, no changes needed */
    :root {
      --primary-color: #ea4c89;
      --primary-hover: #d13f76;
      --secondary-color: #4b05fd;
      --success-color: #28a745;
      --error-color: #dc3545;
      --light-gray: #f5f7fa;
      --border-color: #e0e0e0;
      --text-color: #1e1e1e;
      --text-light: #555;
    }
    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--light-gray);
      margin: 0;
      padding: 0 40px 40px;
      color: var(--text-color);
      line-height: 1.6;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 0;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 30px;
    }
    .logo { font-size: 24px; font-weight: 700; color: var(--primary-color); }
    nav ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; }
    nav a { text-decoration: none; color: #333; font-size: 16px; padding: 6px 12px; border-radius: 6px; transition: background-color 0.3s ease, color 0.3s ease; }
    nav a:hover, nav a.active { background-color: var(--primary-color); color: white; }
    h1 { text-align: center; margin: 40px 0 30px; color: var(--primary-color); font-weight: 700; }
    form { background: white; max-width: 700px; margin: 0 auto 40px auto; padding: 40px; border-radius: 16px; box-shadow: 0 4px 25px rgba(0,0,0,0.07); }
    label { font-weight: 600; display: block; margin-bottom: 8px; }
    input[type="text"] { width: 100%; padding: 12px; margin-bottom: 20px; font-size: 16px; border-radius: 8px; border: 1px solid var(--border-color); transition: border-color 0.3s, box-shadow 0.3s; }
    input[type="text"]:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(234, 76, 137, 0.2); }
    .upload-section { margin-bottom: 25px; }
    .drop-zone { border: 2px dashed var(--border-color); border-radius: 12px; padding: 25px; text-align: center; color: var(--text-light); background-color: var(--light-gray); transition: background-color 0.3s, border-color 0.3s; cursor: pointer; }
    .drop-zone.drag-over { border-color: var(--primary-color); background-color: rgba(234, 76, 137, 0.05); }
    .drop-zone p { margin: 0; font-size: 16px; }
    .drop-zone span { font-weight: 600; color: var(--primary-color); }
    .file-review-area { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
    .file-pill { display: flex; align-items: center; background-color: #e9ecef; border-radius: 16px; padding: 6px 12px; font-size: 14px; font-weight: 500; animation: fadeIn 0.3s ease; }
    .file-pill .delete-btn { margin-left: 10px; background: none; border: none; color: var(--text-light); cursor: pointer; font-size: 16px; padding: 0; }
    @keyframes fadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    .submit-buttons { display: flex; gap: 10px; margin-top: 20px; }
    button { flex: 1; background-color: var(--primary-color); color: white; padding: 14px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; transition: background-color 0.3s, opacity 0.3s; }
    button:hover:not(:disabled) { background-color: var(--primary-hover); }
    button:disabled { cursor: not-allowed; opacity: 0.6; }
    .draft-btn { background-color: var(--secondary-color); }
    .draft-btn:hover:not(:disabled) { background-color: #3c04ca; }
    #status-container { margin-top: 20px; }
    .progress-container { width: 100%; background-color: #e9ecef; border-radius: 5px; display: none; }
    .progress-bar { width: 0%; height: 10px; background-color: var(--success-color); border-radius: 5px; transition: width 0.4s ease; }
    #status-message { margin-top: 10px; font-weight: 600; text-align: center; }
    #status-message.success { color: var(--success-color); }
    #status-message.error { color: var(--error-color); }
    @media (max-width: 600px) { body { padding: 20px; } form { padding: 20px; } }
  </style>
</head>
<body>
  <header>
    <div class="logo">E-Learning</div>
    <nav>
      <ul>
        <!-- I updated these links to point to the .php files -->
        <li><a href="instructorhomes.php">Dashboard</a></li>
        <li><a href="instructorupload.php" class="active">Upload</a></li>
        <li><a href="instructormanage.php">Manage</a></li>
        <li><a href="../Homepage/Home.html" onclick="confirmLogout(event)">Logout</a></li>
      </ul>
    </nav>
  </header>
  <h1>Create a New Module</h1>

  <form id="uploadForm" action="../backend/module_upload_with_video.php" method="POST" enctype="multipart/form-data" novalidate>
    <!-- The form is unchanged -->
    <label for="title">Module Title</label>
    <input type="text" id="title" name="title" required placeholder="e.g., Introduction to Calculus" />
    <label for="description">Description</label>
    <input type="text" id="description" name="description" required placeholder="A short summary of the module's content" />
    <label for="topic">Topic</label>
    <input type="text" id="topic" name="topic" required placeholder="e.g., Limits and Derivatives" />
    <label for="topicdescription">Topic Description</label>
    <input type="text" id="topicdescription" name="topicdescription" required placeholder="What this specific topic covers" />

    <div class="upload-section">
      <label>Video File (Optional)</label>
      <div class="drop-zone" id="video_drop_zone" data-file-type="video"><p>Drag & drop a video file here, or <span>click to browse</span>.</p></div>
      <div class="file-review-area" id="video_review_area"></div>
    </div>
    <div class="upload-section">
      <label>PowerPoint Slides (Optional)</label>
      <div class="drop-zone" id="slides_drop_zone" data-file-type="slide"><p>Drag & drop slide files here, or <span>click to browse</span>.</p></div>
      <div class="file-review-area" id="slide_review_area"></div>
    </div>
    <div class="upload-section">
      <label>PDF Notes (Optional)</label>
      <div class="drop-zone" id="notes_drop_zone" data-file-type="note"><p>Drag & drop PDF files here, or <span>click to browse</span>.</p></div>
      <div class="file-review-area" id="note_review_area"></div>
    </div>

    <div id="status-container">
      <div class="progress-container"><div class="progress-bar"></div></div>
      <div id="status-message"></div>
    </div>

    <div class="submit-buttons">
      <button type="submit" id="publishBtn" name="action" value="publish">Upload Module</button>
      <button type="submit" id="draftBtn" name="action" value="draft" class="draft-btn">Save as Draft</button>
    </div>
  </form>

  <script>
    // Your JavaScript is perfect, no changes needed here.
    document.addEventListener('DOMContentLoaded', () => {
      const ui = { form: document.getElementById('uploadForm'), dropZones: document.querySelectorAll('.drop-zone'), progressContainer: document.querySelector('.progress-container'), progressBar: document.querySelector('.progress-bar'), statusMessage: document.getElementById('status-message'), publishBtn: document.getElementById('publishBtn'), draftBtn: document.getElementById('draftBtn') };
      let stagedFiles = { video: [], slide: [], note: [] };

      const renderReviewArea = (fileType) => {
        const reviewArea = document.getElementById(`${fileType}_review_area`);
        reviewArea.innerHTML = '';
        stagedFiles[fileType].forEach(file => {
          const pill = document.createElement('div');
          pill.className = 'file-pill';
          pill.innerHTML = `<span>${file.name}</span><button type="button" class="delete-btn" data-file-type="${fileType}" data-file-name="${file.name}">×</button>`;
          reviewArea.appendChild(pill);
        });
      };

      const handleFileSelection = (files, fileType) => {
        const newFiles = Array.from(files);
        if (fileType === 'video') { stagedFiles.video = newFiles.slice(0, 1); } else { stagedFiles[fileType].push(...newFiles); }
        renderReviewArea(fileType);
      };

      const resetForm = () => {
        ui.form.reset();
        stagedFiles = { video: [], slide: [], note: [] };
        ['video', 'slide', 'note'].forEach(renderReviewArea);
        ui.progressContainer.style.display = 'none';
        ui.progressBar.style.width = '0%';
        setTimeout(() => { ui.statusMessage.textContent = ''; ui.statusMessage.className = ''; }, 3000);
      };
      
      const showStatusMessage = (message, type = 'info') => { ui.statusMessage.textContent = message; ui.statusMessage.className = type; };
      const toggleButtons = (disabled) => { ui.publishBtn.disabled = disabled; ui.draftBtn.disabled = disabled; };

      ui.dropZones.forEach(zone => {
        const fileType = zone.dataset.fileType;
        const input = document.createElement('input');
        input.type = 'file';
        input.style.display = 'none';
        if (fileType === 'video') { input.accept = 'video/*'; }
        if (fileType === 'slide') { input.accept = '.ppt,.pptx'; input.multiple = true; }
        if (fileType === 'note') { input.accept = '.pdf'; input.multiple = true; }
        zone.after(input);
        
        zone.addEventListener('click', () => input.click());
        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', (e) => { e.preventDefault(); zone.classList.remove('drag-over'); handleFileSelection(e.dataTransfer.files, fileType); });
        input.addEventListener('change', (e) => handleFileSelection(e.target.files, fileType));
      });

      document.addEventListener('click', (e) => {
        if (e.target.classList.contains('delete-btn')) {
          const { fileType, fileName } = e.target.dataset;
          stagedFiles[fileType] = stagedFiles[fileType].filter(file => file.name !== fileName);
          renderReviewArea(fileType);
        }
      });

      ui.form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('title', document.getElementById('title').value);
        formData.append('description', document.getElementById('description').value);
        formData.append('topic', document.getElementById('topic').value);
        formData.append('topicdescription', document.getElementById('topicdescription').value);
        formData.append('action', e.submitter.value);
        
        for (const type in stagedFiles) {
           stagedFiles[type].forEach(file => {
             const inputName = (type === 'video') ? 'video_file' : `${type}_file[]`;
             formData.append(inputName, file);
           });
        }
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', ui.form.getAttribute('action'), true);
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) { const percent = Math.round((e.loaded / e.total) * 100); ui.progressContainer.style.display = 'block'; ui.progressBar.style.width = `${percent}%`; }
        });
        xhr.onload = () => {
          toggleButtons(false);
          if (xhr.status >= 200 && xhr.status < 300) {
            try {
              const response = JSON.parse(xhr.responseText);
              if (response.success) { 
                showStatusMessage('Module uploaded successfully!', 'success'); 
                setTimeout(resetForm, 2000); 
              } else { 
                showStatusMessage(`Upload failed: ${response.message}`, 'error'); 
              }
            } catch (err) { 
              showStatusMessage('Received an invalid response from the server.', 'error'); 
              console.error("Invalid JSON response:", xhr.responseText);
            }
          } else { 
            showStatusMessage(`Server error: ${xhr.status} ${xhr.statusText}`, 'error'); 
          }
        };
        xhr.onerror = () => { toggleButtons(false); showStatusMessage('A network error occurred. Please try again.', 'error'); };
        toggleButtons(true);
        showStatusMessage('Uploading...');
        xhr.send(formData);
      });
    });

function confirmLogout(event) {
  event.preventDefault(); // Prevent immediate navigation
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "../Homepage/Home.html"; // Change this to your actual logout destination
  }
}
  
  </script>
</body>
</html>