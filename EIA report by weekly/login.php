<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    // Normalize Username (Remove Domain prefixes/suffixes)
    $username = $raw_username;
    if (($pos = strpos($username, "@")) !== false) {
        $username = substr($username, 0, $pos);
    }
    if (($pos = strpos($username, "\\")) !== false) {
        $username = substr($username, $pos + 1);
    }

    // Require LDAP Helper
    if (file_exists('adauthen.php')) {
        require_once 'adauthen.php';
    } elseif (file_exists('inc/adauthen.php')) {
        require_once 'inc/adauthen.php';
    }

    if (function_exists('chkldapuser')) {
        // Authenticate against Central Domain
        $chkldapresult = chkldapuser("central", $username, $password);

        switch ($chkldapresult) {
            case "Pass":
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $username;
                header('Location: index.php');
                exit;
            case "Not found":
                $error = 'ไม่พบเซิร์ฟเวอร์สำหรับตัวตน (Network Error)';
                break;
            case "Not connect":
                $error = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ LDAP Directory ได้';
                break;
            case "Invalid":
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                break;
            default:
                $error = 'เกิดข้อผิดพลาดไม่ทราบสาเหตุในการยืนยันตัวตน';
        }
    } else {
        $error = 'ระบบ LDAP (adauthen.php) ขัดข้อง หรือติดต่อไม่ได้';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EIA Compliance Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'></path></svg>">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            /* Premium V2 Animated Background */
            background: linear-gradient(-225deg, #69EACB 0%, #EACCF8 48%, #6654F1 100%);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Shimmer Effect for Buttons */
        .shimmer-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .shimmer-btn::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50vw;
            width: 15px;
            height: 200%;
            background: rgba(255, 255, 255, 0.4);
            transform: rotate(35deg);
            transition: left 0.7s ease-in-out;
        }
        .shimmer-btn:hover::after {
            left: 150%;
        }
        
        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.2) inset;
        }

        /* Focus Ring Details */
        input:focus {
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
    </style>
</head>
<body>

    <div class="glass-card w-full max-w-lg rounded-[2.5rem] p-10 md:p-14 relative overflow-hidden m-4">
        
        <!-- Decorative bg blur circles -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/40 blur-3xl rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-blue-400/20 blur-3xl rounded-full pointer-events-none"></div>

        <div class="text-center mb-10 relative z-10">
            <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center shadow-xl mx-auto mb-6 p-2 border border-white/60">
                <img src="Logo RIS.png" alt="RIS Logo" class="w-full h-full object-contain" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjMjU2M2ViIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTkgMTJsMiAyIDQtNG01LjYxOC00LjAxNkExMS45NTUgMTEuOTU1IDAgMDExMiAyLjk0NGExMS45NTUgMTEuOTU1IDAgMDEtOC42MTggMy4wNEExMi4wMiAxMi4wMiAwIDAwMyA5YzAgNS41OTEgMy44MjQgMTAuMjkgOSAxMS42MjIgNS4xNzYtMS4zMzIgOS02LjAzIDktMTEuNjIyIDAtMS4wNDItLjEzMy0yLjA1Mi0uMzgyLTMuMDE2eiI+PC9wYXRoPjwvc3ZnPg=='" />
            </div>
            <h1 class="text-3xl font-black text-gray-800 tracking-tight uppercase mb-1">EIA Security Core</h1>
            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-widest">Authentication Required</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-4 rounded-r-lg mb-6 shadow-sm flex items-start gap-3 text-sm font-medium relative z-10">
                <svg class="w-5 h-5 text-rose-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6 relative z-10">
            <div>
                <label for="username" class="block text-xs font-black text-gray-600 uppercase tracking-widest mb-2 ml-1">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="text" name="username" id="username" required 
                           class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-gray-200 rounded-xl text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all font-medium" 
                           placeholder="Enter your username">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-black text-gray-600 uppercase tracking-widest mb-2 ml-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" name="password" id="password" required 
                           class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-gray-200 rounded-xl text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all font-medium" 
                           placeholder="Enter your password">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" 
                        class="shimmer-btn w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-[0_8px_30px_rgb(37,99,235,0.3)] hover:shadow-[0_8px_30px_rgb(37,99,235,0.5)] transform hover:-translate-y-0.5 transition-all uppercase tracking-widest text-sm border-b-4 border-blue-800 active:border-b-0 active:translate-y-1">
                    Secure Login &rarr;
                </button>
            </div>
            
            <p class="text-center text-[10px] text-gray-500 font-medium uppercase tracking-[0.2em] mt-8">
                Authorized Personnel Only
            </p>
        </form>
    </div>

</body>
</html>
