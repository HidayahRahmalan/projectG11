<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>FacilityFix.FTMK</title>
    <link rel="stylesheet" href="..\assests\style.css">
</head>

<body>
    <div class="container-scroller-2">
        <!-- mavigation bar -->
        <nav class="navbar fixed-top">
            <div>
                <h1>FacilityFix.FTMK</h1>
            </div>
            <div>
                <ul>
                    <a href="..\logout.php">
                        <i class="text"></i>
                        Logout
                    </a>
                </ul>
            </div>
        </nav>

        <div class="page-body-wrapper">
            <!-- sidebar -->
            <nav class="sidebar">
                <div class="row">
                    <img class="profile-img" src="../uploads/profile picture.jpg" alt="Profile picture">
                </div>
                <h3>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></h3>
                <hr></br>
                <ul class="nav">
                    <!-- Home -->
                    <li class="nav-item">
                        <a class="nav-link" href="home.php">
                            <span>Home</span>
                        </a>
                    </li>

                    <!-- Report -->
                    <li class="nav-item">
                        <a class="nav-link active" href="report.php">
                            <span>Report</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- content -->
            <div class="main-panel">
                </br></br>
                <div class="row">
                    <div>
                        <h4 class="font-weight-bold">Make Report</h4>
                    </div>
                </div>

                <div class="row">
                    <!-- Form -->
                    <div class="card">
                        <form action="dbconnection/dbreport.php" method="POST" enctype="multipart/form-data" onsubmit="return confirmSubmit()">
                            <!-- Title -->
                            <div class="form-group">
                                <label for="Title">Title</label>
                                <input type="text" class="form-control" name="Title" id="Title" placeholder="Title" required>
                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label for="Description">Description</label>
                                <textarea class="form-control" name="Description" id="Description" placeholder="Type your description or use voice recording below" cols="50" rows="4"></textarea>
                            </div>
                            <div class="form-group">
                                <label></label>
                                <!-- Audio Recording Controls -->
                                <button type="button" onclick="startDictation()" class="btn">Speak</button>
                                <small id="speech-status" style="margin: 12px;"></small>
                            </div>

                            <!-- Location -->
                            <div class="form-group">
                                <label for="Location">Location</label>
                                <select class="form-control" name="Location" id="Category" required>
                                    <option value="" disabled selected>Location</option>
                                    <option value="Lobby">Lobby</option>
                                    <option value="Staff Room">Staff Room</option>
                                    <option value="Lab 1">Lab 1</option>
                                    <option value="Lab 2">Lab 2</option>
                                    <option value="Lab 3">Lab 3</option>
                                    <option value="Toilet 1">Toilet 1</option>
                                    <option value="Toilet 2">Toilet 2</option>
                                    <option value="Toilet 3">Toilet 3</option>
                                </select>
                            </div>

                            <!-- Date -->
                            <div class="form-group">
                                <label for="Date">Date</label>
                                <input type="date" class="form-control" name="Date" id="Date" required>
                            </div>

                            <!-- Proof -->
                            <div class="form-group">
                                <label for="Proof">Proof</label>
                                <input type="file" class="form-control" name="Proof[]" id="Proof" accept="image/*,video/*" multiple required>
                            </div>

                            <!-- Category -->
                            <div class="form-group">
                                <label for="Category">Category</label>
                                <select class="form-control" name="Category" id="Category" required>
                                    <option value="" disabled selected>Category</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Facility">Facility</option>
                                    <option value="Safety">Safety</option>
                                    <option value="Cleaning">Cleaning</option>
                                    <option value="Landscaping">Landscaping</option>
                                    <option value="IT">IT Support</option>
                                    <option value="Plumbing">Plumbing</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="HVAC">HVAC</option>
                                    <option value="Grounds">Grounds Maintenance</option>
                                    <option value="Security">Security</option>
                                    <option value="Pest Control">Pest Control</option>
                                </select>
                            </div>

                            <!-- Urgency level -->
                            <div class="form-group">
                                <label for="UrgencyLevel">Urgency Level</label>
                                <select class="form-control" name="UrgencyLevel" id="UrgencyLevel" required>
                                    <option value="" disabled selected>Urgency Level</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>

                            <?php
                            // Success message
                            if (isset($_SESSION['status'])) {
                            ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo $_SESSION['status']; ?>
                                </div>
                            <?php
                                unset($_SESSION['status']);
                            }

                            // Error message
                            if (isset($_SESSION['EmailMessage'])) {
                            ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $_SESSION['EmailMessage']; ?>
                                </div>
                            <?php
                                unset($_SESSION['EmailMessage']);
                            }
                            ?>
                            </br>

                            <div class="row" style="margin-bottom: 0;">
                                <button type="submit" class="btn" style="margin-right: 15px;">Submit</button>
                                <input type="reset" class="btn btn-secondary" value="Reset">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function startDictation() {
            const status = document.getElementById("speech-status");
            if (!('webkitSpeechRecognition' in window)) {
                alert("Speech Recognition not supported in this browser.");
                return;
            }

            const recognition = new webkitSpeechRecognition(); // Works in Chrome
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = "ms-MY";

            status.innerText = "Listening...";

            recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                document.getElementById("Description").value += (document.getElementById("Description").value ? " " : "") + transcript;
                status.innerText = "Captured!";
            };

            recognition.onerror = function(event) {
                status.innerText = "Error or blocked.";
            };

            recognition.onend = function() {
                setTimeout(() => status.innerText = "", 2000);
            };

            recognition.start();
        }

        function confirmSubmit() {
            return confirm('Are you sure you want to submit this report?');
        }
    </script>
</body>

</html>