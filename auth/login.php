<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Already logged in? Skip straight to the dashboard.
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password'])) {

            /* Deactivated accounts are rejected even with the right password. */
            if (array_key_exists('is_active', $account) && (int) $account['is_active'] !== 1) {
                $error = 'This account has been deactivated. Please contact an administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $account['id'];
                $_SESSION['name']      = $account['name'];
                $_SESSION['username']  = $account['username'];
                $_SESSION['role']      = $account['role'];
                $_SESSION['last_seen'] = time();

                /* Stamp the login time (column exists from the Demo 3 migration). */
                try {
                    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$account['id']]);
                } catch (Exception $e) { /* pre-migration DB -- ignore */ }

                log_activity('Authentication', 'Signed in to SMART STOCK');
                log_audit('Authentication', 'LOGIN', 'User', (int) $account['id']);

                redirect('dashboard.php');
            }
        } else {
            $error = 'Incorrect username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · SMART STOCK — Botol Anggun Sdn. Bhd.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root{
    /* Brand purple sampled from the Botol Anggun logo */
    --purple-950:#210830;
    --purple-900:#2F0E44;
    --purple-800:#43135A;
    --purple-700:#5A1C77;
    --purple-600:#7429A0;
    --purple-500:#8E3FBE;
    --purple-100:#F0E6F8;
    --purple-050:#FAF6FD;
    --gold:#D9B65C;
    --gold-soft:#F0DCA6;

    --ink-900:#1D1226;
    --ink-700:#463753;
    --ink-500:#7A6B85;
    --ink-400:#A498AD;
    --border:#E7DCF0;
    --red:#D0392C;
    --red-bg:#FCE7E4;
  }
  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{
    margin:0;
    font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
    color:var(--ink-900);
    background:var(--purple-800);
    -webkit-font-smoothing:antialiased;
    position:relative;overflow-x:hidden;
  }

  /* ---------------- background ---------------- */
  .bg{position:fixed;inset:0;z-index:0;overflow:hidden;
      background:radial-gradient(125% 125% at 20% 0%, #7429A0 0%, #43135A 48%, #210830 100%);}
  .bg::after{
    content:'';position:absolute;inset:0;
    background-image:
      linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size:48px 48px;
    mask-image:radial-gradient(circle at 50% 40%, black, transparent 80%);
    -webkit-mask-image:radial-gradient(circle at 50% 40%, black, transparent 80%);
  }
  .glow{position:absolute;border-radius:50%;filter:blur(100px);}
  .g1{width:560px;height:560px;background:#9B4BD0;top:-220px;left:-170px;opacity:.40;}
  .g2{width:480px;height:480px;background:#D9B65C;bottom:-230px;right:-150px;opacity:.13;}
  .g3{width:380px;height:380px;background:#C6A0DF;top:34%;right:26%;opacity:.14;}

  /* ---------------- page grid ---------------- */
  .page{
    position:relative;z-index:2;min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    gap:52px;padding:40px 56px 64px;max-width:1280px;margin:0 auto;
  }
  .left,.right{flex:1 1 0;min-width:0;max-width:530px;}

  /* ---------------- LEFT: brand lockup + card ---------------- */
  .left{display:flex;flex-direction:column;align-items:center;
        animation:rise .55s cubic-bezier(.22,1,.36,1) both;}
  @keyframes rise{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:none;}}

  /* horizontal lockup: swan mark beside the two stacked lines */
  .lockup{display:flex;align-items:center;gap:12px;margin-bottom:20px;width:100%;max-width:430px;}
  .lockup img{height:48px;width:auto;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(12,3,20,.45));}
  .lockup .txt{text-align:left;display:flex;flex-direction:column;justify-content:center;}
  .lockup .l1{
    font-family:'Poppins',sans-serif;font-weight:800;color:#fff;
    font-size:20px;letter-spacing:2px;line-height:1.1;
    text-shadow:0 2px 10px rgba(12,3,20,.35);
  }
  .lockup .l2{
    font-family:'Poppins',sans-serif;font-weight:700;
    color:var(--gold-soft);font-size:10px;letter-spacing:1.5px;
    margin-top:2px;text-transform:uppercase;
  }

  .card{
    width:100%;max-width:430px;
    background:#fff;border-radius:22px;padding:32px 32px 26px;
    box-shadow:0 26px 64px -18px rgba(20,4,32,.62), 0 2px 6px rgba(20,4,32,.14);
    border-top:3px solid var(--gold);
  }
  .card h1{
    font-family:'Poppins',sans-serif;font-size:24px;font-weight:600;
    margin:0 0 5px;color:var(--ink-900);letter-spacing:.5px;text-transform:uppercase;
  }
  .card .lead{font-size:13.3px;color:var(--ink-500);margin:0 0 22px;}

  .field{margin-bottom:16px;}
  .field label{display:block;font-size:12.8px;font-weight:600;color:var(--ink-700);margin-bottom:7px;}
  .input-wrap{position:relative;}
  .input-wrap > i{
    position:absolute;left:14px;top:50%;transform:translateY(-50%);
    color:var(--ink-400);font-size:15px;pointer-events:none;transition:color .15s;
  }
  .input-wrap input{
    width:100%;padding:13px 14px 13px 42px;
    border:1.5px solid var(--border);border-radius:12px;
    background:var(--purple-050);font-size:14.5px;font-family:inherit;color:var(--ink-900);
    transition:border-color .15s, background .15s, box-shadow .15s;
  }
  .input-wrap input::placeholder{color:var(--ink-400);}
  .input-wrap input:focus{
    outline:none;border-color:var(--purple-600);background:#fff;
    box-shadow:0 0 0 4px rgba(116,41,160,.16);
  }
  .input-wrap:focus-within > i{color:var(--purple-700);}
  .eye{
    position:absolute;right:8px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:var(--ink-400);padding:7px;cursor:pointer;display:flex;border-radius:8px;
  }
  .eye:hover{color:var(--purple-700);background:var(--purple-100);}

  .row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:2px 0 20px;}
  .remember{display:flex;align-items:center;gap:8px;font-size:12.8px;color:var(--ink-700);cursor:pointer;user-select:none;}
  .remember input{width:16px;height:16px;accent-color:var(--purple-700);cursor:pointer;}
  .forgot{font-size:12.8px;font-weight:600;color:var(--purple-700);text-decoration:none;}
  .forgot:hover{text-decoration:underline;}

  .btn-login{
    width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;
    background:linear-gradient(135deg, var(--purple-600) 0%, var(--purple-800) 100%);
    color:#fff;font-family:'Poppins',sans-serif;font-size:15px;font-weight:600;letter-spacing:1.6px;
    display:flex;align-items:center;justify-content:center;gap:9px;
    box-shadow:0 12px 24px -9px rgba(67,19,90,.85);
    transition:transform .12s, box-shadow .2s, filter .2s;
  }
  .btn-login:hover{filter:brightness(1.13);box-shadow:0 16px 30px -9px rgba(67,19,90,.95);transform:translateY(-1px);}
  .btn-login:active{transform:translateY(0) scale(.99);}
  .btn-login.shake{animation:shake .4s;}
  @keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-7px)}40%{transform:translateX(7px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}

  .err{
    display:none;align-items:center;gap:9px;background:var(--red-bg);color:var(--red);
    font-size:12.6px;font-weight:600;padding:11px 13px;border-radius:10px;margin-bottom:17px;
  }
  .err.show{display:flex;}

  .card-foot{
    text-align:center;font-size:11.4px;color:var(--ink-500);
    margin-top:20px;padding-top:15px;border-top:1px solid var(--border);line-height:1.7;
  }
  .card-foot b{color:var(--purple-800);font-weight:700;}

  /* ---------------- RIGHT: brand panel ---------------- */
  .right{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    text-align:center;padding:0 10px;
    animation:rise .55s cubic-bezier(.22,1,.36,1) .12s both;
  }
  .right .logo-full{
    width:100%;max-width:400px;margin-bottom:30px;
    filter:drop-shadow(0 12px 30px rgba(12,3,20,.5));
  }
  .right .tagline{
    font-family:'Poppins',sans-serif;font-weight:700;color:#fff;
    font-size:26px;line-height:1.42;letter-spacing:.2px;margin:0;
    text-shadow:0 3px 14px rgba(12,3,20,.4);
  }
  .right .rule{
    width:74px;height:3px;border-radius:3px;margin:24px auto 0;
    background:linear-gradient(90deg, transparent, var(--gold), transparent);
  }
  .right .sub{
    margin-top:18px;color:rgba(255,255,255,.62);
    font-size:12.3px;letter-spacing:2.4px;text-transform:uppercase;font-weight:600;
  }

  .page-foot{
    position:absolute;bottom:16px;left:0;right:0;z-index:2;text-align:center;
    font-size:11.3px;color:rgba(255,255,255,.45);
  }

  /* ---------------- responsive ---------------- */
  @media (max-width:960px){
    .page{flex-direction:column;padding:38px 22px 64px;gap:32px;}
    .left,.right{max-width:440px;width:100%;}
    .right{order:-1;}                     /* brand panel sits above the form */
    .right .logo-full{max-width:280px;margin-bottom:18px;}
    .right .tagline{font-size:19px;}
    .right .rule{margin-top:18px;}
    .right .sub{display:none;}
    .lockup{display:none;}               /* avoid showing the logo twice */
  }
  @media (max-width:480px){
    .card{padding:26px 20px 22px;border-radius:18px;}
    .right .logo-full{max-width:225px;}
    .right .tagline{font-size:16.5px;}
    .card h1{font-size:21px;}
  }
</style>
</head>
<body>

<div class="bg">
  <span class="glow g1"></span>
  <span class="glow g2"></span>
  <span class="glow g3"></span>
</div>

<div class="page">

  <!-- ============ LEFT: lockup + login card ============ -->
  <div class="left">
    <div class="lockup">
      <img src="<?= e(base_url('assets/img/SmartStockLogo.png')) ?>" alt="Botol Anggun">
      <div class="txt">
        <div class="l1">SMART STOCK</div>
        <div class="l2">Digital Inventory Management System</div>
      </div>
    </div>

    <div class="card">
      <h1>Welcome</h1>
      <p class="lead">Sign in to your Smart Stock account to continue.</p>

      <?php $flash = get_flash(); ?>
      <?php if ($flash): ?>
        <div class="err show" style="<?= $flash['type'] === 'success' ? 'background:#E4F7EF;color:#12966B;' : ($flash['type'] === 'warning' ? 'background:#FDF1DD;color:#C5790E;' : '') ?>">
          <i class="bi bi-info-circle-fill"></i>
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="err show">
          <i class="bi bi-exclamation-circle-fill"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="field">
          <label for="username">Username</label>
          <div class="input-wrap">
            <i class="bi bi-person"></i>
            <input type="text" id="username" name="username" placeholder="Enter your username" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
          </div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <i class="bi bi-lock"></i>
            <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="eye" id="eye" aria-label="Show password"><i class="bi bi-eye" id="eyeIcon"></i></button>
          </div>
        </div>

        <div class="row">
          <label class="remember"><input type="checkbox" id="remember" name="remember" checked> Remember me</label>
          <a href="#" class="forgot" id="forgot">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">
          LOG IN <i class="bi bi-arrow-right"></i>
        </button>
      </form>

      <div class="card-foot">
        <b>Digital Inventory Management System</b><br>
        Botol Anggun Sdn. Bhd.
      </div>
    </div>
  </div>

  <!-- ============ RIGHT: brand panel ============ -->
  <div class="right">
    <img class="logo-full" src="<?= e(base_url('assets/img/botol-anggun-full.png')) ?>" alt="Botol Anggun">
    <h2 class="tagline">Pembekal dan Pemborong<br>Botol Kosmetik dan Botol Plastik</h2>
    <div class="rule"></div>
    <div class="sub">Selangor · Malaysia</div>
  </div>

</div>

<div class="page-foot">© 2026 Politeknik Muadzam Shah</div>

<script>
  var pw = document.getElementById('password');
  var eyeIcon = document.getElementById('eyeIcon');
  document.getElementById('eye').addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    eyeIcon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  var btn = document.getElementById('btnLogin');
  document.querySelector('form').addEventListener('submit', function () {
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> SIGNING IN\u2026';
    btn.disabled = true;
  });

  document.getElementById('forgot').addEventListener('click', function (ev) {
    ev.preventDefault();
    alert('Please contact an administrator to reset your password.\n\nAdmins can do this in User Management \u2192 Reset Password.');
  });
</script>
<?php if ($error): ?>
<script>
  var b = document.getElementById('btnLogin');
  b.classList.add('shake');
  setTimeout(function(){ b.classList.remove('shake'); }, 500);
</script>
<?php endif; ?>
</body>
</html>