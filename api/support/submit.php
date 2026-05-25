<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::requireAuth();
requirePost();

try {
    $authUser = Auth::user();
    $userId   = (int)$authUser['id'];
    $fullName = trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? ''));
    $email    = $authUser['email'] ?? '';

    $data        = requestData();
    $category    = trim((string)($data['category']    ?? ''));
    $priority    = trim((string)($data['priority']    ?? 'normal'));
    $subject     = trim((string)($data['subject']     ?? ''));
    $description = trim((string)($data['description'] ?? ''));

    if ($category === '' || $subject === '' || $description === '') {
        ob_end_clean();
        jsonResponse(['success' => false, 'message' => 'Category, subject, and description are required.'], 422);
    }

    $allowedPriorities = ['normal', 'high', 'urgent'];
    if (!in_array($priority, $allowedPriorities, true)) {
        $priority = 'normal';
    }

    $stmt = $pdo->prepare("
        INSERT INTO support_tickets (user_id, full_name, email, category, priority, subject, description)
        VALUES (:user_id, :full_name, :email, :category, :priority, :subject, :description)
    ");
    $stmt->execute([
        'user_id'     => $userId,
        'full_name'   => $fullName,
        'email'       => $email,
        'category'    => $category,
        'priority'    => $priority,
        'subject'     => $subject,
        'description' => $description,
    ]);
    $ticketId = (int)$pdo->lastInsertId();

    $priorityLabel = match($priority) {
        'high'   => 'High — affecting my work',
        'urgent' => 'Urgent — system down',
        default  => 'Normal',
    };

    $emailSubject = "[Support Ticket #$ticketId] $subject";
    $emailBody    = <<<TEXT
A new IT support request has been submitted via Treasury Revenue System.

────────────────────────────────────────
TICKET DETAILS
────────────────────────────────────────
Ticket #   : $ticketId
Submitted  : {$authUser['first_name']} {$authUser['last_name']} <$email>
Category   : $category
Priority   : $priorityLabel
Subject    : $subject

Description:
$description
────────────────────────────────────────
Reply to this email or update the ticket in the system.
TEXT;

    $headers  = "From: $fullName <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: TreasuryConnect/1.0\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $mailSent = @mail('webdevelopment@bhilimited.com', $emailSubject, $emailBody, $headers);

    ob_end_clean();
    jsonResponse([
        'success'   => true,
        'ticket_id' => $ticketId,
        'mail_sent' => $mailSent,
        'message'   => "Your support request (#$ticketId) has been submitted. IT support will contact you shortly.",
    ]);

} catch (Throwable $e) {
    ob_end_clean();
    jsonResponse(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
}
