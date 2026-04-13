<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../phpmailer/vendor/autoload.php';

function sendCustomEmail($recMail, $recName, $positionType)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'stremaxfoundation01@gmail.com';
        $mail->Password = 'app passwods here';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients and Content
        $mail->setFrom('stremaxfoundation01@gmail.com', 'Stremax Foundation');
        $mail->addAddress($recMail, $recName);
        $mail->addCC('stremaxfoundation01@gmail.com', 'Stremax Foundation');

        $mail->addAttachment('../documents/Prospectus.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'Application confirmed for ' . $positionType;

        $mail->addEmbeddedImage('../img/sf_logo.png', 'header_logo');

        // 2. Define the HTML Body
        $mail->Body = <<<HTML
<div style="background-color: #354983; padding: 20px; font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #E9F5FF; padding: 20px; border: 1px solid #e0e0e0; border-radius: 16px">
        
        <!-- Logo Section -->
        <div style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 16px;">
            <img src="cid:header_logo" alt="Logo" style="height: 40px; display: block;">
            <h1 style="color: #354983; margin: 0;">Stremax Foundation</h1>
        </div>

        <!-- Text Content Section -->
        <div style="color: #333333; line-height: 1.6; font-size: 15px;">
            <p>Dear $recName,</p>
            <p>
                Your application process for {$positionType} has been completed.
            </p>
            <p>Our team will contact you shortly.</p>
            <p>You may go through the Prospectus (attached below).</p>
            <p><b>चयन का आधार:</b> फॉर्म भरने के बाद, 10-15 दिनों के भीतर आपका ऑनलाइन इंटरव्यू होगा और उस इंटरव्यू
                    के आधार
                    पर आपका चयन सुनिश्चित किया जाएगा।</p>
            
            <p style="margin-top: 30px;">Regards,<br><strong>Team Stremax Foundation</strong></p>
        </div>
        
    </div>
</div>
HTML;

        // 3. Plain text fallback for old email clients
        $mail->AltBody = "Dear {$recName}, Your application process for {$positionType} has been completed.";

        $mail->send();
        return true; // Return true on success

    } catch (Exception $e) {
        return "Message could not be sent: {$mail->ErrorInfo}"; // Return error message on failure
    }
}
