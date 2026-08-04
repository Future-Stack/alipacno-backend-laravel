<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verification Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 550px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }
        .header h1 {
            color: #111827;
            font-size: 24px;
            margin: 0;
        }
        .content {
            padding: 30px 0;
            text-align: center;
        }
        .content p {
            color: #4b5563;
            font-size: 16px;
            line-height: 1.6;
        }
        .otp-box {
            display: inline-block;
            background: #f3f4f6;
            border: 2px dashed #3b82f6;
            border-radius: 10px;
            padding: 18px 36px;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 10px;
            color: #1d4ed8;
            margin: 24px 0;
        }
        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
            border-top: 1px solid #eeeeee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Alipacno Platform</h1>
        </div>
        <div class="content">
            <p>Hello, <strong>{{ $name }}</strong>!</p>
            @if($type === 'forgot_password')
                <p>We received a request to reset your password. Use the 5-digit verification code below to proceed:</p>
            @else
                <p>Thank you for registering! Use the 5-digit verification code below to complete your email verification:</p>
            @endif

            <div class="otp-box">{{ $otp }}</div>

            <p style="font-size: 14px; color: #6b7280;">This code is valid for <strong>10 minutes</strong>. If you did not request this code, please ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Alipacno. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
PHP,Description:
