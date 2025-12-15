<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset Code</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .header { background-color: #007bff; color: white; padding: 10px 0; text-align: center; border-radius: 6px 6px 0 0; }
        .content { padding: 20px 0; line-height: 1.6; color: #333333; }
        .otp-code { font-size: 24px; font-weight: bold; color: #dc3545; display: block; text-align: center; margin: 20px 0; padding: 10px; border: 2px dashed #dc3545; border-radius: 4px; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #777777; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Password Reset Request</h2>
        </div>
        <div class="content">
            <p>Hello <?php echo e($user->firstname); ?>,</p>
            <p>You recently requested to reset the password for your account. Please use the following One-Time Password (OTP) code to proceed with your password reset:</p>

            <span class="otp-code"><?php echo e($otp); ?></span>

            <p>This code is valid for a short time. Do not share this code with anyone.</p>
            
            <p>If you did not request a password reset, please ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo e(date('Y')); ?> Equipment Inventory System</p>
        </div>
    </div>
</body>
</html><?php /**PATH D:\Installations\xampp\htdocs\joshua\laravel\equipment\resources\views/emails/password_reset_otp.blade.php ENDPATH**/ ?>