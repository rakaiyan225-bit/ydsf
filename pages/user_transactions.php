<?php
require_once __DIR__ . '/../inc/functions.php';

// cek login user
if (!isset($_SESSION['user_id'])) {
    header('Location: /tabungan_qurban/auth/login_user.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'setor'; // setor / tarik

// Ambil target_nominal dari tabel savers (sesuai permintaan)
$stmt = $pdo->prepare("SELECT target_nominal FROM savers WHERE id = ?");
$stmt->execute([$user_id]);
$saverData = $stmt->fetch();
$target_nominal = (int) ($saverData['target_nominal'] ?? 0);

// Hitung total setoran user (transactions.amount) dan sisa target
$stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM transactions WHERE saver_id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch();
$total_sekarang = (int) ($row['total'] ?? 0);

$sisa = max($target_nominal - $total_sekarang, 0);

// lokasi upload
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

// error / success messages
$errors = [];
$success = null;
$warning = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ambil jumlah: bisa mengandung titik, jadi bersihkan
    $raw_jumlah = $_POST['jumlah'] ?? '0';
    // hapus semua non digit
    $clean = preg_replace('/[^\d]/', '', $raw_jumlah);
    $jumlah = (int)$clean;

    // payment method
    $payment_method = trim($_POST['payment_method'] ?? '');

    // bukti
    $bukti_name = null;

    // Validasi wajib: jumlah, payment_method, bukti
    if ($jumlah <= 0) {
        $errors[] = "Nominal harus diisi dan lebih besar dari 0.";
    }

    if ($payment_method === '') {
        $errors[] = "Pilih metode pembayaran terlebih dahulu.";
    }

    if (empty($_FILES['bukti']['name']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload bukti transfer wajib (JPG/PNG/PDF, max 5MB).";
    }

    // cek jika melebihi sisa -> tolak di server (atau otomatis set ke sisa jika ingin)
    if ($sisa > 0 && $jumlah > $sisa) {
        $errors[] = "Nominal melebihi sisa target. Maksimal yang bisa disetor: Rp " . number_format($sisa,0,',','.');
    } elseif ($sisa === 0) {
        $errors[] = "Target sudah terpenuhi, tidak bisa melakukan setor lagi.";
    }

    // proses upload kalau tidak ada error sampai sekarang
    if (empty($errors)) {
        $allowed = ['image/jpeg','image/png','application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['bukti']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed) || $_FILES['bukti']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Upload gagal! Hanya file JPG, PNG, atau PDF maksimal 5MB yang diperbolehkan.";
        } else {
            $ext = pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION);
            $safe_ext = $ext ?: ($mime === 'application/pdf' ? 'pdf' : 'jpg');
            $newName = time() . '_' . uniqid() . '.' . $safe_ext;
            if (move_uploaded_file($_FILES['bukti']['tmp_name'], $upload_dir . $newName)) {
                $bukti_name = $newName;
            } else {
                $errors[] = "Gagal memindahkan file bukti.";
            }
        }
    }

    if (empty($errors)) {
        // note: batasi 100 kata
        $note = trim($_POST['note'] ?? '');
        $words = preg_split('/\s+/', $note);
        if (count($words) > 100) $note = implode(' ', array_slice($words, 0, 100));

        // insert transaksi
        $stmt = $pdo->prepare("INSERT INTO transactions (saver_id, jenis, amount, note, payment_method, created_at, receipt, bukti_transfer, status)
                               VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'unpaid')");
        // note: field names match DB? we add payment_method and bukti_transfer to columns used in your DB listing.
        $stmt->execute([$user_id, $type, $jumlah, $note, $payment_method, $bukti_name, $bukti_name]);

        $_SESSION['success'] = "Transaksi berhasil disimpan, bukti telah dikirim ke admin.";
        header('Location: /tabungan_qurban/pages/user_dashboard.php');
        exit;
    } else {
        // kirim errors ke session agar tampilkan setelah reload
        $_SESSION['form_errors'] = $errors;
        // jangan redirect, biarkan render page sehingga user bisa perbaiki
    }
}

include __DIR__ . '/../inc/header.php';
?>

<?php
// tampilkan pesan dari session jika ada
if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $_SESSION['success']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php unset($_SESSION['success']); endif; ?>

<?php
// tampilkan errors dari validasi server
$server_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);
if (!empty($server_errors)): ?>
  <div class="alert alert-danger">
    <ul style="margin:0;">
      <?php foreach ($server_errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3"><?= ucfirst($type) ?> Tabungan</h5>

        <form method="post" enctype="multipart/form-data" id="txForm" novalidate>
          <!-- Pilih nominal -->
          <div class="mb-3 text-center">
            <label class="form-label fw-semibold">Pilih Jumlah Setoran</label>
            <div class="d-flex flex-wrap justify-content-center gap-2" id="nominalButtons">
              <button type="button" class="btn btn-outline-primary nominal-btn" data-value="50000">Rp 50.000</button>
              <button type="button" class="btn btn-outline-primary nominal-btn" data-value="100000">Rp 100.000</button>
              <button type="button" class="btn btn-outline-primary nominal-btn" data-value="200000">Rp 200.000</button>
              <button type="button" class="btn btn-outline-primary nominal-btn" data-value="300000">Rp 300.000</button>
              <button type="button" class="btn btn-outline-primary nominal-btn" data-value="500000">Rp 500.000</button>
              <button type="button" class="btn btn-outline-primary nominal-btn" data-value="1000000">Rp 1.000.000</button>
              <button type="button" class="btn btn-outline-secondary" id="btnLainnya">Nominal Lainnya</button>
            </div>

            <input type="hidden" name="jumlah" id="jumlah" value="0">

            <div class="mt-3">
              <span id="selectedNominal" class="fw-bold text-success fs-5">Belum ada nominal dipilih</span>
            </div>
          </div>

          <!-- Modal nominal lain -->
          <div class="modal fade" id="modalNominalLain" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title">Masukkan Nominal Lain</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                  <div class="nominal-wrapper">
                    <label class="form-label">Nominal Lainnya</label>
                    <div class="d-flex justify-content-center gap-2 align-items-center mt-2">
                      <button type="button" class="btn btn-outline-secondary" id="btnDown">−</button>
                      <input type="text" id="inputNominalLain" class="form-control text-center" placeholder="0" style="max-width:220px;" inputmode="numeric" autocomplete="off">
                      <button type="button" class="btn btn-outline-secondary" id="btnUp">+</button>
                    </div>
                    <div class="form-text mt-2" id="hintLain"></div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-primary" id="simpanNominalLain" data-bs-dismiss="modal">Simpan</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pilih metode pembayaran -->
          <div class="container mt-3 text-center">
            <button type="button" class="card shadow-sm p-3 border-0 w-100" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#paymentModal">
              <div class="d-flex justify-content-center align-items-center">
                <img src="/tabungan_qurban/assets/images/Metode.png" width="120" class="me-3">
                <h6 class="mb-0">Pilih Metode Pembayaran</h6>
              </div>
            </button>
          </div>

          <!-- Tampilkan Metode Terpilih -->
          <div id="selectedMethod" class="text-center mt-3" style="display:none;">
    <div class="d-inline-flex align-items-center bg-light rounded-pill px-3 py-2 shadow-sm">
        <img id="selectedLogo" src="" width="35" class="me-2">
        <span id="selectedName" class="fw-semibold text-primary"></span>
    </div>
</div>


          <!-- Hidden input untuk dikirim ke PHP -->
          <input type="hidden" name="payment_method" id="payment_method">

          <!-- Modal Metode Pembayaran -->
          <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
              <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header text-white" style="background:linear-gradient(90deg,#009272,#00b894)">
                  <h5 class="modal-title">Pilih Metode Pembayaran</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div id="step-kategori">
                    <div class="list-group">
                      <a href="#" class="list-group-item list-group-item-action" onclick="showList('bank')">🏦 Transfer Bank / M-Banking</a>
                      <a href="#" class="list-group-item list-group-item-action" onclick="showList('ewallet')">💸 E-Wallet (GoPay, OVO, Dana, ShopeePay)</a>
                      <a href="#" class="list-group-item list-group-item-action" onclick="showQRIS()">🔳 QRIS</a>
                    </div>
                  </div>

                  <div id="step-list" style="display:none;">
                    <button class="btn btn-sm btn-outline-secondary mb-3" onclick="backToKategori()">⬅ Kembali</button>
                    <h6 id="listTitle" class="fw-bold mb-3"></h6>
                    <div class="list-group" id="listContainer"></div>
                  </div>

                  <div id="step-detail" style="display:none;">
                    <button class="btn btn-sm btn-outline-secondary mb-3" onclick="backToList()">⬅ Kembali</button>
                    <div class="text-center">
                      <img id="detailImg" src="" width="60" class="mb-2">
                      <h5 id="detailName"></h5>
                      <p class="mt-3">Nomor Rekening / E-Wallet:</p>
                      <h5 class="fw-bold" id="detailNumber"></h5>
                      <p>Atas Nama: <strong id="detailOwner">YDSF</strong></p>

                      <div class="mt-3 d-flex justify-content-center gap-2">
                        <button class="btn btn-success" type="button" onclick="copyRekening()">Salin Nomor</button>
                        <button class="btn btn-primary" type="button" onclick="selesaiPilih()">Oke</button>
                      </div>
                    </div>
                  </div>

                  <div id="step-qris" style="display:none;">
                    <button class="btn btn-sm btn-outline-secondary mb-3" onclick="backToKategori()">⬅ Kembali</button>
                    <div class="text-center">
                      <h5>Scan QRIS Berikut</h5>
                      <img src="/tabungan_qurban/assets/images/payments/qris.jpg" width="250" class="rounded shadow-sm my-3">
                      <p>Atas Nama: <strong>Yayasan Dana Sosial Al Falah</strong></p>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
          <!-- Upload Bukti -->
          <div class="mb-3 mt-4">
            <label class="form-label">Upload Bukti Transfer Setor Tabung (JPG/PNG/PDF, max 5MB) <span class="text-danger">*</span></label>
            <input type="file" name="bukti" id="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
          </div>
          <!-- Catatan -->
          <div class="mb-3">
            <label class="form-label">Pesan / Catatan (opsional)</label>
            <textarea name="note" id="note" class="form-control" rows="2" placeholder="Tulis pesan untuk admin (maks 100 kata)"></textarea>
            <small id="noteHelp" class="form-text text-muted">0 / 100 kata</small>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary" id="btnSubmit" type="submit">Simpan</button>
            <a href="user_dashboard.php" class="btn btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  // sisa target dari server (angka murni)
  const SISA = <?= (int)$sisa ?>;
  const targetNominal = <?= (int)$target_nominal ?>;

  // helper format
  function formatRupiah(num){
    if (!num && num !== 0) return '';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }
  function unformatRupiah(str){
    if(!str) return 0;
    return parseInt(String(str).replace(/\D/g,'')) || 0;
  }

  // tombol nominal
  const nominalBtns = document.querySelectorAll('.nominal-btn');
  const inputJumlah = document.getElementById('jumlah');
  const selectedNominalEl = document.getElementById('selectedNominal');
  const btnLainnya = document.getElementById('btnLainnya');
  const modalNominal = new bootstrap.Modal(document.getElementById('modalNominalLain'));
  const inputNominalLain = document.getElementById('inputNominalLain');
  const btnUp = document.getElementById('btnUp');
  const btnDown = document.getElementById('btnDown');
  const simpanNominalLain = document.getElementById('simpanNominalLain');
  const hintLain = document.getElementById('hintLain');

  // disable tombol nominal yang > SISA
  function refreshNominalButtons(){
    nominalBtns.forEach(btn=>{
      const val = parseInt(btn.dataset.value,10) || 0;
      if (SISA === 0 || val > SISA) {
        btn.disabled = true;
        btn.classList.add('disabled');
        btn.style.opacity = 0.55;
        btn.title = "Melebihi sisa target";
      } else {
        btn.disabled = false;
        btn.classList.remove('disabled');
        btn.style.opacity = 1;
        btn.title = "";
      }
    });

    // jika sisa kurang dari 10000 maka nonaktifkan 'Nominal Lainnya'
    if (SISA < 10000 || SISA === 0) {
      btnLainnya.disabled = true;
      btnLainnya.classList.add('btn-secondary');
      btnLainnya.classList.remove('btn-outline-secondary');
      btnLainnya.style.opacity = 0.6;
      hintLain.textContent = SISA === 0 ? "Target sudah terpenuhi." : "Sisa kurang dari minimal input (Rp 10.000).";
    } else {
      btnLainnya.disabled = false;
      btnLainnya.classList.remove('btn-secondary');
      btnLainnya.classList.add('btn-outline-secondary');
      btnLainnya.style.opacity = 1;
      hintLain.textContent = "";
    }
  }
  refreshNominalButtons();

  // pilih nominal cepat
  nominalBtns.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (btn.disabled) return;
      nominalBtns.forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      const value = parseInt(btn.dataset.value,10) || 0;
      inputJumlah.value = value;
      selectedNominalEl.textContent = "Nominal dipilih: Rp " + formatRupiah(value);
    });
  });

  // nominal lainnya modal
  btnLainnya.addEventListener('click', ()=>{
    if (btnLainnya.disabled) return;
    // set default to smallest step or 0
    inputNominalLain.value = formatRupiah(Math.min(10000, SISA));
    // show hint of max
    hintLain.textContent = "Maksimum: Rp " + formatRupiah(SISA);
    modalNominal.show();
  });

  // format input manual saat ketik
  inputNominalLain.addEventListener('input', function(){
    let angka = unformatRupiah(this.value);
    // jika lebih dari sisa -> set ke sisa
    if (angka > SISA) angka = SISA;
    this.value = angka ? formatRupiah(angka) : '';
  });

  // tombol up/down menambah/turun kelipatan 10k, batasi sisa dan minimum 0
  btnUp.addEventListener('click', function(){
    let angka = unformatRupiah(inputNominalLain.value);
    angka += 10000;
    if (angka > SISA) angka = SISA;
    inputNominalLain.value = angka ? formatRupiah(angka) : '';
  });
  btnDown.addEventListener('click', function(){
    let angka = unformatRupiah(inputNominalLain.value);
    angka -= 10000;
    if (angka < 0) angka = 0;
    inputNominalLain.value = angka ? formatRupiah(angka) : '';
  });

  // keyboard arrow up/down on inputNominalLain
  inputNominalLain.addEventListener('keydown', function(e){
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      let angka = unformatRupiah(this.value) + 10000;
      if (angka > SISA) angka = SISA;
      this.value = formatRupiah(angka) || '';
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      let angka = unformatRupiah(this.value) - 10000;
      if (angka < 0) angka = 0;
      this.value = formatRupiah(angka) || '';
    }
  });

  // simpan nominal lain -> transfer ke input jumlah
  simpanNominalLain.addEventListener('click', ()=>{
    let val = unformatRupiah(inputNominalLain.value);
    if (val <= 0) return;
    // cap by SISA
    if (val > SISA) val = SISA;
    inputJumlah.value = val;
    selectedNominalEl.textContent = "Nominal dipilih: Rp " + formatRupiah(val);
    // mark as active none
    nominalBtns.forEach(b=>b.classList.remove('active'));
  });

  // PAYMENT modal data (bank/ewallet)
  const bankData = {
    'BCA': { img: 'bca.jpg', no: '0112998061' },
    'BNI': { img: 'bni.jpg', no: '1296373192' },
    'BTN  Syariah': { img: 'btn.jpg', no: '7061002216 ' },
    'Cimb Niaga': { img: 'cimb.jpg', no: '1029384756' },
    'Muamalat': { img: 'muamalat.jpg', no: ' 7110029306' },
    // qurban
    'BSI Syariah': {img: 'bsi.jpg', no: '5656050506'},
  };
  const ewalletData = {
    'GoPay': { img: 'gopay.jpg', no: '081234567890' },
    'OVO': { img: 'ovo.jpg', no: '081234567891' },
    'Dana': { img: 'dana.jpg', no: '081234567892' },
    'ShopeePay': { img: 'shope.jpg', no: '081234567893' },
    'LinkAja': { img: 'link.jpg', no: '081234567894' },
  };

  let currentCategory = '', selectedNumber = '', selectedName = '', selectedImg = '';

  function showList(type){
    currentCategory = type;
    document.getElementById('step-kategori').style.display = 'none';
    document.getElementById('step-list').style.display = 'block';
    document.getElementById('step-detail').style.display = 'none';
    document.getElementById('step-qris').style.display = 'none';
    document.getElementById('listTitle').textContent = type === 'bank' ? 'Pilih Bank Transfer' : 'Pilih E-Wallet';
    const data = type === 'bank' ? bankData : ewalletData;
    const html = Object.entries(data).map(([name, info])=>
      `<a href="#" class="list-group-item list-group-item-action d-flex align-items-center" onclick="showDetail('${name}')">
        <img src="/tabungan_qurban/assets/images/payments/${info.img}" width="40" class="me-3">${name}
      </a>`).join('');
    document.getElementById('listContainer').innerHTML = html;
  }
  window.showList = showList;

  // showDetail (exposed to window so onclick in generated html resolves)
