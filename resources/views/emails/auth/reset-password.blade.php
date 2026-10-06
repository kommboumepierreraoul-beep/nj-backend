<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reinitialisation de mot de passe</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 20px;">Reinitialisation de votre mot de passe</h1>
    <p>Bonjour {{ $user->full_name ?? $user->name }},</p>
    <p>Une demande de reinitialisation de mot de passe a ete effectuee pour votre compte NJ Global Trade.</p>
    <p>
        <a href="{{ $url }}" style="display: inline-block; padding: 12px 18px; background: #0f766e; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Reinitialiser le mot de passe
        </a>
    </p>
    <p>Ce lien expire dans {{ $expireMinutes }} minutes.</p>
    <p>Si vous n avez pas fait cette demande, ignorez cet email.</p>
</body>
</html>
