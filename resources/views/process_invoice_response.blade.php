@extends('layout')

@section('content')

    @foreach ($exports as $export)
        <p>This is user {{ $export[0] }}</p>
    @endforeach

@stop