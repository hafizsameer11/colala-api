<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Colala Mall</title>
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
            padding: 40px 20px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .email-header p {
            color: #ffffff;
            font-size: 16px;
            margin-top: 10px;
            opacity: 0.95;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            color: #333333;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .message {
            color: #666666;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 25px;
        }
        .features {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        .features-title {
            color: #dc3545;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .feature-icon {
            color: #dc3545;
            font-size: 20px;
            margin-right: 12px;
            margin-top: 2px;
        }
        .feature-text {
            color: #555555;
            font-size: 15px;
            line-height: 1.6;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 35px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            margin: 25px 0;
            text-align: center;
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        .cta-container {
            text-align: center;
            margin: 30px 0;
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
        .social-links {
            margin-top: 20px;
        }
        .social-links a {
            color: #dc3545;
            text-decoration: none;
            margin: 0 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>🎉 Welcome to Colala Mall!</h1>
            <p>Your shopping journey begins here</p>
        </div>
        
        <div class="email-body">
            <div class="greeting">Hello {{ $userName }},</div>
            
            <div class="message">
                We're thrilled to have you join the Colala Mall family! Your account has been successfully created, and you're now ready to explore thousands of products from trusted sellers.
            </div>
            
            <div class="features">
                <div class="features-title">What you can do on Colala Mall:</div>
                
                <div class="feature-item">
                    <span class="feature-icon">🛍️</span>
                    <div class="feature-text">
                        <strong>Shop from thousands of products</strong> across various categories
                    </div>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">💳</span>
                    <div class="feature-text">
                        <strong>Secure payments</strong> with multiple payment options
                    </div>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">🚚</span>
                    <div class="feature-text">
                        <strong>Fast and reliable delivery</strong> to your doorstep
                    </div>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">⭐</span>
                    <div class="feature-text">
                        <strong>Earn rewards and loyalty points</strong> on every purchase
                    </div>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">💬</span>
                    <div class="feature-text">
                        <strong>Chat directly with sellers</strong> for any questions
                    </div>
                </div>
            </div>
            
            <div class="cta-container">
                <a href="{{ $appUrl ?? 'https://colala.hmstech.xyz' }}" class="cta-button">Start Shopping Now</a>
            </div>
            
            <div class="message">
                If you have any questions or need assistance, our support team is here to help. Simply reach out through the app or contact us directly.
            </div>
            
            <div class="message" style="margin-top: 30px;">
                Happy shopping!<br>
                <strong>The Colala Mall Team</strong>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-text">
                This is an automated welcome message. Please do not reply to this email.
            </div>
            <div class="divider"></div>
            <div class="footer-brand">Colala Mall</div>
            <div class="footer-text" style="margin-top: 10px; font-size: 12px;">
                Your trusted online marketplace
            </div>
            <div class="social-links">
                <a href="#">Help Center</a> | 
                <a href="#">Contact Us</a> | 
                <a href="#">Terms & Conditions</a>
            </div>
        </div>
    </div>
</body>
</html>