window.showDetail = function(name){
    const data = currentCategory === 'bank' ? bankData[name] : ewalletData[name];

    selectedName = name;
    selectedImg  = "/tabungan_qurban/assets/images/payments/" + data.img;
    selectedNumber = data.no;

    document.getElementById("detailImg").src = selectedImg;
    document.getElementById("detailName").textContent = name;
    document.getElementById("detailNumber").textContent = data.no;

    document.getElementById("step-list").style.display = "none";
    document.getElementById("step-detail").style.display = "block";
};


  window.showQRIS = function(){
    document.getElementById('step-kategori').style.display = 'none';
    document.getElementById('step-qris').style.display = 'block';
  };

  window.backToKategori = function(){
    document.getElementById('step-list').style.display = 'none';
    document.getElementById('step-detail').style.display = 'none';
    document.getElementById('step-qris').style.display = 'none';
    document.getElementById('step-kategori').style.display = 'block';
  };

  window.backToList = function(){
    document.getElementById('step-detail').style.display = 'none';
    document.getElementById('step-list').style.display = 'block';
  };

  window.copyRekening = function(){
    if (!selectedNumber) return alert('Tidak ada nomor untuk disalin');
    navigator.clipboard.writeText(selectedNumber);
    alert('Nomor berhasil disalin!');
  };

 window.selesaiPilih = function() {

    if (!selectedName || !selectedImg || !selectedNumber) {
        alert("Pilih metode pembayaran terlebih dahulu.");
        return;
    }
    // Tutup modal
    const modalEl = document.getElementById('paymentModal');
    const bs = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    bs.hide();
    // TAMPILKAN METODE DI KOTAK INPUT
    document.getElementById("selectedMethod").style.display = "block";
    document.getElementById("selectedLogo").src = selectedImg;
    document.getElementById("selectedName").textContent = selectedName;
    // KIRIM KE PHP
    document.getElementById("payment_method").value = selectedName;
};
function resetPayment() {
    document.getElementById("paymentBox").style.display = "none";
    document.getElementById("payment_method").value = "";
}
  // FORM submit client-side validation
  const form = document.getElementById('txForm');
  form.addEventListener('submit', function(e){
    const jumlah = unformatRupiah(inputJumlah.value);
    const paymentMethod = document.getElementById('payment_method').value.trim();
    const bukti = document.getElementById('bukti').files.length;

    if (jumlah <= 0) {
      alert('Isi nominal terlebih dahulu.');
      e.preventDefault();
      return;
    }
    if (SISA === 0) {
      alert('Target sudah terpenuhi, tidak bisa melakukan setor.');
      e.preventDefault();
      return;
    }
    if (jumlah > SISA) {
      alert('Nominal melebihi sisa target: Rp ' + formatRupiah(SISA));
      e.preventDefault();
      return;
    }
    if (!paymentMethod) {
      alert('Pilih metode pembayaran terlebih dahulu.');
      e.preventDefault();
      return;
    }
    if (!bukti) {
      alert('Upload bukti transfer wajib.');
      e.preventDefault();
      return;
    }
    // else allow submit
  });
})();
</script>
<?php include __DIR__ . '/../inc/footer.php'; ?>
