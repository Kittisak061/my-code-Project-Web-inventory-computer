<?php
// ============================================================
// DeviceHub — Admin Register Page
// เปิด: http://localhost/devicehub/register.php
// ต้องยืนยัน Admin Password ก่อนสร้าง User ได้
// ============================================================
require_once __DIR__ . '/api/config.php';

$error   = '';
$success = '';
$step    = isset($_POST['step']) ? (int)$_POST['step'] : 1;

// ── STEP 1: ตรวจสอบ Admin Password ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $adminPw = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
    if (!$adminPw) {
        $error = 'กรุณากรอก Admin Password';
        $step  = 1;
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT `password` FROM `users` WHERE `username` = 'admin' AND `status` = 'active' LIMIT 1");
            $stmt->execute();
            $row  = $stmt->fetch();
            if (!$row) {
                $error = 'ไม่พบ admin ในระบบ กรุณา import devicehub.sql ก่อน';
                $step  = 1;
            } elseif (!password_verify($adminPw, $row['password'])) {
                $error = 'Admin Password ไม่ถูกต้อง';
                $step  = 1;
            } else {
                $step = 2; // ผ่าน → ไปหน้าสร้าง user
            }
        } catch (Exception $e) {
            $error = 'DB Error: ' . $e->getMessage();
            $step  = 1;
        }
    }
}

// ── STEP 2: สร้าง User ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $adminPw  = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
    $name     = trim(isset($_POST['name'])     ? $_POST['name']     : '');
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email    = trim(isset($_POST['email'])    ? $_POST['email']    : '');
    $dept     = trim(isset($_POST['dept'])     ? $_POST['dept']     : '');
    $role     = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'user';
    $password = isset($_POST['password'])      ? $_POST['password'] : '';
    $confirm  = isset($_POST['confirm'])       ? $_POST['confirm']  : '';

    // Validate
    if (!$name || !$username || !$email || !$password) {
        $error = 'กรุณากรอกข้อมูลให้ครบ (ชื่อ, Username, Email, Password)';
    } elseif (strlen($password) < 4) {
        $error = 'Password ต้องมีอย่างน้อย 4 ตัวอักษร';
    } elseif ($password !== $confirm) {
        $error = 'Password และ Confirm ไม่ตรงกัน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบ Email ไม่ถูกต้อง';
    } else {
        try {
            $db = getDB();

            // ตรวจ admin password อีกครั้ง (security)
            $stmt = $db->prepare("SELECT `password` FROM `users` WHERE `username` = 'admin' AND `status` = 'active' LIMIT 1");
            $stmt->execute();
            $row  = $stmt->fetch();
            if (!$row || !password_verify($adminPw, $row['password'])) {
                $error = 'Admin Password หมดอายุ กรุณากลับไปยืนยันใหม่';
                $step  = 1;
            } else {
                // ตรวจ duplicate
                $chk = $db->prepare("SELECT `id` FROM `users` WHERE `username` = ? OR `email` = ? LIMIT 1");
                $chk->execute([$username, $email]);
                if ($chk->fetch()) {
                    $error = 'Username หรือ Email นี้มีอยู่ในระบบแล้ว';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins  = $db->prepare("INSERT INTO `users` (`name`,`username`,`password`,`email`,`dept`,`role`,`status`) VALUES (?,?,?,?,?,?,'active')");
                    $ins->execute([$name, $username, $hash, $email, $dept ?: null, $role]);
                    $success = 'สร้างบัญชี <b>' . htmlspecialchars($username) . '</b> สำเร็จแล้ว!';
                    $step    = 2; // อยู่หน้าเดิม ให้สร้างต่อได้
                }
            }
        } catch (Exception $e) {
            $error = 'DB Error: ' . $e->getMessage();
        }
    }
}

$DEPARTMENTS = [
    "AD - AC","AD - GA","AD - HRD","AD - HRM","AD-IT",
    "AUTOMOTIVE - PD",
    "BC CENTER","BC CENTER - PC","BC CENTER - PURCHASE","BC CENTER - SALE",
    "BC CENTER - STANDARD SETTING","BC CENTER - WAREHOUSE",
    "EC - FM","EC - ME","EC - PE","EC DIVISION","MARKETING",
    "MEDICAL - PD","MEDICAL - PE",
    "PD1 - PE","PD1 - PE (ME/AUTO)","PD1 - PE (ME/INJ)","PD1 - PE (MN/ASSY)",
    "PD1 - PE (PE/AUTO)","PD1 - PE (PE/INJ)",
    "PRECISION - PD ASSY","PRECISION - PD ASSY(ASSY)","PRECISION - PD ASSY(PAINT)",
    "PRECISION - PD INJ1","PRECISION - PD INJ2",
    "PRODUCTION DIVISION 1","PRODUCTION DIVISION 2",
    "QA CENTER","QA CENTER - QA","QA CENTER - QC","QA CENTER - QM","QA CENTER - QS",
    "SLIT - PD","SLIT - PE"
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DeviceHub — สร้างบัญชีผู้ใช้</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&family=Syne:wght@700;800&display=swap');
:root{
  --bg:#080b11;--surface:#0f1319;--s2:#161b24;
  --border:#1f2733;--border2:#2a3444;
  --cyan:#00d4ff;--green:#00e896;--yellow:#ffcc44;--red:#ff4d6d;
  --text:#dde3ee;--text2:#7a8899;--text3:#3d4e60;
  --font:'IBM Plex Sans Thai',sans-serif;--mono:'IBM Plex Mono',monospace;--display:'Syne',sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,.02) 1px,transparent 1px);background-size:48px 48px;pointer-events:none;z-index:0;}

