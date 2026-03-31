<?php

namespace EorBah545\Eorbahapi\responses;

class MailerSmtp {
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $fromEmail;
    private $fromName;
    
    public function __construct(array $config) {
        $this->smtpHost = $config['host'];
        $this->smtpPort = $config['port'] ?? 587;
        $this->smtpUser = $config['user'];
        $this->smtpPass = $config['pass'];
        $this->fromEmail = $config['from_email'];
        $this->fromName = $config['from_name'] ?? '';
    }
    
    public function send(
        string $to, 
        string $subject, 
        string $body, 
        bool $isHtml = true,
        array $attachments = []
    ): bool {
        $mail = new PHPMailer(true);
        
        try {
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUser;
            $mail->Password = $this->smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            
            // Destinataires
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Contenu
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Pièces jointes
            foreach ($attachments as $attachment) {
                $mail->addAttachment(
                    $attachment['path'],
                    $attachment['name'] ?? basename($attachment['path'])
                );
            }
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Erreur d'envoi d'email: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendTemplate(
        string $to, 
        string $templateName, 
        array $data = [],
        array $attachments = []
    ): bool {
        $templatePath = "templates/emails/$templateName.html";
        
        if (!file_exists($templatePath)) {
            throw new Exception("Template $templateName not found");
        }
        
        $template = file_get_contents($templatePath);
        
        // Remplacement des variables
        foreach ($data as $key => $value) {
            $template = str_replace("{{ $key }}", $value, $template);
        }
        
        $subject = $data['subject'] ?? 'Notification';
        
        return $this->send($to, $subject, $template, true, $attachments);
    }
}