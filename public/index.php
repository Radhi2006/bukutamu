<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require __DIR__ . '/../src/db.php';

// Token CSRF: memastikan form dikirim dari halaman ini, bukan dari situs lain
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$nama   = '';
$pesan  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token tidak valid. Muat ulang halaman lalu coba lagi.');
    }

    $nama  = trim($_POST['nama'] ?? '');
    $pesan = trim($_POST['pesan'] ?? '');

    if ($nama === '' || mb_strlen($nama) > 50) {
        $errors[] = 'Nama wajib diisi, maksimal 50 karakter.';
    }
    if ($pesan === '' || mb_strlen($pesan) > 500) {
        $errors[] = 'Pesan wajib diisi, maksimal 500 karakter.';
    }

    if (!$errors) {
        // Prepared statement: data dikirim terpisah dari query, jadi aman dari SQL injection
        $stmt = db()->prepare('INSERT INTO pesan (nama, isi) VALUES (:nama, :isi)');
        $stmt->execute(['nama' => $nama, 'isi' => $pesan]);

        // Redirect setelah POST, supaya refresh browser nggak mengirim ulang form
        header('Location: /');
        exit;
    }
}

$daftar = db()
    ->query('SELECT nama, isi, dibuat_pada FROM pesan ORDER BY dibuat_pada DESC LIMIT 50')
    ->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Tamu</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <main>
        <h1>Buku Tamu</h1>

        <?php if ($errors): ?>
            <ul class="errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

            <label for="nama">Nama</label>
            <input type="text" id="nama" name="nama" maxlength="50" required value="<?= e($nama) ?>">

            <label for="pesan">Pesan</label>
            <textarea id="pesan" name="pesan" maxlength="500" rows="4" required><?= e($pesan) ?></textarea>

            <button type="submit">Kirim</button>
        </form>

        <h2>Pesan (<?= count($daftar) ?>)</h2>

        <?php if (!$daftar): ?>
            <p class="kosong">Belum ada pesan. Jadilah yang pertama!</p>
        <?php endif; ?>

        <?php foreach ($daftar as $item): ?>
            <article>
                <header>
                    <strong><?= e($item['nama']) ?></strong>
                    <time><?= e($item['dibuat_pada']) ?></time>
                </header>
                <p><?= nl2br(e($item['isi'])) ?></p>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>