.wrap{width:480px;max-width:98vw;position:relative;z-index:1;}

/* LOGO */
.logo{text-align:center;margin-bottom:28px;}
.logo-ico{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#00d4ff,#0055aa);display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 14px;box-shadow:0 0 32px rgba(0,212,255,.3);}
.logo-name{font-family:var(--display);font-size:26px;font-weight:800;}
.logo-sub{font-size:11px;color:var(--text3);font-family:var(--mono);letter-spacing:2px;margin-top:4px;}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.5);}
.stripe{height:4px;background:linear-gradient(90deg,var(--cyan),var(--green));}
.card-body{padding:28px 32px 32px;}
.card-title{font-size:15px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px;}

/* FORM */
.field{margin-bottom:16px;}
.field label{display:block;font-size:11px;font-family:var(--mono);color:var(--text2);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px;}
.field label span{color:var(--red);}
.iw{position:relative;}
.ii{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;pointer-events:none;}
.inp,.sel{width:100%;background:var(--s2);border:1px solid var(--border);border-radius:8px;padding:11px 12px 11px 40px;color:var(--text);font-family:var(--font);font-size:14px;outline:none;transition:border-color .15s;}
.sel{padding-left:12px;cursor:pointer;}
.inp:focus,.sel:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,212,255,.1);}
.inp::placeholder{color:var(--text3);}
.eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:14px;color:var(--text3);}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* ROLE TOGGLE */
.role-row{display:flex;gap:8px;margin-top:6px;}
.role-opt{flex:1;padding:10px;border-radius:8px;border:1px solid var(--border);background:var(--s2);color:var(--text2);font-family:var(--font);font-size:13px;cursor:pointer;text-align:center;transition:all .15s;}
.role-opt.active-user{border-color:rgba(0,232,150,.4);background:rgba(0,232,150,.08);color:var(--green);}
.role-opt.active-admin{border-color:rgba(0,212,255,.4);background:rgba(0,212,255,.08);color:var(--cyan);}

/* BUTTON */
.btn-go{width:100%;padding:12px;font-size:14px;font-weight:700;border-radius:8px;margin-top:8px;background:linear-gradient(135deg,var(--cyan),#0088cc);color:#080b11;border:none;cursor:pointer;font-family:var(--font);display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 16px rgba(0,212,255,.25);transition:all .2s;}
.btn-go:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,212,255,.35);}
.btn-back{background:transparent;border:1px solid var(--border);color:var(--text2);padding:10px 18px;border-radius:8px;cursor:pointer;font-family:var(--font);font-size:13px;transition:all .15s;}
.btn-back:hover{background:var(--s2);color:var(--text);}

/* ALERTS */
.alert{border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:flex-start;gap:10px;}
.alert-err{background:rgba(255,77,109,.09);border:1px solid rgba(255,77,109,.3);color:var(--red);}
.alert-ok{background:rgba(0,232,150,.08);border:1px solid rgba(0,232,150,.25);color:var(--green);}

/* DIVIDER */
.divider{border:none;border-top:1px solid var(--border);margin:18px 0;}

/* LOGIN LINK */
.link-row{text-align:center;margin-top:18px;font-size:12.5px;color:var(--text3);}
.link-row a{color:var(--cyan);text-decoration:none;}
.link-row a:hover{text-decoration:underline;}

/* SECURITY NOTE */
.sec-note{background:rgba(255,204,68,.06);border:1px solid rgba(255,204,68,.2);border-radius:8px;padding:12px 14px;font-size:12px;color:var(--yellow);margin-bottom:18px;}

