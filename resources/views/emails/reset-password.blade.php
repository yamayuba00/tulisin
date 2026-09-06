@extends('emails.layout')

@section('title', 'Reset Password Tulisin')

@section('content')
    <h1 style="margin:0 0 16px;font-size:20px;color:#18181b;">Reset Password</h1>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#52525b;">
        Halo {{ $name }}, kami menerima permintaan reset password untuk akun Tulisin kamu.
        Klik tombol di bawah untuk membuat password baru.
    </p>

    <p style="margin:0 0 24px;">
        <a href="{{ $url }}" style="display:inline-block;background-color:#171717;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;">
            Reset Password
        </a>
    </p>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#a1a1aa;">
        Tautan ini berlaku 12 jam. Abaikan email ini jika kamu tidak meminta reset password.
    </p>

    <p style="margin:8px 0 0;font-size:12px;line-height:1.5;word-break:break-all;color:#71717a;">{{ $url }}</p>
@endsection
