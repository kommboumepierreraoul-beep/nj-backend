@extends('emails.layouts.app')

@php($emailTitle = 'Votre compte est prêt')

@section('content')
    <p>Bonjour {{ $user->full_name }},</p>
    <p>Un compte a été créé pour vous sur la plateforme {{ config('app.name') }}.</p>
    <p>
        <strong>Email:</strong> {{ $user->email }}<br>
        <strong>Role:</strong> {{ $user->role?->value ?? $user->role }}
    </p>
    <p>
        <a href="{{ $url }}" style="display:inline-block; padding:13px 20px; background:#e5a817; color:#171717; text-decoration:none; border-radius:8px; font-weight:bold;">
            Définir mon mot de passe
        </a>
    </p>
    <p>Ce lien est personnel et expire dans {{ $expireMinutes }} minutes.</p>
    <p>Aucun mot de passe temporaire n&apos;est envoyé par email.</p>
@endsection
