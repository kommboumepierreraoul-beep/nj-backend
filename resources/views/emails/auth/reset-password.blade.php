@extends('emails.layouts.app')

@php($emailTitle = 'Réinitialisation de votre mot de passe')

@section('content')
    <p>Bonjour {{ $user->full_name ?? $user->name }},</p>
    <p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte {{ config('app.name') }}.</p>
    <p>
        <a href="{{ $url }}" style="display:inline-block; padding:13px 20px; background:#e5a817; color:#171717; text-decoration:none; border-radius:8px; font-weight:bold;">
            Réinitialiser le mot de passe
        </a>
    </p>
    <p>Ce lien expire dans {{ $expireMinutes }} minutes.</p>
    <p>Si vous n&apos;êtes pas à l&apos;origine de cette demande, vous pouvez ignorer cet email.</p>
@endsection
