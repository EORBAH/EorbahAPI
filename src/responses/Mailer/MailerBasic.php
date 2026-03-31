<?php

namespace EorBah545\Eorbahapi\responses;

class MailerBasic {
    private $fromEmail;
    private $fromName;
    
    public function __construct(array $config) {
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
        try {
            // Générer un boundary unique pour les parties MIME
            $boundary = md5(uniqid(time()));
            
            // Préparer les en-têtes de base
            $headers = [];
            $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
            $headers[] = "MIME-Version: 1.0";
            
            // Corps de l'e-mail
            $message = '';
            
            if (empty($attachments)) {
                // Cas simple : pas de pièces jointes
                $headers[] = $isHtml ? "Content-Type: text/html; charset=UTF-8" : "Content-Type: text/plain; charset=UTF-8";
                $message = $body;
            } else {
                // Cas avec pièces jointes : utiliser multipart/mixed
                $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
                
                // Partie texte (HTML ou texte brut)
                $message .= "--$boundary\r\n";
                $message .= $isHtml ? "Content-Type: text/html; charset=UTF-8\r\n" : "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "\r\n";
                $message .= "$body\r\n";
                
                // Ajouter les pièces jointes
                foreach ($attachments as $attachment) {
                    $filePath = $attachment['path'];
                    $fileName = $attachment['name'] ?? basename($filePath);
                    
                    if (!file_exists($filePath)) {
                        throw new Exception("Fichier joint $filePath non trouvé");
                    }
                    
                    $fileContent = file_get_contents($filePath);
                    $fileEncoded = chunk_split(base64_encode($fileContent));
                    
                    $message .= "--$boundary\r\n";
                    $message .= "Content-Type: application/octet-stream; name=\"$fileName\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
                    $message .= "\r\n";
                    $message .= "$fileEncoded\r\n";
                }
                
                $message .= "--$boundary--\r\n";
            }
            
            // Envoi de l'e-mail
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            
            if (!$result) {
                error_log("Échec de l'envoi de l'e-mail à $to");
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur d'envoi d'email: " . $e->getMessage());
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