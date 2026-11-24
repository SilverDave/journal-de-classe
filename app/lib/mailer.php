<?php
/** Envoi d'emails via la fonction mail() d'OVH. */

function send_mail(string $to, string $subject, string $body): bool
{
    $from = config('mail.from');
    $fromName = config('mail.from_name', config('app_name', 'Journal de classe'));

    if (config('mail.debug')) {
        // Mode test : l'email est écrit dans un fichier au lieu d'être envoyé.
        file_put_contents(ROOT . '/storage/logs/mail.log',
            '[' . now() . "] À: $to | $subject\n$body\n\n", FILE_APPEND | LOCK_EX);
        return true;
    }

    $headers = [
        'From' => mb_encode_mimeheader($fromName) . " <$from>",
        'Reply-To' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
        'X-Mailer' => 'JournalDeClasse',
    ];
    return mail($to, mb_encode_mimeheader($subject), $body, $headers, '-f' . $from);
}
