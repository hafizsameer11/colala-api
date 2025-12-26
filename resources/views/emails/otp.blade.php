<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Colala Mall</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            color: #333333;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .message {
            color: #666666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .otp-container {
            background-color: #f8f9fa;
            border: 2px dashed #dc3545;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-label {
            color: #666666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-code {
            color: #dc3545;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        .otp-expiry {
            color: #999999;
            font-size: 13px;
            margin-top: 15px;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .warning-text {
            color: #856404;
            font-size: 14px;
            line-height: 1.5;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer-text {
            color: #666666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .footer-brand {
            color: #dc3545;
            font-size: 16px;
            font-weight: 700;
            margin-top: 10px;
        }
        .divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Colala Mall</h1>
        </div>
        
        <div class="email-body">
            <div class="greeting">Hello,</div>
            
            <div class="message">
                Your One-Time Password (OTP) for account verification is:
            </div>
            
            <div class="otp-container">
                <div class="otp-label">Your OTP Code</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">This code will expire in 5 minutes</div>
            </div>
            
            <div class="warning">
                <div class="warning-text">
                    <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Colala Mall staff will never ask for your OTP code.
                </div>
            </div>
            
            <div class="message">
                If you did not request this verification code, please ignore this email or contact our support team immediately.
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-text">
                This is an automated message. Please do not reply to this email.
            </div>
            <div class="divider"></div>
            <div class="footer-brand">Colala Mall</div>
            <div class="footer-text" style="margin-top: 10px; font-size: 12px;">
                Your trusted online marketplace
            </div>
        </div>
    </div>
</body>
</html>
