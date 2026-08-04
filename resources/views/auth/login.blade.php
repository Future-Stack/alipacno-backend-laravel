<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pacino's The Finest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #151517;
            --card-bg: #1E1E22;
            --input-bg: #28282D;
            --input-border: #38383E;
            --input-focus: #F95700;
            --primary-orange: #F95700;
            --primary-hover: #E04D00;
            --text-white: #FFFFFF;
            --text-muted: #A0A0AA;
            --text-dim: #6E6E78;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 50% 30%, rgba(249, 87, 0, 0.08) 0%, transparent 60%),
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.015) 0px, rgba(255, 255, 255, 0.015) 1px, transparent 1px, transparent 20px);
            color: var(--text-white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        /* Pacino's Logo Badge */
        .logo-container {
            text-align: center;
            margin-bottom: 28px;
        }

        .pacinos-logo {
            display: inline-block;
            position: relative;
            width: 180px;
            height: auto;
        }

        /* View Animation */
        .auth-view {
            display: none;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .auth-view.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .title {
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-white);
        }

        .form-input {
            width: 100%;
            height: 50px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 0 16px;
            color: var(--text-white);
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input::placeholder {
            color: var(--text-dim);
        }

        .form-input:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(249, 87, 0, 0.2);
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            font-size: 14px;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            color: var(--text-white);
        }

        .checkbox-container input {
            display: none;
        }

        .checkmark {
            width: 18px;
            height: 18px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 4px;
            margin-right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, border-color 0.2s;
        }

        .checkbox-container input:checked ~ .checkmark {
            background: var(--primary-orange);
            border-color: var(--primary-orange);
        }

        .checkmark::after {
            content: "";
            width: 5px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
            display: none;
        }

        .checkbox-container input:checked ~ .checkmark::after {
            display: block;
        }

        .forgot-link, .toggle-link {
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link:hover, .toggle-link:hover {
            color: var(--primary-orange);
        }

        .btn-primary {
            width: 100%;
            height: 52px;
            background: var(--primary-orange);
            color: #FFFFFF;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(249, 87, 0, 0.35);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .btn-primary:disabled {
            background: #55555C;
            cursor: not-allowed;
            box-shadow: none;
        }

        .footer-link-text {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .footer-link-text a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link-text a:hover {
            text-decoration: underline;
        }

        /* OTP Code Boxes */
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 28px;
        }

        .otp-box {
            width: 52px;
            height: 56px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-white);
            text-align: center;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .otp-box:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(249, 87, 0, 0.25);
        }

        /* Resend Section */
        .resend-section {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .timer-text {
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .timer-text strong {
            color: var(--primary-orange);
            font-weight: 700;
        }

        .resend-btn {
            background: none;
            border: none;
            color: var(--primary-orange);
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
        }

        .resend-btn:disabled {
            color: var(--text-dim);
            cursor: not-allowed;
        }

        /* Encrypted Footer Notice */
        .security-footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
        }

        /* Alert Toast */
        .alert-banner {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
        }
        .alert-error {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #FF6B6B;
        }
        .alert-success {
            background: rgba(52, 199, 89, 0.15);
            border: 1px solid rgba(52, 199, 89, 0.3);
            color: #30D158;
        }

        .switch-method-btn {
            background: none;
            border: none;
            color: var(--primary-orange);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
            text-decoration: underline;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <!-- Pacino's Logo Badge Header -->
        <div class="logo-container">
            <svg class="pacinos-logo" viewBox="0 0 240 120" xmlns="http://www.w3.org/2000/svg">
                <!-- Badge Wings -->
                <path d="M 10 50 Q 60 40 90 45 L 90 65 Q 50 65 10 50 Z" fill="#F95700"/>
                <path d="M 230 50 Q 180 40 150 45 L 150 65 Q 190 65 230 50 Z" fill="#F95700"/>
                <!-- Outer Ring -->
                <circle cx="120" cy="60" r="55" fill="#141416" stroke="#F95700" stroke-width="4"/>
                <circle cx="120" cy="60" r="48" fill="#1E1E22" stroke="#FFFFFF" stroke-width="1.5" stroke-dasharray="3,3"/>
                <!-- Center Banner -->
                <rect x="35" y="42" width="170" height="36" rx="8" fill="#F95700" stroke="#FFFFFF" stroke-width="2"/>
                <!-- Brand Text -->
                <text x="120" y="66" font-family="'Plus Jakarta Sans', sans-serif" font-size="24" font-weight="900" fill="#FFFFFF" text-anchor="middle" letter-spacing="1">PACINO'S</text>
                <!-- Top Arc Text -->
                <path id="textArcTop" d="M 75 52 A 42 42 0 0 1 165 52" fill="none"/>
                <text font-size="7.5" font-weight="800" fill="#FFFFFF" letter-spacing="1.5">
                    <textPath href="#textArcTop" startOffset="50%" text-anchor="middle">★ PIZZA ★ BURGER ★</textPath>
                </text>
                <!-- Bottom Subtext -->
                <text x="120" y="87" font-size="7.5" font-weight="700" fill="#FFFFFF" text-anchor="middle" letter-spacing="1">THE FINEST</text>
                <text x="120" y="96" font-size="6" font-weight="600" fill="#A0A0AA" text-anchor="middle">HOTDOG ★ MILKSHAKE</text>
            </svg>
        </div>

        <div id="alertBanner" class="alert-banner"></div>

        <!-- SCREEN 1: LOGIN TO ACCOUNT -->
        <div id="viewAccountLogin" class="auth-view active">
            <h1 class="title">Login to Account</h1>
            <p class="subtitle">Enter your details to access your account</p>

            <form id="formAccountLogin" onsubmit="handleAccountLogin(event)">
                <div class="form-group">
                    <label class="form-label" for="loginInput">Email or Phone Number</label>
                    <input type="text" id="loginInput" class="form-input" placeholder="enter your email or phone" required>
                </div>

                <div id="passwordGroup" class="form-group">
                    <label class="form-label" for="passwordInput">Password</label>
                    <input type="password" id="passwordInput" class="form-input" placeholder="enter password">
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" id="rememberPassword" checked>
                        <span class="checkmark"></span>
                        Remember Password
                    </label>
                    <a href="#" class="forgot-link" onclick="handleForgotPassword(event)">Forgot Password?</a>
                </div>

                <button type="submit" id="btnLoginSubmit" class="btn-primary">
                    <span class="btn-text">Sign in</span>
                </button>
            </form>

            <div style="text-align: center; margin-top: 16px;">
                <button type="button" class="switch-method-btn" onclick="switchToPhoneOtpView()">
                    Login with Phone Verification OTP
                </button>
            </div>

            <div class="footer-link-text">
                Already have an account? <a href="#" onclick="alert('Registration route active at /api/v1/auth/register')">Sign Up Here</a>
            </div>
        </div>

        <!-- SCREEN 2: VERIFY PHONE NUMBER (IMAGE 1 FLOW) -->
        <div id="viewPhoneVerify" class="auth-view">
            <h1 class="title">Verify Phone Number</h1>
            <p id="otpSentSubtitle" class="subtitle">A 5-digit code has been sent to <span id="displayPhoneNumber">+1 (xxx) xxx-xxxx</span></p>

            <form id="formOtpVerify" onsubmit="handleOtpSubmit(event)">
                <!-- 5 Digit OTP Inputs -->
                <div class="otp-inputs">
                    <input type="text" maxlength="1" class="otp-box" data-index="0" inputmode="numeric" pattern="[0-9]*" autofocus>
                    <input type="text" maxlength="1" class="otp-box" data-index="1" inputmode="numeric" pattern="[0-9]*">
                    <input type="text" maxlength="1" class="otp-box" data-index="2" inputmode="numeric" pattern="[0-9]*">
                    <input type="text" maxlength="1" class="otp-box" data-index="3" inputmode="numeric" pattern="[0-9]*">
                    <input type="text" maxlength="1" class="otp-box" data-index="4" inputmode="numeric" pattern="[0-9]*">
                </div>

                <button type="submit" id="btnOtpLogin" class="btn-primary">
                    <span class="btn-text">Login</span>
                </button>
            </form>

            <div class="resend-section">
                <div class="timer-text">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Resend code in <strong id="timerDisplay">00:54</strong>
                </div>
                <button type="button" id="btnResendCode" class="resend-btn" onclick="handleResendCode()" disabled>
                    Resend Code
                </button>
            </div>

            <div style="text-align: center; margin-top: 16px;">
                <button type="button" class="switch-method-btn" onclick="switchToAccountLoginView()">
                    ← Back to Email & Password Login
                </button>
            </div>

            <div class="security-footer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                END-TO-END ENCRYPTED VERIFICATION
            </div>
        </div>
    </div>

    <script>
        let currentPhone = "";
        let countdownTimer = null;
        let timeLeft = 54;

        // OTP Box Input Auto-Traversal
        const otpBoxes = document.querySelectorAll('.otp-box');

        otpBoxes.forEach((box, idx) => {
            box.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value.length >= 1) {
                    e.target.value = value.charAt(value.length - 1);
                    if (idx < otpBoxes.length - 1) {
                        otpBoxes[idx + 1].focus();
                    }
                }
            });

            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && idx > 0) {
                    otpBoxes[idx - 1].focus();
                }
            });

            box.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = (e.clipboardData || window.clipboardData).getData('text').trim();
                if (/^\d{1,5}$/.test(pastedData)) {
                    const digits = pastedData.split('');
                    digits.forEach((digit, i) => {
                        if (otpBoxes[i]) otpBoxes[i].value = digit;
                    });
                    if (otpBoxes[Math.min(digits.length, 4)]) {
                        otpBoxes[Math.min(digits.length, 4)].focus();
                    }
                }
            });
        });

        function showAlert(msg, isError = false) {
            const banner = document.getElementById('alertBanner');
            banner.className = `alert-banner ${isError ? 'alert-error' : 'alert-success'}`;
            banner.innerText = msg;
            banner.style.display = 'block';
        }

        function clearAlert() {
            const banner = document.getElementById('alertBanner');
            banner.style.display = 'none';
        }

        function switchView(viewId) {
            clearAlert();
            document.querySelectorAll('.auth-view').forEach(el => el.classList.remove('active'));
            document.getElementById(viewId).classList.add('active');
        }

        function switchToPhoneOtpView() {
            const loginVal = document.getElementById('loginInput').value.trim();
            if (loginVal && !loginVal.includes('@')) {
                currentPhone = loginVal;
                sendPhoneOtpRequest(currentPhone);
            } else {
                const phonePrompt = prompt("Enter your Phone Number for verification:", "+1234567890");
                if (phonePrompt) {
                    currentPhone = phonePrompt.trim();
                    sendPhoneOtpRequest(currentPhone);
                }
            }
        }

        function switchToAccountLoginView() {
            clearInterval(countdownTimer);
            switchView('viewAccountLogin');
        }

        function startResendTimer(seconds = 54) {
            clearInterval(countdownTimer);
            timeLeft = seconds;
            const resendBtn = document.getElementById('btnResendCode');
            const timerDisplay = document.getElementById('timerDisplay');
            resendBtn.disabled = true;

            const updateDisplay = () => {
                const mins = String(Math.floor(timeLeft / 60)).padStart(2, '0');
                const secs = String(timeLeft % 60).padStart(2, '0');
                timerDisplay.innerText = `${mins}:${secs}`;
            };

            updateDisplay();
            countdownTimer = setInterval(() => {
                timeLeft--;
                if (timeLeft <= 0) {
                    clearInterval(countdownTimer);
                    timerDisplay.innerText = "00:00";
                    resendBtn.disabled = false;
                } else {
                    updateDisplay();
                }
            }, 1000);
        }

        // Send Phone OTP API Request
        async function sendPhoneOtpRequest(phone) {
            showAlert("Sending 5-digit verification code...", false);
            try {
                const response = await fetch('/api/v1/auth/phone/send-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: phone })
                });
                const data = await response.json();

                if (response.ok) {
                    currentPhone = phone;
                    document.getElementById('displayPhoneNumber').innerText = data.masked_phone || phone;
                    switchView('viewPhoneVerify');
                    startResendTimer(54);
                    otpBoxes[0].focus();
                    let msg = data.message;
                    if (data.otp_code) {
                        msg += ` [Testing OTP Code: ${data.otp_code}]`;
                    }
                    showAlert(msg, false);
                } else {
                    showAlert(data.message || 'Failed to send OTP.', true);
                }
            } catch (err) {
                showAlert('Network connection error. Please try again.', true);
            }
        }

        // Submit Standard Account Login
        async function handleAccountLogin(e) {
            e.preventDefault();
            clearAlert();
            const loginVal = document.getElementById('loginInput').value.trim();
            const passwordVal = document.getElementById('passwordInput').value;

            if (!passwordVal) {
                // If user entered phone without password, redirect to Phone OTP flow
                sendPhoneOtpRequest(loginVal);
                return;
            }

            const btn = document.getElementById('btnLoginSubmit');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Signing in...';

            try {
                const response = await fetch('/api/v1/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ login: loginVal, password: passwordVal })
                });
                const data = await response.json();

                if (response.ok) {
                    showAlert("Login successful! Redirecting...", false);
                    localStorage.setItem('auth_token', data.access_token);
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 1000);
                } else {
                    showAlert(data.message || 'Invalid credentials.', true);
                    btn.disabled = false;
                    btn.innerHTML = '<span class="btn-text">Sign in</span>';
                }
            } catch (err) {
                showAlert('Network error. Please try again.', true);
                btn.disabled = false;
                btn.innerHTML = '<span class="btn-text">Sign in</span>';
            }
        }

        // Submit OTP Code
        async function handleOtpSubmit(e) {
            e.preventDefault();
            clearAlert();
            const code = Array.from(otpBoxes).map(box => box.value).join('');
            if (code.length < 5) {
                showAlert('Please enter the full 5-digit verification code.', true);
                return;
            }

            const btn = document.getElementById('btnOtpLogin');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Verifying...';

            try {
                const response = await fetch('/api/v1/auth/phone/verify-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: currentPhone, otp: code })
                });
                const data = await response.json();

                if (response.ok) {
                    showAlert("Phone verified successfully! Logging in...", false);
                    localStorage.setItem('auth_token', data.access_token);
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 1000);
                } else {
                    showAlert(data.message || 'Invalid verification code.', true);
                    btn.disabled = false;
                    btn.innerHTML = '<span class="btn-text">Login</span>';
                }
            } catch (err) {
                showAlert('Network error. Please try again.', true);
                btn.disabled = false;
                btn.innerHTML = '<span class="btn-text">Login</span>';
            }
        }

        // Resend Code Trigger
        async function handleResendCode() {
            clearAlert();
            try {
                const response = await fetch('/api/v1/auth/phone/resend-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: currentPhone })
                });
                const data = await response.json();

                if (response.ok) {
                    let msg = data.message;
                    if (data.otp_code) {
                        msg += ` [New OTP Code: ${data.otp_code}]`;
                    }
                    showAlert(msg, false);
                    startResendTimer(54);
                } else {
                    showAlert(data.message || 'Failed to resend code.', true);
                }
            } catch (err) {
                showAlert('Network error. Please try again.', true);
            }
        }

        function handleForgotPassword(e) {
            e.preventDefault();
            const email = prompt("Enter your registered email for password reset:");
            if (email) {
                fetch('/api/v1/auth/forgot-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email: email })
                })
                .then(res => res.json())
                .then(data => showAlert(data.message || "Password reset OTP sent to email."))
                .catch(() => showAlert("Failed to request password reset.", true));
            }
        }
    </script>
</body>
</html>
