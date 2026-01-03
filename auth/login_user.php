<?php
require_once __DIR__ . '/../inc/functions.php';

$err = '';
$msg = '';

// jika user sudah login, arahkan ke halaman user dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /tabungan_qurban/pages/user_dashboard.php');
    exit;
}

// pesan setelah logout
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $msg = 'Anda berhasil logout.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';

    // cek login user
    $stmt = $pdo->prepare("SELECT * FROM savers WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();

    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nama'];
        header('Location: /tabungan_qurban/pages/user_dashboard.php');
        exit;
    } else {
        $err = 'Username atau password salah.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login Donatur - Tabungan Qurban</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="/tabungan_qurban/assets/css/login_user.css">
</head>
<body class="bg-light">

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="login-header">
                <img src="/tabungan_qurban/assets/images/YDSF.png" alt="Logo Sistem" class="gambar">
            </div>
          <h4 class="card-title mb-3 text-center">Login Donatur</h4>

          <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>

          <?php if ($err): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required>
            </div>
             <div class="mb-3">
    <label class="form-label fw-semibold">Password</label>

    <div class="input-password-wrapper">
        <input type="password" name="password" id="password" 
               class="form-control" placeholder="Masukkan password" required>

        <span class="password-toggle-icon" onclick="togglePassword()">

            <!-- Ikon Sapi Normal -->
            <span id="iconNormal">
            <svg id="cowNormal" width="32" viewBox="0 0 64 64">
              <circle cx="32" cy="32" r="22" fill="#fff7ec" stroke="#4a3728" stroke-width="3"/>
              <ellipse cx="15" cy="25" rx="7" ry="10" fill="#ffe6d5" stroke="#4a3728" stroke-width="3"/>
              <ellipse cx="49" cy="25" rx="7" ry="10" fill="#ffe6d5" stroke="#4a3728" stroke-width="3"/>
              <circle cx="24" cy="32" r="4" fill="#000"/>
              <circle cx="40" cy="32" r="4" fill="#000"/>
              <ellipse cx="32" cy="42" rx="10" ry="6" fill="#ffcabd" stroke="#4a3728" stroke-width="3" />
              <circle cx="28" cy="42" r="2" fill="#4a3728"/>
              <circle cx="36" cy="42" r="2" fill="#4a3728"/>
            </svg>
            </span>
            <!-- Ikon Sapi Kaget -->
            <span id="iconShock" style="display:none;">
            <svg id="cowShock" width="32" viewBox="0 0 64 64">
              <circle cx="32" cy="32" r="22" fill="#fff7ec" stroke="#4a3728" stroke-width="3"/>
              <ellipse cx="15" cy="25" rx="7" ry="10" fill="#ffe6d5" stroke="#4a3728" stroke-width="3"/>
              <ellipse cx="49" cy="25" rx="7" ry="10" fill="#ffe6d5" stroke="#4a3728" stroke-width="3"/>
              <circle cx="24" cy="30" r="6" fill="#ffffff" stroke="#000" stroke-width="2"/>
              <circle cx="40" cy="30" r="6" fill="#ffffff" stroke="#000" stroke-width="2"/>
              <circle cx="24" cy="30" r="3" fill="#000"/>
              <circle cx="40" cy="30" r="3" fill="#000"/>
              <ellipse cx="32" cy="44" rx="5" ry="7" fill="#ff7676" stroke="#4a3728" stroke-width="3"/>
            </svg>
            </span>

        </span>
        <br>
    </div>
            <button class="btn btn-success w-100">Login</button>
          </form>

          <div class="mt-3 text-center">
            <a href="/tabungan_qurban/auth/register_user.php">Belum punya akun? Registrasi disini</a>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
<script>
  function togglePassword() {
    const field  = document.getElementById("password");
    const normal = document.getElementById("iconNormal");
    const shock  = document.getElementById("iconShock");

    if (field.type === "password") {
        field.type = "text";
        normal.style.display = "none";
        shock.style.display  = "block";
    } else {
        field.type = "password";
        shock.style.display  = "none";
        normal.style.display = "block";
    }
}
</script>
</body>
</html>
