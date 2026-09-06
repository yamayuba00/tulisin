@extends('emails.layout')

@section('title', 'Pembelian Koin Berhasil')

@section('content')
    <h1 style="margin:0 0 16px;font-size:20px;color:#18181b;">Pembelian Koin Berhasil</h1>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#52525b;">
        Halo {{ $name }}, pembayaran kamu sudah kami terima. Koin sudah ditambahkan ke saldo akunmu.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #e4e4e7;border-radius:8px;font-size:14px;">
        <tr>
            <td style="padding:10px 14px;color:#71717a;">Invoice</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600;color:#18181b;">{{ $invoice }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;color:#71717a;border-top:1px solid #f4f4f5;">Koin Didapat</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600;color:#18181b;border-top:1px solid #f4f4f5;">{{ number_format((int) $credits, 0, ',', '.') }} koin</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;color:#71717a;border-top:1px solid #f4f4f5;">Nominal</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600;color:#18181b;border-top:1px solid #f4f4f5;">Rp {{ number_format((float) $amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;color:#71717a;border-top:1px solid #f4f4f5;">Total Dibayar</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600;color:#18181b;border-top:1px solid #f4f4f5;">Rp {{ number_format((float) $total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#a1a1aa;">
        Tanggal: {{ $date }}. Terima kasih sudah memakai Tulisin.
    </p>
@endsection
