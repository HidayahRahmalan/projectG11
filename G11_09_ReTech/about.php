<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>About Us - ReTech Maintenance Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        .navbar {
            background-color: rgba(0, 102, 204, 0.9);
            /* ✅ Blue */
        }

        .navbar-brand {
            font-weight: 700;
        }

        .nav-link {
            color: #ffffff !important;
            font-weight: 500;
        }

        .profile-card {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            padding: 20px;
        }

        .profile-card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .profile-card img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 4px solid #007bff;
            transition: transform 0.3s ease-in-out;
        }

        .profile-card:hover img {
            transform: scale(1.05);
        }

        .resume-btn {
            margin-top: 10px;
        }

        header {
            background-color: #007bff;
            color: white;
            padding: 30px 0;
            text-align: center;
        }

        .team-photo {
            max-width: 30%;
            height: auto;
            border: 3px solid #007bff;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        footer {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark px-4">
        <a class="navbar-brand" href="#">ReTech</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="register.php">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <header>
        <h1>Meet the Team</h1>
        <p>Explore our featured projects and meet our wonderful team</p>
        <section class="text-center ">
            <img src="uploads/geng.jpeg" alt="Our Team" class="team-photo img-fluid rounded shadow">
        </section>
        <p>Together with our inspiring lecturer, Madam Hidayah</p>
        <p>the guiding light behind ReTech. A team fueled by passion, collaboration, and purpose✨</p>
    </header>



    <div class="container py-5">
        <div class="row justify-content-center g-4">

            <!-- Member 1 -->
            <div class="col-md-6 col-lg-5">
                <div class="profile-card text-center">
                    <img src="uploads/nabilah.jpg" alt="User 1">
                    <h5 class="mt-3">NABILAH NURIZZATI BINTI AZIZAM</h5>
                    <p class="text-muted mb-1">B032320065</p>
                    <p class="text-muted">System Developer</p>
                    <a href="uploads/nabilahResume.pdf" class="btn btn-outline-primary btn-sm resume-btn"
                        target="_blank">
                        📄 View Resume
                    </a>
                </div>
            </div>

            <!-- Member 2 -->
            <div class="col-md-6 col-lg-5">
                <div class="profile-card text-center">
                    <img src="uploads/sab.jpg" alt="User 2">
                    <h5 class="mt-3">NURUL SABRINA BINTI ZULKEPLI</h5>
                    <p class="text-muted mb-1">B032320087</p>
                    <p class="text-muted">Database Specialist</p>
                    <a href="uploads/sabResume.pdf" class="btn btn-outline-primary btn-sm resume-btn" target="_blank">
                        📄 View Resume
                    </a>
                </div>
            </div>

            <!-- Member 3 -->
            <div class="col-md-6 col-lg-5">
                <div class="profile-card text-center">
                    <img src="uploads/azim.jpg" alt="User 3">
                    <h5 class="mt-3">ABDUL AZIM BIN SULAIMAN</h5>
                    <p class="text-muted mb-1">B032320001</p>
                    <p class="text-muted">UI/UX Designer</p>
                    <a href="uploads/azimResume.pdf" class="btn btn-outline-primary btn-sm resume-btn" target="_blank">
                        📄 View Resume
                    </a>
                </div>
            </div>

            <!-- Member 4 -->
            <div class="col-md-6 col-lg-5">
                <div class="profile-card text-center">
                    <img src="uploads/foo.jpg" alt="User 4">
                    <h5 class="mt-3">FOO YIT POH</h5>
                    <p class="text-muted mb-1">B032210223</p>
                    <p class="text-muted">Multimedia Designer</p>
                    <a href="uploads/fooResume.pdf" class="btn btn-outline-primary btn-sm resume-btn" target="_blank">
                        📄 View Resume
                    </a>
                </div>
            </div>

        </div>
    </div>

    <footer>
        &copy; <?= date('Y') ?> ReTech Maintenance Reports. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>