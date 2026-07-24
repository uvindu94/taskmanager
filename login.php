<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['division_id'] = $user['division_id']; // Added for V2
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Task Manager</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'blob': 'blob 7s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 min-h-screen flex items-center justify-center p-4 sm:p-6 overflow-hidden relative">

    <!-- Animated Background Blobs -->
    <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-blob"></div>
    <div class="absolute top-0 -right-4 w-72 h-72 bg-brand-300 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-blob animation-delay-2000"></div>
    <div class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-blob animation-delay-4000"></div>

    <div class="max-w-5xl w-full bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row relative z-10 border border-white/50">
        
        <!-- Left Side: Branding / Abstract Image -->
        <div class="w-full md:w-5/12 bg-gradient-to-br from-brand-600 via-brand-700 to-indigo-900 p-10 flex flex-col justify-between text-white relative overflow-hidden">
            <!-- Decorative Elements -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/3 blur-2xl"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-brand-400/20 rounded-full translate-y-1/3 -translate-x-1/4 blur-2xl"></div>
            
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-12">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-chart-line text-brand-600 text-xl"></i>
                    </div>
                    <span class="font-display font-bold text-2xl tracking-tight">Task Manager (v2)</span>
                    
                </div>
        
                
                <div class="space-y-6">
                    <h1 class="font-display text-4xl sm:text-5xl font-bold leading-tight">
                        Empower <br>
                        <span class="text-brand-200">Your Team</span>
                    </h1>
                    <p class="text-brand-100 text-lg leading-relaxed max-w-sm font-light">
                        Track performance, measure KPIs, and manage company-wide tasks seamlessly with our modern dashboard.
                    </p>
                </div>
            </div>
            
            <div class="relative z-10 mt-12 md:mt-0">
                <div class="flex -space-x-4">
                    <img class="w-12 h-12 rounded-full border-2 border-brand-600 object-cover" src="https://i.pravatar.cc/100?img=1" alt="Avatar">
                    <img class="w-12 h-12 rounded-full border-2 border-brand-600 object-cover" src="https://i.pravatar.cc/100?img=2" alt="Avatar">
                    <img class="w-12 h-12 rounded-full border-2 border-brand-600 object-cover" src="https://i.pravatar.cc/100?img=3" alt="Avatar">
                    <div class="w-12 h-12 rounded-full border-2 border-brand-600 bg-brand-800 flex items-center justify-center text-sm font-medium">
                        +99
                    </div>
                </div>
                <p class="mt-4 text-sm text-brand-200 font-medium">Join the whole company today.</p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="w-full md:w-7/12 p-8 sm:p-12 md:p-16 flex flex-col justify-center bg-white" x-data="{ loading: false }">
            <div class="max-w-md w-full mx-auto">
                                     <img width="200" src="./assets/tasklogo.png" alt="Task Manager Logo">
                <h2 class="font-display text-3xl font-bold text-slate-900 mb-2">Welcome Back</h2>
                <p class="text-slate-500 mb-8">Please sign in to your account to continue.</p>

                <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 text-red-600 border border-red-100 rounded-xl flex items-center gap-3 animate-[pulse_1s_ease-in-out]">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span class="font-medium text-sm"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" @submit="loading = true" class="space-y-5">
                    <div class="space-y-2 relative group">
                        <label class="text-sm font-semibold text-slate-700 block transition-colors group-focus-within:text-brand-600">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-user text-slate-400 group-focus-within:text-brand-500 transition-colors"></i>
                            </div>
                            <input type="text" name="username" required 
                                class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all duration-200"
                                placeholder="Enter your username">
                        </div>
                    </div>

                    <div class="space-y-2 relative group" x-data="{ showPassword: false }">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-slate-700 transition-colors group-focus-within:text-brand-600">Password</label>
                            <a href="mailto:uvindua@sltds.lk?subject=Password%20Reset%20Request&body=Hi%20IT%20Support%2C%0D%0A%0D%0AI%20would%20like%20to%20request%20a%20password%20reset%20for%20my%20account.%0D%0A%0D%0AMy%20Username%3A%20%5BEnter%20Username%5D%0D%0A%0D%0AThank%20you." class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400 group-focus-within:text-brand-500 transition-colors"></i>
                            </div>
                            <input x-bind:type="showPassword ? 'text' : 'password'" name="password" required 
                                class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all duration-200"
                                placeholder="••••••••">
                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors focus:outline-none">
                                <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" 
                        class="w-full py-4 mt-8 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-semibold tracking-wide transition-all duration-200 shadow-[0_4px_14px_0_rgba(15,23,42,0.39)] hover:shadow-[0_6px_20px_rgba(15,23,42,0.23)] hover:-translate-y-0.5 flex items-center justify-center gap-2 group overflow-hidden relative">
                        <span x-show="!loading" class="flex items-center gap-2 relative z-10">
                            Sign In <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </span>
                        <span x-show="loading" x-cloak class="relative z-10">
                            <i class="fas fa-spinner fa-spin"></i> Authenticating...
                        </span>
                    </button>
                </form>
                
                <div class="mt-8 text-center">
                    <p class="text-sm text-slate-500">
                        Need an account? <a href="mailto:uvindua@sltds.lk?subject=New%20Account%20Request&body=Hi%20IT%20Support%2C%0D%0A%0D%0AI%20would%20like%20to%20request%20a%20new%20account%20for%20the%20Task%20Manager%20system.%0D%0A%0D%0AFull%20Name%3A%20%5BEnter%20Full%20Name%5D%0D%0ADesignation%3A%20%5BEnter%20Designation%5D%0D%0ADivision%3A%20%5BEnter%20Division%5D%0D%0A%0D%0AThank%20you." class="font-semibold text-brand-600 hover:text-brand-700 transition-colors">Contact IT Support</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>