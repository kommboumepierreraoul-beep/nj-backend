@extends('emails.layouts.app')

@php($emailTitle = $title)

@section('content')
    <p>Bonjour {{ $user->full_name ?? $user->name ?? '' }},</p>
    <p>{{ $body }}</p>
@endsection
