@extends('emails.layouts.app')

@php($emailTitle = 'Test Brevo réussi')

@section('content')
    <p>Bonjour {{ $name }},</p>
    <p>Ceci est un email de test envoyé depuis le backend {{ config('app.name') }} avec l&apos;API Brevo.</p>
@endsection
