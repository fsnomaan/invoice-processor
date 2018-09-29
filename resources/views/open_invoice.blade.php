@extends('layout')

@section('content')
    <h1>Process an open invoice</h1>

{{ $success }}

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
{{ Form::open(array('url' => '/open-invoice', 'files' => true)) }}

{{ Form::open(array('action' => 'OpenInvoiceController@processOpenInvoice')) }}

{{ Form::file('OpenInvoice') }}

{{ Form::submit('Process') }}

{{ Form::close() }}

@stop