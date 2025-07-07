<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>FacilityFix.FTMK</title>
  <link rel="stylesheet" href="assests\style.css">
</head>

<body class="body-login">
  <div class="container-scroller">
    <div class="form">
      <h4>Sign In</h4>
      <form method="POST" action="dblogin.php">
        <!-- Role -->
        <div class="form-group">
          <select class="form-control" name="Role" id="Role">
            <option value="" disabled selected>Role</option>
            <option value="Staff">Staff</option>
            <option value="Admin">Admin</option>
          </select>
        </div>

        <!-- Email -->
        <div class="form-group">
          <input type="email" class="form-control" style="width: 300px;" name="Email" id="Email" placeholder="Email">
        </div>

        <!-- Password -->
        <div class="form-group">
          <input type="password" class="form-control" name="Password" id="Password" placeholder="Password">
        </div>

        <!-- Login error message -->
        <?php
        session_start();
        if (isset($_SESSION['login_error'])) {
          echo '<div class="alert" role="alert">' . $_SESSION['login_error'] . '</div>';
          unset($_SESSION['login_error']);
        }
        ?>

        <!-- Log in button -->
        <div>
          <button class="btn" type="submit">Sign In</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>