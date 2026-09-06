@extends('emails.layout')

@section('title', 'Langganan Tulisin Aktif')

@section('content')
    <h1 style="margin:0 0 16px;font-size:20px;color:#18181b;">Langganan Kamu Sudah Aktif</h1>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#52525b;">
        Halo {{ $name }}, langganan bulanan Tulisin kamu sudah aktif.
        Sekarang kamu bisa mengunduh PDF, memakai Agent Canvas, Turnitin, dan Plagiarism.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #e4e4e7;border-radius:8px;font-size:14px;">
        <tr>
            <td style="padding:10px 14px;color:#71717a;">Berlaku Sampai</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600;color:#18181b;">{{ $ends_at }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;color:#71717a;border-top:1px solid #f4f4f5;">Harga</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600;color:#18181b;border-top:1px solid #f4f4f5;">Rp {{ number_format((float) $price, 0, ',', '.') }}</td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#a1a1aa;">
        Kami akan mengingatkan kamu 5 hari sebelum masa langganan berakhir.
    </p>
@endsection
