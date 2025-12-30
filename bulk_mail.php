<?php
// No configuration hardcoded - all from form

// Load PHPMailer (adjust path if needed)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if using Composer or manual installation
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

// Function to send email via Gmail using PHPMailer
function sendEmail($to, $toName, $subject, $message, $fromEmail, $fromName, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $fromEmail;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Sender and recipient
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->CharSet = 'UTF-8';
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// Process form submission
$status = '';
$sentCount = 0;
$failedCount = 0;
$totalCount = 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enable long execution time and output buffering for live updates
    ini_set('max_execution_time', 0);
    ob_implicit_flush(true);
    ob_end_flush();
    
    $gmailAddress = trim($_POST['gmail_address'] ?? '');
    $appPassword = trim($_POST['app_password'] ?? '');
    $yourName = trim($_POST['your_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $template = trim($_POST['template'] ?? '');
    $csvUrl = trim($_POST['csv_url'] ?? '');
    
    // Validation
    if (empty($gmailAddress) || empty($appPassword) || empty($yourName) || empty($subject) || empty($template) || empty($csvUrl)) {
        $error = 'Please fill all fields.';
    } elseif (!filter_var($gmailAddress, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid Gmail address.';
    } else {
        // Fetch CSV from Google Sheet URL
        $csvContent = @file_get_contents($csvUrl);
        if ($csvContent === false) {
            $error = 'Failed to fetch CSV from URL. Ensure the Google Sheet is published to web as CSV (File > Share > Publish to web > CSV format).';
        } else {
            $lines = explode("\n", $csvContent);
            $totalCount = count($lines) - 1; // Subtract header
            
            // Show initial status
            echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sending Emails...</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-label {
            color: #718096;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .stat-value.success {
            color: #48bb78;
        }
        
        .stat-value.error {
            color: #f56565;
        }
        
        .stat-value.pending {
            color: #667eea;
        }
        
        .progress-container {
            padding: 0 30px 20px 30px;
            background: #f7fafc;
        }
        
        .progress-bar-wrapper {
            background: #e2e8f0;
            border-radius: 50px;
            height: 30px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
            border-radius: 50px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
        }
        
        .live-feed {
            padding: 30px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .live-feed-title {
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pulse {
            width: 12px;
            height: 12px;
            background: #48bb78;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }
        
        .email-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .email-item.success {
            background: #f0fff4;
            border-left-color: #48bb78;
        }
        
        .email-item.error {
            background: #fff5f5;
            border-left-color: #f56565;
        }
        
        .email-item.processing {
            background: #ebf4ff;
            border-left-color: #4299e1;
        }
        
        .email-icon {
            font-size: 20px;
            line-height: 1;
        }
        
        .email-content {
            flex: 1;
        }
        
        .email-company {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .email-address {
            font-size: 13px;
            color: #718096;
        }
        
        .email-timestamp {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 4px;
        }
        
        .completion-card {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            margin: 30px;
        }
        
        .completion-card h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .completion-card p {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .completion-card button {
            background: white;
            color: #38a169;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .completion-card button:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Sending Emails Live</h1>
            <p>Real-time status updates</p>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total</div>
                <div class="stat-value pending" id="totalCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sent</div>
                <div class="stat-value success" id="sentCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Failed</div>
                <div class="stat-value error" id="failedCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Remaining</div>
                <div class="stat-value" id="remainingCount">0</div>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
            </div>
        </div>
        
        <div class="live-feed">
            <div class="live-feed-title">
                <span class="pulse"></span>
                Live Updates
            </div>
            <div id="emailFeed"></div>
        </div>
    </div>
    
    <script>
        function updateStats(sent, failed, total) {
            document.getElementById("sentCount").textContent = sent;
            document.getElementById("failedCount").textContent = failed;
            document.getElementById("totalCount").textContent = total;
            document.getElementById("remainingCount").textContent = total - (sent + failed);
            
            const progress = total > 0 ? Math.round(((sent + failed) / total) * 100) : 0;
            const progressBar = document.getElementById("progressBar");
            progressBar.style.width = progress + "%";
            progressBar.textContent = progress + "%";
        }
        
        function addEmailUpdate(company, email, status, message = "") {
            const feed = document.getElementById("emailFeed");
            const item = document.createElement("div");
            item.className = "email-item " + status;
            
            const icon = status === "success" ? "✅" : status === "error" ? "❌" : "⏳";
            const timestamp = new Date().toLocaleTimeString();
            
            item.innerHTML = `
                <div class="email-icon">${icon}</div>
                <div class="email-content">
                    <div class="email-company">${company}</div>
                    <div class="email-address">${email}</div>
                    ${message ? `<div class="email-address" style="color: #f56565;">${message}</div>` : ""}
                    <div class="email-timestamp">${timestamp}</div>
                </div>
            `;
            
            feed.insertBefore(item, feed.firstChild);
        }
        
        function showCompletion(sent, failed, total) {
            const container = document.querySelector(".container");
            const completionCard = document.createElement("div");
            completionCard.className = "completion-card";
            completionCard.innerHTML = `
                <h2>🎉 Bulk Email Campaign Completed!</h2>
                <p>Successfully sent ${sent} out of ${total} emails</p>
                ${failed > 0 ? `<p style="color: #fff3cd;">⚠️ ${failed} emails failed to send</p>` : ""}
                <button onclick="window.location.href=window.location.pathname">Send Another Campaign</button>
            `;
            container.appendChild(completionCard);
            
            // Remove pulse animation
            document.querySelector(".pulse").style.display = "none";
        }
    </script>';
            
            flush();
            
            // Initialize stats
            echo "<script>updateStats(0, 0, $totalCount);</script>";
            flush();
            
            // Parse CSV lines
            $headerSkipped = false;
            $processed = 0;
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                $data = str_getcsv($line);
                if (!$headerSkipped && count($data) >= 2 && trim($data[0]) === 'Company Name' && trim($data[1]) === 'Email') {
                    $headerSkipped = true;
                    continue;
                }
                
                if (count($data) < 2) continue;
                
                $companyName = trim($data[0]);
                $email = trim($data[1]);
                
                if (empty($companyName) || empty($email)) continue;
                
                // Show processing status
                echo "<script>addEmailUpdate('" . addslashes($companyName) . "', '" . addslashes($email) . "', 'processing');</script>";
                flush();
                
                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failedCount++;
                    echo "<script>
                        addEmailUpdate('" . addslashes($companyName) . "', '" . addslashes($email) . "', 'error', 'Invalid email address');
                        updateStats($sentCount, $failedCount, $totalCount);
                    </script>";
                    flush();
                    continue;
                }
                
                // Personalize message
                $personalizedMessage = str_replace('{company}', $companyName, $template);
                
                $result = sendEmail(
                    $email,
                    $companyName,
                    $subject,
                    $personalizedMessage,
                    $gmailAddress,
                    $yourName,
                    $appPassword
                );
                
                if ($result === true) {
                    $sentCount++;
                    echo "<script>
                        addEmailUpdate('" . addslashes($companyName) . "', '" . addslashes($email) . "', 'success');
                        updateStats($sentCount, $failedCount, $totalCount);
                    </script>";
                } else {
                    $failedCount++;
                    $errorMsg = str_replace("'", "\\'", strip_tags($result));
                    echo "<script>
                        addEmailUpdate('" . addslashes($companyName) . "', '" . addslashes($email) . "', 'error', '" . $errorMsg . "');
                        updateStats($sentCount, $failedCount, $totalCount);
                    </script>";
                }
                
                flush();
                
                // Add delay to avoid rate limits
                sleep(2);
            }
            
            // Show completion
            echo "<script>showCompletion($sentCount, $failedCount, $totalCount);</script>";
            flush();
            
            echo '</body></html>';
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Bulk Email Sender</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 700px;
            width: 100%;
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="email"], input[type="text"], input[type="url"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 14px;
            min-height: 200px;
            resize: vertical;
            transition: border-color 0.3s;
        }
        
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .error {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
        
        .info-box {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            color: #2d3748;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .info-box p, .info-box ol {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .info-box ol {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        .warning-box {
            background: #fffaf0;
            border-left: 4px solid #ed8936;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        
        .warning-box h3 {
            color: #c05621;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .warning-box p {
            color: #7c2d12;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        
        code {
            background: #2d3748;
            color: #48bb78;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Live Bulk Email Sender</h1>
        <p class="subtitle">Send personalized emails with real-time status updates</p>
        
        <div class="warning-box">
            <h3>⚠️ Important Setup</h3>
            <p><strong>Gmail:</strong> Enable 2FA, then generate App Password (Security > App Passwords > Mail)</p>
            <p><strong>Google Sheet:</strong> File > Share > Publish to web > Link > CSV > Publish. Copy the CSV URL</p>
            <p><strong>Limits:</strong> Gmail allows ~500 emails/day. Script adds 2s delay between emails</p>
        </div>
        
        <div class="info-box">
            <h3>📋 CSV Format Required</h3>
            <ol>
                <li><strong>Column A:</strong> Company Name</li>
                <li><strong>Column B:</strong> Email</li>
            </ol>
            <p style="font-size: 12px; color: #718096; margin-top: 10px; font-family: monospace;">
                Company Name,Email<br>
                Tech Solutions,info@techsolutions.com<br>
                Digital Corp,contact@digitalcorp.com
            </p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" id="emailForm">
            <div class="form-group">
                <label for="gmail_address">📧 Your Gmail Address</label>
                <input type="email" name="gmail_address" id="gmail_address" required placeholder="your.email@gmail.com">
            </div>
            
            <div class="form-group">
                <label for="app_password">🔑 Gmail App Password</label>
                <input type="password" name="app_password" id="app_password" required placeholder="xxxx xxxx xxxx xxxx">
            </div>
            
            <div class="form-group">
                <label for="your_name">👤 Your Name</label>
                <input type="text" name="your_name" id="your_name" required placeholder="Ayushi Gautam">
            </div>
            
            <div class="form-group">
                <label for="subject">📝 Email Subject</label>
                <input type="text" name="subject" id="subject" required placeholder="Web Development & SEO Services">
            </div>
            
            <div class="form-group">
                <label for="template">✉️ Email Body (use {company} for personalization)</label>
                <textarea name="template" id="template" required>&lt;html&gt;
&lt;body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'&gt;
    &lt;h2 style='color: #667eea;'&gt;👋 Hello {company}!&lt;/h2&gt;
    
    &lt;p&gt;I hope this email finds you well.&lt;/p&gt;
    
    &lt;p&gt;My name is &lt;strong&gt;Ayushi Gautam&lt;/strong&gt;. I am a &lt;strong&gt;Freelance Full Stack Developer and SEO Specialist&lt;/strong&gt;...&lt;/p&gt;
    
    &lt;p&gt;&lt;strong&gt;Best regards,&lt;/strong&gt;&lt;/p&gt;
    &lt;p&gt;&lt;strong&gt;Ayushi Gautam&lt;/strong&gt;&lt;/p&gt;
&lt;/body&gt;
&lt;/html&gt;</textarea>
            </div>
            
            <div class="form-group">
                <label for="csv_url">🔗 Google Sheet CSV URL</label>
                <input type="url" name="csv_url" id="csv_url" required placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv">
            </div>
            
            <button type="submit">🚀 Start Sending (Live Updates)</button>
        </form>
        
        <div class="info-box" style="margin-top: 25px;">
            <h3>✨ What Happens Next</h3>
            <p>After clicking "Start Sending", you'll see a beautiful live dashboard showing real-time progress with counters, progress bar, and individual email statuses as they're sent.</p>
        </div>
    </div>
</body>
</html>