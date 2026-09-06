@extends('emails.layout')

@section('title', $title)

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.35;color:#18181b;">{{ $title }}</h1>
    <div style="font-size:14px;line-height:1.6;color:#3f3f46;">
        {!! $content !!}
    </div>
@endsection
