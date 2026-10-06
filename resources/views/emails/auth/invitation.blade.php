<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Votre acces NJ Global Trade</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 20px;">Votre compte NJ Global Trade est pret</h1>
    <p>Bonjour {{ $user->full_name }},</p>
    <p>Un compte a ete cree pour vous sur la plateforme NJ Global Trade.</p>
    <p>
        <strong>Email:</strong> {{ $user->email }}<br>
        <strong>Role:</strong> {{ $user->role?->value ?? $user->role }}
    </p>
    <p>
        <a href="{{ $url }}" style="display: inline-block; padding: 12px 18px; background: #0f766e; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Definir mon mot de passe
        </a>
    </p>
    <p>Ce lien est personnel et expire dans {{ $expireMinutes }} minutes.</p>
    <p>Aucun mot de passe temporaire n est envoye par email.</p>
</body>
</html>
