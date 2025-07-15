<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>ReTech Maintenance Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: url('image.png') no-repeat center center fixed;
            background-size: cover;
            color: #ffffff;
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

        .hero {
            height: 100vh;
            background:
                linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
                url('uploads/image.png') no-repeat center center;
            background-size: cover;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
        }


        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.3rem;
            margin-top: 10px;
        }

        .btn-primary {
            background-color: #0066cc;
            border: none;
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 30px;
            transition: 0.3s;
        }

        .btn-primary:hover {
            background-color: #004a99;
        }

        footer {
            background-color: rgba(0, 102, 204, 0.9);
            padding: 15px;
            text-align: center;
            color: #eee;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }
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

    <div class="hero text-white">
        <div>
            <h1>Welcome to ReTech Maintenance Reports</h1>
            <p>Streamlined facility reporting, simplified.</p>
            <a href="login.php" class="btn btn-primary mt-4">Get Started</a>
        </div>
    </div>

    <section id="about" class="py-5" style="background-color: rgba(255,255,255,0.95); color: #333;">
        <div class="container text-center">
            <h2 class="mb-3">About ReTech</h2>
            <p class="lead">
                ReTech Maintenance Reports helps organizations manage and streamline facility issue reporting with ease.
                From leaking pipes to faulty lights, our system ensures problems are logged, tracked, and resolved
                efficiently.
            </p>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y') ?> ReTech Maintenance Reports. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>