@keyframes shake{0%,100%{transform:none}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.shake{animation:shake .3s ease;}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-ico">🖥</div>
    <div class="logo-name">DeviceHub</div>
    <div class="logo-sub">ADMIN — CREATE USER</div>
  </div>

  <div class="card" id="card">
    <div class="stripe"></div>
    <div class="card-body">

      <?php if ($error): ?>
      <div class="alert alert-err">❌ <?= $error ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-ok">✅ <?= $success ?> &nbsp;<a href="login.html" style="color:var(--green);text-decoration:underline">→ ไปหน้า Login</a></div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
      <!-- ════ STEP 1: ยืนยัน Admin Password ════ -->
      <div class="card-title">🔐 ยืนยันตัวตน Admin</div>
      <div class="sec-note">⚠️ หน้านี้สำหรับ <b>Admin</b> เท่านั้น — ต้องกรอก Admin Password ก่อนสร้าง User ใหม่ได้</div>
      <form method="POST" id="form1">
        <input type="hidden" name="step" value="1">
        <div class="field">
          <label>Admin Password <span>*</span></label>
          <div class="iw">
            <span class="ii">🔒</span>
            <input class="inp" name="admin_password" id="ap" type="password" placeholder="กรอก Password ของ admin" autofocus>
            <button type="button" class="eye" onclick="var e=document.getElementById('ap');e.type=e.type==='password'?'text':'password'">👁</button>
          </div>
        </div>
        <button type="submit" class="btn-go">🔓 ยืนยัน Admin Password</button>
      </form>

      <?php else: ?>
      <!-- ════ STEP 2: สร้าง User ════ -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div class="card-title" style="margin-bottom:0">👤 สร้างบัญชีผู้ใช้ใหม่</div>
        <form method="POST" style="margin:0">
          <input type="hidden" name="step" value="1">
          <button type="submit" class="btn-back">← กลับ</button>
        </form>
      </div>

      <form method="POST" id="form2">
        <input type="hidden" name="step" value="2">
        <input type="hidden" name="admin_password" value="<?= htmlspecialchars($_POST['admin_password'] ?? '') ?>">

        <div class="grid2">
          <div class="field">
            <label>ชื่อ-นามสกุล <span>*</span></label>
            <div class="iw">
              <span class="ii">👤</span>
              <input class="inp" name="name" placeholder="ชื่อ นามสกุล" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
          </div>
          <div class="field">
            <label>Username <span>*</span></label>
            <div class="iw">
              <span class="ii">@</span>
              <input class="inp" name="username" placeholder="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="field">
          <label>Email <span>*</span></label>
          <div class="iw">
            <span class="ii">✉️</span>
            <input class="inp" name="email" type="email" placeholder="user@company.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="field">
          <label>แผนก</label>
          <select class="sel" name="dept">
            <option value="">— ไม่ระบุ —</option>
            <?php foreach ($DEPARTMENTS as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= (isset($_POST['dept']) && $_POST['dept'] === $d) ? 'selected' : '' ?>>
              <?= htmlspecialchars($d) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Role</label>
          <div class="role-row" id="roleRow">
            <div class="role-opt active-user" id="rUser" onclick="setRole('user')">👤 User</div>
            <div class="role-opt" id="rAdmin" onclick="setRole('admin')">👑 Admin</div>
          </div>
          <input type="hidden" name="role" id="roleVal" value="user">
        </div>

        <hr class="divider">

        <div class="grid2">
          <div class="field">
            <label>Password <span>*</span></label>
            <div class="iw">
              <span class="ii">🔒</span>
              <input class="inp" name="password" id="pw1" type="password" placeholder="อย่างน้อย 4 ตัว">
              <button type="button" class="eye" onclick="var e=document.getElementById('pw1');e.type=e.type==='password'?'text':'password'">👁</button>
            </div>
          </div>
          <div class="field">
            <label>ยืนยัน Password <span>*</span></label>
            <div class="iw">
              <span class="ii">🔒</span>
              <input class="inp" name="confirm" id="pw2" type="password" placeholder="พิมพ์อีกครั้ง">
              <button type="button" class="eye" onclick="var e=document.getElementById('pw2');e.type=e.type==='password'?'text':'password'">👁</button>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-go" onclick="return validateForm()">➕ สร้างบัญชีใหม่</button>
      </form>
      <?php endif; ?>

    </div>
  </div>

  <div class="link-row">
    มีบัญชีอยู่แล้ว? <a href="login.html">🔑 เข้าสู่ระบบ</a>
    &nbsp;|&nbsp;
    <a href="dashboard.html">📊 Dashboard</a>
  </div>
</div>

<script>
function setRole(r){
  document.getElementById('roleVal').value=r;
  document.getElementById('rUser').className='role-opt'+(r==='user'?' active-user':'');
  document.getElementById('rAdmin').className='role-opt'+(r==='admin'?' active-admin':'');
}

function validateForm(){
  var pw1=document.getElementById('pw1');
  var pw2=document.getElementById('pw2');
  if(!pw1||!pw2)return true;
  if(pw1.value!==pw2.value){
    alert('Password และ Confirm ไม่ตรงกัน');
    pw2.focus();
    var c=document.getElementById('card');
    c.classList.remove('shake');void c.offsetWidth;c.classList.add('shake');
    return false;
  }
  if(pw1.value.length<4){
    alert('Password ต้องมีอย่างน้อย 4 ตัวอักษร');
    pw1.focus();
    return false;
  }
  return true;
}

// Enter key submit
document.addEventListener('keydown',function(e){
  if(e.key==='Enter'){
    var f=document.getElementById('form1')||document.getElementById('form2');
    if(f)f.submit();
  }
});
</script>
</body>
</html>
