<?php
session_start();

// Jika sudah login, langsung arahkan ke index.php (tidak perlu login lagi)
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // SILAKAN UBAH USERNAME DAN PASSWORD DI BAWAH INI:
    if ($username === 'sigma' && $password === '     ') {
        $_SESSION['login'] = true;
        header("Location: index.php"); // Jika benar, masuk ke Dashboard
        exit;
    } else {
        $error = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JTO Verifikator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 380px; border-top: 5px solid #2b6cb0; }
        .btn-login { background-color: #2b6cb0; color: white; width: 100%; font-weight: bold; }
        .btn-login:hover { background-color: #1a4a7e; color: white; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <h4 class="fw-bold" style="color: #2b6cb0;">🛡️ Login Verifikator</h4>
            <p class="text-muted small">Masukkan kredensial Anda untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger p-2 text-center" style="font-size: 13px;"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label small fw-bold">Username</label>
                <input type="text" name="username" class="form-control form-control-sm" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Password</label>
                <input type="password" name="password" class="form-control form-control-sm" required>
            </div>
            <button type="submit" class="btn btn-login btn-sm py-2">MASUK</button>
        </form>
    </div>

</body>
</html>