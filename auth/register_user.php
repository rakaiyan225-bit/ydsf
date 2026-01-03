<?php
require_once __DIR__ . '/../inc/functions.php';

$err = '';
$msg = '';

// Inisialisasi supaya nilai tetap ada di form saat error
$nama = $alamat = $telp = $username = $target_qurban = '';
$target_nominal = 0;
$username_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = $_POST['nama'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $telp   = $_POST['telp'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $target_qurban = $_POST['target_qurban'] ?? '';
    $target_nominal = $_POST['target_nominal'] ?? 0;
    $tanggal_lunas = $_POST['tanggal_lunas'] ?? '';

    if ($nama && $alamat && $telp && $username && $password && $target_qurban) {
        global $pdo;
        // cek apakah username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM savers WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $err = "⚠️ Username sudah terpakai. Silakan gunakan yang lain.";
            $username_error = true;
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO savers 
                (nama, alamat, telp, username, password, target_qurban, target_nominal, tanggal_lunas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$nama, $alamat, $telp, $username, $hash, $target_qurban, $target_nominal, $tanggal_lunas])) {
                $msg = "✅ Registrasi berhasil! Silakan login.";
                // reset form setelah sukses
                $nama = $alamat = $telp = $username = $target_qurban = '';
                $target_nominal = 0;
            } else {
                $err = "❌ Terjadi kesalahan saat menyimpan data.";
            }
        }
    } else {
        $err = "⚠️ Harap lengkapi semua field.";
    }
}

include __DIR__ . '/../inc/header.php';
?>

<link rel="stylesheet" href="/tabungan_qurban/assets/css/register.css">

<style>
.form-control.error {
  border-color: #dc3545;
  background-color: #fff5f5;
}
</style>

<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="card-title mb-3">Registrasi Penabung</h4>

        <?php if ($err): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
          <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($nama) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Alamat</label>
            <textarea name="alamat" class="form-control" required><?= htmlspecialchars($alamat) ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">No. Telepon</label>
            <input type="text" name="telp" class="form-control" value="<?= htmlspecialchars($telp) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" 
                   class="form-control <?= $username_error ? 'error' : '' ?>" 
                   value="<?= htmlspecialchars($username) ?>" required>

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
    </div>
</div>


</div>

          <div class="mb-3">
            <label class="form-label">Target Tabungan Qurban</label>
            <select name="target_qurban" id="target_qurban" class="form-select" required>
              <option value="">-- Pilih Hewan Qurban --</option>
              <option value="Sapi" data-nominal="20000000" <?= $target_qurban == 'Sapi' ? 'selected' : '' ?>>Sapi - Rp 20.000.000</option>
              <option value="Domba" data-nominal="5000000" <?= $target_qurban == 'Domba' ? 'selected' : '' ?>>Domba - Rp 5.000.000</option>
              <option value="Kambing" data-nominal="3000000" <?= $target_qurban == 'Kambing' ? 'selected' : '' ?>>Kambing - Rp 3.000.000</option>
            </select>
            <input type="hidden" name="target_nominal" id="target_nominal" value="<?= htmlspecialchars($target_nominal) ?>">
          </div>

          <div class="mb-3">
  <label for="tanggal_lunas" class="form-label">Tanggal Target Pelunasan</label>
  <input type="date" name="tanggal_lunas" id="tanggal_lunas" class="form-control" required>
</div>

          <button type="submit" class="btn btn-primary">Daftar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('target_qurban').addEventListener('change', function() {
  let nominal = this.options[this.selectedIndex].getAttribute('data-nominal');
  document.getElementById('target_nominal').value = nominal || 0;
});
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
