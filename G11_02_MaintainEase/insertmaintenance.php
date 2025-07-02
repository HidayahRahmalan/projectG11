<?php
session_start();
include 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Submit Maintenance Request</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        :root {
            --color-bg: #ffffff;
            --color-text-body: #6b7280;
            --color-text-head: #111827;
            --color-shadow: rgba(0, 0, 0, 0.05);
            --color-button-bg: #111827;
            --color-button-bg-hover: #000000;
            --radius: 0.75rem;
            --transition: 0.3s ease;
            --max-width: 1200px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-body);
            font-size: 18px;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            position: sticky;
            top: 0;
            background: var(--color-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            box-shadow: 0 2px 6px var(--color-shadow);
            z-index: 10;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--color-text-head);
            user-select: none;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }

        nav a {
            color: var(--color-text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition);
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        nav a:hover,
        nav a:focus {
            color: var(--color-button-bg);
            outline: none;
            background-color: rgba(0, 0, 0, 0.05);
        }

    main {
      flex-grow: 1;
      max-width: var(--max-width);
      width: 100%;
      padding: 3rem 2rem 4rem;
      margin: 0 auto;
    }

    h1 {
      font-weight: 700;
      font-size: 3rem;
      color: var(--color-text-head);
      margin-bottom: 0.5rem;
      user-select: none;
      text-align: center;
    }

    p.subtitle {
      font-weight: 400;
      font-size: 1.125rem;
      max-width: 600px;
      margin-top: 0;
      margin-bottom: 3rem;
      text-align: center;
      margin-left: auto;
      margin-right: auto;
    }

    form.card {
      background-color: var(--color-bg);
      box-shadow: 0 4px 12px var(--color-shadow);
      border-radius: var(--radius);
      padding: 2.5rem 3rem;
      max-width: 700px;
      margin: 0 auto;
      display: grid;
      gap: 1.5rem;
      grid-template-columns: 1fr;
    }

    label {
      font-weight: 600;
      color: var(--color-text-head);
      display: block;
      margin-bottom: 0.4rem;
      user-select: none;
    }

    input[type="file"],
    input[type="text"],
    textarea,
    select {
      width: 100%;
      border: 1.5px solid #d1d5db;
      padding: 0.5rem 0.75rem;
      border-radius: 0.5rem;
      font-size: 1rem;
      font-family: inherit;
      background-color: #fafafa;
      color: var(--color-text-head);
      transition: border-color var(--transition), box-shadow var(--transition);
    }

    input[type="text"]:focus,
    textarea:focus,
    select:focus {
      border-color: var(--color-button-bg);
      outline: none;
      box-shadow: 0 0 8px var(--color-button-bg);
      background-color: #ffffff;
    }

    textarea {
      min-height: 120px;
      resize: vertical;
    }

    /* Responsive layout for larger screens */
    @media (min-width: 768px) {
      form.card {
        grid-template-columns: 1fr 1fr;
        gap: 2rem 3rem;
      }
      label[for="description"],
      textarea,
      label[for="status"],
      select[name="status"] {
        grid-column: 1 / -1;
      }
    }

    button.submit-btn {
      grid-column: 1 / -1;
      background-color: var(--color-button-bg);
      color: #fff;
      border: none;
      padding: 0.8rem 1.75rem;
      font-weight: 700;
      font-size: 1.125rem;
      border-radius: var(--radius);
      cursor: pointer;
      user-select: none;
      transition: background-color var(--transition), transform 0.2s ease;
      justify-self: start;
    }

    button.submit-btn:hover,
    button.submit-btn:focus {
      background-color: var(--color-button-bg-hover);
      outline: none;
      transform: scale(1.05);
    }

    /* Helper text style */
    small.helper-text {
      font-weight: 400;
      font-size: 0.9rem;
      color: var(--color-text-body);
      user-select: none;
    }
  </style>
</head>
<body>
  <header>
        <div class="logo" tabindex="0">MaintainEase</div>
        <nav aria-label="Primary navigation">
            <a href="home.php" class="active" aria-current="page">Home</a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'staff'): ?>
                    <a href="insertmaintenance.php">Submit Request</a>
                    <a href="request_list.php">My Requests</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Users</a>
                    <a href="viewquery.php">Maintenance List</a>
                <?php endif; ?>

                <a href="profile.php">Profile</a>
                <a href="logout.php">Sign Out</a>
            <?php else: ?>
                <a href="login.php">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <h1>Submit a Maintenance Request</h1>
        <p class="subtitle">Fill out the form below to describe the issue and provide the media file (image, video, or audio).</p>

        <form class="card" id="maintenanceForm" novalidate>
            <div>
                <label for="title">Title <span style="color:#ef4444">*</span></label>
                <input type="text" id="title" name="title" placeholder="E.g., Leaking faucet in kitchen" required minlength="5" maxlength="200" />
            </div>

            <div>
                <label for="category">Category <span style="color:#ef4444">*</span></label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Electrical">Electrical</option>
                    <option value="HVAC">HVAC</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label for="urgency">Urgency <span style="color:#ef4444">*</span></label>
                <select id="urgency" name="urgency" required>
                    <option value="">Select urgency</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>

            <div>
                <label for="location">Location <span style="color:#ef4444">*</span></label>
                <input type="text" id="location" name="location" placeholder="E.g., Building A, 3rd Floor, Room 301" required />
            </div>

            <div style="grid-column: 1 / -1;">
                <label for="description">Description <span style="color:#ef4444">*</span></label>
                <textarea id="description" name="description" placeholder="Detailed description of the issue..." rows="5" required></textarea>
            </div>

            <div style="grid-column: 1 / -1;">
                <label for="mediaFile">Upload Media File (Image, Video, or Audio) <span style="color:#ef4444">*</span></label>
                <input type="file" id="mediaFile" name="mediaFile" accept="image/*,video/*,audio/*" required />
                <small class="helper-text">Supported formats: Images, Videos, or Audio files</small>
            </div>

            <div style="grid-column: 1 / -1;">
                <label>Record Audio (Optional)</label>
                <button type="button" id="startRecordingBtn">Start Recording</button>
                <button type="button" id="stopRecordingBtn" disabled>Stop Recording</button>
                <audio id="audioPreview" controls style="display: none; margin-top: 10px;"></audio>
            </div>

            <div style="grid-column: 1 / -1;">
                <label for="transcription">Live Transcription (Optional)</label>
                <textarea id="transcription" name="transcription" rows="4" placeholder="Your speech will be transcribed here..." readonly></textarea>
            </div>


            <button type="submit" class="submit-btn">Submit Request</button>
        </form>
    </main>

    <script>
      const form = document.getElementById('maintenanceForm');
      let recordedAudioBlob = null;

      let mediaRecorder;
      let audioChunks = [];

      document.getElementById('startRecordingBtn').addEventListener('click', async () => {
          try {
              const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
              mediaRecorder = new MediaRecorder(stream);
              audioChunks = [];

              mediaRecorder.ondataavailable = event => audioChunks.push(event.data);

              mediaRecorder.onstop = () => {
                  recordedAudioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                  const audioUrl = URL.createObjectURL(recordedAudioBlob);
                  const audioPreview = document.getElementById('audioPreview');
                  audioPreview.src = audioUrl;
                  audioPreview.style.display = 'block';
              };

              mediaRecorder.start();
              document.getElementById('startRecordingBtn').disabled = true;
              document.getElementById('stopRecordingBtn').disabled = false;
          } catch (err) {
              alert('Microphone access denied or not supported.');
          }
      });

      document.getElementById('stopRecordingBtn').addEventListener('click', () => {
          mediaRecorder.stop();
          document.getElementById('startRecordingBtn').disabled = false;
          document.getElementById('stopRecordingBtn').disabled = true;
      });

      form.addEventListener('submit', async (e) => {
          e.preventDefault();

          const mediaFile = document.getElementById('mediaFile').files[0];

          if (!mediaFile) {
              alert('Please provide supporting media.');
              return;
          }

          if (form.title.value.trim().length < 5) {
              alert('Please provide a valid title with at least 5 characters.');
              form.title.focus();
              return;
          }

          const formData = new FormData();
          formData.append('user_id', <?= $_SESSION['user_id'] ?>);
          formData.append('title', form.title.value.trim());
          formData.append('category', form.category.value);
          formData.append('urgency', form.urgency.value);
          formData.append('location', form.location.value.trim());
          formData.append('description', form.description.value.trim());
          formData.append('status', 'Pending'); // Auto-set status
          formData.append('transcription', transcriptionBox.value.trim());

          formData.append('mediaFile', mediaFile);

          if (recordedAudioBlob) {
              formData.append('recordedAudio', recordedAudioBlob, 'recording.wav');
          }

          try {
              const response = await fetch('insertquery.php', {
                  method: 'POST',
                  body: formData
              });

              const result = await response.json();

              if (response.ok) {
                  alert(result.message);
                  form.reset();
                  document.getElementById('audioPreview').style.display = 'none';
              } else {
                  alert(result.error || 'Submission failed.');
              }
          } catch (error) {
              alert('Network error occurred.');
          }
      });

      let recognition;
        let isRecognizing = false;
        const transcriptionBox = document.getElementById('transcription');

        document.getElementById('startRecordingBtn').addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = event => audioChunks.push(event.data);

                mediaRecorder.onstop = () => {
                    recordedAudioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                    const audioUrl = URL.createObjectURL(recordedAudioBlob);
                    const audioPreview = document.getElementById('audioPreview');
                    audioPreview.src = audioUrl;
                    audioPreview.style.display = 'block';
                };

                mediaRecorder.start();
                document.getElementById('startRecordingBtn').disabled = true;
                document.getElementById('stopRecordingBtn').disabled = false;

                // Start speech recognition
                if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                    recognition = new SpeechRecognition();
                    recognition.lang = 'en-US';
                    recognition.interimResults = true;
                    recognition.continuous = true;

                    recognition.onresult = (event) => {
                        let transcript = '';
                        for (let i = event.resultIndex; i < event.results.length; ++i) {
                            transcript += event.results[i][0].transcript + ' ';
                        }
                        transcriptionBox.value = transcript;
                    };

                    recognition.onerror = (e) => {
                        console.error('Transcription error:', e.error);
                    };

                    recognition.start();
                    isRecognizing = true;
                } else {
                    alert('Speech recognition not supported in this browser.');
                }

            } catch (err) {
                alert('Microphone access denied or not supported.');
            }
        });

        document.getElementById('stopRecordingBtn').addEventListener('click', () => {
            mediaRecorder.stop();
            document.getElementById('startRecordingBtn').disabled = false;
            document.getElementById('stopRecordingBtn').disabled = true;

            // Stop recognition
            if (isRecognizing && recognition) {
                recognition.stop();
                isRecognizing = false;
            }
        });

  </script>


</body>
</html>
